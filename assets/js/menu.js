(function () {
  'use strict';

  var menu = document.querySelector('.umenu');
  if (!menu) return;

  // Click vznikající po zavření menu musí být stále zablokovaný.
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
      // Nový samostatný stisk po zavření menu může normálně pokračovat.
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

    if (menu.open || blockFollowingClick) {
      stopEvent(ev);
      blockFollowingClick = false;
    }
  }

  // Zachytit začátek interakce dříve, než ji převezme cílový prvek.
  document.addEventListener('pointerdown', blockOutsidePointer, true);

  // Záloha pro syntetické a klávesnicí vyvolané kliknutí.
  document.addEventListener('click', blockOutsideClick, true);
})();
