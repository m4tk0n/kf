/* assets/js/app.js */
(function () {
  'use strict';
  var sel = document.querySelector('.bar select');
  if (sel) sel.addEventListener('change', function () { sel.form.submit(); });

  // potvrzení nebezpečných akcí (CSP zakazuje inline JS)
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (t && !window.confirm(t.getAttribute('data-confirm'))) ev.preventDefault();
  });

  // Zavření informačního modalu o převzetí účtu.
  var sessionModal = document.getElementById('session-replaced-modal');
  if (sessionModal) {
    var closeModal = sessionModal.querySelector('[data-modal-close]');
    if (closeModal) closeModal.addEventListener('click', function () {
      sessionModal.remove();
    });
    if (closeModal) closeModal.focus();
  }

  // Měsíční kalendář v pageru akcí.
  var calTrigger = document.querySelector('[data-cal]');
  if (calTrigger) {
    var activeModal = null;
    var activeKeyHandler = null;
    var lang = document.documentElement.lang || 'cs';
    var monthFmt = new Intl.DateTimeFormat(lang, { month: 'long', year: 'numeric' });
    var weekdayFmt = new Intl.DateTimeFormat(lang, { weekday: 'short' });
    var dayFmt = new Intl.DateTimeFormat(lang, { day: 'numeric' });

    function isoDate(date) {
      return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }
    function addMonths(year, month, delta) {
      return new Date(year, month - 1 + delta, 1);
    }
    function closeCalendar() {
      if (activeModal) {
        activeModal.remove();
        activeModal = null;
      }
      if (activeKeyHandler) {
        document.removeEventListener('keydown', activeKeyHandler);
        activeKeyHandler = null;
      }
    }
    function openCalendar() {
      if (activeModal) return;
      var selected = calTrigger.getAttribute('data-date') || isoDate(new Date());
      var current = new Date(selected + 'T12:00:00');
      var urlTemplate = calTrigger.getAttribute('data-cal-url') || '';
      var dayTemplate = calTrigger.getAttribute('data-day-url') || '';

      var backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop calendar-backdrop';
      backdrop.setAttribute('role', 'presentation');
      var dialog = document.createElement('section');
      dialog.className = 'modal calendar-modal';
      dialog.setAttribute('role', 'dialog');
      dialog.setAttribute('aria-modal', 'true');
      dialog.setAttribute('aria-label', 'Kalendář akcí');
      backdrop.appendChild(dialog);
      document.body.appendChild(backdrop);
      activeModal = backdrop;

      function makeButton(text, cls, label, handler) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = cls;
        button.textContent = text;
        button.setAttribute('aria-label', label);
        button.addEventListener('click', handler);
        return button;
      }
      function renderLoading(title) {
        dialog.replaceChildren();
        var head = document.createElement('div');
        head.className = 'calendar-head';
        head.appendChild(makeButton('‹', 'calendar-nav', 'Předchozí měsíc', function () { moveMonth(-1); }));
        var heading = document.createElement('h2');
        heading.textContent = title;
        head.appendChild(heading);
        head.appendChild(makeButton('›', 'calendar-nav', 'Další měsíc', function () { moveMonth(1); }));
        dialog.appendChild(head);
        var loading = document.createElement('p');
        loading.className = 'calendar-loading';
        loading.textContent = 'Načítám…';
        dialog.appendChild(loading);
      }
      function renderCalendar(data) {
        dialog.replaceChildren();
        var first = new Date(current.getFullYear(), current.getMonth(), 1);
        var heading = document.createElement('div');
        heading.className = 'calendar-head';
        heading.appendChild(makeButton('‹', 'calendar-nav', 'Předchozí měsíc', function () { moveMonth(-1); }));
        var title = document.createElement('h2');
        title.textContent = monthFmt.format(first);
        heading.appendChild(title);
        heading.appendChild(makeButton('›', 'calendar-nav', 'Další měsíc', function () { moveMonth(1); }));
        dialog.appendChild(heading);

        var grid = document.createElement('div');
        grid.className = 'calendar-grid';
        var monday = new Date(first);
        monday.setDate(first.getDate() - ((first.getDay() + 6) % 7));
        for (var w = 0; w < 7; w++) {
          var weekday = new Date(2024, 0, 1 + w);
          var wd = document.createElement('span');
          wd.className = 'calendar-weekday';
          wd.textContent = weekdayFmt.format(weekday);
          grid.appendChild(wd);
        }
        for (var i = 0; i < 42; i++) {
          var date = new Date(monday);
          date.setDate(monday.getDate() + i);
          var key = isoDate(date);
          var inMonth = date.getMonth() === current.getMonth();
          var day = makeButton(dayFmt.format(date), 'calendar-day' + (inMonth ? '' : ' outside') + (key === selected ? ' selected' : ''), 'Vybrat ' + key, function (value) {
            return function () {
              closeCalendar();
              var target = dayTemplate.replace('__D__', encodeURIComponent(value));
              if (window.kfLoadView) window.kfLoadView(target); else window.location.href = target;
            };
          }(key));
          if (key === isoDate(new Date())) day.classList.add('today');
          var types = data.days && data.days[key] ? data.days[key] : [];
          if (types.length) {
            var dots = document.createElement('span');
            dots.className = 'calendar-dots';
            types.forEach(function (type) {
              var dot = document.createElement('i');
              dot.className = 'calendar-dot type-' + Number(type);
              dots.appendChild(dot);
            });
            day.appendChild(dots);
          }
          grid.appendChild(day);
        }
        dialog.appendChild(grid);
        var footer = document.createElement('div');
        footer.className = 'calendar-footer';
        [['Koncert', 'type-1'], ['Festival', 'type-2'], ['Ostatní', 'type-3']].forEach(function (item) {
          var legend = document.createElement('span');
          legend.className = 'calendar-legend';
          var dot = document.createElement('i');
          dot.className = 'calendar-dot ' + item[1];
          legend.appendChild(dot);
          legend.appendChild(document.createTextNode(item[0]));
          footer.appendChild(legend);
        });
        dialog.appendChild(footer);
      }
      function loadMonth() {
        var month = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0');
        renderLoading(monthFmt.format(current));
        var url = urlTemplate.replace('__M__', encodeURIComponent(month));
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
          })
          .then(renderCalendar)
          .catch(function () {
            var error = document.createElement('p');
            error.className = 'calendar-loading';
            error.textContent = 'Kalendář se nepodařilo načíst.';
            dialog.replaceChildren(error);
          });
      }
      function moveMonth(delta) {
        current = addMonths(current.getFullYear(), current.getMonth() + 1, delta);
        loadMonth();
      }
      backdrop.addEventListener('click', function (ev) {
        if (ev.target === backdrop) closeCalendar();
      });
      loadMonth();
      activeKeyHandler = function (ev) {
        if (ev.key === 'Escape') closeCalendar();
      };
      document.addEventListener('keydown', activeKeyHandler);
    }
    // pager se po částečném načtení nahrazuje, proto delegovaný klik a aktuální tlačítko
    document.addEventListener('click', function (ev) {
      var t = ev.target.closest ? ev.target.closest('[data-cal]') : null;
      if (t) { calTrigger = t; openCalendar(); }
    });
  }

  // Výpis bez obnovení stránky: hvězdička v liště, stránkování a výběr data v kalendáři.
  var view = document.getElementById('view');
  var viewSeq = 0;
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
  function loadView(href, push) {
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
      })
      .catch(function () {
        if (seq === viewSeq) window.location.href = href;
      });
  }
  window.kfLoadView = view ? function (href) { loadView(href, true); } : null;
  if (view) {
    document.addEventListener('click', function (ev) {
      if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
      var a = ev.target.closest ? ev.target.closest('[data-favtop], #view a.pg') : null;
      if (!a) return;
      ev.preventDefault();
      loadView(a.getAttribute('href'), true);
    });
    window.addEventListener('popstate', function () { loadView(window.location.href, false); });
  }

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
          loadView(window.location.href, false);
        }
      })
      .catch(function (e) {
        window.alert(b.getAttribute(e.message === 'limit' ? 'data-msg-limit' : 'data-msg-error'));
      })
      .then(function () { b.disabled = false; });
  });

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

  // zavření uživatelského menu kliknutím mimo něj
  var menu = document.querySelector('.umenu');
  if (menu) document.addEventListener('click', function (ev) {
    if (menu.open && !menu.contains(ev.target)) menu.open = false;
  });
})();
