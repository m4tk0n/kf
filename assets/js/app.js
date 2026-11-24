/* assets/js/app.js */
(function () {
  'use strict';
  var sel = document.querySelector('.bar select');
  if (sel && !document.getElementById('view')) {
    sel.addEventListener('change', function () { sel.form.submit(); });
  }

  // potvrzení nebezpečných akcí (CSP zakazuje inline JS)
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (t && !window.confirm(t.getAttribute('data-confirm'))) ev.preventDefault();
  });

  // Zavření informačního modalu o převzetí účtu.
  var sessionModal = document.getElementById('session-replaced-modal');
  if (sessionModal) {
    var closeModal = sessionModal.querySelector('[data-modal-close]');
    if (closeModal) closeModal.addEventListener('click', function () {
      sessionModal.remove();
    });
    if (closeModal) closeModal.focus();
  }

})();
