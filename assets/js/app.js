/* assets/js/app.js */
(function () {
  'use strict';

  // Návrat z detailu používá původní výpis uložený při otevření položky.
  // Tím zůstává veřejná URL detailu čistá a přechody mezi souvisejícími detaily
  // nepřepíší původní návratový cíl.
  var detailReturnKey = 'kf:detail-return-url';
  document.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
    var back = ev.target.closest ? ev.target.closest('a[data-detail-back]') : null;
    if (!back) return;
    try {
      var savedUrl = sessionStorage.getItem(detailReturnKey);
      if (!savedUrl) return;
      var target = new URL(savedUrl, window.location.href);
      if (target.origin !== window.location.origin) return;
      ev.preventDefault();
      sessionStorage.removeItem(detailReturnKey);
      window.location.assign(target.href);
    } catch (e) {}
  });

  // Jednotná normalizace pomlček při každém psaní i vložení do textových polí.
  function normalizeTextInput(el) {
    if (!el || !('value' in el) || typeof el.value !== 'string') return;
    var normalized = el.value.replace(/[–—‑−]/g, '-');
    if (normalized === el.value) return;
    var start = null;
    var end = null;
    try { start = el.selectionStart; end = el.selectionEnd; } catch (e) {}
    el.value = normalized;
    if (start !== null && end !== null && typeof el.setSelectionRange === 'function') {
      try { el.setSelectionRange(start, end); } catch (e) {}
    }
  }
  document.addEventListener('input', function (ev) {
    var el = ev.target;
    if (!el || !el.matches) return;
    if (el.matches('textarea, input:not([type]), input[type="text"], input[type="search"], input[type="url"], input[type="tel"], input[type="email"]')) {
      normalizeTextInput(el);
    }
  }, true);
  // Jednotné tlačítko Zpět na každé samostatné stránce s nadpisem.
  // Pokud předchozí stránka patří do stejného webu, vrátíme se v historii;
  // při přímém otevření stránky nabídneme návrat na hlavní výpis.
  var main = document.querySelector('main.main');
  var heading = main && main.querySelector('h1');
  if (heading && !main.querySelector('.detail') && !main.querySelector('.page-title-row > .btn, .back')) {
    var row = document.createElement('div');
    row.className = 'page-title-row';
    var parent = heading.parentNode;
    parent.insertBefore(row, heading);
    row.appendChild(heading);
    var back = document.createElement('a');
    back.className = 'btn page-back';
    back.href = document.body.getAttribute('data-home-url') || '/';
    back.textContent = document.body.getAttribute('data-back-label') || 'Zpět';
    back.addEventListener('click', function (ev) {
      var ref = document.referrer;
      if (ref) {
        try {
          if (new URL(ref).origin === window.location.origin) {
            ev.preventDefault();
            window.history.back();
          }
        } catch (e) {}
      }
    });
    row.appendChild(back);
  }

  var sectionSelect = document.querySelector('.bar-search select');
  if (sectionSelect && !document.getElementById('view')) {
    sectionSelect.addEventListener('change', function () { sectionSelect.form.submit(); });
  }
  // Zprávy po akcích zobrazujeme přímo na tlačítku, které akci vyvolalo.
  var actionKey = 'kf-last-action';
  function actionLabel(el) {
    if (!el) return '';
    return (el.tagName === 'INPUT' ? (el.value || '') : (el.textContent || '')).replace(/\\s+/g, ' ').trim();
  }
  function rememberAction(el) {
    if (!el || !/^(BUTTON|A|INPUT)$/.test(el.tagName)) return;
    var label = actionLabel(el);
    if (!label) return;
    try {
      sessionStorage.setItem(actionKey, JSON.stringify({
        label: label,
        tag: el.tagName,
        time: Date.now()
      }));
    } catch (e) {}
  }
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest ? ev.target.closest('button, input[type="submit"], a.btn') : null;
    if (el) rememberAction(el);
  }, true);
  document.addEventListener('submit', function (ev) {
    if (ev.submitter) rememberAction(ev.submitter);
  }, true);

  var flashText = document.body.getAttribute('data-flash-text');
  if (flashText) {
    var saved = null;
    try {
      saved = JSON.parse(sessionStorage.getItem(actionKey) || 'null');
      sessionStorage.removeItem(actionKey);
    } catch (e) {}
    if (saved && saved.label && Date.now() - saved.time < 15000) {
      var candidates = document.querySelectorAll('button, input[type="submit"], a.btn');
      var target = null;
      for (var i = 0; i < candidates.length; i++) {
        if (candidates[i].tagName === saved.tag && actionLabel(candidates[i]) === saved.label) {
          target = candidates[i];
          break;
        }
      }
      if (target) {
        var original = target.tagName === 'INPUT' ? target.value : target.textContent;
        if (target.tagName === 'INPUT') target.value = flashText;
        else target.textContent = flashText;
        target.classList.add('flash-success');
        target.setAttribute('aria-live', 'polite');
        window.setTimeout(function () {
          if (target.tagName === 'INPUT') target.value = original;
          else target.textContent = original;
          target.classList.remove('flash-success');
          target.removeAttribute('aria-live');
        }, 3000);
      }
    }
  }

  // potvrzení nebezpečných akcí (CSP zakazuje inline JS)
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (t && !window.confirm(t.getAttribute('data-confirm'))) ev.preventDefault();
  });

  // Zavření informačního modalu o převzetí účtu.
  var sessionModal = document.getElementById('session-replaced-modal');
  if (sessionModal) {
    var closeModal = sessionModal.querySelector('[data-modal-close]');
    if (closeModal) closeModal.addEventListener('click', function () {
      sessionModal.remove();
    });
    if (closeModal) closeModal.focus();
  }

  // Informační okno na stránce filtrů.
  var filtersModal = document.getElementById('filters-help-modal');
  if (filtersModal) {
    var filtersOpen = document.querySelector('[data-filters-help-open]');
    var filtersClose = filtersModal.querySelectorAll('[data-filters-help-close]');
    var previousFocus = null;
    function openFiltersHelp() {
      previousFocus = document.activeElement;
      filtersModal.hidden = false;
      var close = filtersModal.querySelector('[data-filters-help-close]');
      if (close) close.focus();
      document.addEventListener('keydown', onFiltersHelpKeydown);
    }
    function closeFiltersHelp() {
      filtersModal.hidden = true;
      document.removeEventListener('keydown', onFiltersHelpKeydown);
      if (previousFocus && previousFocus.focus) previousFocus.focus();
    }
    function onFiltersHelpKeydown(ev) {
      if (ev.key === 'Escape') {
        ev.preventDefault();
        closeFiltersHelp();
      }
    }
    if (filtersOpen) filtersOpen.addEventListener('click', openFiltersHelp);
    for (var fi = 0; fi < filtersClose.length; fi++) {
      filtersClose[fi].addEventListener('click', closeFiltersHelp);
    }
    filtersModal.addEventListener('click', function (ev) {
      if (ev.target === filtersModal) closeFiltersHelp();
    });
  }

})();
