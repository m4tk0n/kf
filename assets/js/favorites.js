(function () {
  'use strict';
  var view = document.getElementById('view');
  // Hvězdička oblíbených (detail i řádky výpisu): idempotentní add/remove, výsledný stav bere ze serveru.
  function setFav(b, on) {
    var label = b.getAttribute(on ? 'data-label-on' : 'data-label-off');
    b.classList.toggle('on', on);
    b.setAttribute('aria-pressed', on ? 'true' : 'false');
    b.setAttribute('aria-label', label);
    b.title = label;
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-fav]') : null;
    if (!b || b.disabled) return;
    var body = new URLSearchParams();
    body.set(b.getAttribute('data-csrf-field'), b.getAttribute('data-csrf'));
    body.set('kind', b.getAttribute('data-kind'));
    body.set('id', b.getAttribute('data-id'));
    body.set('op', b.getAttribute('aria-pressed') === 'true' ? 'remove' : 'add');
    b.disabled = true;
    fetch(b.getAttribute('data-url'), { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json' }, body: body })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.j || !res.j.ok) throw new Error((res.j && res.j.error) || 'error');
        setFav(b, !!res.j.on);
        // ve výpisu filtrovaném na oblíbené položka zmizí ihned, pak se stránka doplní z dalších záznamů
        if (!res.j.on && b.classList.contains('rowfav') && view && view.getAttribute('data-fav') === '1') {
          var li = b.closest('li');
          if (li) li.remove();
          if (window.kfLoadView) window.kfLoadView(window.location.href, false);
        }
      })
      .catch(function (e) {
        window.alert(b.getAttribute(e.message === 'limit' ? 'data-msg-limit' : 'data-msg-error'));
      })
      .then(function () { b.disabled = false; });
  });

})();
