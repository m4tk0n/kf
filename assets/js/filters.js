/* assets/js/filters.js */
(function () {
  'use strict';
  var identity = document.body.getAttribute('data-filter-identity') || 'guest';
  var identityKey = 'kf:filter-identity';
  var storageKey = 'kf:list-filters:' + identity;
  try {
    var previous = sessionStorage.getItem(identityKey);
    if (previous && previous !== identity) {
      for (var i = sessionStorage.length - 1; i >= 0; i--) {
        var key = sessionStorage.key(i);
        if (key && key.indexOf('kf:list-filters:') === 0) sessionStorage.removeItem(key);
      }
    }
    sessionStorage.setItem(identityKey, identity);
    if (document.body.getAttribute('data-clear-filter-session') === '1') sessionStorage.removeItem(storageKey);
  } catch (e) {}
  function read() {
    try { var raw = sessionStorage.getItem(storageKey); if (!raw) return ''; var state = JSON.parse(raw); return state && state.v === 1 && state.sets && typeof state.sets === 'object' ? raw : ''; }
    catch (e) { return ''; }
  }
  window.KFFilters = {
    decorateUrl: function (href) {
      var raw = read(); if (!raw) return href;
      try { var url = new URL(href, window.location.href); url.searchParams.set('kf_filters', raw); return url.toString(); } catch (e) { return href; }
    },
    restore: function () { if (read() && typeof window.kfLoadView === 'function') window.kfLoadView(window.location.href, false); }
  };
  window.kfSetSessionFilters = function (filters) {
    if (!filters || typeof filters !== 'object' || !filters.sets) return false;
    var state = { v: 1, show_all: !!filters.show_all, none: !!filters.none, sets: filters.sets };
    try { sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch (e) { return false; }
    if (typeof window.kfLoadView === 'function') { var url = new URL(window.location.href); url.searchParams.delete('p'); url.searchParams.delete('d'); url.searchParams.delete('k'); window.kfLoadView(url.toString(), false); }
    return true;
  };
  var form = document.querySelector('[data-filter-form]');
  if (form) {
    var all = form.querySelector('[data-filter-all]');
    var options = Array.prototype.slice.call(form.querySelectorAll('[data-filter-option]'));
    function cityInputs(countryId) {
      return form.querySelectorAll('[data-filter-option][data-country-id="' + countryId + '"]');
    }
    function syncCountry(country) {
      var cities = cityInputs(country.value);
      var checked = cities.length > 0;
      cities.forEach(function (city) { if (!city.checked) checked = false; });
      country.checked = checked;
    }
    function syncCountries() {
      form.querySelectorAll('[data-filter-country-option]').forEach(syncCountry);
    }
    function syncAll() {
      if (!all) return;
      var n = options.filter(function (x) { return x.checked; }).length;
      all.checked = options.length > 0 && n === options.length;
      all.indeterminate = n > 0 && n < options.length;
    }
    if (all) all.addEventListener('change', function () {
      options.forEach(function (x) { x.checked = all.checked; });
      if (!form.hasAttribute('data-quick-filter-form')) syncCountries();
      all.indeterminate = false;
    });
    if (!form.hasAttribute('data-quick-filter-form')) {
      form.querySelectorAll('[data-filter-country-option]').forEach(function (country) {
        country.addEventListener('change', function () {
          cityInputs(country.value).forEach(function (city) { city.checked = country.checked; });
          syncAll();
        });
      });
    }
    options.forEach(function (x) {
      x.addEventListener('change', function () {
        if (!form.hasAttribute('data-quick-filter-form') && x.hasAttribute('data-country-id')) {
          var country = form.querySelector('[data-filter-country-option][value="' + x.getAttribute('data-country-id') + '"]');
          if (country) syncCountry(country);
        }
        syncAll();
      });
    });
    if (!form.hasAttribute('data-quick-filter-form')) syncCountries();
    syncAll();
    var tabs = Array.prototype.slice.call(form.querySelectorAll('[data-filter-tab]'));
    var panels = Array.prototype.slice.call(form.querySelectorAll('[data-filter-panel]'));
    function activate(name, focus) {
      tabs.forEach(function (tab) { var active = tab.getAttribute('data-filter-tab') === name; tab.classList.toggle('active', active); tab.setAttribute('aria-selected', active ? 'true' : 'false'); tab.tabIndex = active ? 0 : -1; if (active && focus) tab.focus(); });
      panels.forEach(function (panel) { panel.hidden = panel.getAttribute('data-filter-panel') !== name; });
    }
    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () { activate(tab.getAttribute('data-filter-tab'), false); });
      tab.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' && event.key !== 'Home' && event.key !== 'End') return;
        event.preventDefault();
        var next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
        activate(tabs[next].getAttribute('data-filter-tab'), true);
      });
    });
    function fitOptions() {
      form.querySelectorAll('.filter-option').forEach(function (label) {
        var text = label.querySelector('span');
        if (!text) return;
        label.classList.remove('filter-option-wide');
        if (text.scrollWidth > text.clientWidth + 1) label.classList.add('filter-option-wide');
      });
    }
    if (!form.hasAttribute('data-quick-filter-form')) {
      fitOptions();
      if (window.ResizeObserver) { var observer = new ResizeObserver(fitOptions); observer.observe(form); }
      window.addEventListener('resize', fitOptions);
    }
  }
})();
