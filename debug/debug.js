/* /debug/debug.js */
(function () {
  'use strict';

  var storageKey = 'kf-debug-state';
  var saved = null;
  var events = [];
  var panel, output, toggle, filterSelect;
  var activeFilter = 'all';

  try {
    saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    if (saved && Array.isArray(saved.events)) events = saved.events.slice(-200);
    if (saved && saved.filter) activeFilter = saved.filter;
  } catch (e) {}

  function persist() {
    try {
      sessionStorage.setItem(storageKey, JSON.stringify({
        events: events.slice(-200),
        filter: activeFilter,
        open: !!(panel && !panel.hidden),
        left: panel ? panel.style.left : '',
        top: panel ? panel.style.top : '',
        right: panel ? panel.style.right : '',
        bottom: panel ? panel.style.bottom : ''
      }));
    } catch (e) {}
  }
  var nativeAlert = window.alert.bind(window);

  function add(type, message, detail) {
    events.push({
      time: new Date().toLocaleTimeString(),
      type: type,
      message: String(message == null ? 'null' : message),
      detail: detail || ''
    });
    if (events.length > 200) events.shift();
    persist();
    render();
  }

  function isError(event) {
    return String(event.type).includes('ERROR') || /^HTTP [45]\d\d$/.test(String(event.type));
  }

  function matchesFilter(event) {
    var type = String(event.type);
    if (activeFilter === 'errors') return isError(event);
    if (activeFilter === 'network') return type === 'FETCH' || type === 'FETCH ERROR' || /^HTTP \d{3}$/.test(type);
    if (activeFilter === 'actions') return type === 'CLICK' || type === 'ALERT';
    if (activeFilter === 'system') return type === 'START' || type === 'INFO';
    return true;
  }

  function render() {
    if (!output) return;
    output.replaceChildren();
    var visibleEvents = events.filter(matchesFilter).slice(-100);
    visibleEvents.forEach(function (event) {
      var item = document.createElement('div');
      if (isError(event)) item.classList.add('kf-debug-error');
      var heading = document.createElement('b');
      var body = document.createElement('pre');
      heading.textContent = event.time + '  ' + event.type;
      body.textContent = event.message + (event.detail ? '\n' + event.detail : '');
      item.append(heading, body);
      output.appendChild(item);
    });
    if (!visibleEvents.length) {
      var empty = document.createElement('div');
      empty.className = 'kf-debug-empty';
      empty.textContent = 'Pro tento filtr nejsou žádné záznamy.';
      output.appendChild(empty);
    }
    // Po překreslení DOMu posuň výpis dolů, aby byl poslední záznam vždy viditelný.
    requestAnimationFrame(function () {
      output.scrollTop = output.scrollHeight;
      requestAnimationFrame(function () {
        output.scrollTop = output.scrollHeight;
      });
    });
  }

  function copy() {
    var text = events.map(function (e) {
      return '[' + e.time + '] ' + e.type + ': ' + e.message +
        (e.detail ? '\n' + e.detail : '');
    }).join('\n\n');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        add('INFO', 'Záznam zkopírován.');
      });
    } else {
      window.prompt('Zkopírujte diagnostický záznam:', text);
    }
  }

  function init() {
    var script = document.querySelector('script[src*="debug/debug.js"]');
    var css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = new URL('debug.css', script ? script.src : location.href).href;
    document.head.appendChild(css);

    toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.id = 'kf-debug-toggle';
    toggle.textContent = 'DEBUG';
    toggle.setAttribute('aria-label', 'Otevřít diagnostický panel');
    document.body.appendChild(toggle);

    panel = document.createElement('section');
    panel.id = 'kf-debug';
    panel.hidden = true;
    panel.innerHTML = '<header class="kf-debug-bar"><strong>DEBUG</strong><select data-filter aria-label="Filtr záznamů"><option value="all">Vše</option><option value="errors">Chyby</option><option value="network">Síť</option><option value="actions">Akce</option><option value="system">Systém</option></select><button type="button" data-copy>Kopírovat</button><button type="button" data-clear>Vymazat</button><button type="button" data-close>×</button></header><div class="kf-debug-output"></div>';
    document.body.appendChild(panel);
    output = panel.querySelector('.kf-debug-output');
    filterSelect = panel.querySelector('[data-filter]');
    filterSelect.value = activeFilter;
    filterSelect.addEventListener('change', function () {
      activeFilter = filterSelect.value;
      persist();
      render();
    });

    if (saved) {
      panel.hidden = !saved.open;
      toggle.hidden = !!saved.open;
      ['left', 'top', 'right', 'bottom'].forEach(function (key) {
        if (saved[key]) panel.style[key] = saved[key];
      });
    }

    toggle.addEventListener('click', function () {
      panel.hidden = false;
      toggle.hidden = true;
      persist();
      render();
    });
    panel.querySelector('[data-close]').addEventListener('click', function () {
      panel.hidden = true;
      toggle.hidden = false;
      persist();
    });
    panel.querySelector('[data-copy]').addEventListener('click', copy);
    panel.querySelector('[data-clear]').addEventListener('click', function () {
      events = [];
      persist();
      render();
    });

    // Přesouvání funguje myší i dotykem; hlavička je úchyt.
    var drag = null;
    panel.querySelector('.kf-debug-bar').addEventListener('pointerdown', function (event) {
      if (event.target.closest('button,select,input')) return;
      var rect = panel.getBoundingClientRect();
      drag = { x: event.clientX, y: event.clientY, left: rect.left, top: rect.top };
      panel.style.left = rect.left + 'px';
      panel.style.top = rect.top + 'px';
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
      event.currentTarget.setPointerCapture(event.pointerId);
    });
    panel.querySelector('.kf-debug-bar').addEventListener('pointermove', function (event) {
      if (!drag) return;
      var left = Math.max(0, Math.min(window.innerWidth - panel.offsetWidth, drag.left + event.clientX - drag.x));
      var top = Math.max(0, Math.min(window.innerHeight - 44, drag.top + event.clientY - drag.y));
      panel.style.left = left + 'px';
      panel.style.top = top + 'px';
      persist();
    });
    panel.querySelector('.kf-debug-bar').addEventListener('pointerup', function () { drag = null; });
    panel.querySelector('.kf-debug-bar').addEventListener('pointercancel', function () { drag = null; });

    add('START', 'Debug aktivní', location.href);
  }

  window.alert = function (message) {
    add('ALERT', message, new Error('Místo volání alert()').stack);
    nativeAlert(message);
  };

  window.addEventListener('error', function (event) {
    add('JS ERROR', event.message || 'Chyba načítání souboru',
      (event.filename || '') + ':' + (event.lineno || 0) + ':' + (event.colno || 0) +
      (event.error && event.error.stack ? '\n' + event.error.stack : ''));
  });

  window.addEventListener('unhandledrejection', function (event) {
    add('PROMISE ERROR', event.reason && event.reason.stack ? event.reason.stack : event.reason);
  });

  document.addEventListener('click', function (event) {
    var el = event.target.closest && event.target.closest('a,button');
    if (!el) return;
    add('CLICK', el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') +
      (el.href ? ' → ' + el.href : '') +
      (el.textContent ? ' (' + el.textContent.trim().slice(0, 100) + ')' : ''));
  }, true);

  var originalFetch = window.fetch;
  if (originalFetch) {
    window.fetch = function () {
      var started = Date.now();
      var input = arguments[0];
      var url;
      try {
        url = typeof input === 'string' ? input :
          (input && typeof input.url === 'string' ? input.url : String(input));
      } catch (e) {
        url = '[URL se nepodařilo přečíst]';
      }

      // Diagnostické logování nesmí nikdy zabránit zavolání původního fetch().
      try { add('FETCH', url); } catch (e) {}
      return originalFetch.apply(this, arguments).then(function (response) {
        try { add('HTTP ' + response.status, url, (Date.now() - started) + ' ms'); } catch (e) {}
        return response;
      }).catch(function (error) {
        try { add('FETCH ERROR', url, error && error.stack ? error.stack : String(error)); } catch (e) {}
        throw error;
      });
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
