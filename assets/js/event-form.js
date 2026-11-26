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

  // Našeptávač názvů zveřejněných akcí (výběr pouze doplní text; neváže akce k sobě).
  var nameInput = document.querySelector('[data-event-name-input]');
  var suggestions = document.querySelector('[data-event-name-suggestions]');
  if (nameInput && suggestions) {
    var suggestTimer = 0;
    var activeRequest = null;
    var minSuggestChars = parseInt(nameInput.getAttribute('data-suggest-min') || '2', 10);
    function closeSuggestions() {
      suggestions.hidden = true;
      suggestions.replaceChildren();
    }
    function loadSuggestions() {
      var term = nameInput.value.trim();
      if (term.length < minSuggestChars) { closeSuggestions(); return; }
      if (activeRequest) activeRequest.abort();
      activeRequest = new AbortController();
      fetch(nameInput.getAttribute('data-suggest-url') + '?q=' + encodeURIComponent(term), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        signal: activeRequest.signal
      }).then(function (response) {
        if (!response.ok) throw new Error('Suggestion request failed');
        return response.json();
      }).then(function (data) {
        suggestions.replaceChildren();
        (Array.isArray(data.items) ? data.items : []).forEach(function (value) {
          var option = document.createElement('button');
          option.type = 'button';
          option.className = 'autocomplete-option';
          option.setAttribute('role', 'option');
          option.textContent = value;
          option.addEventListener('click', function () {
            nameInput.value = value;
            closeSuggestions();
            nameInput.focus();
          });
          suggestions.appendChild(option);
        });
        suggestions.hidden = suggestions.childElementCount === 0;
      }).catch(function (error) {
        if (error.name !== 'AbortError') closeSuggestions();
      });
    }
    nameInput.addEventListener('input', function () {
      window.clearTimeout(suggestTimer);
      suggestTimer = window.setTimeout(loadSuggestions, 180);
    });
    nameInput.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeSuggestions();
    });
    document.addEventListener('click', function (event) {
      if (!event.target.closest('.event-name-field')) closeSuggestions();
    });
  }

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
