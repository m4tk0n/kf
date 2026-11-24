/* debug/debug.js – K&F Developer Tools v3
 * Záložky: Console, Network, Errors, Events, App, Stránka, Kontroly, Server, ⚙ Nastavení.
 * Hooky (console, fetch, XHR, chyby, CSP) se instalují hned při načtení skriptu, UI až po DOMContentLoaded.
 * Nikdy nemění chování aplikace: původní funkce se vždy volají a jejich výsledek/výjimka se předává dál.
 */
(() => {
  'use strict';
  if (window.__kfDevtools) return;
  window.__kfDevtools = true;

  const SETTINGS_KEY = 'kf-devtools-settings-v3';
  const LOG_KEY = 'kf-devtools-log-v3';
  const TYPES = ['console', 'network', 'errors', 'events'];
  const MAX = { console: 300, network: 300, errors: 150, events: 300 };
  const PERSIST = 80;
  const SECRET = /^(_csrf|csrf|token|access_token|code|state|key|password|secret|debug_key|appsecret_proof|code_verifier)$/i;

  const DEFAULTS = {
    keepLog: true, captureBodies: false, showAssets: false, logClicks: true, logNav: true,
    autoChecks: true, flagElements: true, showTaps: false, outlineAll: false, viewportBadge: false,
    reserveSpace: true, slowMs: 800, dock: 'bottom', height: 38, fontSize: 11, collapsed: false,
    tab: 'console', levels: { log: true, info: true, warn: true, error: true, debug: true }
  };

  /* ---------- úložiště ---------- */
  const store = area => ({
    get(k) { try { return JSON.parse(window[area].getItem(k) || 'null'); } catch (_) { return null; } },
    set(k, v) { try { window[area].setItem(k, JSON.stringify(v)); } catch (_) {} },
    del(k) { try { window[area].removeItem(k); } catch (_) {} }
  });
  const ls = store('localStorage');
  const ss = store('sessionStorage');
  const S = Object.assign({}, DEFAULTS, ls.get(SETTINGS_KEY) || {});
  S.levels = Object.assign({}, DEFAULTS.levels, S.levels || {});
  const saveSettings = () => ls.set(SETTINGS_KEY, S);

  /* ---------- pomocné funkce ---------- */
  const pad = (n, l = 2) => String(n).padStart(l, '0');
  const fmtTime = t => { const d = new Date(t); return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()) + '.' + pad(d.getMilliseconds(), 3); };
  const fmtMs = v => v == null ? '…' : v >= 1000 ? (v / 1000).toFixed(2) + ' s' : Math.round(v) + ' ms';
  const fmtBytes = v => v == null ? '—' : v < 1024 ? v + ' B' : v < 1048576 ? (v / 1024).toFixed(1) + ' kB' : (v / 1048576).toFixed(2) + ' MB';
  const clip = (s, n) => { s = String(s == null ? '' : s); return s.length > n ? s.slice(0, n) + '… (+' + (s.length - n) + ')' : s; };
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
  const clearNode = n => { while (n.firstChild) n.removeChild(n.firstChild); };
  const safeDecode = s => { try { return decodeURIComponent(s.replace(/\+/g, ' ')); } catch (_) { return s; } };
  const stackHere = () => String(new Error().stack || '').split('\n').slice(3).join('\n');

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
          if (typeof Element !== 'undefined' && item instanceof Element) return describeEl(item);
          if (seen.has(item)) return '[Circular]';
          seen.add(item);
        }
        return item;
      }, 2);
    } catch (_) {
      try { return String(value); } catch (__) { return '[Unprintable]'; }
    }
  };

  function describeEl(node) {
    if (!node || !node.tagName) return String(node);
    let s = node.tagName.toLowerCase();
    if (node.id) s += '#' + node.id;
    const c = (typeof node.className === 'string' ? node.className : '').trim().split(/\s+/).filter(Boolean).slice(0, 3);
    if (c.length) s += '.' + c.join('.');
    return s;
  }

  function redactUrl(u) {
    try {
      const x = new URL(String(u), location.href);
      const keys = [];
      x.searchParams.forEach((v, k) => { if (SECRET.test(k)) keys.push(k); });
      keys.forEach(k => x.searchParams.set(k, '***'));
      return (x.origin === location.origin ? '' : x.origin) + x.pathname + safeDecode(x.search) + (x.hash || '');
    } catch (_) { return clip(u, 300); }
  }
  const perfEntries = t => { try { return performance.getEntriesByType(t) || []; } catch (_) { return []; } };
  const finite = v => (v != null && v !== '' && isFinite(Number(v))) ? Number(v) : null;
  const absUrl = u => { try { return new URL(String(u), location.href).href; } catch (_) { return String(u); } };

  function bodyText(b) {
    try {
      if (b == null) return '';
      let pairs = null;
      if (typeof URLSearchParams !== 'undefined' && b instanceof URLSearchParams) pairs = Array.from(b.entries());
      else if (typeof FormData !== 'undefined' && b instanceof FormData) pairs = Array.from(b.entries()).map(([k, v]) => [k, typeof v === 'string' ? v : '[soubor ' + (v.name || '') + ']']);
      else if (typeof b === 'string') {
        if (/^[^=\s&{[]+=/.test(b)) pairs = Array.from(new URLSearchParams(b).entries());
        else return clip(b.replace(/("(?:_?csrf|token|password|secret)"\s*:\s*")[^"]*/gi, '$1***'), 1500);
      } else if (typeof Blob !== 'undefined' && b instanceof Blob) return '[Blob ' + fmtBytes(b.size) + ']';
      else return '[' + Object.prototype.toString.call(b).slice(8, -1) + ']';
      return pairs.map(([k, v]) => k + '=' + (SECRET.test(k) ? '***' : clip(v, 300))).join('\n');
    } catch (_) { return '[tělo nelze zobrazit]'; }
  }

  /* ---------- záznamy ---------- */
  const records = { console: [], network: [], errors: [], events: [] };
  let seq = 0;
  let paused = false;
  let filterText = '';
  let activeTab = S.tab;
  let ui = false;
  const expanded = new Set();
  const pageTag = redactUrl(location.href);
  const startedAt = Date.now();
  const perf = { lcp: null, lcpEl: '', cls: 0, long: 0, longMs: 0 };
  let checks = { groups: [], ok: [], at: 0 };
  let serverLog = null;
  let serverLoading = false;
  const netF = { xhr: true, asset: !!S.showAssets, bad: false, slow: false };
  const evF = { act: true, nav: true, perf: true, dlg: true };

  // záznamy z předchozích stránek téže karty
  (function loadPersisted() {
    if (!S.keepLog) return;
    const data = ss.get(LOG_KEY);
    if (!data) return;
    TYPES.forEach(t => {
      (data[t] || []).forEach(r => {
        r.old = true;
        if (r.pending) { r.pending = false; r.status = 'zrušeno'; r.failed = false; }
        if (r.id > seq) seq = r.id;
        records[t].push(r);
      });
    });
  })();

  let renderTimer = 0;
  let persistTimer = 0;
  function dirty() {
    if (!renderTimer) renderTimer = setTimeout(() => { renderTimer = 0; if (ui) render(); }, 50);
    if (S.keepLog && !persistTimer) persistTimer = setTimeout(() => { persistTimer = 0; persist(); }, 400);
  }
  function persist() {
    if (!S.keepLog) return;
    const slim = r => {
      const c = {};
      Object.keys(r).forEach(k => { c[k] = typeof r[k] === 'string' ? clip(r[k], k === 'body' ? 600 : 1000) : r[k]; });
      return c;
    };
    const out = {};
    TYPES.forEach(t => { out[t] = records[t].filter(r => !(t === 'network' && r.type === 'asset')).slice(-PERSIST).map(slim); });
    ss.set(LOG_KEY, out);
  }

  function add(type, rec) {
    if (paused) return null;
    rec.id = ++seq;
    rec.t = rec.t || Date.now();
    rec.page = pageTag;
    const list = records[type];
    list.push(rec);
    if (list.length > MAX[type]) {
      let idx = 0;
      if (type === 'network') { const a = list.findIndex(r => r.type === 'asset'); if (a > -1) idx = a; }
      list.splice(idx, 1);
    }
    dirty();
    return rec;
  }
  const addErr = (kind, message, detail) => {
    const r = add('errors', { kind, message: clip(message, 3000), detail: clip(detail || '', 6000) });
    add('console', { level: 'error', message: '[' + kind + '] ' + clip(message, 1500) });
    return r;
  };
  const addEvent = (kind, message, detail) => add('events', { kind, message: clip(message, 1000), detail: clip(detail || '', 3000) });

  /* ---------- console ---------- */
  function pushConsole(level, args) {
    const message = clip(args.map(safeString).join(' '), 4000);
    const last = records.console[records.console.length - 1];
    if (!paused && last && !last.old && last.level === level && last.message === message && !last.detail) {
      last.count = (last.count || 1) + 1;
      last.t = Date.now();
      dirty();
    } else {
      add('console', { level, message, detail: level === 'warn' || level === 'error' ? stackHere() : '' });
    }
    if (level === 'error') add('errors', { kind: 'console.error', message, detail: stackHere() });
  }
  ['log', 'info', 'warn', 'error', 'debug'].forEach(level => {
    const native = typeof console[level] === 'function' ? console[level].bind(console) : () => {};
    try {
      console[level] = function (...args) {
        native(...args);
        try { pushConsole(level, args); } catch (_) {}
      };
    } catch (_) {}
  });

  /* ---------- chyby ---------- */
  window.addEventListener('error', event => {
    const t = event.target;
    if (t && t !== window && t.nodeType === 1) {
      addErr('Resource', 'Nepodařilo se načíst <' + t.tagName.toLowerCase() + '> ' + redactUrl(t.src || t.href || ''));
      return;
    }
    addErr('JavaScript', event.message || 'JavaScript error',
      (event.filename ? redactUrl(event.filename) + ':' + event.lineno + ':' + event.colno + '\n' : '') + (event.error && event.error.stack ? event.error.stack : ''));
  }, true);
  window.addEventListener('unhandledrejection', event => {
    const r = event.reason;
    addErr('Promise', 'Unhandled rejection: ' + (r instanceof Error ? r.name + ': ' + r.message : safeString(r)), r instanceof Error ? r.stack : '');
  });
  document.addEventListener('securitypolicyviolation', ev => {
    addErr('CSP', ev.violatedDirective + ' blokuje ' + (ev.blockedURI ? redactUrl(ev.blockedURI) : 'inline') + (ev.sample ? ' („' + clip(ev.sample, 60) + '“)' : ''),
      (ev.sourceFile ? redactUrl(ev.sourceFile) + ':' + ev.lineNumber + ':' + ev.columnNumber + '\n' : '') + 'Politika: ' + clip(ev.originalPolicy, 400));
  });

  /* ---------- síť ---------- */
  const rawFetch = typeof window.fetch === 'function' ? window.fetch.bind(window) : null;

  function netStart(type, method, url, body) {
    return add('network', {
      type, method: String(method || 'GET').toUpperCase(), url: redactUrl(absUrl(url)),
      status: '…', ms: null, size: null, failed: false, pending: true,
      reqBody: bodyText(body), t0: performance.now()
    });
  }
  function netEnd(rec, patch) {
    if (!rec) return;
    Object.assign(rec, patch);
    rec.pending = false;
    rec.ms = performance.now() - rec.t0;
    dirty();
  }

  if (rawFetch) {
    window.fetch = function (input, init) {
      let url = '';
      let method = 'GET';
      let body;
      try {
        if (input == null) addErr('Fetch', 'fetch() voláno s hodnotou ' + input, stackHere());
        else if (typeof input === 'string') url = input;
        else if (typeof input.url === 'string') url = input.url;
        else url = String(input);
        method = (init && init.method) || (input && input.method) || 'GET';
        body = init && init.body;
      } catch (_) {}
      const rec = netStart('fetch', method, url, body);
      let promise;
      try { promise = rawFetch.apply(this, arguments); } catch (error) {
        netEnd(rec, { status: 'ERR', failed: true });
        addErr('Fetch', rec ? rec.method + ' ' + rec.url : String(url), safeString(error));
        throw error;
      }
      return promise.then(res => {
        try {
          const ct = res.headers.get('content-type') || '';
          const len = res.headers.get('content-length');
          netEnd(rec, {
            status: res.status, failed: !res.ok, resType: ct.split(';')[0], size: finite(len),
            cache: res.headers.get('cache-control') || '', redirected: res.redirected ? redactUrl(res.url) : ''
          });
          let errRec = null;
          if (!res.ok) errRec = addErr('HTTP ' + res.status, (rec ? rec.method + ' ' + rec.url : String(url)));
          if ((S.captureBodies || !res.ok) && /text|json|xml|html|javascript/i.test(ct)) {
            res.clone().text().then(txt => {
              if (rec) { rec.body = clip(txt, S.captureBodies ? 6000 : 2000); if (rec.size == null) rec.size = txt.length; }
              if (errRec) errRec.detail = clip(txt, 2000);
              dirty();
            }).catch(() => {});
          }
        } catch (_) {}
        return res;
      }, error => {
        const aborted = error && error.name === 'AbortError';
        netEnd(rec, { status: aborted ? 'ABORT' : 'ERR', failed: !aborted });
        if (!aborted) addErr('Fetch', (rec ? rec.method + ' ' + rec.url : String(url)), safeString(error));
        throw error;
      });
    };
  }

  if (window.XMLHttpRequest) {
    const proto = XMLHttpRequest.prototype;
    const origOpen = proto.open;
    const origSend = proto.send;
    proto.open = function (method, url) {
      this.__kfd = { method, url };
      return origOpen.apply(this, arguments);
    };
    proto.send = function (body) {
      const info = this.__kfd;
      if (info) {
        const rec = netStart('xhr', info.method, info.url, body);
        this.addEventListener('loadend', () => {
          try {
            const ct = this.getResponseHeader('content-type') || '';
            const failed = this.status < 200 || this.status >= 400;
            netEnd(rec, { status: this.status || 'ERR', failed, resType: ct.split(';')[0], size: finite(this.getResponseHeader('content-length')) });
            if (failed) addErr('XHR ' + (this.status || 'ERR'), info.method + ' ' + redactUrl(absUrl(info.url)));
            if (rec && S.captureBodies && (this.responseType === '' || this.responseType === 'text') && /text|json|xml|html/i.test(ct)) rec.body = clip(this.responseText, 6000);
          } catch (_) {}
        }, { once: true });
      }
      return origSend.apply(this, arguments);
    };
  }

  function assetEntry(e) {
    if (/^(fetch|xmlhttprequest|beacon)$/.test(e.initiatorType)) return;
    const size = e.transferSize || e.encodedBodySize || null;
    const status = e.responseStatus || '—';
    add('network', {
      type: e.entryType === 'navigation' ? 'document' : 'asset', method: 'GET', url: redactUrl(e.name), status,
      ms: e.duration, size, cached: e.transferSize === 0 && e.decodedBodySize > 0, initiator: e.initiatorType || e.entryType,
      failed: typeof e.responseStatus === 'number' && e.responseStatus >= 400, pending: false,
      t: Math.round(performance.timeOrigin + e.startTime), t0: 0
    });
  }
  try {
    new PerformanceObserver(list => list.getEntries().forEach(assetEntry)).observe({ type: 'resource', buffered: true });
  } catch (_) {}
  try { perfEntries('navigation').forEach(assetEntry); } catch (_) {}

  /* ---------- výkon ---------- */
  try {
    new PerformanceObserver(list => {
      const es = list.getEntries();
      const last = es[es.length - 1];
      perf.lcp = last.startTime;
      perf.lcpEl = last.element ? describeEl(last.element) : (last.url ? redactUrl(last.url) : '');
      if (ui && activeTab === 'page') dirty();
    }).observe({ type: 'largest-contentful-paint', buffered: true });
  } catch (_) {}
  try {
    new PerformanceObserver(list => list.getEntries().forEach(e => {
      if (e.hadRecentInput) return;
      perf.cls += e.value;
      if (e.value >= 0.02 && S.logNav) {
        const src = (e.sources || []).map(s => describeEl(s.node)).slice(0, 2).join(', ');
        addEvent('shift', 'Posun layoutu ' + e.value.toFixed(3) + (src ? ' – ' + src : ''));
      }
    })).observe({ type: 'layout-shift', buffered: true });
  } catch (_) {}
  try {
    new PerformanceObserver(list => list.getEntries().forEach(e => {
      perf.long++;
      perf.longMs += e.duration;
      if (e.duration >= 150 && S.logNav) addEvent('longtask', 'Dlouhý úkol hlavního vlákna: ' + fmtMs(e.duration));
    })).observe({ type: 'longtask', buffered: true });
  } catch (_) {}

  /* ---------- události ---------- */
  const nativeAlert = window.alert;
  window.alert = function (m) {
    addEvent('alert', String(m), stackHere());
    return nativeAlert.apply(this, arguments);
  };
  const nativeConfirm = window.confirm;
  window.confirm = function (m) {
    const r = nativeConfirm.apply(this, arguments);
    addEvent('confirm', String(m) + ' → ' + (r ? 'ANO' : 'NE'));
    return r;
  };
  ['pushState', 'replaceState'].forEach(name => {
    const orig = history[name];
    history[name] = function (state, title, url) {
      const r = orig.apply(this, arguments);
      if (S.logNav) addEvent('nav', name + ' → ' + redactUrl(url == null ? location.href : url));
      return r;
    };
  });
  window.addEventListener('popstate', () => { if (S.logNav) addEvent('nav', 'popstate → ' + redactUrl(location.href)); });
  window.addEventListener('hashchange', () => { if (S.logNav) addEvent('nav', 'hashchange → ' + location.hash); });
  window.addEventListener('pageshow', ev => { if (ev.persisted && S.logNav) addEvent('nav', 'pageshow z bfcache: ' + redactUrl(location.href)); });
  window.addEventListener('online', () => addEvent('online', 'Síťové připojení obnoveno.'));
  window.addEventListener('offline', () => addEvent('online', 'Síťové připojení ztraceno.'));

  document.addEventListener('click', ev => {
    if (!S.logClicks || paused) return;
    const node = ev.target.closest && ev.target.closest('a,button,summary,input,select,label,[role="button"],[data-fav],[data-cal],[data-lightbox]');
    if (!node || (panelRef && panelRef.contains(node))) return;
    const text = String(node.getAttribute('aria-label') || node.textContent || node.value || '').trim().replace(/\s+/g, ' ').slice(0, 50);
    const datas = Array.from(node.attributes).filter(a => a.name.indexOf('data-') === 0 && !/csrf/i.test(a.name)).map(a => a.name + '=' + clip(a.value, 40)).join(' ');
    addEvent('click', describeEl(node) + (text ? ' „' + text + '“' : ''),
      [node.href ? 'href: ' + redactUrl(node.href) : '', datas ? 'data: ' + datas : ''].filter(Boolean).join('\n'));
  }, true);

  document.addEventListener('submit', ev => {
    if (!S.logClicks || paused) return;
    const form = ev.target;
    if (!form || !form.elements) return;
    const method = String(form.getAttribute('method') || 'get').toUpperCase();
    const names = Array.from(form.elements).filter(f => f.name && f.type !== 'password').map(f => f.name);
    const field = (panelRef && panelRef.dataset.csrfField) || '_csrf';
    const missing = method === 'POST' && names.indexOf(field) === -1;
    addEvent(missing ? 'warn' : 'submit', method + ' ' + redactUrl(form.action || location.href) + (missing ? '  ⚠ chybí ' + field : ''), 'pole: ' + names.join(', '));
  }, true);

  /* ---------- UI ---------- */
  let panelRef = null;
  const FILTER_TABS = ['console', 'network', 'errors', 'events', 'server'];
  const LIST_TABS = ['console', 'network', 'errors', 'events'];
  let views = {};
  let vpBadge = null;
  let toolsEl, chipsEl, metaEl, filterEl, summaryEl;
  let pauseBtn, toggleBtn;

  const haystack = r => Object.keys(r).filter(k => typeof r[k] !== 'object').map(k => r[k]).join(' ').toLowerCase();
  const match = r => !filterText || haystack(r).indexOf(filterText) > -1;

  function entry(type, r, o) {
    const key = type + ':' + r.id;
    const open = expanded.has(key);
    const row = el('div', 'kf-debug-entry ' + (o.cls || '') + (open ? ' kf-debug-open' : '') + (r.old ? ' kf-debug-old' : '') + (o.detail ? ' kf-debug-expandable' : ''));
    row.dataset.key = key;
    row.title = r.page || '';
    row.appendChild(el('span', 'kf-debug-time', fmtTime(r.t)));
    const body = el('span', 'kf-debug-message');
    if (o.tag) body.appendChild(el('b', 'kf-debug-tag', o.tag + ' '));
    body.appendChild(document.createTextNode(o.text));
    if (r.count > 1) body.appendChild(el('i', 'kf-debug-count', ' ×' + r.count));
    row.appendChild(body);
    if (open && o.detail) row.appendChild(el('pre', 'kf-debug-detail', o.detail));
    return row;
  }

  function fillList(container, items, build, emptyText) {
    const top = container.parentNode ? container.parentNode.scrollTop : 0;
    clearNode(container);
    if (!items.length) { container.appendChild(el('div', 'kf-debug-empty', emptyText || 'Žádné záznamy.')); return; }
    const shown = items.slice().reverse().slice(0, 200);
    shown.forEach(r => container.appendChild(build(r)));
    if (items.length > shown.length) container.appendChild(el('div', 'kf-debug-empty', '… a dalších ' + (items.length - shown.length) + ' starších záznamů (zpřesni filtr).'));
    if (container.parentNode) container.parentNode.scrollTop = top;
  }

  function renderConsole() {
    const list = records.console.filter(r => S.levels[r.level] !== false && match(r));
    fillList(views.console, list, r => entry('console', r, { cls: 'kf-debug-log-' + r.level, text: r.message, detail: r.detail }), 'Žádné záznamy konzole.');
    metaEl.textContent = list.length + ' / ' + records.console.length;
  }

  function netRowNode(r) {
    const key = 'network:' + r.id;
    const open = expanded.has(key);
    const slow = r.ms != null && r.ms > S.slowMs;
    const row = el('div', 'kf-debug-network-row' + (r.failed ? ' kf-debug-bad' : '') + (slow ? ' kf-debug-slow' : '') + (r.pending ? ' kf-debug-pending' : '') + (r.old ? ' kf-debug-old' : ''));
    row.dataset.key = key;
    const st = el('span', r.failed ? 'kf-debug-status-error' : (r.pending ? '' : 'kf-debug-status-ok'), String(r.status));
    row.append(el('span', '', r.method), st, el('span', '', r.url), el('span', 'kf-debug-n-ms', fmtMs(r.ms)), el('span', 'kf-debug-n-size', fmtBytes(r.size)));
    if (open) {
      const lines = [
        'URL: ' + r.url, 'Typ: ' + r.type + (r.initiator ? ' (' + r.initiator + ')' : ''), 'Spuštěno: ' + fmtTime(r.t),
        'Stav: ' + r.status + (r.cached ? ' (z cache)' : ''), 'Doba: ' + fmtMs(r.ms) + (slow ? '  ⚠ pomalé (> ' + S.slowMs + ' ms)' : ''),
        'Velikost: ' + fmtBytes(r.size), r.resType ? 'Content-Type: ' + r.resType : '', r.cache ? 'Cache-Control: ' + r.cache : '',
        r.redirected ? 'Přesměrováno na: ' + r.redirected : '', r.reqBody ? '\n— Tělo požadavku —\n' + r.reqBody : '',
        r.body ? '\n— Odpověď (náhled) —\n' + r.body : ''
      ].filter(Boolean).join('\n');
      const d = el('pre', 'kf-debug-detail', lines);
      const b = el('button', 'kf-debug-copybtn', 'Kopírovat URL');
      b.dataset.copy = r.url;
      d.appendChild(document.createTextNode('\n'));
      d.appendChild(b);
      row.appendChild(d);
    }
    return row;
  }
  function renderNetwork() {
    const list = records.network.filter(r => {
      if (!match(r)) return false;
      const isAsset = r.type === 'asset';
      if (isAsset ? !netF.asset : !netF.xhr) return false;
      if (netF.bad && !r.failed) return false;
      if (netF.slow && !(r.ms != null && r.ms > S.slowMs)) return false;
      return true;
    });
    fillList(views.network, list, netRowNode, 'Žádné požadavky (zkontroluj filtry nahoře).');
    const reqs = records.network.filter(r => r.type !== 'asset');
    const bad = reqs.filter(r => r.failed).length;
    const done = reqs.filter(r => r.ms != null && !r.old);
    const avg = done.length ? done.reduce((a, r) => a + r.ms, 0) / done.length : 0;
    metaEl.textContent = list.length + ' zobrazeno · ' + bad + ' chyb · ⌀ ' + fmtMs(avg);
  }
  function renderErrors() {
    const list = records.errors.filter(match);
    fillList(views.errors, list, r => entry('errors', r, { cls: 'kf-debug-err', tag: '[' + r.kind + ']', text: r.message, detail: r.detail }), 'Žádné chyby. 🎉');
    metaEl.textContent = list.length + ' / ' + records.errors.length;
  }
  const EV_GROUP = { click: 'act', submit: 'act', warn: 'act', nav: 'nav', view: 'nav', shift: 'perf', longtask: 'perf', alert: 'dlg', confirm: 'dlg', online: 'dlg' };
  function renderEvents() {
    const list = records.events.filter(r => evF[EV_GROUP[r.kind] || 'dlg'] && match(r));
    fillList(views.events, list, r => entry('events', r, { cls: 'kf-debug-ev-' + (r.kind === 'warn' ? 'warn' : (EV_GROUP[r.kind] === 'nav' ? 'nav' : r.kind)), tag: r.kind, text: r.message, detail: r.detail }), 'Žádné události.');
    metaEl.textContent = list.length + ' / ' + records.events.length;
  }

  /* --- kv helper --- */
  function section(container, title, rows, extra) {
    const h = el('div', 'kf-debug-section', title);
    if (extra) h.appendChild(extra);
    container.appendChild(h);
    const grid = el('div', 'kf-debug-kv');
    rows.forEach(([label, value, cls]) => {
      const item = el('div', 'kf-debug-kv-item');
      item.append(el('span', '', label), el('b', cls || '', value == null || value === '' ? '—' : String(value)));
      grid.appendChild(item);
    });
    container.appendChild(grid);
  }
  const rate = (v, good, bad) => v == null ? '' : v <= good ? 'ok' : v <= bad ? 'warn' : 'bad';
  function refreshBtn(fn) { const b = el('button', '', '↻ Obnovit'); b.addEventListener('click', fn); return b; }

  /* --- App --- */
  function renderApp() {
    const c = views.app;
    clearNode(c);
    c.appendChild(el('div', 'kf-debug-toolbar')).appendChild(refreshBtn(() => renderApp()));

    const params = new URLSearchParams(location.search);
    const prow = [];
    params.forEach((v, k) => {
      prow.push([k, SECRET.test(k) ? '***' : v === '' ? '(prázdné)' : v]);
      if (k === 'return') {
        const inner = new URLSearchParams(v);
        inner.forEach((iv, ik) => prow.push(['  return.' + ik, iv === '' ? '(prázdné)' : iv]));
      }
    });
    section(c, 'Adresa', [['Cesta', location.pathname], ...(prow.length ? prow : [['Parametry', 'žádné']]), ['Hash', location.hash]]);

    const view = document.getElementById('view');
    const bar = document.querySelector('.bar');
    const sel = bar && bar.querySelector('select[name="s"]');
    const q = bar && bar.querySelector('input[name="q"]');
    const cur = document.querySelector('.pager-cur');
    const favs = document.querySelectorAll('button[data-fav]');
    const favOn = Array.from(favs).filter(b => b.getAttribute('aria-pressed') === 'true').length;
    section(c, 'Aplikace', [
      ['Režim', view ? 'výpis (#view)' : 'samostatná stránka'],
      ['Položek ve výpisu', document.querySelectorAll('[data-list-item]').length],
      ['Katalog', sel ? sel.value : ''],
      ['Hledání', q ? '„' + q.value + '“ (min. ' + (q.getAttribute('data-min-chars') || '?') + ' zn.)' : ''],
      ['Pager', cur ? cur.textContent.trim() : ''],
      ['Datum pageru', cur && cur.getAttribute('data-date')],
      ['Filtr oblíbených', view ? (view.getAttribute('data-fav') === '1' ? 'zapnutý' : 'vypnutý') : ''],
      ['Hvězdičky (aktivní / celkem)', favs.length ? favOn + ' / ' + favs.length : ''],
      ['Jazyk dokumentu', document.documentElement.lang],
      ['Titulek', document.title], ['H1', (document.querySelector('h1') || {}).textContent],
      ['kfLoadView', typeof window.kfLoadView === 'function' ? 'definováno' : 'není (stránka bez výpisu)']
    ]);

    // verze assetů (parametr ?v= je filemtime)
    const assets = [];
    const seen = {};
    document.querySelectorAll('script[src],link[rel="stylesheet"]').forEach(n => {
      const src = n.src || n.href;
      if (!src) return;
      let u;
      try { u = new URL(src, location.href); } catch (_) { return; }
      const base = u.pathname.split('/').slice(-2).join('/');
      const v = Number(u.searchParams.get('v'));
      const when = v > 1e9 ? new Date(v * 1000).toLocaleString() : (u.search ? u.search : 'bez ?v=');
      const dup = seen[u.pathname]; seen[u.pathname] = true;
      assets.push([base + (u.origin !== location.origin ? ' (cizí původ!)' : ''), dup ? 'DUPLICITNÍ · ' + when : when, dup || u.origin !== location.origin ? 'bad' : (u.search ? '' : 'warn')]);
    });
    section(c, 'Načtené JS / CSS (verze podle filemtime)', assets);

    const store1 = (name, area) => {
      const rows = [];
      try {
        const s = window[area];
        for (let i = 0; i < s.length; i++) {
          const k = s.key(i);
          const v = s.getItem(k) || '';
          rows.push([k, k.indexOf('kf-devtools') === 0 ? '(DevTools) ' + fmtBytes(v.length) : clip(v, 120)]);
        }
      } catch (_) { rows.push(['—', 'nedostupné']); }
      section(c, name + ' (' + rows.length + ')', rows.length ? rows : [['—', 'prázdné']]);
    };
    store1('sessionStorage', 'sessionStorage');
    store1('localStorage', 'localStorage');
    const cookies = document.cookie ? document.cookie.split(';').map(x => x.trim().split('=')[0]) : [];
    section(c, 'Cookies čitelné z JS', [['Názvy', cookies.join(', ') || 'žádné (session a jazyk jsou HttpOnly)']]);
  }

  /* --- Stránka / výkon --- */
  function renderPage() {
    const c = views.page;
    clearNode(c);
    c.appendChild(el('div', 'kf-debug-toolbar')).appendChild(refreshBtn(() => renderPage()));
    const nav = (perfEntries('navigation'))[0];
    const fcp = perfEntries('paint').find(p => p.name === 'first-contentful-paint');
    if (nav) {
      section(c, 'Načtení stránky', [
        ['TTFB (odezva serveru)', fmtMs(nav.responseStart), rate(nav.responseStart, 300, 800)],
        ['First Contentful Paint', fcp ? fmtMs(fcp.startTime) : '—', fcp ? rate(fcp.startTime, 1800, 3000) : ''],
        ['Largest Contentful Paint', perf.lcp != null ? fmtMs(perf.lcp) + (perf.lcpEl ? ' · ' + perf.lcpEl : '') : '—', rate(perf.lcp, 2500, 4000)],
        ['DOMContentLoaded', fmtMs(nav.domContentLoadedEventEnd)],
        ['Load', nav.loadEventEnd ? fmtMs(nav.loadEventEnd) : 'probíhá…'],
        ['Cumulative Layout Shift', perf.cls.toFixed(3), rate(perf.cls, 0.1, 0.25)],
        ['Dlouhé úkoly (>50 ms)', perf.long + (perf.long ? ' · celkem ' + fmtMs(perf.longMs) : ''), perf.long ? 'warn' : 'ok'],
        ['Typ navigace', nav.type], ['Přesměrování', nav.redirectCount], ['Protokol', nav.nextHopProtocol],
        ['Přenesený HTML dokument', fmtBytes(nav.transferSize || nav.encodedBodySize)]
      ]);
    }
    const res = perfEntries('resource');
    const sum = res.reduce((a, r) => a + (r.transferSize || 0), 0);
    const bySlow = res.slice().sort((a, b) => b.duration - a.duration).slice(0, 5).map(r => [redactUrl(r.name), fmtMs(r.duration)]);
    const byBig = res.slice().sort((a, b) => (b.transferSize || b.encodedBodySize) - (a.transferSize || a.encodedBodySize)).slice(0, 5).map(r => [redactUrl(r.name), fmtBytes(r.transferSize || r.encodedBodySize)]);
    const nodes = document.getElementsByTagName('*').length;
    section(c, 'Zdroje a DOM', [
      ['Počet zdrojů', res.length], ['Přeneseno celkem', fmtBytes(sum)],
      ['Prvků v DOM', nodes, nodes > 1500 ? 'warn' : 'ok'],
      ['Obrázků', document.images.length], ['Skriptů', document.scripts.length],
      ['JS heap', performance.memory ? fmtBytes(performance.memory.usedJSHeapSize) + ' / ' + fmtBytes(performance.memory.jsHeapSizeLimit) : 'nepodporováno']
    ]);
    if (bySlow.length) section(c, 'Nejpomalejší zdroje', bySlow);
    if (byBig.length) section(c, 'Největší zdroje', byBig);
    const mq = q => window.matchMedia && window.matchMedia(q).matches;
    const conn = navigator.connection || {};
    section(c, 'Zařízení', [
      ['Viewport', document.documentElement.clientWidth + ' × ' + document.documentElement.clientHeight],
      ['Obrazovka / DPR', screen.width + ' × ' + screen.height + ' @' + (window.devicePixelRatio || 1) + 'x'],
      ['Orientace', mq('(orientation: portrait)') ? 'na výšku' : 'na šířku'],
      ['Dotyk (max. bodů)', navigator.maxTouchPoints || 0], ['Hover', mq('(hover: hover)') ? 'ano' : 'ne'],
      ['Tmavý režim', mq('(prefers-color-scheme: dark)') ? 'ano' : 'ne'], ['Omezit animace', mq('(prefers-reduced-motion: reduce)') ? 'ano' : 'ne'],
      ['Online', navigator.onLine ? 'ano' : 'ne'], ['Připojení', conn.effectiveType ? conn.effectiveType + (conn.downlink ? ' · ' + conn.downlink + ' Mb/s' : '') + (conn.saveData ? ' · šetření dat' : '') : '—'],
      ['Jazyk prohlížeče', navigator.language], ['Prohlížeč', navigator.userAgent], ['DevTools běží', Math.round((Date.now() - startedAt) / 1000) + ' s']
    ]);
  }

  /* --- Kontroly DOM --- */
  function clearFlags() {
    document.querySelectorAll('.kf-dbg-flag,.kf-dbg-tap').forEach(n => n.classList.remove('kf-dbg-flag', 'kf-dbg-tap'));
  }
  function inScroller(node) {
    for (let p = node.parentElement; p && p !== document.body && p !== document.documentElement; p = p.parentElement) {
      if (getComputedStyle(p).overflowX !== 'visible') return true;
    }
    return false;
  }
  function runChecks() {
    clearFlags();
    const groups = [];
    const ok = [];
    const doc = document.documentElement;
    const vw = doc.clientWidth;
    const mine = n => (panelRef && panelRef.contains(n)) || n === vpBadge;
    const all = Array.from(document.body.querySelectorAll('*')).filter(n => !mine(n));
    const push = (sev, title, hint, items, flag) => {
      if (!items.length) { ok.push(title); return; }
      groups.push({ sev, title, hint, items: items.map(i => ({ label: i.label, el: i.el })), flag });
    };

    // 1) horizontální přetečení
    const over = all.filter(n => {
      const r = n.getBoundingClientRect();
      if (!r.width || !r.height || (r.right <= vw + 1 && r.left >= -1)) return false;
      return !inScroller(n);
    });
    const overSet = new Set(over);
    const topOver = over.filter(n => !overSet.has(n.parentElement));
    const docScrolls = doc.scrollWidth > vw + 1;
    push(docScrolls ? 'err' : 'warn', 'Prvky přesahují šířku viewportu (' + vw + ' px)' + (docScrolls ? ' – stránka se posouvá do stran' : ''),
      'Mobilní layout nesmí mít horizontální posouvání. Tabulky a kód patří do kontejneru s overflow-x:auto.',
      topOver.slice(0, 15).map(n => ({ label: describeEl(n) + '  ' + Math.round(n.getBoundingClientRect().width) + ' px', el: n })).concat(!topOver.length && docScrolls ? [{ label: 'zdroj přetečení se nepodařilo určit (scrollWidth ' + doc.scrollWidth + ')', el: doc }] : []), 'kf-dbg-flag');

    // 2) duplicitní ID
    const ids = {};
    all.forEach(n => { if (n.id) (ids[n.id] = ids[n.id] || []).push(n); });
    push('err', 'Duplicitní atributy id', 'ID musí být unikátní (label for, aria, JS selektory).',
      Object.keys(ids).filter(k => ids[k].length > 1).map(k => ({ label: '#' + k + ' ×' + ids[k].length, el: ids[k][1] })), 'kf-dbg-flag');

    // 3) inline event handlery (CSP script-src 'self')
    const inl = [];
    all.forEach(n => Array.from(n.attributes).forEach(a => { if (a.name.length > 2 && a.name.indexOf('on') === 0) inl.push({ label: describeEl(n) + ' [' + a.name + ']', el: n }); }));
    push('err', 'Inline JS atributy (onclick…)', 'CSP script-src \'self\' je blokuje. Použij addEventListener v souboru .js.', inl.slice(0, 15), 'kf-dbg-flag');

    // 4) POST formuláře bez CSRF
    const field = (panelRef && panelRef.dataset.csrfField) || '_csrf';
    push('err', 'POST formuláře bez pole ' + field, 'Server vrátí 403 (csrf_verify).',
      Array.from(document.forms).filter(f => String(f.getAttribute('method') || '').toLowerCase() === 'post' && !f.querySelector('[name="' + field + '"]') && !mine(f)).map(f => ({ label: describeEl(f) + ' → ' + redactUrl(f.action), el: f })), 'kf-dbg-flag');

    // 5) obrázky
    const imgs = Array.from(document.images).filter(i => !mine(i));
    push('err', 'Nenačtené obrázky', 'Soubor chybí nebo flag v DB neodpovídá souboru na disku.',
      imgs.filter(i => i.complete && i.naturalWidth === 0 && i.currentSrc).map(i => ({ label: redactUrl(i.currentSrc), el: i })), 'kf-dbg-flag');
    push('warn', 'Obrázky bez atributu alt', 'Dekorativní obrázky mají mít alt="".',
      imgs.filter(i => !i.hasAttribute('alt')).map(i => ({ label: describeEl(i) + ' ' + redactUrl(i.currentSrc || i.src), el: i })), 'kf-dbg-flag');
    push('info', 'Zbytečně velké obrázky (přes 2× DPR)', 'Např. náhled používá _l místo _s.',
      imgs.filter(i => i.clientWidth > 0 && i.naturalWidth > i.clientWidth * (window.devicePixelRatio || 1) * 2).map(i => ({ label: describeEl(i) + ' ' + i.naturalWidth + ' px → zobrazeno ' + i.clientWidth + ' px', el: i })), null);

    // 6) dotykové prvky
    const taps = all.filter(n => n.matches('a[href],button,select,summary,input:not([type=hidden]),[role="button"]') && !n.closest('[hidden]')).filter(n => {
      const r = n.getBoundingClientRect();
      if (!r.width || !r.height) return false;
      if (r.width >= 32 && r.height >= 32) return false;
      return getComputedStyle(n).display !== 'inline';
    });
    push('info', 'Malé dotykové prvky (pod 32 px)', 'Doporučeno min. 32–44 px; zapni zvýraznění v ⚙.',
      taps.slice(0, 20).map(n => { const r = n.getBoundingClientRect(); return { label: describeEl(n) + ' ' + Math.round(r.width) + '×' + Math.round(r.height), el: n }; }), S.showTaps ? 'kf-dbg-tap' : null);

    // 7) formuláře a odkazy
    push('warn', 'Pole bez popisku (label / aria-label)', 'Čtečky a autofill je potřebují.',
      all.filter(n => n.matches('input:not([type=hidden]):not([type=submit]):not([type=button]),select,textarea') && !(n.labels && n.labels.length) && !n.getAttribute('aria-label') && !n.getAttribute('aria-labelledby') && !n.title)
        .map(n => ({ label: describeEl(n) + (n.name ? ' name=' + n.name : ''), el: n })), 'kf-dbg-flag');
    push('info', 'target="_blank" bez rel="noopener"', '',
      all.filter(n => n.matches('a[target="_blank"]') && !/noopener/.test(n.rel)).map(n => ({ label: describeEl(n) + ' ' + redactUrl(n.href), el: n })), null);

    // 8) struktura dokumentu
    const h1 = document.querySelectorAll('h1').length;
    push('warn', 'Počet H1 na stránce: ' + h1, 'Doporučen právě jeden.', h1 === 1 ? [] : [{ label: h1 ? 'více nadpisů H1' : 'chybí H1', el: document.body }], null);
    const meta = [];
    if (!doc.lang) meta.push({ label: '<html> nemá atribut lang', el: doc });
    if (!document.querySelector('meta[name="viewport"]')) meta.push({ label: 'chybí meta viewport', el: document.head });
    push('warn', 'Základní meta údaje', '', meta, null);
    const ext = Array.from(document.querySelectorAll('script[src],link[rel="stylesheet"]')).filter(n => { try { return new URL(n.src || n.href, location.href).origin !== location.origin; } catch (_) { return false; } });
    push('err', 'Skripty / styly z cizího původu', 'CSP povoluje jen \'self\'.', ext.map(n => ({ label: redactUrl(n.src || n.href), el: n })), 'kf-dbg-flag');
    push('warn', 'Příliš velký DOM (' + all.length + ' prvků)', 'Nad 1500 prvků zpomaluje mobily.', all.length > 1500 ? [{ label: all.length + ' prvků', el: document.body }] : [], null);

    // označení prvků na stránce
    groups.forEach(g => { if (g.flag && (S.flagElements || g.flag === 'kf-dbg-tap')) g.items.forEach(i => { if (i.el && i.el.classList && i.el !== doc && i.el !== document.body && i.el !== document.head) i.el.classList.add(g.flag); }); });
    checks = { groups, ok, at: Date.now() };
    updateCounts();
    if (ui && activeTab === 'checks') renderChecks();
  }
  let checkTimer = 0;
  function scheduleChecks() {
    if (!S.autoChecks || !ui) return;
    clearTimeout(checkTimer);
    checkTimer = setTimeout(() => { try { runChecks(); } catch (e) { addErr('DevTools', 'Kontroly DOM selhaly: ' + e.message); } }, 500);
  }
  function renderChecks() {
    const c = views.checks;
    clearNode(c);
    const bar = el('div', 'kf-debug-toolbar');
    const run = el('button', '', '▶ Spustit kontroly');
    run.addEventListener('click', () => runChecks());
    bar.append(run, el('span', 'kf-debug-meta', checks.at ? 'naposledy ' + fmtTime(checks.at) : 'zatím nespuštěno'));
    c.appendChild(bar);
    if (!checks.at) return;
    if (!checks.groups.length) c.appendChild(el('div', 'kf-debug-ok-line', '✓ Žádné problémy nenalezeny.'));
    const sym = { err: '✕', warn: '!', info: 'i' };
    checks.groups.forEach((g, gi) => {
      const h = el('div', 'kf-debug-check-head ' + g.sev);
      h.append(el('span', '', sym[g.sev]), el('span', '', g.title + ' (' + g.items.length + ')'));
      c.appendChild(h);
      if (g.hint) c.appendChild(el('div', 'kf-debug-check-hint', g.hint));
      g.items.forEach((it, ii) => {
        const b = el('button', 'kf-debug-check-item', it.label);
        b.dataset.check = gi + ':' + ii;
        c.appendChild(b);
      });
    });
    if (checks.ok.length) c.appendChild(el('div', 'kf-debug-ok-line', '✓ V pořádku: ' + checks.ok.join(' · ')));
  }

  /* --- Server --- */
  function parseErrLine(line) {
    try {
      const j = JSON.parse(line);
      if (j && typeof j === 'object') {
        const x = j.context || {};
        const msg = [x.class, x.message].filter(Boolean).join(': ');
        return { time: String(j.time || '').replace('T', ' ').slice(0, 19), title: (j.type || 'LOG') + ' ' + (j.method || '') + ' ' + (j.path || '') + (msg ? ' — ' + msg : ''), detail: [x.file ? x.file + ':' + (x.line || 0) : '', x.trace || ''].filter(Boolean).join('\n') };
      }
    } catch (_) {}
    return { time: '', title: clip(line, 300), detail: line.length > 300 ? line : '' };
  }
  function parseSecLine(line) {
    const p = line.split('\t');
    if (p.length >= 5) return { time: p[0].replace('T', ' ').slice(0, 19), title: p[4] + ' · ' + p[3] + ' · ' + p[1], detail: p[5] || '' };
    return { time: '', title: clip(line, 300), detail: '' };
  }
  function loadServerLog() {
    if (serverLoading || !rawFetch || !panelRef) return;
    serverLoading = true;
    rawFetch(panelRef.dataset.logUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status + (r.status === 404 ? ' – chybí route diagnostics/log v index.php' : '')); return r.json(); })
      .then(j => { serverLog = { error: j.error || [], security: j.security || [], at: Date.now() }; })
      .catch(e => { serverLog = { err: e.message, at: Date.now() }; })
      .then(() => { serverLoading = false; if (activeTab === 'server') renderServer(); });
  }
  function renderServer() {
    const c = views.server;
    const d = panelRef.dataset;
    clearNode(c);
    const bar = el('div', 'kf-debug-toolbar');
    bar.appendChild(refreshBtn(() => { serverLog = null; renderServer(); }));
    c.appendChild(bar);
    section(c, 'Tento požadavek (PHP)', [
      ['HTML vygenerováno za', d.serverMs + ' ms', rate(Number(d.serverMs), 150, 500)],
      ['Špička paměti', d.memPeak + ' MB / limit ' + d.memLimit, rate(Number(d.memPeak), 16, 64)],
      ['PHP', d.php], ['Načtených PHP souborů', d.files], ['Metoda', d.method], ['APP_ENV', d.env],
      ['Uživatel (ID / role)', d.userId + ' / ' + d.userRole], ['Jazyk aplikace', d.lang], ['OPcache', d.opcache === '1' ? 'zapnutý' : 'vypnutý']
    ]);
    if (serverLog === null) { loadServerLog(); c.appendChild(el('div', 'kf-debug-empty', 'Načítám serverové logy…')); return; }
    if (serverLog.err) { c.appendChild(el('div', 'kf-debug-empty kf-debug-err', 'Logy nelze načíst: ' + serverLog.err)); return; }
    const block = (title, lines, parser) => {
      const parsed = lines.slice().reverse().map(parser).filter(p => !filterText || (p.title + ' ' + p.detail).toLowerCase().indexOf(filterText) > -1);
      c.appendChild(el('div', 'kf-debug-section', title + ' (' + parsed.length + ')'));
      if (!parsed.length) { c.appendChild(el('div', 'kf-debug-empty', 'Prázdné.')); return; }
      parsed.forEach((p, i) => {
        const key = 'srv:' + title + i;
        const open = expanded.has(key);
        const row = el('div', 'kf-debug-entry' + (p.detail ? ' kf-debug-expandable' : '') + (open ? ' kf-debug-open' : ''));
        row.dataset.key = key;
        row.append(el('span', 'kf-debug-time', p.time.slice(5) || '—'), el('span', 'kf-debug-message', p.title));
        if (open && p.detail) row.appendChild(el('pre', 'kf-debug-detail', p.detail));
        c.appendChild(row);
      });
    };
    block('PHP error log', serverLog.error, parseErrLine);
    block('Bezpečnostní log', serverLog.security, parseSecLine);
  }

  /* --- Nastavení --- */
  const TOGGLES = [
    ['keepLog', 'Zachovat záznamy mezi stránkami', 'sessionStorage, do zavření karty. Starší záznamy jsou ztlumené.'],
    ['captureBodies', 'Ukládat těla odpovědí fetch/XHR', 'Náhled v detailu požadavku. Chybové odpovědi 4xx/5xx se ukládají vždy.'],
    ['showAssets', 'Zobrazit statické soubory v Network', 'CSS, JS, obrázky.'],
    ['logClicks', 'Zaznamenávat kliknutí a odeslání formulářů', 'Záložka Events.'],
    ['logNav', 'Zaznamenávat navigaci, posuny layoutu a dlouhé úkoly', 'pushState, popstate, CLS, longtask.'],
    ['autoChecks', 'Automaticky spouštět kontroly DOM', 'Po načtení, změně výpisu a změně velikosti okna.'],
    ['flagElements', 'Označit problémové prvky na stránce', 'Červený čárkovaný obrys.'],
    ['showTaps', 'Označit malé dotykové prvky (pod 32 px)', 'Oranžový tečkovaný obrys.'],
    ['outlineAll', 'Obrysy všech prvků', 'Pomůcka pro ladění layoutu.'],
    ['viewportBadge', 'Ukázat rozměry viewportu v rohu', 'Při testu responzivity.'],
    ['reserveSpace', 'Odsadit stránku o výšku panelu', 'Aby panel nezakrýval konec obsahu.']
  ];
  function buildSettings() {
    const c = views.settings;
    clearNode(c);
    TOGGLES.forEach(([key, label, hint]) => {
      const row = el('label', 'kf-debug-switch');
      const inp = el('input');
      inp.type = 'checkbox';
      inp.checked = !!S[key];
      inp.addEventListener('change', () => {
        S[key] = inp.checked;
        saveSettings();
        if (key === 'showAssets') netF.asset = S.showAssets;
        if (key === 'keepLog') { if (S.keepLog) persist(); else ss.del(LOG_KEY); }
        applySettings();
        if (['flagElements', 'showTaps', 'autoChecks'].indexOf(key) > -1) runChecks();
        render();
      });
      const txt = el('span', '', label);
      txt.appendChild(el('small', '', hint));
      row.append(inp, txt);
      c.appendChild(row);
    });
    const field = (label, control) => { const f = el('div', 'kf-debug-field'); f.append(el('label', '', label), control); c.appendChild(f); };
    const num = el('input'); num.type = 'number'; num.min = '50'; num.step = '50'; num.value = S.slowMs;
    num.addEventListener('change', () => { S.slowMs = Math.max(50, Number(num.value) || 800); saveSettings(); render(); });
    field('Hranice „pomalého“ požadavku (ms)', num);
    const rng = el('input'); rng.type = 'range'; rng.min = '15'; rng.max = '85'; rng.value = S.height;
    rng.addEventListener('input', () => { S.height = Number(rng.value); applySettings(); });
    rng.addEventListener('change', saveSettings);
    field('Výška panelu (% obrazovky)', rng);
    const fs = el('select');
    [10, 11, 12, 13, 14].forEach(n => { const o = el('option', '', n + ' px'); o.value = n; if (n === S.fontSize) o.selected = true; fs.appendChild(o); });
    fs.addEventListener('change', () => { S.fontSize = Number(fs.value); saveSettings(); applySettings(); });
    field('Velikost písma', fs);
    const dock = el('select');
    [['bottom', 'dole'], ['top', 'nahoře']].forEach(([v, t]) => { const o = el('option', '', t); o.value = v; if (v === S.dock) o.selected = true; dock.appendChild(o); });
    dock.addEventListener('change', () => { S.dock = dock.value; saveSettings(); applySettings(); });
    field('Umístění panelu', dock);

    const bar = el('div', 'kf-debug-toolbar');
    const mk = (text, fn) => { const b = el('button', '', text); b.addEventListener('click', () => fn(b)); bar.appendChild(b); };
    mk('Report: vše', b => copyText(buildReport('all'), b));
    mk('Report: jen chyby', b => copyText(buildReport('errors'), b));
    mk('Smazat uložené záznamy', () => { ss.del(LOG_KEY); clearAll(); });
    mk('Výchozí nastavení', () => { ls.del(SETTINGS_KEY); location.reload(); });
    c.appendChild(bar);
  }

  /* --- chipy --- */
  function chipDefs() {
    if (activeTab === 'console') return ['log', 'info', 'warn', 'error', 'debug'].map(l => ({ label: l, get: () => S.levels[l] !== false, set: v => { S.levels[l] = v; saveSettings(); } }));
    if (activeTab === 'network') return [
      { label: 'Fetch/XHR', get: () => netF.xhr, set: v => { netF.xhr = v; } },
      { label: 'Assety', get: () => netF.asset, set: v => { netF.asset = v; } },
      { label: 'Chyby', get: () => netF.bad, set: v => { netF.bad = v; } },
      { label: 'Pomalé', get: () => netF.slow, set: v => { netF.slow = v; } }
    ];
    if (activeTab === 'events') return [['act', 'Akce'], ['nav', 'Navigace'], ['perf', 'Výkon'], ['dlg', 'Dialogy']].map(([k, l]) => ({ label: l, get: () => evF[k], set: v => { evF[k] = v; } }));
    return [];
  }
  function buildChips() {
    clearNode(chipsEl);
    chipDefs().forEach(def => {
      const b = el('button', '', def.label);
      b.type = 'button';
      b.setAttribute('aria-pressed', def.get() ? 'true' : 'false');
      b.addEventListener('click', () => { def.set(!def.get()); b.setAttribute('aria-pressed', def.get() ? 'true' : 'false'); render(); });
      chipsEl.appendChild(b);
    });
  }

  /* --- celkové vykreslení --- */
  function updateCounts() {
    if (!ui) return;
    const checkN = checks.groups.filter(g => g.sev !== 'info').reduce((a, g) => a + g.items.length, 0);
    const n = {
      console: records.console.length,
      network: records.network.filter(r => r.type !== 'asset').length,
      errors: records.errors.length, events: records.events.length, checks: checkN
    };
    panelRef.querySelectorAll('[data-debug-count]').forEach(node => {
      const k = node.getAttribute('data-debug-count');
      node.textContent = String(n[k] || 0);
      node.classList.toggle('kf-debug-badge-bad', (k === 'errors' && n.errors > 0) || (k === 'checks' && checks.groups.some(g => g.sev === 'err')));
      node.classList.toggle('kf-debug-badge-warn', k === 'checks' && checkN > 0 && !checks.groups.some(g => g.sev === 'err'));
    });
    const failed = records.network.filter(r => r.failed && !r.old).length;
    const warns = records.console.filter(r => r.level === 'warn' && !r.old).length;
    const parts = [document.documentElement.clientWidth + '×' + document.documentElement.clientHeight];
    if (n.errors) parts.push(n.errors + ' chyb');
    if (failed) parts.push(failed + ' selhaných požadavků');
    if (warns) parts.push(warns + ' varování');
    if (checkN) parts.push(checkN + ' nálezů');
    if (panelRef.dataset.serverMs) parts.push('server ' + panelRef.dataset.serverMs + ' ms');
    summaryEl.textContent = parts.join(' · ');
    panelRef.classList.toggle('kf-debug-has-errors', n.errors > 0);
  }
  function renderTab(name) {
    if (name === 'console') renderConsole();
    else if (name === 'network') renderNetwork();
    else if (name === 'errors') renderErrors();
    else if (name === 'events') renderEvents();
    else if (name === 'app') renderApp();
    else if (name === 'page') renderPage();
    else if (name === 'checks') renderChecks();
    else if (name === 'server') renderServer();
  }
  function render() {
    if (!ui) return;
    updateCounts();
    if (LIST_TABS.indexOf(activeTab) > -1) renderTab(activeTab);
  }
  function selectTab(name) {
    if (!views[name]) name = 'console';
    activeTab = name;
    S.tab = name;
    saveSettings();
    panelRef.querySelectorAll('[data-debug-tab]').forEach(b => b.setAttribute('aria-selected', b.dataset.debugTab === name ? 'true' : 'false'));
    Object.keys(views).forEach(k => { views[k].hidden = k !== name; });
    toolsEl.hidden = FILTER_TABS.indexOf(name) === -1;
    metaEl.textContent = '';
    buildChips();
    renderTab(name);
  }

  function clearAll() {
    TYPES.forEach(t => { records[t] = []; });
    expanded.clear();
    serverLog = null;
    perf.cls = 0; perf.long = 0; perf.longMs = 0;
    render();
  }

  /* --- nastavení → DOM --- */
  function updateVp() {
    if (vpBadge) vpBadge.textContent = document.documentElement.clientWidth + '×' + document.documentElement.clientHeight + ' @' + (window.devicePixelRatio || 1) + 'x';
  }
  function applySettings() {
    const root = document.documentElement;
    root.classList.toggle('kf-dbg-outline', !!S.outlineAll);
    panelRef.classList.toggle('kf-debug-dock-top', S.dock === 'top');
    panelRef.classList.toggle('kf-debug-collapsed', !!S.collapsed);
    panelRef.style.setProperty('--kfd-h', String(S.height));
    panelRef.style.setProperty('--kfd-fs', S.fontSize + 'px');
    root.classList.toggle('kf-dbg-space-bottom', !!S.reserveSpace && S.dock !== 'top');
    root.classList.toggle('kf-dbg-space-top', !!S.reserveSpace && S.dock === 'top');
    root.style.setProperty('--kfd-space', S.collapsed ? '44px' : 'calc(' + S.height + 'vh + 8px)');
    vpBadge.hidden = !S.viewportBadge;
    updateVp();
    if (toggleBtn) {
      toggleBtn.textContent = S.collapsed ? '▴' : '▾';
      toggleBtn.setAttribute('aria-expanded', S.collapsed ? 'false' : 'true');
    }
    if (!S.flagElements && !S.showTaps) clearFlags();
  }

  /* --- kopírování a report --- */
  function copyText(text, btn) {
    const done = () => { if (btn) { const old = btn.textContent; btn.textContent = '✓'; setTimeout(() => { btn.textContent = old; }, 1200); } };
    const fallback = () => {
      const a = document.createElement('textarea');
      a.value = text; a.style.position = 'fixed'; a.style.opacity = '0';
      document.body.appendChild(a); a.select();
      try { document.execCommand('copy'); done(); } catch (_) {}
      a.remove();
    };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done, fallback);
    else fallback();
  }
  function buildReport(mode) {
    const L = [];
    const only = mode === 'errors';
    const ind = s => String(s || '').split('\n').map(x => '    ' + x).join('\n');
    const d = panelRef.dataset;
    L.push('# K&F DevTools report' + (only ? ' (jen chyby)' : ''), '',
      'Čas: ' + new Date().toISOString(), 'URL: ' + redactUrl(location.href),
      'Viewport: ' + document.documentElement.clientWidth + '×' + document.documentElement.clientHeight + ' @' + (window.devicePixelRatio || 1) + 'x',
      'Prohlížeč: ' + navigator.userAgent,
      'Server: PHP ' + d.php + ', HTML za ' + d.serverMs + ' ms, paměť ' + d.memPeak + ' MB, lang ' + d.lang, '');
    L.push('## Chyby (' + records.errors.length + ')');
    if (!records.errors.length) L.push('žádné');
    records.errors.slice(-40).forEach(r => { L.push('- [' + fmtTime(r.t) + '] [' + r.kind + '] ' + clip(r.message, 800) + (r.old ? '  (předchozí stránka: ' + r.page + ')' : '')); if (r.detail) L.push(ind(clip(r.detail, 1200))); });
    const bad = records.network.filter(r => r.failed);
    L.push('', '## Selhané požadavky (' + bad.length + ')');
    bad.slice(-20).forEach(r => { L.push('- ' + r.method + ' ' + r.status + ' ' + r.url + ' (' + fmtMs(r.ms) + ')'); if (r.reqBody) L.push(ind('request: ' + clip(r.reqBody, 400))); if (r.body) L.push(ind('response: ' + clip(r.body, 800))); });
    if (!only) {
      L.push('', '## Console (posledních 60)');
      records.console.slice(-60).forEach(r => L.push('- [' + fmtTime(r.t) + '] ' + r.level.toUpperCase() + (r.count > 1 ? ' ×' + r.count : '') + ' ' + clip(r.message, 600)));
      L.push('', '## Network (posledních 50, bez assetů)');
      records.network.filter(r => r.type !== 'asset').slice(-50).forEach(r => L.push('- ' + r.method + ' ' + r.status + ' ' + r.url + ' ' + fmtMs(r.ms) + ' ' + fmtBytes(r.size)));
      L.push('', '## Events (posledních 60)');
      records.events.slice(-60).forEach(r => L.push('- [' + fmtTime(r.t) + '] ' + r.kind + ': ' + clip(r.message, 300)));
      L.push('', '## Kontroly DOM');
      if (!checks.groups.length) L.push('bez nálezů');
      checks.groups.forEach(g => { L.push('- (' + g.sev + ') ' + g.title); g.items.slice(0, 8).forEach(i => L.push('    · ' + i.label)); });
      const nav = (perfEntries('navigation'))[0];
      L.push('', '## Výkon', 'TTFB ' + (nav ? fmtMs(nav.responseStart) : '—') + ', LCP ' + (perf.lcp != null ? fmtMs(perf.lcp) : '—') + ', CLS ' + perf.cls.toFixed(3) + ', dlouhé úkoly ' + perf.long);
    }
    if (serverLog && serverLog.error) {
      L.push('', '## PHP error log (posledních 15)');
      serverLog.error.slice(-15).forEach(x => L.push('- ' + clip(x, 500)));
    }
    return L.join('\n');
  }

  /* ---------- inicializace UI ---------- */
  function init() {
    panelRef = document.getElementById('kf-debug-panel');
    if (!panelRef || panelRef.dataset.initialized === '1') return;
    panelRef.dataset.initialized = '1';
    const q = sel => panelRef.querySelector(sel);
    panelRef.querySelectorAll('[data-debug-view]').forEach(v => { views[v.dataset.debugView] = v; });
    toolsEl = q('.kf-debug-tools'); chipsEl = q('[data-debug-chips]'); metaEl = q('[data-debug-meta]');
    filterEl = q('[data-debug-filter]'); summaryEl = q('[data-debug-summary]');
    pauseBtn = q('[data-debug-pause]'); toggleBtn = q('[data-debug-toggle]');

    vpBadge = el('div'); vpBadge.id = 'kf-debug-vp'; vpBadge.hidden = true;
    document.body.appendChild(vpBadge);
    const grip = el('div', 'kf-debug-grip');
    grip.setAttribute('aria-hidden', 'true');
    panelRef.insertBefore(grip, panelRef.firstChild);
    ui = true;

    applySettings();
    buildSettings();

    panelRef.querySelectorAll('[data-debug-tab]').forEach(b => b.addEventListener('click', () => selectTab(b.dataset.debugTab)));
    filterEl.addEventListener('input', () => { filterText = filterEl.value.trim().toLowerCase(); if (activeTab === 'server') renderServer(); else render(); });
    q('[data-debug-clear]').addEventListener('click', clearAll);
    q('[data-debug-copy]').addEventListener('click', ev => copyText(buildReport('all'), ev.currentTarget));
    q('[data-debug-dock]').addEventListener('click', () => { S.dock = S.dock === 'top' ? 'bottom' : 'top'; saveSettings(); applySettings(); buildSettings(); });
    toggleBtn.addEventListener('click', () => { S.collapsed = !S.collapsed; saveSettings(); applySettings(); });
    pauseBtn.addEventListener('click', () => {
      paused = !paused;
      pauseBtn.textContent = paused ? '▶' : '❚❚';
      pauseBtn.setAttribute('aria-pressed', paused ? 'true' : 'false');
      summaryEl.textContent = paused ? 'POZASTAVENO' : '';
      updateCounts();
    });
    document.addEventListener('keydown', ev => {
      if (ev.altKey && (ev.key === 'd' || ev.key === 'D')) { ev.preventDefault(); S.collapsed = !S.collapsed; saveSettings(); applySettings(); }
    });

    // rozbalování řádků, kopírování, přeskok na prvek z kontrol
    panelRef.querySelector('.kf-debug-content').addEventListener('click', ev => {
      const cb = ev.target.closest('[data-copy]');
      if (cb) { copyText(cb.dataset.copy, cb); return; }
      const chk = ev.target.closest('[data-check]');
      if (chk) {
        const [gi, ii] = chk.dataset.check.split(':').map(Number);
        const item = checks.groups[gi] && checks.groups[gi].items[ii];
        if (item && item.el && item.el.scrollIntoView) {
          item.el.scrollIntoView({ block: 'center', behavior: 'smooth' });
          item.el.classList.add('kf-dbg-flash');
          setTimeout(() => item.el.classList.remove('kf-dbg-flash'), 1800);
        }
        return;
      }
      const row = ev.target.closest('[data-key]');
      if (!row || (window.getSelection && String(window.getSelection()).length)) return;
      if (ev.target.closest('.kf-debug-detail')) return;
      const key = row.dataset.key;
      if (expanded.has(key)) expanded.delete(key); else expanded.add(key);
      if (activeTab === 'server') renderServer(); else renderTab(activeTab);
    });

    // změna výšky tažením okraje
    grip.addEventListener('pointerdown', ev => {
      ev.preventDefault();
      grip.setPointerCapture(ev.pointerId);
      const move = e => {
        const h = S.dock === 'top' ? e.clientY : innerHeight - e.clientY;
        S.height = Math.round(Math.max(15, Math.min(85, h / innerHeight * 100)));
        applySettings();
      };
      const up = () => {
        grip.removeEventListener('pointermove', move);
        grip.removeEventListener('pointerup', up);
        grip.removeEventListener('pointercancel', up);
        saveSettings();
        buildSettings();
      };
      grip.addEventListener('pointermove', move);
      grip.addEventListener('pointerup', up);
      grip.addEventListener('pointercancel', up);
    });

    window.addEventListener('resize', () => { updateVp(); updateCounts(); scheduleChecks(); }, { passive: true });

    // sledování #view (částečné načítání výpisu přes partial=1)
    const view = document.getElementById('view');
    if (view && window.MutationObserver) {
      let loadStart = 0;
      new MutationObserver(muts => {
        muts.forEach(m => {
          if (m.type !== 'attributes') return;
          const loading = view.classList.contains('loading');
          if (loading && !loadStart) loadStart = performance.now();
          if (!loading && loadStart) {
            const ms = performance.now() - loadStart;
            loadStart = 0;
            if (S.logNav) addEvent('view', 'Výpis přenačten za ' + fmtMs(ms) + ' · ' + view.querySelectorAll('[data-list-item]').length + ' položek', redactUrl(location.href));
          }
        });
        scheduleChecks();
        if (activeTab === 'app') renderApp();
      }).observe(view, { childList: true, attributes: true, attributeFilter: ['class'] });
    }

    addEvent('nav', 'Stránka načtena: ' + pageTag);
    if (!S.keepLog || !records.console.length) add('console', { level: 'info', message: 'K&F Developer Tools aktivní.' });
    selectTab(activeTab);
    window.addEventListener('load', () => { scheduleChecks(); if (activeTab === 'page') renderPage(); });
    if (document.readyState === 'complete') scheduleChecks();
  }

  window.addEventListener('pagehide', persist);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') persist(); });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
