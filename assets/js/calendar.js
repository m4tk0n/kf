(function () {
  'use strict';
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
        if (window.KFFilters) url = window.KFFilters.decorateUrl(url);
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

})();
