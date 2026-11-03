'use strict';
(() => {
  const $ = s => document.querySelector(s);
  $('#year').textContent = String(new Date().getFullYear());
  const nav = $('.nav'), btn = $('.menu-toggle');
  if (btn && nav) btn.addEventListener('click', () => { const o = nav.classList.toggle('open'); btn.setAttribute('aria-expanded', String(o)); });

  const form = $('#login-form'), box = $('#login-message');
  let csrf = '';
  const showError = text => { box.textContent = text; box.hidden = false; };

  fetch('api/auth.php', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
      if (d.authenticated) { location.replace('admin.html'); return; }
      csrf = d.csrf || '';
      if (!d.configured) { $('#not-configured').hidden = false; return; }
      form.hidden = false;
    })
    .catch(() => showError('ارتباط با سرور برقرار نشد. صفحه را تازه‌سازی کنید.'));

  form.addEventListener('submit', async event => {
    event.preventDefault();
    box.hidden = true;
    const fd = new FormData(form);
    fd.set('csrf', csrf); fd.set('action', 'login');
    const submit = form.querySelector('button'); submit.disabled = true;
    try {
      const r = await fetch('api/auth.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (d.ok) { location.replace('admin.html'); return; }
      showError(d.message || 'ورود ناموفق بود.');
    } catch (e) {
      showError('ارتباط با سرور برقرار نشد. دوباره تلاش کنید.');
    } finally { submit.disabled = false; }
  });
})();
