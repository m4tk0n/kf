/* assets/js/form-validation.js — společná validace formulářů */
(function () {
  'use strict';

  function validEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
  }
  function validUrl(value) {
    try {
      var parsed = new URL(value.trim());
      return (parsed.protocol === 'http:' || parsed.protocol === 'https:') && !!parsed.hostname;
    } catch (error) {
      return false;
    }
  }
  function validAddress(value) { return value.trim().length >= 2; }
  function validCity(value, selectedId) { return value.trim().length >= 2 && Number(selectedId) > 0; }

  function validate(form) {
    var valid = true;
    Array.prototype.forEach.call(form.querySelectorAll('[data-validate-email]'), function (input) {
      var value = input.value.trim();
      var ok = value === '' || validEmail(value);
      input.setCustomValidity(ok ? '' : (input.getAttribute('data-invalid-message') || 'Enter a valid e-mail address.'));
      valid = valid && ok;
    });
    Array.prototype.forEach.call(form.querySelectorAll('[data-validate-url]'), function (input) {
      var value = input.value.trim();
      var ok = value === '' || validUrl(value);
      input.setCustomValidity(ok ? '' : (input.getAttribute('data-invalid-message') || 'Enter a valid URL beginning with http:// or https://.'));
      valid = valid && ok;
    });
    Array.prototype.forEach.call(form.querySelectorAll('[data-validate-address]'), function (input) {
      var value = input.value.trim();
      var gps = form.querySelectorAll('[data-validate-gps]');
      var hasGps = Array.prototype.some.call(gps, function (field) {
        return field.value.trim() !== '' && validUrl(field.value);
      });
      var ok = value === '' ? hasGps : validAddress(value);
      var message = value.length === 1
        ? (input.getAttribute('data-invalid-message') || 'Address must contain at least 2 characters.')
        : (form.getAttribute('data-location-required') || 'Enter an address or a valid GPS link.');
      input.setCustomValidity(ok ? '' : message);
      valid = valid && ok;
    });
    Array.prototype.forEach.call(form.querySelectorAll('[data-validate-city]'), function (input) {
      var selector = input.getAttribute('data-city-id-selector') || '[name="city_id"]';
      var idField = form.querySelector(selector);
      var ok = validCity(input.value, idField ? idField.value : 0);
      input.setCustomValidity(ok ? '' : (input.getAttribute('data-invalid-message') || 'Select a valid city.'));
      valid = valid && ok;
    });
    return valid;
  }

  window.KFFormValidation = {
    validEmail: validEmail,
    validUrl: validUrl,
    validAddress: validAddress,
    validCity: validCity,
    validate: validate
  };

  Array.prototype.forEach.call(document.querySelectorAll('form[data-common-validation]'), function (form) {
    form.addEventListener('input', function (event) {
      if (event.target.matches('[data-validate-email], [data-validate-url], [data-validate-address], [data-validate-city], [data-validate-gps]')) validate(form);
    });
    form.addEventListener('change', function () { validate(form); });
    form.addEventListener('submit', function (event) {
      if (!validate(form)) {
        event.preventDefault();
        var invalid = form.querySelector(':invalid');
        if (invalid) invalid.reportValidity();
      }
    });
  });
})();
