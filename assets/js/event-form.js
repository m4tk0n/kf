/* assets/js/event-form.js */
(function () {
  'use strict';
  // Okamžitý náhled vybraného souboru (data: URL; CSP blob: nepovoluje) nebo zadané https URL.
  var file = document.querySelector('[data-event-image]');
  var url = document.querySelector('[data-event-image-url]');
  var img = document.querySelector('[data-event-preview]');
  var original = img ? img.getAttribute('src') || '' : '';
  function show(src) {
    if (!img) return;
    if (!src) { img.hidden = true; img.removeAttribute('src'); return; }
    img.src = src;
    img.hidden = false;
  }
  if (file) file.addEventListener('change', function () {
    var f = file.files && file.files[0];
    if (!f) { show(original); return; }
    if (url) url.value = '';
    var r = new FileReader();
    r.onload = function () { show(String(r.result)); };
    r.readAsDataURL(f);
  });
  if (url) url.addEventListener('change', function () {
    var val = url.value.trim();
    if (/^https:\/\//i.test(val)) {
      if (file) file.value = '';
      show(val);
    } else if (!file || !file.files.length) {
      show(original);
    }
  });

  // Řádky kapel: zobrazí se vyplněné + jeden prázdný, tlačítko "+" přidá další.
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-band-row]'));
  var more = document.querySelector('[data-band-more]');
  if (rows.length && more) {
    var shown = 1;
    rows.forEach(function (row, i) {
      var sel = row.querySelector('select');
      if (sel && sel.value !== '0' && sel.value !== '') shown = i + 2;
    });
    var apply = function () {
      shown = Math.min(shown, rows.length);
      rows.forEach(function (row, i) { row.hidden = i >= shown; });
      more.hidden = shown >= rows.length;
    };
    more.addEventListener('click', function () { shown++; apply(); });
    apply();
  }
})();
