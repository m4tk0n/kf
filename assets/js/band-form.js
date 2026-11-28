/* assets/js/band-form.js */
(function () {
  'use strict';
  var form = document.querySelector('[data-band-form]');
  if (!form) return;

  ['logo', 'detail'].forEach(function (which) {
    var file = form.querySelector('[data-band-image="' + which + '"]');
    var url = form.querySelector('[data-band-image-url="' + which + '"]');
    var img = form.querySelector('[data-band-image-preview="' + which + '"]');
    var original = img ? img.getAttribute('src') || '' : '';
    function show(src) {
      if (!img) return;
      if (!src) { img.hidden = true; img.removeAttribute('src'); return; }
      img.src = src; img.hidden = false;
    }
    if (file) file.addEventListener('change', function () {
      var selected = file.files && file.files[0];
      if (!selected) { show(original); return; }
      if (url) url.value = '';
      var reader = new FileReader();
      reader.onload = function () { show(String(reader.result)); };
      reader.readAsDataURL(selected);
    });
    if (url) url.addEventListener('change', function () {
      var value = url.value.trim();
      if (/^https?:\/\//i.test(value)) { if (file) file.value = ''; show(value); }
      else if (!file || !file.files.length) show(original);
    });
  });

  var city = form.querySelector('[data-venue-city-input]');
  var cityId = form.querySelector('[data-venue-city-id]');
  var cityList = form.querySelector('[data-venue-city-suggestions]');
  var country = form.querySelector('[data-venue-country-input]');
  var countryId = form.querySelector('[data-venue-country-id]');
  var countryList = form.querySelector('[data-venue-country-suggestions]');
  var iso = form.querySelector('[data-venue-country-iso2]');
  var timer = 0, requests = { city: 0, country: 0 };
  function close(list) { if (list) { list.hidden = true; list.replaceChildren(); } }
  function load(kind, input, hidden, list, extra, choose, label) {
    var term = input.value.trim(), request = ++requests[kind];
    if (term.length < parseInt(input.getAttribute('data-suggest-min') || '2', 10)) { close(list); return; }
    var query = '?q=' + encodeURIComponent(term);
    Object.keys(extra || {}).forEach(function (key) { var value = extra[key](); if (value) query += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(value); });
    fetch(input.getAttribute('data-suggest-url') + query, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(function (response) { if (!response.ok) throw new Error('Suggestion request failed'); return response.json(); })
      .then(function (data) {
        if (request !== requests[kind] || term !== input.value.trim()) return;
        list.replaceChildren();
        (Array.isArray(data.items) ? data.items : []).forEach(function (item) {
          var option = document.createElement('button');
          option.type = 'button'; option.className = 'autocomplete-option'; option.setAttribute('role', 'option');
          option.textContent = label(item);
          option.addEventListener('click', function () { choose(item); close(list); input.focus(); });
          list.appendChild(option);
        });
        list.hidden = list.childElementCount === 0;
      }).catch(function () { if (request === requests[kind]) close(list); });
  }
  if (city) city.addEventListener('input', function () {
    cityId.value = '0'; window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      load('city', city, cityId, cityList, { country_id: function () { return countryId.value; } }, function (item) {
        city.value = item.name; cityId.value = String(item.id || 0);
        country.value = item.country; countryId.value = String(item.country_id); iso.textContent = item.iso2 || '';
      }, function (item) { return item.name + ' · ' + item.country; });
    }, 180);
  });
  if (country) country.addEventListener('input', function () {
    countryId.value = '0'; iso.textContent = ''; window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      load('country', country, countryId, countryList, {}, function (item) {
        country.value = item.name; countryId.value = String(item.id); iso.textContent = item.iso2 || '';
      }, function (item) { return item.name; });
    }, 180);
  });
  document.addEventListener('click', function (event) {
    if (!event.target.closest || !event.target.closest('[data-band-form] label')) { close(cityList); close(countryList); }
  });
})();
