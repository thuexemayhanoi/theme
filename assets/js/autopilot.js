/* Native display, server clock, contact disclosure and local retrieval UI. */
(() => {
  'use strict';
  const root = document.documentElement;
  const media = matchMedia('(prefers-color-scheme: dark)');
  const applyMode = (mode) => {
    root.dataset.ntMode = mode;
    root.dataset.ntTheme = mode === 'auto' ? (media.matches ? 'dark' : 'light') : mode;
    document.querySelectorAll('[data-nt-mode]').forEach((select) => { select.value = mode; });
  };
  applyMode(root.dataset.ntMode || 'auto');
  document.querySelectorAll('[data-nt-mode]').forEach((select) => {
    select.addEventListener('change', () => {
      applyMode(select.value);
      try { localStorage.setItem('nt-color-mode', select.value); } catch { /* Private browsing. */ }
    });
  });
  media.addEventListener('change', () => applyMode(root.dataset.ntMode));
  const config = window.ntAutopilot;
  if (!config) return;
  // Keep the authenticated sandbox theme context when testing REST routes in a draft.
  const preview = new URL(location.href).searchParams.get('wpvibe_preview');
  if (preview) for (const key of ['hours', 'assistant']) {
    const url = new URL(config[key], location.href);
    url.searchParams.set('wpvibe_preview', preview);
    config[key] = url.href;
  }
  let timer;
  const showHours = (data) => {
    document.querySelectorAll('[data-nt-hours]').forEach((node) => { node.textContent = data.label; });
    const seconds = data.next_change ? data.next_change - data.server_time : 60;
    clearTimeout(timer);
    timer = setTimeout(refreshHours, Math.max(1000, Math.min(60000, seconds * 1000 + 200)));
  };
  async function refreshHours() {
    if (document.hidden) return;
    try {
      const response = await fetch(config.hours, { cache: 'no-store', signal: AbortSignal.timeout(8000) });
      if (!response.ok) throw new Error('clock');
      showHours(await response.json());
    } catch {
      document.querySelectorAll('[data-nt-hours]').forEach((node) => { node.textContent = 'Giờ thường lệ 09:00–21:00 — Liên hệ để xác nhận'; });
      clearTimeout(timer);
      timer = setTimeout(refreshHours, 60000);
    }
  }
  refreshHours();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshHours(); else clearTimeout(timer); });

  const panel = document.querySelector('#nt-assistant');
  const toggle = document.querySelector('[data-nt-chat-toggle]');
  const contact = document.querySelector('[data-nt-quickcall]');
  if (!panel || !toggle) return;
  const input = panel.querySelector('input');
  const close = () => { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); };
  toggle.addEventListener('click', () => {
    if (!panel.hidden) { close(); return; }
    if (contact) contact.open = false;
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    input.focus();
  });
  panel.querySelector('[data-nt-chat-close]').addEventListener('click', close);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { if (contact) contact.open = false; if (!panel.hidden) close(); }
  });
  contact?.addEventListener('toggle', () => { if (contact.open && !panel.hidden) { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); } });
  const messages = panel.querySelector('[data-nt-messages]');
  const append = (text, kind = 'answer') => {
    const p = document.createElement('p');
    p.className = `nt-message nt-message--${kind}`;
    p.textContent = text;
    messages.append(p);
    while (messages.children.length > 20) messages.firstElementChild.remove();
    messages.scrollTop = messages.scrollHeight;
  };
  let context = '';
  const form = panel.querySelector('form');
  const send = form.querySelector('button');
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const question = input.value.trim();
    if (!question || send.disabled) return;
    append(question, 'question');
    input.value = '';
    send.disabled = true;
    send.textContent = 'Đang tìm…';
    try {
      const response = await fetch(config.assistant, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ question, context }), signal: AbortSignal.timeout(12000) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Vui lòng gọi trực tiếp hoặc nhắn Zalo.');
      append(data.answer);
      context = data.context || '';
      if (data.hours) showHours(data.hours);
      for (const source of data.sources || []) {
        const url = new URL(source.url, location.href);
        if (url.origin !== location.origin) continue;
        const link = document.createElement('a');
        link.href = url.href;
        link.textContent = source.title;
        messages.append(link);
      }
    } catch (error) {
      append(error.name === 'TimeoutError' ? 'Kết nối chậm. Khách vui lòng gọi hoặc nhắn Zalo.' : error.message);
    } finally {
      send.disabled = false;
      send.textContent = 'Gửi';
      input.focus();
      messages.scrollTop = messages.scrollHeight;
    }
  });
})();
