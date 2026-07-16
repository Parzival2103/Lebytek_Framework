/* public/assets/publico/landing_metrics.js
 * Colector first-party de métricas de landing (scroll, secciones, CTAs,
 * heartbeat, exit). Envía eventos a `window.__LB_METRICS__.endpoint`
 * (POST /marketing/collect) vía sendBeacon/fetch keepalive, form-urlencoded.
 *
 * Reglas duras:
 *  - Nunca emite el evento de conversión de lead (ese lo emite el servidor
 *    tras una captura exitosa — ver Task 7).
 *  - Nunca inspecciona las cookies del navegador; toda la resolución de
 *    variante/preview/visitor_id vive en el servidor (__LB_METRICS__ ya
 *    trae lo necesario para pintar la UI de debug si aplica).
 *  - Todos los fallos de red/DOM se ignoran en silencio: la telemetría
 *    nunca debe afectar la experiencia del visitante.
 */
(function () {
  'use strict';

  var cfg = window.__LB_METRICS__;
  if (!cfg || typeof cfg.variant !== 'string' || cfg.variant === '') {
    return;
  }

  var HEARTBEAT_MS = 15000;
  var SCROLL_BUCKETS = [25, 50, 75, 100];

  var state = {
    variant: cfg.variant,
    visitorId: typeof cfg.visitorId === 'string' ? cfg.visitorId : '',
    endpoint: typeof cfg.endpoint === 'string' && cfg.endpoint !== '' ? cfg.endpoint : '/marketing/collect',
    sessionId: ensureSessionId(),
    startedAt: Date.now(),
    maxScrollPct: 0,
    scrolledBuckets: {},
    seenSections: {},
    lastSection: '',
    exitSent: false,
    heartbeatTimer: null,
  };

  function uuidV4() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    var bytes = new Uint8Array(16);
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      window.crypto.getRandomValues(bytes);
    } else {
      for (var i = 0; i < 16; i++) {
        bytes[i] = Math.floor(Math.random() * 256);
      }
    }
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    var hex = [];
    for (var b = 0; b < bytes.length; b++) {
      hex.push((bytes[b] + 0x100).toString(16).slice(1));
    }

    return (
      hex[0] + hex[1] + hex[2] + hex[3] + '-' +
      hex[4] + hex[5] + '-' +
      hex[6] + hex[7] + '-' +
      hex[8] + hex[9] + '-' +
      hex[10] + hex[11] + hex[12] + hex[13] + hex[14] + hex[15]
    );
  }

  function ensureSessionId() {
    try {
      var existing = window.sessionStorage.getItem('lb_sess');
      if (existing) {
        return existing;
      }
      var fresh = uuidV4();
      window.sessionStorage.setItem('lb_sess', fresh);
      return fresh;
    } catch (e) {
      return uuidV4();
    }
  }

  function elapsedMs() {
    return Date.now() - state.startedAt;
  }

  function toFormBody(payload) {
    var parts = [];
    for (var key in payload) {
      if (!Object.prototype.hasOwnProperty.call(payload, key)) {
        continue;
      }
      parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]));
    }
    return parts.join('&');
  }

  function transport(payload) {
    try {
      if (typeof navigator.sendBeacon === 'function') {
        var sent = navigator.sendBeacon(state.endpoint, new URLSearchParams(payload));
        if (sent) {
          return;
        }
      }
    } catch (e) {
      // Ignorado — se intenta fetch abajo.
    }

    try {
      fetch(state.endpoint, {
        method: 'POST',
        keepalive: true,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: toFormBody(payload),
      }).catch(function () {});
    } catch (e) {
      // Swallow — la telemetría nunca debe romper la página.
    }
  }

  /**
   * @param {string} eventType uno de: pageview, scroll_depth, section_view,
   *   heartbeat, exit, cta_click. El evento de conversión de lead es
   *   exclusivamente server-side y este colector jamás lo construye.
   */
  function send(eventType, extra) {
    extra = extra || {};

    var payload = {
      visitor_id: state.visitorId,
      variant_slug: state.variant,
      session_public_id: state.sessionId,
      event_type: eventType,
    };

    if (extra.meta) {
      try {
        payload.meta = JSON.stringify(extra.meta);
      } catch (e) {
        // Meta inválida: se omite, el resto del evento sigue su curso.
      }
    }
    if (typeof extra.duration_ms === 'number') {
      payload.duration_ms = Math.max(0, Math.round(extra.duration_ms));
    }
    if (typeof extra.max_scroll_pct === 'number') {
      payload.max_scroll_pct = Math.max(0, Math.min(100, Math.round(extra.max_scroll_pct)));
    }
    if (typeof extra.exit_section === 'string' && extra.exit_section !== '') {
      payload.exit_section = extra.exit_section;
    }

    transport(payload);
  }

  function throttle(fn, waitMs) {
    var pending = false;
    return function () {
      if (pending) {
        return;
      }
      pending = true;
      window.setTimeout(function () {
        pending = false;
        fn();
      }, waitMs);
    };
  }

  function currentScrollPct() {
    var doc = document.documentElement;
    var scrollTop = window.pageYOffset || doc.scrollTop || 0;
    var viewport = doc.clientHeight || window.innerHeight || 0;
    var scrollable = (doc.scrollHeight || 0) - viewport;
    if (scrollable <= 0) {
      return 100;
    }
    return Math.max(0, Math.min(100, Math.round((scrollTop / scrollable) * 100)));
  }

  function onScroll() {
    var pct = currentScrollPct();
    if (pct > state.maxScrollPct) {
      state.maxScrollPct = pct;
    }
    for (var i = 0; i < SCROLL_BUCKETS.length; i++) {
      var bucket = SCROLL_BUCKETS[i];
      if (pct >= bucket && !state.scrolledBuckets[bucket]) {
        state.scrolledBuckets[bucket] = true;
        send('scroll_depth', { meta: { pct: bucket } });
      }
    }
  }

  function initSectionObserver() {
    var nodes = document.querySelectorAll('[data-reveal-id],[data-section]');
    if (!nodes.length || !('IntersectionObserver' in window)) {
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) {
          return;
        }
        var el = entry.target;
        var section = el.getAttribute('data-reveal-id') || el.getAttribute('data-section') || '';
        if (section === '' || state.seenSections[section]) {
          return;
        }
        state.seenSections[section] = true;
        state.lastSection = section;
        send('section_view', { meta: { section: section } });
      });
    }, { threshold: 0.4 });

    nodes.forEach(function (el) {
      observer.observe(el);
    });
  }

  function initCtaClicks() {
    document.addEventListener('click', function (e) {
      var target = e.target && typeof e.target.closest === 'function'
        ? e.target.closest('[data-lb-cta]')
        : null;
      if (!target) {
        return;
      }
      var cta = target.getAttribute('data-lb-cta') || '';
      send('cta_click', { meta: { cta: cta } });
    });
  }

  function startHeartbeat() {
    stopHeartbeat();
    state.heartbeatTimer = window.setInterval(function () {
      if (document.visibilityState === 'visible') {
        send('heartbeat', { duration_ms: elapsedMs(), max_scroll_pct: state.maxScrollPct });
      }
    }, HEARTBEAT_MS);
  }

  function stopHeartbeat() {
    if (state.heartbeatTimer) {
      window.clearInterval(state.heartbeatTimer);
      state.heartbeatTimer = null;
    }
  }

  function sendExit() {
    if (state.exitSent) {
      return;
    }
    state.exitSent = true;
    send('exit', {
      duration_ms: elapsedMs(),
      max_scroll_pct: state.maxScrollPct,
      exit_section: state.lastSection,
    });
  }

  function initLifecycle() {
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        sendExit();
      } else {
        state.exitSent = false;
      }
    });
    window.addEventListener('pagehide', sendExit);
  }

  function init() {
    send('pageview');
    window.addEventListener('scroll', throttle(onScroll, 300), { passive: true });
    initSectionObserver();
    initCtaClicks();
    initLifecycle();
    startHeartbeat();
  }

  init();
})();
