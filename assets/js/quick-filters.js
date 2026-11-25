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

  var state = { v: 1, show_all: !!base.show_all, sets: base.sets };  function syncCountry(country) {
    var countryId = country.value;
    var cities = form.querySelectorAll('[data-quick-option$=":cities"][data-country-id="' + countryId + '"]');
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
      var countryId = country.value;
      form.querySelectorAll('[data-quick-option$=":cities"][data-country-id="' + countryId + '"]').forEach(function (city) {
        city.checked = country.checked;
      });
    });
  });

  options.forEach(function (input) {
    if (!input.getAttribute('data-quick-option').endsWith(':cities')) return;
    input.addEventListener('change', function () {
      var countryId = input.getAttribute('data-country-id');
      var country = form.querySelector('[data-quick-country-option][value="' + countryId + '"]');
      if (country) syncCountry(country);
    });
  });
