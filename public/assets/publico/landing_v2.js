/* public/assets/publico/landing_v2.js — Landing v2 behaviors (vanilla) */
(function () {
  'use strict';

  // 1) Scroll reveal
  var revealEls = document.querySelectorAll('[data-reveal-id].lb-reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('lb-reveal--on');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealEls.forEach(function (el) { obs.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('lb-reveal--on'); });
  }

  // 2) FAQ accordion (single open)
  var faqButtons = document.querySelectorAll('[data-faq-toggle]');
  faqButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.parentElement.querySelector('.lb-faq-panel');
      var icon = btn.querySelector('.lb-faq-icon');
      var isOpen = panel && panel.style.maxHeight && panel.style.maxHeight !== '0px';
      faqButtons.forEach(function (other) {
        var p = other.parentElement.querySelector('.lb-faq-panel');
        var ic = other.querySelector('.lb-faq-icon');
        if (p) { p.style.maxHeight = '0px'; }
        if (ic) { ic.style.transform = 'rotate(0deg)'; }
      });
      if (!isOpen && panel) {
        panel.style.maxHeight = panel.scrollHeight + 'px';
        if (icon) { icon.style.transform = 'rotate(45deg)'; }
      }
    });
  });

  // 3) Billing toggle (monthly / annual)
  var billingBtns = document.querySelectorAll('.lb-billing-btn[data-period]');
  function applyPeriod(period) {
    billingBtns.forEach(function (b) {
      var active = b.getAttribute('data-period') === period;
      b.classList.toggle('is-active', active);
      b.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-monthly][data-annual]').forEach(function (priceEl) {
      var amount = priceEl.querySelector('.lb-price-amount');
      if (amount) { amount.textContent = priceEl.getAttribute('data-' + period); }
    });
    document.querySelectorAll('.lb-compra[data-compra-' + period + ']').forEach(function (link) {
      link.setAttribute('href', link.getAttribute('data-compra-' + period));
    });
  }
  billingBtns.forEach(function (b) {
    b.addEventListener('click', function () { applyPeriod(b.getAttribute('data-period')); });
  });

  // 4) Merge optional "Empresa" into mensaje on submit
  var leadForm = document.querySelector('form[data-lead-form]');
  if (leadForm) {
    leadForm.addEventListener('submit', function () {
      var empresa = leadForm.querySelector('[data-empresa-merge]');
      var mensaje = leadForm.querySelector('textarea[name="mensaje"]');
      if (empresa && empresa.value.trim() && mensaje) {
        var prefix = 'Empresa: ' + empresa.value.trim();
        mensaje.value = mensaje.value.trim() ? (prefix + '\n' + mensaje.value) : prefix;
      }
    });
  }
})();
