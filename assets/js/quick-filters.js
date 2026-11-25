/* assets/js/quick-filters.js */
(function () {
  'use strict';
  var form = document.querySelector('[data-quick-filter-form]');
  if (!form || typeof window.kfSetSessionFilters !== 'function') return;

  var section = form.getAttribute('data-section');
  var storageKey = 'kf:list-filters:' + (document.body.getAttribute('data-filter-identity') || 'guest');
  var all = form.querySelector('[data-quick-all]');
  var options = Array.prototype.slice.call(form.querySelectorAll('[data-quick-option]'));
  var base;

  try {
    base = JSON.parse(form.getAttribute('data-base-state') || '{}');
  } catch (e) {
    base = { show_all: true, sets: {} };
  }
  if (!base || !base.sets || typeof base.sets !== 'object') base = { show_all: true, sets: {} };

  var state = { v: 1, show_all: !!base.show_all, sets: base.sets };
  try {
    var raw = sessionStorage.getItem(storageKey);
    if (raw) {
      var saved = JSON.parse(raw);
      if (saved && saved.v === 1 && saved.sets && typeof saved.sets === 'object') {
        state = saved;
      }
    }
  } catch (e) {}

  function syncControls() {
    var current = state.sets[section] || {};
    options.forEach(function (input) {
      var field = input.getAttribute('data-quick-option');
      var ids = Array.isArray(current[field]) ? current[field].map(String) : [];
      input.checked = state.show_all || ids.indexOf(input.value) !== -1;
    });
    syncAll();
  }

  function syncAll() {
    if (!all) return;
    var checked = options.filter(function (input) { return input.checked; }).length;
    all.checked = options.length > 0 && checked === options.length;
    all.indeterminate = checked > 0 && checked < options.length;
  }

  function readCurrentSection() {
    var sets = {};
    options.forEach(function (input) {
      var field = input.getAttribute('data-quick-option');
      if (!sets[field]) sets[field] = [];
      if (input.checked) sets[field].push(parseInt(input.value, 10));
    });
    state.sets[section] = sets;
    state.show_all = false;
    state.none = Object.keys(sets).every(function (field) { return sets[field].length === 0; });
    state.v = 1;
  }

  if (all) {
    all.addEventListener('change', function () {
      options.forEach(function (input) { input.checked = all.checked; });
      all.indeterminate = false;
    });
  }
  options.forEach(function (input) {
    input.addEventListener('change', syncAll);
  });
  syncControls();

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    readCurrentSection();
    if (!window.kfSetSessionFilters(state)) return;
    window.location.href = form.getAttribute('data-back-url') || '/';
  });
})();
