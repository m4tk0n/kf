(function () {
  'use strict';
  // Měsíční kalendář v pageru akcí.
  var calTrigger = document.querySelector('[data-cal]');
  if (!calTrigger) return;

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
    var requestId = 0;

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

    var heading = document.createElement('div');
    heading.className = 'calendar-head';
    heading.appendChild(makeButton('‹', 'calendar-nav', 'Předchozí měsíc', function () { moveMonth(-1); }));
    var title = document.createElement('h2');
    heading.appendChild(title);
    heading.appendChild(makeButton('›', 'calendar-nav', 'Další měsíc', function () { moveMonth(1); }));
    dialog.appendChild(heading);

    var body = document.createElement('div');
    body.className = 'calendar-body';
    var grid = document.createElement('div');
    grid.className = 'calendar-grid';
    for (var w = 0; w < 7; w++) {
      var weekday = new Date(2024, 0, 1 + w);
      var wd = document.createElement('span');
      wd.className = 'calendar-weekday';
      wd.textContent = weekdayFmt.format(weekday);
      grid.appendChild(wd);
    }
    var dayCells = [];
    for (var i = 0; i < 42; i++) {
      var cell = document.createElement('span');
      cell.className = 'calendar-day calendar-placeholder';
      cell.setAttribute('aria-hidden', 'true');
      grid.appendChild(cell);
      dayCells.push(cell);
    }
    body.appendChild(grid);

    dialog.appendChild(body);

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

    function setLoading(isLoading) {
      dialog.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function renderCalendar(data) {
      var first = new Date(current.getFullYear(), current.getMonth(), 1);
      var monday = new Date(first);
      monday.setDate(first.getDate() - ((first.getDay() + 6) % 7));
      dayCells.forEach(function (oldCell, i) {
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
        oldCell.replaceWith(day);
        dayCells[i] = day;
      });
      setLoading(false);
    }

    function loadMonth() {
      var thisRequest = ++requestId;
      var month = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0');
      title.textContent = monthFmt.format(current);
      setLoading(true);
      var url = urlTemplate.replace('__M__', encodeURIComponent(month));
      if (window.KFFilters) url = window.KFFilters.decorateUrl(url);
      fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (response) {
          if (!response.ok) throw new Error('HTTP ' + response.status);
          return response.json();
        })
        .then(function (data) {
          if (thisRequest !== requestId || !activeModal) return;
          renderCalendar(data);
        })
        .catch(function () {
          if (thisRequest !== requestId || !activeModal) return;
          setLoading(false);
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

  // Pager se po částečném načtení nahrazuje, proto delegovaný klik.
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-cal]') : null;
    if (t) { calTrigger = t; openCalendar(); }
  });
})();
