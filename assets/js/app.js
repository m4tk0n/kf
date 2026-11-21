// assets/js/app.js
(function () {
  'use strict';

  // Rozbalovací menu avatara: zavřít kliknutím mimo a klávesou Esc.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.usermenu[open]').forEach(function (d) {
      if (!d.contains(e.target)) { d.removeAttribute('open'); }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('details.usermenu[open]').forEach(function (d) { d.removeAttribute('open'); });
    }
  });

  // Nenačtená fotka avatara: zůstane barevné kolečko s písmenem.
  document.addEventListener('error', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('avatar-img')) { e.target.remove(); }
  }, true);

  // Potvrzení nebezpečných akcí (atribut data-confirm), bez inline JS kvůli CSP.
  document.addEventListener('submit', function (e) {
    var msg = e.submitter && e.submitter.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });
})();
