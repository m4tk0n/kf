(function () {
  'use strict';
  // Lightbox: kliknutí na obrázek ho zobrazí přes celou obrazovku, další kliknutí nebo Esc ho zavře.
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('[data-lightbox]') : null;
    if (!a) return;
    ev.preventDefault();
    var box = document.createElement('div');
    box.className = 'lightbox';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    var img = document.createElement('img');
    img.src = a.getAttribute('href');
    var thumb = a.querySelector('img');
    img.alt = thumb ? thumb.alt : '';
    box.appendChild(img);
    document.body.appendChild(box);
    function onKey(e) { if (e.key === 'Escape') close(); }
    function close() {
      box.remove();
      document.removeEventListener('keydown', onKey);
    }
    box.addEventListener('click', close);
    document.addEventListener('keydown', onKey);
  });

})();
