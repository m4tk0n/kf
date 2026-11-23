(function () {
  'use strict';
  // Výpis bez obnovení stránky: živé hledání, hvězdička, stránkování a kalendář.
  var view = document.getElementById('view');
  var viewSeq = 0;
  var positionKey = 'kf:list-return-position';

  function saveListPosition(item) {
    if (!item) return;
    var rect = item.getBoundingClientRect();
    var state = {
      id: item.getAttribute('data-list-item'),
      top: rect.top,
      scrollY: window.scrollY
    };
    try {
      sessionStorage.setItem(positionKey, JSON.stringify(state));
    } catch (e) {
      // Pokud úložiště není dostupné, návrat funguje bez obnovy pozice.
    }
  }

  function restoreListPosition() {
    var state;
    try {
      var raw = sessionStorage.getItem(positionKey);
      if (!raw) return;
      sessionStorage.removeItem(positionKey);
      state = JSON.parse(raw);
    } catch (e) {
      return;
    }
    if (!state || !/^\d+$/.test(String(state.id)) || !Number.isFinite(Number(state.top))) return;

    var item = view && view.querySelector('[data-list-item="' + String(state.id) + '"]');
    if (!item) return;

    var rect = item.getBoundingClientRect();
    window.scrollTo(0, Math.max(0, window.scrollY + rect.top - Number(state.top)));
  }

  function applyViewState() {
    var st = view.querySelector('[data-view-state]');
    if (!st) return;
    var on = st.getAttribute('data-fav') === '1';
    view.setAttribute('data-fav', on ? '1' : '0');
    var star = document.querySelector('[data-favtop]');
    if (star) {
      var label = star.getAttribute(on ? 'data-label-on' : 'data-label-off');
      star.classList.toggle('on', on);
      star.setAttribute('aria-pressed', on ? 'true' : 'false');
      star.setAttribute('aria-label', label);
      star.title = label;
      star.setAttribute('href', st.getAttribute('data-fav-href'));
    }
    var bar = document.querySelector('.bar');
    var hid = bar ? bar.querySelector('input[name="fav"]') : null;
    if (bar && on && !hid) {
      hid = document.createElement('input');
      hid.type = 'hidden';
      hid.name = 'fav';
      hid.value = '1';
      bar.insertBefore(hid, bar.firstChild);
    }
    if (hid && !on) hid.remove();
    st.remove();
  }

  function loadView(href, push, replace) {
    if (!view) { window.location.href = href; return; }
    var seq = ++viewSeq;
    view.classList.add('loading');
    var u = href + (href.indexOf('?') > -1 ? '&' : '?') + 'partial=1';
    fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function (html) {
        if (seq !== viewSeq) return;
        view.innerHTML = html;
        applyViewState();
        view.classList.remove('loading');
        if (push) history.pushState(null, '', href);
        else if (replace) history.replaceState(null, '', href);
      })
      .catch(function () {
        if (seq === viewSeq) {
          view.classList.remove('loading');
          // Při chybě živého hledání zůstane stránka i klávesnice zachována.
        }
      });
  }

  function searchFromForm(form, minChars) {
    if (!form) return;
    var params = new URLSearchParams(new FormData(form));
    var query = (params.get('q') || '').trim();
    var currentQuery = (new URLSearchParams(window.location.search).get('q') || '').trim();

    // Minimální délka je společná s nastavením autocomplete v config.php.
    // Pod limitem se nehledá; pokud už ale byl aktivní delší dotaz, vrátí se celý seznam.
    if (query.length < minChars) {
      if (currentQuery.length < minChars) return;
      params.delete('q');
    }

    // Nový dotaz vždy začíná od první stránky; datum a pozice pageru akcí
    // patří k předchozímu hledání a nesmí omezit nové výsledky.
    params.delete('p');
    params.delete('d');
    params.delete('k');
    var href = form.action + (params.toString() ? '?' + params.toString() : '');
    loadView(href, false, true);
  }

  window.kfLoadView = view ? function (href, push) { loadView(href, push !== false); } : null;

  if (view) {
    var bar = document.querySelector('.bar');
    var searchInput = bar ? bar.querySelector('input[name="q"]') : null;
    var minSearchChars = searchInput ? Math.max(1, parseInt(searchInput.getAttribute('data-min-chars') || '2', 10) || 2) : 2;

    // Každá změna textu vyhledá znovu a nahradí pouze obsah výpisu.
    // Input zůstává mimo #view, takže neztrácí fokus ani mobilní klávesnici.
    if (searchInput && bar) {
      searchInput.addEventListener('input', function () {
        searchFromForm(bar, minSearchChars);
      });
      bar.addEventListener('submit', function (ev) {
        ev.preventDefault();
        searchFromForm(bar);
      });
      var sectionSelect = bar.querySelector('select[name="s"]');
      if (sectionSelect) {
        sectionSelect.addEventListener('change', function () {
          searchFromForm(bar);
        });
      }
    }

    document.addEventListener('click', function (ev) {
      if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;

      var item = ev.target.closest ? ev.target.closest('#view a[data-list-item]') : null;
      if (item) {
        saveListPosition(item);
        return;
      }

      var a = ev.target.closest ? ev.target.closest('[data-favtop], #view a.pg') : null;
      if (!a) return;
      ev.preventDefault();
      loadView(a.getAttribute('href'), true);
    });

    window.addEventListener('load', restoreListPosition, { once: true });
    window.addEventListener('pageshow', function (ev) {
      if (ev.persisted) {
        try { sessionStorage.removeItem(positionKey); } catch (e) {}
      }
    });
    window.addEventListener('popstate', function () { loadView(window.location.href, false); });
  }
})();