/* public/assets/publico/landing.js — interacciones del front público (sin dominio). */
(function () {
  'use strict';

  // Navbar: solidificar al hacer scroll
  var nav = document.querySelector('.ct-nav');
  if (nav) {
    var onScroll = function () { nav.classList.toggle('is-scrolled', window.scrollY > 8); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // Toggle de facturación mensual/anual
  var billing = document.querySelector('.ct-billing');
  if (billing) {
    var prices = document.querySelectorAll('.ct-plan__price');
    billing.addEventListener('click', function (e) {
      var btn = e.target.closest('.ct-billing__btn');
      if (!btn) return;
      var period = btn.getAttribute('data-period');
      billing.querySelectorAll('.ct-billing__btn').forEach(function (b) {
        var active = b === btn;
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      prices.forEach(function (p) {
        var amount = p.querySelector('.ct-plan__amount');
        var periodEl = p.querySelector('.ct-plan__period');
        var value = period === 'annual' ? p.getAttribute('data-annual') : p.getAttribute('data-monthly');
        if (amount) amount.textContent = value || '';
        if (periodEl) {
          var numeric = /\d/.test(value || '');
          periodEl.textContent = numeric ? (period === 'annual' ? '/año' : '/mes') : '';
        }
      });
    });
  }

  // Reveal on scroll
  var revealables = document.querySelectorAll('[data-reveal]');
  if (revealables.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } });
    }, { threshold: 0.12 });
    revealables.forEach(function (el) { io.observe(el); });
  } else {
    revealables.forEach(function (el) { el.classList.add('is-visible'); });
  }
})();
