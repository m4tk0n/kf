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

  var state = { v: 1, show_all: !!base.show_all, sets: base.sets };
  try {
    var raw = sessionStorage.getItem(storageKey);
    if (raw) {
      var saved = JSON.parse(raw);
      if (saved && saved.v === 1 && saved.sets && typeof saved.sets === 'object') state = saved;
    }
  } catch (e) {}

  function cityInputs(countryId) {
    return form.querySelectorAll('[data-quick-option$=":cities"][data-country-id="' + countryId + '"]');
  }

  function syncCountry(country) {
    var cities = cityInputs(country.value);
    var allChecked = cities.length > 0;
    cities.forEach(function (city) { if (!city.checked) allChecked = false; });
    country.checked = allChecked;
  }

  function syncControls() {
    options.forEach(function (input) {
      var parts = input.getAttribute('data-quick-option').split(':');
      var current = state.sets[parts[0]] || {};
      var ids = Array.isArray(current[parts[1]]) ? current[parts[1]].map(String) : [];
      input.checked = state.show_all || ids.indexOf(input.value) !== -1;
    });
    form.querySelectorAll('[data-quick-country-option]').forEach(syncCountry);
    if (options.length) options[0].dispatchEvent(new Event('change', { bubbles: true }));
  }

  form.querySelectorAll('[data-quick-country-option]').forEach(function (country) {
    country.addEventListener('change', function () {
      cityInputs(country.value).forEach(function (city) { city.checked = country.checked; });
    });
  });

  options.forEach(function (input) {
    if (!input.getAttribute('data-quick-option').endsWith(':cities')) return;
    input.addEventListener('change', function () {
      var country = form.querySelector('[data-quick-country-option][value="' + input.getAttribute('data-country-id') + '"]');
      if (country) syncCountry(country);
    });
  });

  function readAllSections() {
    var sets = {};
    options.forEach(function (input) {
      var parts = input.getAttribute('data-quick-option').split(':');
      if (parts[1] === 'countries') return;
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

  syncControls();
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    readAllSections();
    if (!window.kfSetSessionFilters(state)) return;
    window.location.href = form.getAttribute('data-back-url') || '/';
  });
})();
