/* /debug/debug.js */
(function () {
  'use strict';

  var params = new URLSearchParams(location.search);
  if (params.get('debug') !== '1') return;

  var events = [];
  var panel;
  var output;
  var nativeAlert = window.alert.bind(window);

  function add(type, message, detail) {
    events.push({
      time: new Date().toLocaleTimeString(),
      type: type,
      message: String(message == null ? 'null' : message),
      detail: detail || ''
    });
    if (events.length > 200) events.shift();
    render();
  }

  function render() {
    if (!output) return;
    output.replaceChildren();
    events.slice(-100).forEach(function (event) {
      var item = document.createElement('div');
      var heading = document.createElement('b');
      var body = document.createElement('pre');
      heading.textContent = event.time + '  ' + event.type;
      body.textContent = event.message + (event.detail ? '\n' + event.detail : '');
      item.append(heading, body);
      output.appendChild(item);
    });
    output.scrollTop = output.scrollHeight;
  }

  function copy() {
    var text = events.map(function (e) {
      return '[' + e.time + '] ' + e.type + ': ' + e.message + (e.detail ? '\n' + e.detail : '');
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
    var css = document.createElement('link');
    css.rel = 'stylesheet';
    var script = document.querySelector('script[src*="debug/debug.js"]');
    css.href = new URL('debug.css', script ? script.src : location.href).href;
    document.head.appendChild(css);

    panel = document.createElement('section');
    panel.id = 'kf-debug';
    panel.innerHTML = '<header><b>DEBUG</b><button type="button" data-copy>Kopírovat</button><button type="button" data-clear>Vymazat</button><button type="button" data-hide>×</button></header><div class="kf-debug-output"></div>';
    document.body.appendChild(panel);
    output = panel.querySelector('.kf-debug-output');

    panel.querySelector('[data-copy]').addEventListener('click', copy);
    panel.querySelector('[data-clear]').addEventListener('click', function () {
      events = [];
      render();
    });
    panel.querySelector('[data-hide]').addEventListener('click', function () {
      panel.remove();
      panel = null;
      output = null;
    });

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
      (el.href ? ' → ' + el.href : '') + (el.textContent ? ' (' + el.textContent.trim().slice(0, 100) + ')' : ''));
  }, true);

  var originalFetch = window.fetch;
  if (originalFetch) {
    window.fetch = function () {
      var started = Date.now();
      var url = typeof arguments[0] === 'string' ? arguments[0] : arguments[0].url;
      add('FETCH', url);
      return originalFetch.apply(this, arguments).then(function (response) {
        add('HTTP ' + response.status, url, (Date.now() - started) + ' ms');
        return response;
      }).catch(function (error) {
        add('FETCH ERROR', url, error.stack || error);
        throw error;
      });
    };
  }

  document.addEventListener('DOMContentLoaded', init);
})();
