(function () {
  'use strict';

  var menu = document.querySelector('.umenu');
  if (!menu) return;

  // Kliknutí mimo otevřené menu slouží pouze k jeho zavření.
  // Capture fáze zabrání aktivaci prvku pod místem kliknutí.
  document.addEventListener('click', function (ev) {
    if (!menu.open || menu.contains(ev.target)) return;

    ev.preventDefault();
    ev.stopPropagation();
    ev.stopImmediatePropagation();
    menu.open = false;
  }, true);
})();
