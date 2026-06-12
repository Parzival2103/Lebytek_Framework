/**
 * app.js — JavaScript vanilla del panel administrativo
 * Sistema modular sin dependencias externas (salvo Bootstrap y AOS vía CDN).
 */

'use strict';

/* ============================================================
   MÓDULO: Sidebar
   ============================================================ */
const Sidebar = (() => {
  const COLLAPSED_KEY = 'sidebar_collapsed';
  const sidebar       = document.getElementById('sidebar');
  const toggleBtn     = document.getElementById('sidebarToggle');
  const mobileBtn     = document.getElementById('sidebarToggleMobile');

  function setCollapsed(collapsed) {
    if (!sidebar) return;
    sidebar.classList.toggle('collapsed', collapsed);
    localStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0');

    /* En modo colapsado, desactivar data-bs-toggle en los links con submenú
       para que Bootstrap no procese esos clics. El flyout se encarga de eso. */
    sidebar.querySelectorAll('.with-submenu').forEach(link => {
      if (collapsed) {
        link.dataset.bsToggleBak = link.getAttribute('data-bs-toggle') ?? '';
        link.removeAttribute('data-bs-toggle');
      } else {
        if (link.dataset.bsToggleBak !== undefined) {
          if (link.dataset.bsToggleBak) {
            link.setAttribute('data-bs-toggle', link.dataset.bsToggleBak);
          }
          delete link.dataset.bsToggleBak;
        }
      }
    });
  }

  function initMobileOverlay() {
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'sidebar-overlay';
      document.body.appendChild(overlay);
    }

    function openMobile() {
      sidebar?.classList.add('mobile-open');
      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeMobile() {
      sidebar?.classList.remove('mobile-open');
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }

    mobileBtn?.addEventListener('click', openMobile);

    overlay.addEventListener('click', closeMobile);

    // Cerrar con Escape en mobile
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar?.classList.contains('mobile-open')) {
        closeMobile();
      }
    });
  }

  function init() {
    if (!sidebar) return;

    // Restaurar estado colapsado solo en desktop
    if (window.innerWidth >= 992) {
      const wasCollapsed = localStorage.getItem(COLLAPSED_KEY) === '1';
      setCollapsed(wasCollapsed);
    }

    toggleBtn?.addEventListener('click', () => {
      setCollapsed(!sidebar.classList.contains('collapsed'));
    });

    initMobileOverlay();
    initCollapsedFlyout();

    // Limpiar will-change tras la transición para ahorrar memoria GPU
    sidebar.addEventListener('transitionend', (e) => {
      if (e.propertyName === 'width' || e.propertyName === 'transform') {
        sidebar.style.willChange = '';
        sidebar.addEventListener('click', () => {
          sidebar.style.willChange = 'width';
        }, { once: true });
      }
    });
  }

  /* ── Flyout de submenú para sidebar colapsada ────────────── */
  function initCollapsedFlyout() {
    if (!sidebar) return;

    const flyout = document.createElement('div');
    flyout.className = 'sidebar-flyout';
    flyout.style.display = 'none';
    document.body.appendChild(flyout);

    let currentTarget = null;

    function closeFlyout() {
      flyout.style.display = 'none';
      currentTarget = null;
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onEsc);
    }

    function openFlyout(link, collapseEl) {
      const rect  = link.getBoundingClientRect();
      const label = link.querySelector('.nav-label')?.textContent?.trim() ?? '';
      const items = collapseEl.querySelectorAll('.sidebar-sublink');

      flyout.innerHTML = '';

      if (label) {
        const title = document.createElement('div');
        title.className = 'sidebar-flyout-title';
        title.textContent = label;
        flyout.appendChild(title);
      }

      items.forEach(orig => {
        const a   = orig.cloneNode(true);
        const lbl = a.querySelector('.nav-label');
        if (lbl) {
          lbl.style.opacity  = '1';
          lbl.style.maxWidth = 'none';
        }
        a.style.display = '';
        a.addEventListener('click', closeFlyout);
        flyout.appendChild(a);
      });

      flyout.style.display = 'block';
      flyout.style.top     = rect.top + 'px';
      currentTarget        = link;

      /* Ajustar si el flyout se sale por abajo */
      requestAnimationFrame(() => {
        const flyH  = flyout.offsetHeight;
        const vH    = window.innerHeight;
        if (rect.top + flyH > vH) {
          flyout.style.top = Math.max(0, vH - flyH - 8) + 'px';
        }
        document.addEventListener('click', onDocClick);
        document.addEventListener('keydown', onEsc);
      });
    }

    function onDocClick(e) {
      if (!flyout.contains(e.target) && !e.target.closest('.with-submenu')) {
        closeFlyout();
      }
    }

    function onEsc(e) {
      if (e.key === 'Escape') closeFlyout();
    }

    /*
     * Click handler para los links del sidebar en modo colapsado.
     * Como en setCollapsed() ya removemos data-bs-toggle cuando colapsa,
     * Bootstrap no intercepta estos clics — solo necesitamos mostrar el flyout.
     */
    sidebar.addEventListener('click', (e) => {
      if (!sidebar.classList.contains('collapsed')) return;

      const link = e.target.closest('.with-submenu');
      if (!link) return;

      e.preventDefault();

      const targetId   = link.getAttribute('href')?.slice(1);
      const collapseEl = targetId ? document.getElementById(targetId) : null;
      if (!collapseEl) return;

      /* Toggle: si ya está abierto para este link, cerrar */
      if (currentTarget === link && flyout.style.display !== 'none') {
        closeFlyout();
      } else {
        openFlyout(link, collapseEl);
      }
    });
  }

  return { init, setCollapsed };
})();

/* ============================================================
   MÓDULO: Tema (dark/light) — persiste en DB via AJAX
   ============================================================ */
const ThemeToggle = (() => {
  function applyTheme(isDark) {
    const theme = isDark ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.documentElement.setAttribute('data-dark-mode', isDark ? '1' : '0');

    document.querySelectorAll('#themeToggle i').forEach(icon => {
      icon.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
    });

    // Sincronizar toggle en el style panel si existe
    const panelToggle = document.getElementById('panelDarkToggle');
    if (panelToggle) panelToggle.checked = isDark;
  }

  async function toggle() {
    const isDark = document.documentElement.getAttribute('data-dark-mode') === '1';
    applyTheme(!isDark);

    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
      const res   = await fetch('/admin/ajustes/toggle-tema', {
        method:  'POST',
        headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) applyTheme(isDark);
    } catch {
      applyTheme(isDark);
    }
  }

  function init() {
    const isDark = document.documentElement.getAttribute('data-dark-mode') === '1';
    document.querySelectorAll('#themeToggle i').forEach(icon => {
      icon.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
    });

    document.querySelectorAll('#themeToggle').forEach(btn => {
      btn.addEventListener('click', toggle);
    });

    // Toggle en el style panel
    const panelToggle = document.getElementById('panelDarkToggle');
    if (panelToggle) {
      panelToggle.checked = isDark;
      panelToggle.addEventListener('change', toggle);
    }
  }

  return { init, applyTheme, toggle };
})();

/* ============================================================
   MÓDULO: Style Panel — drawer flotante de personalización
   ============================================================ */
const StylePanel = (() => {
  const PANEL_KEY = 'style_panel_open';

  function open() {
    const panel   = document.getElementById('stylePanel');
    const overlay = document.getElementById('stylePanelOverlay');
    if (!panel) return;
    panel.classList.add('open');
    overlay?.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    const panel   = document.getElementById('stylePanel');
    const overlay = document.getElementById('stylePanelOverlay');
    if (!panel) return;
    panel.classList.remove('open');
    overlay?.classList.remove('active');
    document.body.style.overflow = '';
  }

  function toggle() {
    const panel = document.getElementById('stylePanel');
    if (!panel) return;
    panel.classList.contains('open') ? close() : open();
  }

  function init() {
    const btn     = document.getElementById('stylePanelBtn');
    const closeBtn = document.getElementById('stylePanelClose');
    const overlay  = document.getElementById('stylePanelOverlay');

    btn?.addEventListener('click', toggle);
    closeBtn?.addEventListener('click', close);
    overlay?.addEventListener('click', close);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') close();
    });

    // Inicializar presets en el panel
    initPanelPresets();
  }

  const PANEL_PRESETS = {
    primary: ['#0d6efd','#6610f2','#d63384','#dc3545','#fd7e14','#198754','#20c997','#0dcaf0','#6f42c1'],
    navbar:  ['#1a1d2e','#111827','#1e293b','#0f172a','#1c1c2e','#212529','#2d3748','#374151','#ffffff'],
  };

  function initPanelPresets() {
    buildPanelPresets('panelPrimaryPresets', 'primary', PANEL_PRESETS.primary);
    buildPanelPresets('panelNavbarPresets',  'navbar',  PANEL_PRESETS.navbar);

    // Layout options
    document.querySelectorAll('.layout-option[data-layout]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.layout-option').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
    });
  }

  function buildPanelPresets(containerId, type, colors) {
    const container = document.getElementById(containerId);
    if (!container) return;
    colors.forEach(hex => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'color-preset';
      btn.style.background = hex;
      btn.title = hex;
      btn.addEventListener('click', () => {
        const cssVar = type === 'primary' ? '--app-primary' : '--app-navbar-bg';
        document.documentElement.style.setProperty(cssVar, hex);
        // Si hay picker en ajustes, sincronizar
        const inputId = type === 'primary' ? 'primary_color' : 'navbar_color';
        ColorPicker.applyColor(inputId, hex);
      });
      container.appendChild(btn);
    });
  }

  return { init, open, close, toggle };
})();

/* ============================================================
   MÓDULO: ColorPicker — lógica de ajustes de color
   Solo se activa si existe el formulario de ajustes (#ajustesForm)
   ============================================================ */
const ColorPicker = (() => {
  const PRESETS = {
    primary: ['#0d6efd','#6610f2','#d63384','#dc3545','#fd7e14','#198754','#20c997','#0dcaf0','#6f42c1'],
    navbar:  ['#1a1d2e','#111827','#1e293b','#0f172a','#1c1c2e','#212529','#2d3748','#374151','#1a2035'],
    body:    ['#f8f9fa','#f0f2f5','#ffffff','#f1f5f9','#e2e8f0','#f5f5f5','#fafafa','#f3f4f6','#eef2ff'],
  };

  function applyColor(colorInputId, hex) {
    if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) return;

    const picker    = document.getElementById(colorInputId);
    const hexInput  = document.querySelector(`[data-sync="${colorInputId}"]`);
    const cssVar    = picker?.dataset.cssVar;
    const previewId = picker?.dataset.preview;

    if (picker)   picker.value   = hex;
    if (hexInput) hexInput.value = hex;

    if (cssVar) document.documentElement.style.setProperty(cssVar, hex);

    if (previewId) {
      const swatch = document.getElementById(previewId);
      if (swatch) swatch.style.background = hex;
    }
  }

  function buildPresets(containerId, colorInputId, colors) {
    const container = document.getElementById(containerId);
    if (!container) return;
    colors.forEach(hex => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'color-preset';
      btn.style.background = hex;
      btn.title = hex;
      btn.addEventListener('click', () => applyColor(colorInputId, hex));
      container.appendChild(btn);
    });
  }

  function wireColorPicker(colorInputId) {
    const picker   = document.getElementById(colorInputId);
    const hexInput = document.querySelector(`[data-sync="${colorInputId}"]`);
    if (!picker) return;

    picker.addEventListener('input', () => applyColor(colorInputId, picker.value));

    hexInput?.addEventListener('input', () => {
      if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) {
        applyColor(colorInputId, hexInput.value);
      }
    });
  }

  function init() {
    if (!document.getElementById('ajustesForm')) return;

    buildPresets('primary_presets', 'primary_color', PRESETS.primary);
    buildPresets('navbar_presets',  'navbar_color',  PRESETS.navbar);
    buildPresets('body_presets',    'body_color',    PRESETS.body);

    wireColorPicker('primary_color');
    wireColorPicker('navbar_color');
    wireColorPicker('body_color');

    document.getElementById('resetColores')?.addEventListener('click', () => {
      applyColor('primary_color', '#0d6efd');
      applyColor('navbar_color',  '#1a1d2e');
      applyColor('body_color',    '#f0f2f5');
    });

    const mostrarNombreCb = document.getElementById('empresa_mostrar_nombre');
    const previewTitle = document.getElementById('navbarPreviewTitle');
    const empresaNombreInput = document.getElementById('empresa_nombre');

    mostrarNombreCb?.addEventListener('change', () => {
      previewTitle?.classList.toggle('d-none', !mostrarNombreCb.checked);
    });

    empresaNombreInput?.addEventListener('input', () => {
      if (previewTitle) {
        previewTitle.textContent = empresaNombreInput.value.trim() || 'Framework Lebytek';
      }
    });
  }

  return { init, applyColor, buildPresets };
})();

/* ============================================================
   MÓDULO: Alertas auto-dismiss
   ============================================================ */
const AlertManager = (() => {
  function init() {
    document.querySelectorAll('.alert.fade.show').forEach(alert => {
      setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        bsAlert?.close();
      }, 5500);
    });
  }

  return { init };
})();

/* ============================================================
   MÓDULO: Modal de confirmación global (#confirmModal)
   ============================================================ */
const ConfirmModal = (() => {
  const DEFAULTS = {
    title: 'Confirmar acción',
    body: '¿Confirmar esta acción?',
    ok: 'Confirmar',
    cancel: 'Cancelar',
    variant: 'primary',
    cancelVariant: '',
    icon: '',
    emphasis: '',
  };

  // Whitelist: evita inyección de clases arbitrarias vía data-attributes.
  const VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark'];

  const ICONS = {
    warning:  'bi-exclamation-triangle-fill text-warning',
    danger:   'bi-exclamation-octagon-fill text-danger',
    success:  'bi-check-circle-fill text-success',
    info:     'bi-info-circle-fill text-info',
    question: 'bi-question-circle-fill text-primary',
  };

  let pending = null;
  let modalInstance = null;

  function getModal() {
    const el = document.getElementById('confirmModal');
    if (!el || typeof bootstrap === 'undefined') return null;
    modalInstance = modalInstance || bootstrap.Modal.getOrCreateInstance(el);
    return { el, instance: modalInstance };
  }

  // Construye el body con nodos DOM (nunca innerHTML) y subraya
  // la primera ocurrencia de `emphasis` dentro de `body`.
  function renderBody(bodyEl, body, emphasis) {
    bodyEl.textContent = '';
    const idx = emphasis ? body.indexOf(emphasis) : -1;
    if (idx === -1) {
      bodyEl.textContent = body;
      return;
    }
    bodyEl.append(document.createTextNode(body.slice(0, idx)));
    const u = document.createElement('u');
    u.className = 'ct-confirm-emphasis';
    u.textContent = emphasis;
    bodyEl.append(u);
    bodyEl.append(document.createTextNode(body.slice(idx + emphasis.length)));
  }

  function applyOptions(opts) {
    const titleEl = document.getElementById('confirmModalTitle');
    const bodyEl = document.getElementById('confirmModalBody');
    const okBtn = document.getElementById('confirmModalOk');
    const cancelBtn = document.getElementById('confirmModalCancel');
    const iconEl = document.getElementById('confirmModalIcon');
    if (!titleEl || !bodyEl || !okBtn || !cancelBtn) return;

    titleEl.textContent = opts.title;
    renderBody(bodyEl, opts.body, opts.emphasis);
    okBtn.textContent = opts.ok;
    cancelBtn.textContent = opts.cancel;

    const variant = VARIANTS.includes(opts.variant) ? opts.variant : 'primary';
    okBtn.className = 'btn btn-' + variant;
    cancelBtn.className = VARIANTS.includes(opts.cancelVariant)
      ? 'btn btn-' + opts.cancelVariant
      : 'btn btn-outline-secondary';

    if (iconEl) {
      const iconClass = ICONS[opts.icon] || '';
      iconEl.className = iconClass
        ? 'ct-confirm-icon bi ' + iconClass
        : 'ct-confirm-icon d-none';
    }
  }

  function show(options = {}) {
    const opts = Object.assign({}, DEFAULTS, options);
    const modal = getModal();
    if (!modal) return Promise.resolve(window.confirm(opts.body));

    if (pending) pending.resolve(false);

    applyOptions(opts);
    return new Promise((resolve) => {
      pending = { resolve };
      modal.instance.show();
    });
  }

  function init() {
    const modal = getModal();
    if (!modal) return;

    const okBtn = document.getElementById('confirmModalOk');
    const el = modal.el;

    okBtn?.addEventListener('click', () => {
      pending?.resolve(true);
      pending = null;
      modal.instance.hide();
    });

    el.addEventListener('hidden.bs.modal', () => {
      if (pending) {
        pending.resolve(false);
        pending = null;
      }
    });
  }

  return { show, init };
})();

// API pública para otros módulos: window.Lebytek.confirm(opts) => Promise<boolean>
window.Lebytek = Object.assign(window.Lebytek || {}, { confirm: ConfirmModal.show });

/* ============================================================
   MÓDULO: Confirmaciones de formulario (data-confirm)
   ============================================================ */
const ConfirmForms = (() => {
  function resolveConfirmElement(form) {
    if (!(form instanceof HTMLFormElement)) return null;
    return form.querySelector('[data-confirm]')
      || (form.hasAttribute('data-confirm') ? form : null);
  }

  function readConfirmOptions(el) {
    if (!el || !el.dataset.confirm) return null;
    return {
      body: el.dataset.confirm,
      title: el.dataset.confirmTitle || 'Confirmar acción',
      variant: el.dataset.confirmVariant || 'primary',
      ok: el.dataset.confirmOk || 'Confirmar',
      cancel: el.dataset.confirmCancel || 'Cancelar',
      cancelVariant: el.dataset.confirmCancelVariant || '',
      icon: el.dataset.confirmIcon || '',
      emphasis: el.dataset.confirmEmphasis || '',
    };
  }

  function init() {
    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (form.dataset.confirmBypass === '1') {
        delete form.dataset.confirmBypass;
        return;
      }

      const el = resolveConfirmElement(form);
      const opts = readConfirmOptions(el);
      if (!opts) return;

      e.preventDefault();
      ConfirmModal.show(opts).then((ok) => {
        if (!ok) return;
        form.dataset.confirmBypass = '1';
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      });
    });

    document.addEventListener('click', function (e) {
      const el = e.target.closest('[data-confirm]:not([type="submit"])');
      if (!el || el.tagName === 'FORM') return;

      const opts = readConfirmOptions(el);
      if (!opts) return;

      e.preventDefault();
      e.stopPropagation();

      ConfirmModal.show(opts).then((ok) => {
        if (!ok) return;
        if (el.tagName === 'A' && el.href) {
          window.location.assign(el.href);
        }
      });
    });
  }

  return { init };
})();

/* ============================================================
   MÓDULO: Toggle visibility de password
   ============================================================ */
const PasswordToggle = (() => {
  function init() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-toggle-password]');
      if (!btn) return;
      const targetId = btn.dataset.togglePassword;
      const input    = document.getElementById(targetId);
      if (!input) return;
      const icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.className = 'bi bi-eye-slash';
      } else {
        input.type = 'password';
        if (icon) icon.className = 'bi bi-eye';
      }
    });
  }

  return { init };
})();

/* ============================================================
   MÓDULO: Búsqueda en tabla (client-side)
   ============================================================ */
const TableSearch = (() => {
  function init() {
    const searchInput = document.getElementById('tableSearch');
    if (!searchInput) return;

    const table = document.getElementById('usuariosTable') || document.querySelector('table');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody?.querySelectorAll('tr') ?? []);

    searchInput.addEventListener('input', () => {
      const term = searchInput.value.toLowerCase().trim();
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = term === '' || text.includes(term) ? '' : 'none';
      });
    });
  }

  return { init };
})();

/* ============================================================
   MÓDULO: Ordenamiento de tablas (client-side)
   ============================================================ */
const TableSort = (() => {
  function init() {
    document.querySelectorAll('.sortable').forEach(th => {
      th.addEventListener('click', () => {
        const table  = th.closest('table');
        const tbody  = table?.querySelector('tbody');
        if (!tbody) return;

        const colIdx = parseInt(th.dataset.col ?? '0', 10);
        const rows   = Array.from(tbody.querySelectorAll('tr'));
        const asc    = th.dataset.sortDir !== 'asc';

        rows.sort((a, b) => {
          const aText = a.cells[colIdx]?.textContent?.trim().toLowerCase() ?? '';
          const bText = b.cells[colIdx]?.textContent?.trim().toLowerCase() ?? '';
          return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
        });

        rows.forEach(r => tbody.appendChild(r));
        th.dataset.sortDir = asc ? 'asc' : 'desc';

        table.querySelectorAll('.sortable').forEach(t => {
          t.querySelector('.sort-indicator')?.remove();
        });
        const indicator = document.createElement('span');
        indicator.className = 'sort-indicator ms-1 small';
        indicator.textContent = asc ? '↑' : '↓';
        th.appendChild(indicator);
      });
    });
  }

  return { init };
})();

/* ============================================================
   MÓDULO: Tooltips Bootstrap
   ============================================================ */
const Tooltips = (() => {
  function init() {
    document.querySelectorAll('[title]:not([data-bs-toggle])').forEach(el => {
      new bootstrap.Tooltip(el, { trigger: 'hover' });
    });
  }

  return { init };
})();

/* ============================================================
   MÓDULO: BottomNav — panel "más" y submenús del bottombar
   ============================================================ */
const BottomNav = (() => {
  let activeSubBtn = null;

  function init() {
    const moreBtn  = document.getElementById('bottomnavMoreBtn');
    const morePanel = document.getElementById('bottomnavMorePanel');
    const subPanel  = document.getElementById('bottomnavSubPanel');
    const subTitle  = document.getElementById('bottomnavSubTitle');
    const subViewAll = document.getElementById('bottomnavSubViewAll');
    const subGrid   = document.getElementById('bottomnavSubGrid');
    const overlay   = document.getElementById('bottomnavMoreOverlay');

    /* ── Helpers de escape ── */
    function esc(str) {
      return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── Cerrar todo ── */
    function closeAll() {
      morePanel?.classList.remove('open');
      moreBtn?.classList.remove('active');
      moreBtn?.setAttribute('aria-expanded', 'false');

      subPanel?.classList.remove('open');
      activeSubBtn?.classList.remove('active');
      activeSubBtn = null;

      overlay?.classList.remove('active');
      document.removeEventListener('keydown', onKeydown);
    }

    function onKeydown(e) {
      if (e.key === 'Escape') closeAll();
    }

    /* ── Panel "Más" ── */
    function openMore() {
      closeAll();
      morePanel?.classList.add('open');
      overlay?.classList.add('active');
      moreBtn?.classList.add('active');
      moreBtn?.setAttribute('aria-expanded', 'true');
      document.addEventListener('keydown', onKeydown);
    }

    if (moreBtn) {
      moreBtn.addEventListener('click', () => {
        morePanel?.classList.contains('open') ? closeAll() : openMore();
      });
    }

    /* Cierra "más" al navegar desde un link directo del panel */
    morePanel?.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', closeAll);
    });

    /* ── Submenús dinámicos ── */
    function openSub(btn) {
      const items   = JSON.parse(btn.dataset.submenu || '[]');
      const label   = btn.dataset.label   || '';
      const mainUrl = btn.dataset.mainUrl || '#';

      if (subTitle)   subTitle.textContent = label;
      if (subViewAll) subViewAll.href = mainUrl;

      if (subGrid) {
        subGrid.innerHTML = items.map(item => `
          <a href="${esc(item.url || '#')}" class="bottomnav-more-item">
            <i class="bi ${esc(item.icon || 'bi-circle')}"></i>
            <span>${esc(item.label || '')}</span>
          </a>`).join('');

        subGrid.querySelectorAll('a').forEach(a => {
          a.addEventListener('click', closeAll);
        });
      }

      /* Quitar resaltado del botón anterior */
      activeSubBtn?.classList.remove('active');
      activeSubBtn = btn;
      activeSubBtn.classList.add('active');

      /* Mostrar el panel (quitar hidden por si acaso) */
      subPanel?.removeAttribute('hidden');
      subPanel?.classList.add('open');
      overlay?.classList.add('active');
      document.addEventListener('keydown', onKeydown);
    }

    document.querySelectorAll('.bottomnav-sub-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (subPanel?.classList.contains('open') && activeSubBtn === btn) {
          closeAll();
        } else {
          /* Si el panel "más" está abierto, cerrarlo primero */
          morePanel?.classList.remove('open');
          moreBtn?.classList.remove('active');
          moreBtn?.setAttribute('aria-expanded', 'false');

          openSub(btn);
        }
      });
    });

    overlay?.addEventListener('click', closeAll);
  }

  return { init };
})();

/* ============================================================
   MÓDULO: CSRF en peticiones fetch
   ============================================================ */
const CsrfFetch = (() => {
  function getToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  }

  function fetchWithCsrf(url, options = {}) {
    const headers = Object.assign({ 'X-CSRF-Token': getToken() }, options.headers ?? {});
    return fetch(url, Object.assign({}, options, { headers }));
  }

  return { getToken, fetchWithCsrf };
})();

/* ============================================================
   MÓDULO: Formularios — spinner en submit
   ============================================================ */
const FormSubmit = (() => {
  function init() {
    document.querySelectorAll('form:not([data-no-loading])').forEach(form => {
      form.addEventListener('submit', function () {
        const btn = form.querySelector('[type="submit"]');
        if (!btn) return;
        btn.disabled = true;
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm ms-2';
        spinner.setAttribute('role', 'status');
        btn.appendChild(spinner);
      });
    });
  }

  return { init };
})();

/* ============================================================
   MÓDULO: Ajustes — recordar sección del acordeón (localStorage)
   ============================================================ */
const AjustesAccordion = (() => {
  const STORAGE_KEY = 'lebytek_admin_ajustes_accordion';

  function init() {
    const root = document.getElementById('ajustesAccordion');
    if (!root || typeof bootstrap === 'undefined') return;

    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
      const el = document.getElementById(stored);
      if (el?.classList.contains('accordion-collapse')) {
        bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
      }
    }

    root.addEventListener('shown.bs.collapse', (e) => {
      const t = e.target;
      const id = t?.id;
      if (id && t?.classList.contains('accordion-collapse')) {
        localStorage.setItem(STORAGE_KEY, id);
      }
    });

    root.addEventListener('hidden.bs.collapse', () => {
      if (!root.querySelector('.accordion-collapse.show')) {
        localStorage.removeItem(STORAGE_KEY);
      }
    });
  }

  return { init };
})();

/* ============================================================
   MÓDULO: NavDrawer — drawer de nav_top en móvil (<992px)
   Reutiliza el overlay `.sidebar-overlay` (mismo estilo que el sidebar).
   ============================================================ */
const NavDrawer = (() => {
  function init() {
    const toggle = document.getElementById('topNavToggle');
    const drawer = document.getElementById('topNavMenu');
    if (!toggle || !drawer) return;

    // Reutiliza el overlay del sidebar si ya existe; si no, lo crea.
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'sidebar-overlay';
      document.body.appendChild(overlay);
    }

    function open() {
      drawer.classList.add('open');
      overlay.classList.add('active');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }

    function close() {
      drawer.classList.remove('open');
      overlay.classList.remove('active');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    toggle.addEventListener('click', () => {
      drawer.classList.contains('open') ? close() : open();
    });

    overlay.addEventListener('click', close);

    // Cerrar al navegar desde un link del drawer
    drawer.querySelectorAll('a.nav-link:not(.dropdown-toggle), a.dropdown-item').forEach(a => {
      a.addEventListener('click', close);
    });

    // Cerrar con Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && drawer.classList.contains('open')) close();
    });
  }

  return { init };
})();

/* ============================================================
   INICIALIZACIÓN GLOBAL
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  Sidebar.init();
  ThemeToggle.init();
  StylePanel.init();
  ColorPicker.init();
  AjustesAccordion.init();
  BottomNav.init();
  NavDrawer.init();
  AlertManager.init();
  ConfirmModal.init();
  ConfirmForms.init();
  PasswordToggle.init();
  TableSearch.init();
  TableSort.init();
  FormSubmit.init();

  if (typeof bootstrap !== 'undefined') {
    Tooltips.init();
  }

  // AOS — Animate on Scroll
  if (typeof AOS !== 'undefined') {
    AOS.init({
      duration: 350,
      easing: 'ease-out-cubic',
      once: true,
      offset: 40,
    });
  }

  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    const bp = (document.documentElement.getAttribute('data-base-path') || '').trim();
    const swUrl = (bp ? `${bp}/` : '/') + 'sw.js';
    navigator.serviceWorker.register(swUrl, { scope: bp ? `${bp}/` : '/' }).catch(() => {});
  }
});

// Exportar para uso externo
window.App = {
  Sidebar,
  ThemeToggle,
  StylePanel,
  ColorPicker,
  AjustesAccordion,
  BottomNav,
  NavDrawer,
  CsrfFetch,
  ConfirmModal,
};
