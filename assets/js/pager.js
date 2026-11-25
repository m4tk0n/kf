/* assets/js/pager.js */
(function () {
  'use strict';
  // Navigace pageru; samotné načtení obsahu zajišťuje list.js.
  document.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
    var link = ev.target.closest ? ev.target.closest('#view a.pg') : null;
    if (!link || !window.kfLoadView) return;
    ev.preventDefault();
    window.kfLoadView(link.getAttribute('href'), true);
  });
})();
