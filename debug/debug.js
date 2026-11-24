/* Základní živé údaje plovoucí diagnostiky. */
(() => {
  'use strict';

  const panel = document.getElementById('kf-debug');
  if (!panel) return;

  const screen = panel.querySelector('[data-debug-screen]');
  const viewport = panel.querySelector('[data-debug-viewport]');
  const online = panel.querySelector('[data-debug-online]');
  const clock = panel.querySelector('[data-debug-time]');
  const toggle = panel.querySelector('[data-debug-toggle]');

  const update = () => {
    if (screen) screen.textContent = window.screen
      ? window.screen.width + ' × ' + window.screen.height
      : '—';
    if (viewport) viewport.textContent =
      document.documentElement.clientWidth + ' × ' + document.documentElement.clientHeight;
    if (online) online.textContent = navigator.onLine ? 'Ano' : 'Ne';
    if (clock) clock.textContent = new Date().toLocaleTimeString();
  };

  if (toggle) {
    toggle.addEventListener('click', () => {
      const collapsed = panel.classList.toggle('is-collapsed');
      toggle.textContent = collapsed ? '+' : '−';
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggle.setAttribute('aria-label', collapsed ? 'Rozbalit diagnostiku' : 'Sbalit diagnostiku');
    });
  }

  window.addEventListener('resize', update, { passive: true });
  window.addEventListener('online', update);
  window.addEventListener('offline', update);
  update();
  window.setInterval(update, 1000);
})();
