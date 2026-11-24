/* Živé údaje a přesouvání plovoucí diagnostiky. */
(() => {
  'use strict';

  const panel = document.getElementById('kf-debug');
  if (!panel) return;

  const screen = panel.querySelector('[data-debug-screen]');
  const viewport = panel.querySelector('[data-debug-viewport]');
  const online = panel.querySelector('[data-debug-online]');
  const clock = panel.querySelector('[data-debug-time]');
  const toggle = panel.querySelector('[data-debug-toggle]');
  const head = panel.querySelector('.kf-debug-head');
  const positionKey = 'kf-debug-position';

  const update = () => {
    if (screen) screen.textContent = window.screen
      ? window.screen.width + ' × ' + window.screen.height
      : '—';
    if (viewport) viewport.textContent =
      document.documentElement.clientWidth + ' × ' + document.documentElement.clientHeight;
    if (online) online.textContent = navigator.onLine ? 'Ano' : 'Ne';
    if (clock) clock.textContent = new Date().toLocaleTimeString();
  };

  const keepInViewport = () => {
    const rect = panel.getBoundingClientRect();
    const left = Math.max(0, Math.min(rect.left, window.innerWidth - rect.width));
    const top = Math.max(0, Math.min(rect.top, window.innerHeight - rect.height));
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
      requestAnimationFrame(keepInViewport);
    }
  } catch (_) {}

  if (head) {
    head.addEventListener('pointerdown', event => {
      if (event.target.closest('button')) return;
      if (event.pointerType === 'mouse' && event.button !== 0) return;

      const rect = panel.getBoundingClientRect();
      const offsetX = event.clientX - rect.left;
      const offsetY = event.clientY - rect.top;
      head.setPointerCapture(event.pointerId);
      panel.classList.add('is-dragging');

      const move = e => {
        const left = Math.max(0, Math.min(e.clientX - offsetX, window.innerWidth - panel.offsetWidth));
        const top = Math.max(0, Math.min(e.clientY - offsetY, window.innerHeight - panel.offsetHeight));
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
      };
      const finish = () => {
        panel.classList.remove('is-dragging');
        try {
          const rectNow = panel.getBoundingClientRect();
          localStorage.setItem(positionKey, JSON.stringify({ left: rectNow.left, top: rectNow.top }));
        } catch (_) {}
        head.removeEventListener('pointermove', move);
        head.removeEventListener('pointerup', finish);
        head.removeEventListener('pointercancel', finish);
      };

      head.addEventListener('pointermove', move);
      head.addEventListener('pointerup', finish);
      head.addEventListener('pointercancel', finish);
      event.preventDefault();
    });
  }

  if (toggle) {
    toggle.addEventListener('click', () => {
      const collapsed = panel.classList.toggle('is-collapsed');
      toggle.textContent = collapsed ? '+' : '−';
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggle.setAttribute('aria-label', collapsed ? 'Rozbalit diagnostiku' : 'Sbalit diagnostiku');
      requestAnimationFrame(keepInViewport);
    });
  }

  window.addEventListener('resize', () => {
    keepInViewport();
    update();
  }, { passive: true });
  window.addEventListener('online', update);
  window.addEventListener('offline', update);
  update();
  window.setInterval(update, 1000);
})();
