/* assets/js/app.js */
(function () {
  'use strict';
  var sectionSelect = document.querySelector('.bar-search select');
  if (sectionSelect && !document.getElementById('view')) {
    sectionSelect.addEventListener('change', function () { sectionSelect.form.submit(); });
  }
  // Hromadné zaškrtnutí / odškrtnutí všech možností ve správě filtrů.
  var filterForm = document.querySelector('[data-filter-form]');
  if (filterForm) {
    var selectAll = filterForm.querySelector('[data-filter-all]');
    var filterOptions = Array.prototype.slice.call(filterForm.querySelectorAll('[data-filter-option]'));
    function syncSelectAll() {
      if (!selectAll) return;
      var checked = filterOptions.filter(function (item) { return item.checked; }).length;
      selectAll.checked = filterOptions.length > 0 && checked === filterOptions.length;
      selectAll.indeterminate = checked > 0 && checked < filterOptions.length;
    }
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        filterOptions.forEach(function (item) { item.checked = selectAll.checked; });
        selectAll.indeterminate = false;
      });
    }
    filterOptions.forEach(function (item) { item.addEventListener('change', syncSelectAll); });
    syncSelectAll();
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

})();
