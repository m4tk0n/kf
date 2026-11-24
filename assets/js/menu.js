(function () {
  'use strict';
  // zavření uživatelského menu kliknutím mimo něj
  var menu = document.querySelector('.umenu');
  if (menu) document.addEventListener('click', function (ev) {
    if (menu.open && !menu.contains(ev.target)) menu.open = false;
  });
})();
