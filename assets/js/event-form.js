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

  var cityInput = document.querySelector('[data-venue-city-input]');
  var cityId = document.querySelector('[data-venue-city-id]');
  var countryInput = document.querySelector('[data-venue-country-input]');
  var countryId = document.querySelector('[data-venue-country-id]');
  var countryIso2 = document.querySelector('[data-venue-country-iso2]');

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
          if (isVenue && item && typeof item === 'object') {
            var details = [item.country, item.city, item.address].filter(function (part) { return typeof part === 'string' && part.trim() !== ''; });
            option.textContent = details.length ? value + ' · ' + details.join(' · ') : value;
          } else {
            option.textContent = value;
          }
          option.addEventListener('click', function () {
            nameInput.value = value;
            if (isVenue && venueId) {
              venueId.value = String(item.id || 0);
              if (cityInput && item.city) cityInput.value = String(item.city);
              if (cityId && item.city_id) cityId.value = String(item.city_id);
              if (countryInput && item.country_name) countryInput.value = String(item.country_name);
              if (countryId && item.country_id) countryId.value = String(item.country_id);
              if (countryIso2) countryIso2.textContent = item.country || '';
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


  // Město a stát: stejné našeptávání jako ve formuláři klubu, včetně vlastních pending měst.
  function cityCountrySuggest(input, hidden, list, endpoint, extraParams, choose, label) {
    if (!input || !hidden || !list) return;
    var timer = 0, request = 0;
    function close() { list.hidden=true;list.replaceChildren(); }
    input.addEventListener('input',function(){
      hidden.value='0';
      if(input===countryInput&&countryIso2)countryIso2.textContent='';
      window.clearTimeout(timer);
      timer=window.setTimeout(function(){
        var term=input.value.trim(), current=++request;
        if(term.length<parseInt(input.getAttribute('data-suggest-min')||'2',10)){close();return;}
        var query='?q='+encodeURIComponent(term);
        Object.keys(extraParams||{}).forEach(function(k){var value=typeof extraParams[k]==='function'?extraParams[k]():extraParams[k];if(value)query+='&'+encodeURIComponent(k)+'='+encodeURIComponent(value);});
        fetch(input.getAttribute('data-suggest-url')+query,{credentials:'same-origin',headers:{'Accept':'application/json'},cache:'no-store'})
          .then(function(r){if(!r.ok)throw new Error('Suggestion request failed');return r.json();})
          .then(function(data){
            if(current!==request||term!==input.value.trim())return;
            list.replaceChildren();
            (Array.isArray(data.items)?data.items:[]).forEach(function(item){
              var b=document.createElement('button');b.type='button';b.className='autocomplete-option';b.setAttribute('role','option');b.textContent=label(item);
              b.addEventListener('click',function(){choose(item);close();input.focus();});list.appendChild(b);
            });
            list.hidden=list.childElementCount===0;
          }).catch(close);
      },180);
    });
    input.addEventListener('keydown',function(ev){if(ev.key==='Escape')close();});
  }
  cityCountrySuggest(cityInput,cityId,document.querySelector('[data-venue-city-suggestions]'),null,
    {country_id:function(){return countryId?countryId.value:'';}},
    function(item){cityInput.value=item.name;cityId.value=String(item.id||0);if(countryInput)countryInput.value=item.country;if(countryId)countryId.value=String(item.country_id);if(countryIso2)countryIso2.textContent=item.iso2||'';},
    function(item){return item.name+' · '+item.country;});
  cityCountrySuggest(countryInput,countryId,document.querySelector('[data-venue-country-suggestions]'),null,{},
    function(item){countryInput.value=item.name;countryId.value=String(item.id);if(countryIso2)countryIso2.textContent=item.iso2||'';},
    function(item){return item.name;});

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
