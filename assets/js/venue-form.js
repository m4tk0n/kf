/* assets/js/venue-form.js */
(function () {
  'use strict';

  var form = document.querySelector('[data-venue-form]');
  if (!form) return;

  // Náhled obrázku, stejně jako ve formuláři akce.
  var file = form.querySelector('[data-event-image]');
  var url = form.querySelector('[data-event-image-url]');
  var img = form.querySelector('[data-event-preview]');
  var original = img ? img.getAttribute('src') || '' : '';
  function showImage(src) {
    if (!img) return;
    if (!src) { img.hidden = true; img.removeAttribute('src'); return; }
    img.src = src;
    img.hidden = false;
  }
  if (file) file.addEventListener('change', function () {
    var f = file.files && file.files[0];
    if (!f) { showImage(original); return; }
    if (url) url.value = '';
    var reader = new FileReader();
    reader.onload = function () { showImage(String(reader.result)); };
    reader.readAsDataURL(f);
  });
  if (url) url.addEventListener('change', function () {
    var value = url.value.trim();
    if (/^https:\/\//i.test(value)) {
      if (file) file.value = '';
      showImage(value);
    } else if (!file || !file.files.length) showImage(original);
  });

  var cityInput = form.querySelector('[data-venue-city-input]');
  var cityId = form.querySelector('[data-venue-city-id]');
  var cityList = form.querySelector('[data-venue-city-suggestions]');
  var countryInput = form.querySelector('[data-venue-country-input]');
  var countryId = form.querySelector('[data-venue-country-id]');
  var countryList = form.querySelector('[data-venue-country-suggestions]');
  var countryIso2 = form.querySelector('[data-venue-country-iso2]');

  var addressInput = form.querySelector('[name="address"]');
  var googleMapsInput = form.querySelector('[name="google_maps"]');
  var mapyMapsInput = form.querySelector('[name="mapy_maps"]');
  var locationMessage = form.getAttribute('data-location-required') || 'Enter an address or a GPS link.';
  var addressMessage = form.getAttribute('data-address-invalid') || 'Address must contain at least 2 characters.';
  var cityMessage = form.getAttribute('data-city-invalid') || 'Select a valid city.';
  function validateLocation() {
    var address = addressInput ? addressInput.value.trim() : '';
    var hasGps = [googleMapsInput, mapyMapsInput].some(function (input) {
      return input && input.value.trim() !== '';
    });
    var hasLocation = address.length >= 2 || hasGps;
    if (addressInput) addressInput.setCustomValidity(address.length === 1 ? addressMessage : (hasLocation ? '' : locationMessage));
    return hasLocation && address.length !== 1;
  }
  function validateCity() {
    if (!cityInput) return true;
    var valid = cityInput.value.trim().length >= 2 && cityId && Number(cityId.value) > 0;
    cityInput.setCustomValidity(valid ? '' : cityMessage);
    return valid;
  }
  if (cityInput) {
    cityInput.addEventListener('blur', validateCity);
  }
  [addressInput, googleMapsInput, mapyMapsInput].forEach(function (input) {
    if (input) input.addEventListener('input', validateLocation);
  });
  form.addEventListener('submit', function (event) {
    var locationValid = validateLocation();
    var cityValid = validateCity();
    if (!locationValid || !cityValid) {
      event.preventDefault();
      if (!cityValid && cityInput) cityInput.reportValidity();
      else if (addressInput) addressInput.reportValidity();
    }
  });
  var timer = 0;
  var request = { city: 0, country: 0 };

  function close(list) {
    if (!list) return;
    list.hidden = true;
    list.replaceChildren();
  }
  function showOptions(input, list, items, selectItem, labelFor) {
    list.replaceChildren();
    items.forEach(function (item) {
      if (!item || typeof item.name !== 'string') return;
      var option = document.createElement('button');
      option.type = 'button';
      option.className = 'autocomplete-option';
      option.setAttribute('role', 'option');
      option.textContent = labelFor(item);
      option.addEventListener('click', function () {
        selectItem(item);
        close(list);
        input.focus();
      });
      list.appendChild(option);
    });
    list.hidden = list.childElementCount === 0;
  }
  function load(key, input, list, endpoint, extra, choose, label) {
    var term = input.value.trim();
    var current = ++request[key];
    var min = parseInt(input.getAttribute('data-suggest-min') || '2', 10);
    if (term.length < min) { close(list); return; }
    var query = '?q=' + encodeURIComponent(term);
    Object.keys(extra || {}).forEach(function (key) {
      if (extra[key]) query += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(extra[key]);
    });
    fetch(endpoint + query, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    }).then(function (response) {
      if (!response.ok) throw new Error('Suggestion request failed');
      return response.json();
    }).then(function (data) {
      if (current !== request[key] || term !== input.value.trim()) return;
      showOptions(input, list, Array.isArray(data.items) ? data.items : [], choose, label);
    }).catch(function () {
      if (current === request[key]) close(list);
    });
  }

  if (cityInput && cityId && cityList) {
    cityInput.addEventListener('input', function () {
      cityId.value = '0';
      validateCity();
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        load('city', cityInput, cityList, cityInput.getAttribute('data-suggest-url'),
          { country_id: countryId ? countryId.value : '' },
          function (item) {
            cityInput.value = item.name;
            cityId.value = String(item.id);
            validateCity();
            if (countryInput && countryId) {
              countryInput.value = item.country;
              countryId.value = String(item.country_id);
              if (countryIso2) countryIso2.textContent = item.iso2 || '';
            }
          },
          function (item) { return item.name + ' · ' + item.country; });
      }, 180);
    });
    cityInput.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') close(cityList);
    });
  }

  if (countryInput && countryId && countryList) {
    countryInput.addEventListener('input', function () {
      countryId.value = '0';
      if (countryIso2) countryIso2.textContent = '';
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        load('country', countryInput, countryList, countryInput.getAttribute('data-suggest-url'), {},
          function (item) {
            countryInput.value = item.name;
            countryId.value = String(item.id);
            if (countryIso2) countryIso2.textContent = item.iso2 || '';
          },
          function (item) { return item.name; });
      }, 180);
    });
    countryInput.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') close(countryList);
    });
  }

  document.addEventListener('click', function (event) {
    if (!event.target.closest || !event.target.closest('[data-venue-form] label')) {
      close(cityList);
      close(countryList);
    }
  });
})();
