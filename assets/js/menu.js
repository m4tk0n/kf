(function () {
  'use strict';

  var menu = document.querySelector('.umenu');
  if (!menu) return;

  function blockOutsideInteraction(ev) {
    if (!menu.open || menu.contains(ev.target)) return;

    // Zablokovat interakci už při stisku/začátku dotyku.
    // Samotné click je příliš pozdě například pro nativní select.
    ev.preventDefault();
    ev.stopPropagation();
    if (typeof ev.stopImmediatePropagation === 'function') {
      ev.stopImmediatePropagation();
    }

    menu.open = false;
  }

  // Pointerdown zachytí myš, dotyk i pero ještě před výchozí akcí prvku.
  document.addEventListener('pointerdown', blockOutsideInteraction, true);

  // Záložní události pro prostředí bez Pointer Events.
  if (!('onpointerdown' in window)) {
    document.addEventListener('mousedown', blockOutsideInteraction, true);
    document.addEventListener('touchstart', blockOutsideInteraction, {
      capture: true,
      passive: false
    });
  }

  // Záloha pro syntetické/klávesnicí vyvolané kliknutí a prostředí,
  // ve kterých nedošlo k pointerdown.
  document.addEventListener('click', blockOutsideInteraction, true);
})();
