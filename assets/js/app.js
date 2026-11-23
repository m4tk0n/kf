/* assets/js/app.js */
(function () {
  'use strict';
  var sel = document.querySelector('.bar select');
  if (sel) sel.addEventListener('change', function () { sel.form.submit(); });

  // potvrzení nebezpečných akcí (CSP zakazuje inline JS)
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (t && !window.confirm(t.getAttribute('data-confirm'))) ev.preventDefault();
  });

  // zavření uživatelského menu kliknutím mimo něj
  var menu = document.querySelector('.umenu');
  if (menu) document.addEventListener('click', function (ev) {
    if (menu.open && !menu.contains(ev.target)) menu.open = false;
  });
})();
