/* install/assets/installer.js */
(function () {
  'use strict';

  function boxes(name) {
    return document.querySelectorAll('input[name="' + name + '[]"]:not(:disabled)');
  }
  function setAll(name, state) {
    Array.prototype.forEach.call(boxes(name), function (b) { b.checked = state; });
    summary();
  }
  function count(name) {
    return Array.prototype.filter.call(boxes(name), function (b) { return b.checked; }).length;
  }
  function summary() {
    var box = document.getElementById('selection-summary');
    if (!box) return;
    box.textContent = 'Smazat tabulek: ' + count('drop_tables') + ' · SQL struktura: ' + count('schema_files') + ' · SQL data: ' + count('seed_files');
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-check-all]'), function (b) {
    b.addEventListener('click', function () { setAll(b.getAttribute('data-check-all'), true); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-uncheck-all]'), function (b) {
    b.addEventListener('click', function () { setAll(b.getAttribute('data-uncheck-all'), false); });
  });
  var form = document.getElementById('manual-form');
  if (form) form.addEventListener('change', summary);
  summary();
})();
