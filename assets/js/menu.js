(function () {
  'use strict';

  var menu = document.querySelector('.umenu');
  var head = document.querySelector('.head');
  if (!menu || !head) return;

  var blocker = null;

  function removeBlocker() {
    if (blocker && blocker.parentNode) blocker.parentNode.removeChild(blocker);
    blocker = null;
    head.classList.remove('umenu-blocking');
  }

  function closeMenu() {
    menu.open = false;
    removeBlocker();
  }

  function addBlocker() {
    if (blocker || !menu.open) return;

    blocker = document.createElement('div');
    blocker.className = 'umenu-outside-blocker';
    blocker.setAttribute('aria-hidden', 'true');

    // Vrstva fyzicky překryje stránku. Událost proto vůbec nedorazí
    // k prvku pod místem kliknutí (ani nevyvolá jeho :active stav).
    blocker.addEventListener('pointerdown', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      if (typeof ev.stopImmediatePropagation === 'function') {
        ev.stopImmediatePropagation();
      }
    });

    blocker.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      if (typeof ev.stopImmediatePropagation === 'function') {
        ev.stopImmediatePropagation();
      }
      closeMenu();
    });

    document.body.appendChild(blocker);
    head.classList.add('umenu-blocking');
  }

  menu.addEventListener('toggle', function () {
    if (menu.open) addBlocker();
    else removeBlocker();
  });

  // Pro případ, že by menu bylo otevřené už při inicializaci skriptu.
  if (menu.open) addBlocker();
})();
