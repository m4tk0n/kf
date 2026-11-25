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
