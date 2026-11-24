/* assets/js/debug.js */
(function () {
  'use strict';

  var entries = [];
  var limit = 300;
  var panel;
  var output;
  var count;
  var nativeAlert = window.alert.bind(window);
  var nativeConsoleError = console.error ? console.error.bind(console) : function () {};
  var started = performance.now();

  function cleanUrl(value) {
    try {
      var u = new URL(String(value), location.href);
      ['token', 'code', 'state', 'csrf', '_csrf', 'key', 'password', 'secret'].forEach(function (key) {
        if (u.searchParams.has(key)) u.searchParams.set(key, '[redacted]');
      });
      return u.pathname + (u.search ? u.search : '');
    } catch (_) {
      return String(value).slice(0, 500);
    }
  }

  function stringify(value) {
    if (value instanceof Error) return value.name + ': ' + value.message + (value.stack ? '\n' + value.stack : '');
    if (typeof value === 'string') return value;
    try { return JSON.stringify(value); } catch (_) { return String(value); }
  }

  function add(type, message, detail) {
    var item = {
      time: new Date().toISOString(),
      elapsed_ms: Math.round(performance.now() - started),
      type: type,
      message: String(message == null ? 'null' : message).slice(0, 2000),
      detail: detail ? String(detail).slice(0, 5000) : ''
    };
    entries.push(item);
    if (entries.length > limit) entries.shift();
    render();
  }

  function render() {
    if (!panel || !output) return;
    count.textContent = String(entries.length);
    output.replaceChildren();
    entries.slice(-80).forEach(function (entry) {
      var line = document.createElement('div');
      line.className = 'kf-debug-line kf-debug-' + entry.type.toLowerCase().replace(/[^a-z0-9_-]/g, '-');
      var head = document.createElement('strong');
      head.textContent = '+' + entry.elapsed_ms + 'ms  ' + entry.type;
      var message = document.createElement('pre');
      message.textContent = entry.message + (entry.detail ? '\n' + entry.detail : '');
      line.appendChild(head);
      line.appendChild(message);
      output.appendChild(line);
    });
    output.scrollTop = output.scrollHeight;
  }

  function copyLog() {
    var text = JSON.stringify({
      page: location.pathname + location.search.replace(/([?&])(debug_key|token|code|state|_csrf|key)=[^&]*/gi, '$1$2=[redacted]'),
      userAgent: navigator.userAgent,
      viewport: { width: innerWidth, height: innerHeight, dpr: devicePixelRatio },
      entries: entries
    }, null, 2);
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () { add('INFO', 'Diagnostický záznam zkopírován.'); })
        .catch(function () { fallbackCopy(text); });
    } else {
      fallbackCopy(text);
    }
  }

  function fallbackCopy(text) {
    var area = document.createElement('textarea');
    area.value = text;
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); add('INFO', 'Diagnostický záznam zkopírován.'); }
    catch (_) { add('ERROR', 'Kopírování se nepodařilo.'); }
    area.remove();
  }

  function createPanel() {
    var styleLink = document.querySelector('link[href*="debug.css"]');
    panel = document.createElement('section');
    panel.id = 'kf-debug-panel';
    panel.setAttribute('aria-label', 'Diagnostika K&F');

    var header = document.createElement('header');
    var title = document.createElement('strong');
    title.textContent = 'K&F DEBUG';
    count = document.createElement('span');
    count.textContent = '0';
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.textContent = 'Skrýt';
    var copy = document.createElement('button');
    copy.type = 'button';
    copy.textContent = 'Kopírovat';
    var clear = document.createElement('button');
    clear.type = 'button';
    clear.textContent = 'Vymazat';
    header.append(title, count, toggle, copy, clear);

    output = document.createElement('div');
    output.className = 'kf-debug-output';
    panel.append(header, output);
    document.body.appendChild(panel);

    toggle.addEventListener('click', function () {
      panel.classList.toggle('kf-debug-collapsed');
      toggle.textContent = panel.classList.contains('kf-debug-collapsed') ? 'Zobrazit' : 'Skrýt';
    });
    copy.addEventListener('click', copyLog);
    clear.addEventListener('click', function () { entries = []; render(); });
    if (styleLink) styleLink.dataset.ready = '1';
  }

  window.alert = function (message) {
    add('ALERT', stringify(message), new Error('alert() call site').stack);
    nativeAlert(message);
  };

  console.error = function () {
    add('CONSOLE', Array.prototype.map.call(arguments, stringify).join(' '));
    nativeConsoleError.apply(console, arguments);
  };

  window.addEventListener('error', function (event) {
    if (event.target && event.target !== window) {
      add('RESOURCE', 'Nepodařilo se načíst: ' + (event.target.src || event.target.href || event.target.tagName));
      return;
    }
    add('JS ERROR', event.message || 'Neznámá chyba', (event.filename || '') + ':' + (event.lineno || 0) + ':' + (event.colno || 0) + (event.error && event.error.stack ? '\n' + event.error.stack : ''));
  }, true);

  window.addEventListener('unhandledrejection', function (event) {
    add('PROMISE', stringify(event.reason));
  });

  var originalFetch = window.fetch;
  if (originalFetch) {
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      var method = (init && init.method) || (input && input.method) || 'GET';
      var label = String(method).toUpperCase() + ' ' + cleanUrl(url);
      var began = performance.now();
      add('FETCH →', label);
      return originalFetch.apply(this, arguments).then(function (response) {
        add(response.ok ? 'FETCH ←' : 'HTTP ERROR', label + ' — HTTP ' + response.status, Math.round(performance.now() - began) + ' ms');
        return response;
      }, function (error) {
        add('FETCH ERROR', label, stringify(error));
        throw error;
      });
    };
  }

  var xhrOpen = XMLHttpRequest.prototype.open;
  var xhrSend = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (method, url) {
    this.__kfDebug = { method: method, url: cleanUrl(url), start: 0 };
    return xhrOpen.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function () {
    var xhr = this;
    if (xhr.__kfDebug) {
      xhr.__kfDebug.start = performance.now();
      add('XHR →', xhr.__kfDebug.method + ' ' + xhr.__kfDebug.url);
      xhr.addEventListener('loadend', function () {
        add(xhr.status >= 200 && xhr.status < 400 ? 'XHR ←' : 'HTTP ERROR',
          xhr.__kfDebug.method + ' ' + xhr.__kfDebug.url + ' — HTTP ' + xhr.status,
          Math.round(performance.now() - xhr.__kfDebug.start) + ' ms');
      }, { once: true });
    }
    return xhrSend.apply(this, arguments);
  };

  document.addEventListener('click', function (event) {
    var target = event.target.closest && event.target.closest('a,button,input[type="submit"],[role="button"]');
    if (!target) return;
    var label = target.tagName.toLowerCase();
    if (target.id) label += '#' + target.id;
    if (target.className && typeof target.className === 'string') label += '.' + target.className.trim().replace(/\s+/g, '.');
    if (target.href) label += ' → ' + cleanUrl(target.href);
    if (target.dataset && target.dataset.fav !== undefined) label += ' [favorite action]';
    add('CLICK', label);
  }, true);

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var safeFields = [];
    Array.prototype.forEach.call(form.elements || [], function (field) {
      if (!field.name || field.disabled || /csrf|token|password|secret|key|email|name/i.test(field.name)) return;
      if (field.name === 'enabled' || field.name === 'action') {
        safeFields.push(field.name + '=' + String(field.value).slice(0, 20));
      } else {
        safeFields.push(field.name + ' [value hidden]');
      }
    });
    add('FORM', (form.method || 'GET').toUpperCase() + ' ' + cleanUrl(form.action || location.href),
      'form_id=' + (form.id || '—') + '\\n' +
      'fields=' + (safeFields.join(', ') || 'none') + '\\n' +
      'default_prevented=' + String(event.defaultPrevented));
  }, true);

  document.addEventListener('DOMContentLoaded', function () {
    createPanel();
    add('START', 'Diagnostika aktivní; stránka načtena.', 'Request ID: ' + (document.documentElement.getAttribute('data-debug-request-id') || '—') + '\nURL: ' + cleanUrl(location.href) + '\nViewport: ' + innerWidth + '×' + innerHeight + '\nOnline: ' + navigator.onLine);
    performance.getEntriesByType('resource').forEach(function (resource) {
      if (/\.js(?:\?|$)|\.css(?:\?|$)/i.test(resource.name)) {
        add('ASSET', cleanUrl(resource.name), Math.round(resource.duration) + ' ms · ' + Math.round(resource.transferSize || 0) + ' B');
      }
    });
    if (new URLSearchParams(location.search).has('debug_key')) {
      var clean = new URL(location.href);
      clean.searchParams.delete('debug_key');
      history.replaceState(history.state, '', clean.pathname + clean.search + clean.hash);
    }
    var logUrl = new URL(location.href);
    logUrl.searchParams.set('debug_log', '1');
    window.fetch(logUrl.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function (data) {
        (data.entries || []).forEach(function (entry) {
          var context = entry.context || {};
          var details = [
            entry.path ? entry.method + ' ' + entry.path : '',
            context.file ? context.file + ':' + (context.line || 0) : '',
            context.trace || '',
            context.message || context.message === '' ? context.message : '',
            context.class || ''
          ].filter(Boolean).join('\n');
          add('SERVER ' + (entry.type || 'LOG'), details || JSON.stringify(entry));
        });
      })
      .catch(function (error) { add('SERVER LOG', 'Serverový log nelze načíst.', stringify(error)); });
  });

})();
