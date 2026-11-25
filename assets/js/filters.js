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
    function sync() { if (!all) return; var n = options.filter(function (x) { return x.checked; }).length; all.checked = options.length > 0 && n === options.length; all.indeterminate = n > 0 && n < options.length; }
    if (all) all.addEventListener('change', function () { options.forEach(function (x) { x.checked = all.checked; }); all.indeterminate = false; });
    options.forEach(function (x) { x.addEventListener('change', sync); }); sync();
  }
})();
