(function () {
  'use strict';

  var menu = document.querySelector('.umenu');
  if (!menu) return;

  // Zabrání tomu, aby click navazující na zavření menu aktivoval prvek pod ním.
  var blockFollowingClick = false;

  function stopEvent(ev) {
    ev.preventDefault();
    ev.stopPropagation();
    if (typeof ev.stopImmediatePropagation === 'function') {
      ev.stopImmediatePropagation();
    }
  }

  function blockOutsidePointer(ev) {
    if (menu.contains(ev.target)) {
      if (!menu.open) blockFollowingClick = false;
      return;
    }

    if (!menu.open) return;

    stopEvent(ev);
    blockFollowingClick = true;
    menu.open = false;
  }

  function blockOutsideClick(ev) {
    if (menu.contains(ev.target)) return;

    if (menu.open) {
      stopEvent(ev);
      menu.open = false;
      blockFollowingClick = false;
      return;
    }

    if (blockFollowingClick) {
      stopEvent(ev);
      blockFollowingClick = false;
    }
  }

  // Zachytit kliknutí/tap mimo menu před cílovým prvkem a jeho obsluhou.
  document.addEventListener('pointerdown', blockOutsidePointer, true);

  // Záložní ochrana pro kliknutí vyvolané klávesnicí nebo skriptem.
  document.addEventListener('click', blockOutsideClick, true);
})();
