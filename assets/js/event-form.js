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

  // Společné ovládání našeptávačů ve formuláři (název akce a klub/místo).
  Array.prototype.forEach.call(document.querySelectorAll('[data-autocomplete-input]'), function (nameInput) {
    var suggestionsId = nameInput.getAttribute('data-autocomplete-suggestions-target');
    var suggestions = suggestionsId
      ? document.getElementById(suggestionsId)
      : nameInput.parentNode.querySelector('[data-autocomplete-suggestions]');
    if (!suggestions) return;

    var suggestTimer = 0;
    var requestNumber = 0;
    var minSuggestChars = parseInt(nameInput.getAttribute('data-suggest-min') || '2', 10);
    var venueId = nameInput.parentNode.querySelector('[data-venue-id]');
    var citySelect = document.querySelector('select[name="city_id"]');

    function closeSuggestions() {
      suggestions.hidden = true;
      while (suggestions.firstChild) suggestions.removeChild(suggestions.firstChild);
    }
    function loadSuggestions() {
      var term = nameInput.value.trim();
      var currentRequest = ++requestNumber;
      if (term.length < minSuggestChars) { closeSuggestions(); return; }
      fetch(nameInput.getAttribute('data-suggest-url') + '?q=' + encodeURIComponent(term), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      }).then(function (response) {
        if (!response.ok) throw new Error('Suggestion request failed: ' + response.status);
        return response.json();
      }).then(function (data) {
        if (currentRequest !== requestNumber || term !== nameInput.value.trim()) return;
        while (suggestions.firstChild) suggestions.removeChild(suggestions.firstChild);
        (Array.isArray(data.items) ? data.items : []).forEach(function (item) {
          var isVenue = nameInput.hasAttribute('data-venue-autocomplete');
          var value = isVenue && item && typeof item === 'object' ? item.name : item;
          if (typeof value !== 'string' || value === '') return;
          var option = document.createElement('button');
          option.type = 'button';
          option.className = 'autocomplete-option';
          option.setAttribute('role', 'option');
          option.textContent = isVenue && item.city ? value + ' (' + item.city + ')' : value;
          option.addEventListener('click', function () {
            nameInput.value = value;
            if (isVenue && venueId) {
              venueId.value = String(item.id || 0);
              if (citySelect && item.city_id) citySelect.value = String(item.city_id);
            }
            closeSuggestions();
            nameInput.focus();
          });
          suggestions.appendChild(option);
        });
        suggestions.hidden = suggestions.childElementCount === 0;
      }).catch(function () {
        if (currentRequest === requestNumber) closeSuggestions();
      });
    }
    nameInput.addEventListener('input', function () {
      if (venueId) venueId.value = '0';
      window.clearTimeout(suggestTimer);
      suggestTimer = window.setTimeout(loadSuggestions, 180);
    });
    nameInput.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeSuggestions();
    });
    document.addEventListener('click', function (event) {
      if (!event.target.closest || !event.target.closest('.event-name-field')) closeSuggestions();
    });
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
