/* K&F Developer Tools: konzole, chyby, síťové požadavky a informace o prostředí. */
(() => {
  'use strict';

  const panel = document.getElementById('kf-debug-panel');
  if (!panel || panel.dataset.initialized === '1') return;
  panel.dataset.initialized = '1';

  const $ = selector => panel.querySelector(selector);
  const views = {
    console: $('[data-debug-view="console"]'),
    network: $('[data-debug-view="network"]'),
    errors: $('[data-debug-view="errors"]'),
    info: $('[data-debug-view="info"]')
  };
  const records = { console: [], network: [], errors: [] };
  const MAX_RECORDS = 250;
  let activeTab = 'console';
  let filterText = '';
  let paused = false;
  const startedAt = Date.now();
  const nativeConsole = {};
  ['log', 'info', 'warn', 'error', 'debug'].forEach(level => {
    nativeConsole[level] = typeof console[level] === 'function' ? console[level].bind(console) : () => {};
  });

  const time = () => new Date().toLocaleTimeString();
  const safeString = value => {
    if (value instanceof Error) return value.name + ': ' + value.message + (value.stack ? '\n' + value.stack : '');
    if (typeof value === 'string') return value;
    if (typeof value === 'undefined') return 'undefined';
    if (typeof value === 'function') return '[Function ' + (value.name || 'anonymous') + ']';
    if (typeof value === 'symbol') return String(value);
    try {
      const seen = new WeakSet();
      return JSON.stringify(value, (key, item) => {
        if (typeof item === 'object' && item !== null) {
          if (seen.has(item)) return '[Circular]';
          seen.add(item);
        }
        if (item instanceof Element) return '<' + item.tagName.toLowerCase() + (item.id ? '#' + item.id : '') + '>';
        return item;
      }, 2);
    } catch (_) {
      try { return String(value); } catch (__) { return '[Unprintable]'; }
    }
  };
  const cleanUrl = value => {
    try {
      const url = new URL(value, location.href);
      return url.origin === location.origin ? url.pathname : url.origin + url.pathname;
    } catch (_) { return String(value || '').split('?')[0]; }
  };
  const add = (type, record) => {
    if (paused) return;
    const list = records[type];
    list.push(record);
    if (list.length > MAX_RECORDS) list.splice(0, list.length - MAX_RECORDS);
    render();
  };
  const addConsole = (level, args) => {
    const message = Array.from(args).map(safeString).join(' ');
    add('console', { time: time(), level, message });
    if (level === 'error' || level === 'warn') add('errors', { time: time(), kind: 'Console ' + level, message });
  };

  ['log', 'info', 'warn', 'error', 'debug'].forEach(level => {
    try {
      console[level] = function (...args) {
        nativeConsole[level](...args);
        addConsole(level, args);
      };
    } catch (_) {}
  });

  window.addEventListener('error', event => {
    if (event.target && event.target !== window) {
      const target = event.target;
      const url = target.src || target.href || '';
      const message = 'Nepodařilo se načíst: ' + (url ? cleanUrl(url) : target.tagName);
      add('errors', { time: time(), kind: 'Resource', message });
      add('console', { time: time(), level: 'error', message });
      return;
    }
    const message = (event.message || 'JavaScript error') +
      (event.filename ? '\n' + cleanUrl(event.filename) + ':' + event.lineno + ':' + event.colno : '') +
      (event.error && event.error.stack ? '\n' + event.error.stack : '');
    add('errors', { time: time(), kind: 'JavaScript', message });
    add('console', { time: time(), level: 'error', message });
  }, true);

  window.addEventListener('unhandledrejection', event => {
    const message = 'Unhandled Promise rejection: ' + safeString(event.reason);
    add('errors', { time: time(), kind: 'Promise', message });
    add('console', { time: time(), level: 'error', message });
  });

  const recordNetwork = (method, url, status, duration, failed) => {
    add('network', {
      time: time(), method: String(method || 'GET').toUpperCase(),
      url: cleanUrl(url), status: status == null ? 'ERR' : String(status),
      duration: Math.max(0, Math.round(duration)) + ' ms', failed: !!failed
    });
  };

  if (typeof window.fetch === 'function') {
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
      const request = args[0];
      const url = typeof request === 'string' ? request : (request && request.url) || '';
      const method = (args[1] && args[1].method) || (request && request.method) || 'GET';
      const start = performance.now();
      let promise;
      try { promise = originalFetch.apply(this, args); }
      catch (error) {
        recordNetwork(method, url, 0, performance.now() - start, true);
        throw error;
      }
      return promise.then(response => {
        recordNetwork(method, url, response.status, performance.now() - start, !response.ok);
        if (!response.ok) add('errors', { time: time(), kind: 'HTTP ' + response.status, message: method + ' ' + cleanUrl(url) });
        return response;
      }, error => {
        recordNetwork(method, url, 0, performance.now() - start, true);
        add('errors', { time: time(), kind: 'Fetch', message: method + ' ' + cleanUrl(url) + '\n' + safeString(error) });
        throw error;
      });
    };
  }

  if (window.XMLHttpRequest) {
    const proto = XMLHttpRequest.prototype;
    const originalOpen = proto.open;
    const originalSend = proto.send;
    proto.open = function (method, url, ...rest) {
      this.__kfDebugRequest = { method, url, start: 0, done: false };
      return originalOpen.call(this, method, url, ...rest);
    };
    proto.send = function (...args) {
      const info = this.__kfDebugRequest;
      if (info) {
        info.start = performance.now();
        this.addEventListener('loadend', () => {
          if (info.done) return;
          info.done = true;
          const failed = this.status < 200 || this.status >= 400;
          recordNetwork(info.method, info.url, this.status, performance.now() - info.start, failed);
          if (this.status >= 400) add('errors', { time: time(), kind: 'HTTP ' + this.status, message: info.method + ' ' + cleanUrl(info.url) });
        }, { once: true });
      }
      return originalSend.apply(this, args);
    };
  }

  const initialResources = () => {
    if (!performance || !performance.getEntriesByType) return;
    performance.getEntriesByType('resource').forEach(entry => {
      if (!entry.name || entry.name.indexOf(location.origin) !== 0) return;
      add('network', {
        time: 'start', method: 'GET', url: cleanUrl(entry.name),
        status: '—', duration: Math.round(entry.duration) + ' ms', failed: false
      });
    });
  };

  const appendEntry = (container, className, timestamp, message) => {
    const row = document.createElement('div');
    row.className = className;
    const t = document.createElement('span');
    t.className = 'kf-debug-time';
    t.textContent = timestamp || '';
    const body = document.createElement('span');
    body.className = 'kf-debug-message';
    body.textContent = message || '';
    row.append(t, body);
    container.appendChild(row);
  };
  const matches = record => !filterText || (Object.values(record).join(' ').toLowerCase().includes(filterText));
  const empty = (container, message) => {
    const node = document.createElement('div');
    node.className = 'kf-debug-empty';
    node.textContent = message;
    container.appendChild(node);
  };

  const renderList = (type, container) => {
    container.replaceChildren();
    const list = records[type].filter(matches);
    if (!list.length) { empty(container, 'Žádné záznamy.'); return; }
    list.slice().reverse().forEach(item => {
      const level = item.level || '';
      const kind = item.kind || '';
      appendEntry(container, 'kf-debug-entry ' + (level === 'error' || kind ? 'kf-debug-error' : 'kf-debug-log-' + level),
        item.time, (kind ? '[' + kind + '] ' : '') + item.message);
    });
  };
  const renderNetwork = () => {
    const container = views.network;
    container.replaceChildren();
    const list = records.network.filter(matches);
    if (!list.length) { empty(container, 'Zatím nejsou zaznamenány žádné síťové požadavky.'); return; }
    list.slice().reverse().forEach(item => {
      const row = document.createElement('div');
      row.className = 'kf-debug-network-row';
      const cells = [item.method, item.status, item.url, item.duration];
      cells.forEach((value, index) => {
        const cell = document.createElement('span');
        cell.textContent = value;
        if (index === 1) cell.className = item.failed ? 'kf-debug-status-error' : 'kf-debug-status-ok';
        row.appendChild(cell);
      });
      container.appendChild(row);
    });
  };
  const renderInfo = () => {
    const container = views.info;
    const data = [
      ['Adresa', location.pathname],
      ['Prohlížeč', navigator.userAgent],
      ['Obrazovka', screen.width + ' × ' + screen.height],
      ['Viewport', document.documentElement.clientWidth + ' × ' + document.documentElement.clientHeight],
      ['DPR', String(window.devicePixelRatio || 1)],
      ['Online', navigator.onLine ? 'Ano' : 'Ne'],
      ['Jazyk', navigator.language || '—'],
      ['Platforma', navigator.platform || '—'],
      ['Paměť JS', performance && performance.memory ? Math.round(performance.memory.usedJSHeapSize / 1048576) + ' MB' : 'Nepodporováno'],
      ['Běží', Math.round((Date.now() - startedAt) / 1000) + ' s']
    ];
    container.replaceChildren();
    const grid = document.createElement('div');
    grid.className = 'kf-debug-info-grid';
    data.forEach(([label, value]) => {
      const row = document.createElement('div');
      row.className = 'kf-debug-info-item';
      const name = document.createElement('span');
      name.textContent = label;
      const val = document.createElement('b');
      val.textContent = value;
      row.append(name, val);
      grid.appendChild(row);
    });
    container.appendChild(grid);
  };
  function render() {
    renderList('console', views.console);
    renderNetwork();
    renderList('errors', views.errors);
    renderInfo();
    const errorCount = records.errors.length;
    const summary = $('[data-debug-summary]');
    if (summary) summary.textContent = errorCount ? errorCount + ' chyb' : 'Bez zachycených chyb';
    const counts = panel.querySelectorAll('[data-debug-count]');
    counts.forEach(node => {
      const type = node.getAttribute('data-debug-count');
      node.textContent = records[type] ? String(records[type].length) : '0';
    });
  }

  panel.querySelectorAll('[data-debug-tab]').forEach(button => {
    button.addEventListener('click', () => {
      activeTab = button.dataset.debugTab;
      panel.querySelectorAll('[data-debug-tab]').forEach(tab => tab.setAttribute('aria-selected', tab === button ? 'true' : 'false'));
      Object.entries(views).forEach(([name, view]) => { view.hidden = name !== activeTab; });
    });
  });
  const filter = $('[data-debug-filter]');
  if (filter) filter.addEventListener('input', () => { filterText = filter.value.trim().toLowerCase(); render(); });
  const clear = $('[data-debug-clear]');
  if (clear) clear.addEventListener('click', () => {
    Object.keys(records).forEach(key => { records[key] = []; });
    render();
  });
  const pause = $('[data-debug-pause]');
  if (pause) pause.addEventListener('click', () => {
    paused = !paused;
    pause.textContent = paused ? 'Pokračovat' : 'Pozastavit';
    pause.setAttribute('aria-pressed', paused ? 'true' : 'false');
  });
  const toggle = $('[data-debug-toggle]');
  if (toggle) toggle.addEventListener('click', () => {
    const collapsed = panel.classList.toggle('kf-debug-collapsed');
    toggle.textContent = collapsed ? 'Rozbalit' : 'Sbalit';
    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  });

  const positionKey = 'kf-debug-position-v2';
  const clampPosition = () => {
    const rect = panel.getBoundingClientRect();
    const left = Math.max(0, Math.min(rect.left, innerWidth - rect.width));
    const top = Math.max(0, Math.min(rect.top, innerHeight - Math.min(rect.height, innerHeight)));
    panel.style.left = left + 'px';
    panel.style.top = top + 'px';
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
  };
  try {
    const saved = JSON.parse(localStorage.getItem(positionKey) || 'null');
    if (saved && Number.isFinite(saved.left) && Number.isFinite(saved.top)) {
      panel.style.left = saved.left + 'px';
      panel.style.top = saved.top + 'px';
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
      requestAnimationFrame(clampPosition);
    }
  } catch (_) {}
  const header = $('.kf-debug-header');
  if (header) header.addEventListener('pointerdown', event => {
    if (event.target.closest('button')) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    const rect = panel.getBoundingClientRect();
    const dx = event.clientX - rect.left;
    const dy = event.clientY - rect.top;
    header.setPointerCapture(event.pointerId);
    panel.classList.add('kf-debug-dragging');
    const move = e => {
      panel.style.left = Math.max(0, Math.min(e.clientX - dx, innerWidth - panel.offsetWidth)) + 'px';
      panel.style.top = Math.max(0, Math.min(e.clientY - dy, innerHeight - 34)) + 'px';
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
    };
    const finish = () => {
      panel.classList.remove('kf-debug-dragging');
      try {
        const r = panel.getBoundingClientRect();
        localStorage.setItem(positionKey, JSON.stringify({ left: r.left, top: r.top }));
      } catch (_) {}
      header.removeEventListener('pointermove', move);
      header.removeEventListener('pointerup', finish);
      header.removeEventListener('pointercancel', finish);
    };
    header.addEventListener('pointermove', move);
    header.addEventListener('pointerup', finish);
    header.addEventListener('pointercancel', finish);
    event.preventDefault();
  });
  window.addEventListener('resize', clampPosition, { passive: true });
  window.addEventListener('online', () => add('console', { time: time(), level: 'info', message: 'Síťové připojení obnoveno.' }));
  window.addEventListener('offline', () => add('console', { time: time(), level: 'warn', message: 'Síťové připojení ztraceno.' }));

  initialResources();
  add('console', { time: time(), level: 'info', message: 'Developer Tools aktivní.' });
  render();
})();
