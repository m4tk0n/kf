(function () {
  'use strict';

  var menu = document.querySelector('.umenu');
  var head = document.querySelector('.head');

  if (!menu || !head) return;

  var blocker;

  function closeMenu() {
    menu.open = false;
    if (blocker) blocker.remove();
    blocker = null;
    head.classList.remove('umenu-blocking');
  }

  function openMenu() {
    if (!menu.open || blocker) return;

    blocker = document.createElement('div');
    blocker.className = 'umenu-outside-blocker';
    blocker.setAttribute('aria-hidden', 'true');
    blocker.addEventListener('click', closeMenu);

    head.appendChild(blocker);
    head.classList.add('umenu-blocking');
  }

  menu.addEventListener('toggle', function () {
    if (menu.open) openMenu();
    else closeMenu();
  });

  if (menu.open) openMenu();
})();
