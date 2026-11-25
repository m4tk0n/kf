/* assets/js/quick-filters.js */
(function () {
  'use strict';
  var form = document.querySelector('[data-quick-filter-form]');
  if (!form || typeof window.kfSetSessionFilters !== 'function') return;

  var storageKey = 'kf:list-filters:' + (document.body.getAttribute('data-filter-identity') || 'guest');
  var all = form.querySelector('[data-quick-all]');
  var options = Array.prototype.slice.call(form.querySelectorAll('[data-quick-option]'));
  var tabs = Array.prototype.slice.call(form.querySelectorAll('[data-quick-tab]'));
  var panels = Array.prototype.slice.call(form.querySelectorAll('[data-quick-panel]'));
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
      if (saved && saved.v === 1 && saved.sets && typeof saved.sets === 'object') state = saved;
    }
  } catch (e) {}

  function syncAll() {
    if (!all) return;
    var checked = options.filter(function (input) { return input.checked; }).length;
    all.checked = options.length > 0 && checked === options.length;
    all.indeterminate = checked > 0 && checked < options.length;
  }

  function syncControls() {
    options.forEach(function (input) {
      var parts = input.getAttribute('data-quick-option').split(':');
      var current = state.sets[parts[0]] || {};
      var ids = Array.isArray(current[parts[1]]) ? current[parts[1]].map(String) : [];
      input.checked = state.show_all || ids.indexOf(input.value) !== -1;
    });
    syncAll();
  }

  function activate(name, focus) {
    tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-quick-tab') === name;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      if (active && focus) tab.focus();
    });
    panels.forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-quick-panel') !== name;
    });
  }

  function readAllSections() {
    var sets = {};
    options.forEach(function (input) {
      var parts = input.getAttribute('data-quick-option').split(':');
      if (!sets[parts[0]]) sets[parts[0]] = {};
      if (!sets[parts[0]][parts[1]]) sets[parts[0]][parts[1]] = [];
      if (input.checked) sets[parts[0]][parts[1]].push(parseInt(input.value, 10));
    });
    state.sets = sets;
    state.show_all = false;
    state.none = Object.keys(sets).every(function (kind) {
      return Object.keys(sets[kind]).every(function (field) { return sets[kind][field].length === 0; });
    });
    state.v = 1;
  }

  if (all) {
    all.addEventListener('change', function () {
      options.forEach(function (input) { input.checked = all.checked; });
      all.indeterminate = false;
    });
  }
  options.forEach(function (input) { input.addEventListener('change', syncAll); });
  tabs.forEach(function (tab, index) {
    tab.addEventListener('click', function () {
      activate(tab.getAttribute('data-quick-tab'), false);
    });
    tab.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' && event.key !== 'Home' && event.key !== 'End') return;
      event.preventDefault();
      var next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 :
        (index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
      activate(tabs[next].getAttribute('data-quick-tab'), true);
    });
  });

  syncControls();
  activate(form.getAttribute('data-section') || 'events', false);

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    readAllSections();
    if (!window.kfSetSessionFilters(state)) return;
    window.location.href = form.getAttribute('data-back-url') || '/';
  });
})();
