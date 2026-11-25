/* assets/js/quick-filters.js */
(function () {
  'use strict';
  var form = document.querySelector('[data-quick-filter-form]');
  if (!form || typeof window.kfSetSessionFilters !== 'function') return;

  var storageKey = 'kf:list-filters:' + (document.body.getAttribute('data-filter-identity') || 'guest');
  var options = Array.prototype.slice.call(form.querySelectorAll('[data-quick-option]'));
  var base;

  try {
    base = JSON.parse(form.getAttribute('data-base-state') || '{}');
  } catch (e) {
    base = { show_all: true, sets: {} };
  }
  if (!base || !base.sets || typeof base.sets !== 'object') base = { show_all: true, sets: {} };

  var state = { v: 1, show_all: !!base.show_all, sets: base.sets, city_exclusions: base.city_exclusions || {} };
  try {
    var raw = sessionStorage.getItem(storageKey);
    if (raw) {
      var saved = JSON.parse(raw);
      if (saved && saved.v === 1 && saved.sets && typeof saved.sets === 'object') state = saved;
    }
  } catch (e) {}

  function syncControls() {
    options.forEach(function (input) {
      var parts = input.getAttribute('data-quick-option').split(':');
      var current = state.sets[parts[0]] || {};
      var ids = Array.isArray(current[parts[1]]) ? current[parts[1]].map(String) : [];
      if (parts[1] === 'cities') {
        var countryId = input.getAttribute('data-country-id');
        var countryInput = form.querySelector('[data-quick-country-option][value="' + countryId + '"]');
        var countrySelected = !!(countryInput && (state.show_all || countryInput.checked));
        var excluded = Array.isArray((state.city_exclusions || {})[parts[0]]) ? state.city_exclusions[parts[0]].map(String) : [];
        input.checked = state.show_all || (countrySelected && excluded.indexOf(input.value) === -1) || ids.indexOf(input.value) !== -1;
      } else {
        input.checked = state.show_all || ids.indexOf(input.value) !== -1;
      }
    });
    if (options.length) options[0].dispatchEvent(new Event('change', { bubbles: true }));
  }

  form.querySelectorAll('[data-quick-country-option]').forEach(function (country) {
    country.addEventListener('change', function () {
      var countryId = country.value;
      form.querySelectorAll('[data-quick-option$=":cities"][data-country-id="' + countryId + '"]').forEach(function (city) {
        city.checked = country.checked;
      });
    });
  });

  function readAllSections() {
    var sets = {};
    var cityExclusions = {};
    options.forEach(function (input) {
      var parts = input.getAttribute('data-quick-option').split(':');
      if (!sets[parts[0]]) sets[parts[0]] = {};
      if (!sets[parts[0]][parts[1]]) sets[parts[0]][parts[1]] = [];
      if (input.checked) {
        sets[parts[0]][parts[1]].push(parseInt(input.value, 10));
      } else if (parts[1] === 'cities') {
        if (!cityExclusions[parts[0]]) cityExclusions[parts[0]] = [];
        cityExclusions[parts[0]].push(parseInt(input.value, 10));
      }
    });
    state.sets = sets;
    state.city_exclusions = cityExclusions;
    state.show_all = false;
    state.none = Object.keys(sets).every(function (kind) {
      return Object.keys(sets[kind]).every(function (field) { return sets[kind][field].length === 0; });
    });
    state.v = 1;
  }

  syncControls();

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    readAllSections();
    if (!window.kfSetSessionFilters(state)) return;
    window.location.href = form.getAttribute('data-back-url') || '/';
  });
})();
