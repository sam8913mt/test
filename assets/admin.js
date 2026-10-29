'use strict';
(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const nl2br = v => esc(v).replace(/\n/g, '<br>');
  const phoneHref = v => String(v || '').replace(/[^0-9+]/g, '');
  let csrf = '', data = null;

  $('#year').textContent = String(new Date().getFullYear());
  const nav = $('.nav'), menuBtn = $('.menu-toggle');
  if (menuBtn && nav) menuBtn.addEventListener('click', () => { const o = nav.classList.toggle('open'); menuBtn.setAttribute('aria-expanded', String(o)); });

  function notice(type, text) {
    const box = $('#admin-notice');
    box.className = type === 'ok' ? 'success' : 'alert';
    box.textContent = text; box.hidden = false;
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  async function request(method, body) {
    const options = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (body) options.body = body;
    const response = await fetch('api/admin.php', options);
    let payload = {};
    try { payload = await response.json(); } catch (e) { /* non-JSON response */ }
    if (response.status === 401) { location.replace('login.html'); throw new Error('برای ادامه باید وارد شوید.'); }
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'عملیات ناموفق بود.');
    return payload;
  }

  async function send(fd, action) {
    fd.set('csrf', csrf); fd.set('action', action);
    const result = await request('POST', fd);
    csrf = result.csrf || csrf; data = result.data;
    renderAll();
    notice('ok', result.message || 'انجام شد.');
    return result;
  }

  /* ---------- rendering ---------- */
  function renderProjects() {
    $('#project-count').textContent = String(data.projects.length);
    const list = $('#project-list');
    if (!data.projects.length) { list.innerHTML = '<p class="muted">هنوز ساختمانی ثبت نشده است.</p>'; return; }
    list.innerHTML = '<div class="admin-project-list">' + data.projects.map(p => `<div class="admin-project">${p.images.length ? `<img src="${esc(p.images[0].url)}" alt="">` : '<div class="mini-placeholder">⌂</div>'}<div class="admin-project-info"><b>${esc(p.title)}</b><small>${esc(p.location)} · ${esc(p.status)} · ${p.images.length} تصویر</small><a class="text-link" href="${esc(p.url)}" target="_blank" rel="noopener">مشاهده صفحه ↗</a></div><button type="button" class="edit-btn" data-do="edit-project" data-id="${p.id}">ویرایش</button><button type="button" class="delete-btn" data-do="project_delete" data-id="${p.id}" data-confirm="این ساختمان حذف شود؟">حذف</button></div>`).join('') + '</div>';
  }

  function renderContacts() {
    $('#contact-count').textContent = String(data.contacts.length);
    const unread = $('#unread-count');
    unread.hidden = !data.unread; unread.textContent = `${data.unread} درخواست جدید`;
    const list = $('#contact-list');
    if (!data.contacts.length) { list.innerHTML = '<p class="muted">هنوز درخواستی ثبت نشده است.</p>'; return; }
    list.innerHTML = '<div class="lead-list">' + data.contacts.map(l => {
      const isNew = l.status === 'new';
      return `<article class="lead-card"><div class="lead-head"><div><b>${esc(l.name)}</b><small>${esc(l.created_at)} · ${isNew ? '<span class="lead-new">جدید</span>' : 'پیگیری‌شده'}</small></div><span class="lead-topic">${esc(l.topic)}</span></div><div class="lead-contact"><a href="tel:${esc(phoneHref(l.phone))}" dir="ltr">${esc(l.phone)}</a>${l.email ? `<a href="mailto:${esc(l.email)}">${esc(l.email)}</a>` : ''}${l.project_title ? `<span>پروژه: ${esc(l.project_title)}</span>` : ''}</div><p class="lead-message">${nl2br(l.message)}</p><div class="lead-actions"><button type="button" class="edit-btn" data-do="contact_status" data-id="${l.id}" data-status="${isNew ? 'read' : 'new'}">${isNew ? 'علامت‌گذاری به‌عنوان پیگیری‌شده' : 'بازگردانی به جدید'}</button><button type="button" class="delete-btn" data-do="contact_delete" data-id="${l.id}" data-confirm="این درخواست حذف شود؟">حذف درخواست</button></div></article>`;
    }).join('') + '</div>';
  }

  function renderServices() {
    $('#service-list').innerHTML = data.services.map(s => `<div><span>${esc(s.icon)} ${esc(s.title)}</span><button type="button" class="delete-btn" data-do="service_delete" data-id="${s.id}" data-confirm="این خدمت حذف شود؟">حذف</button></div>`).join('');
  }

  function renderAll() { renderProjects(); renderContacts(); renderServices(); }

  function fillStaticForms() {
    const sf = $('#settings-form');
    Object.entries(data.settings).forEach(([k, v]) => { if (sf.elements[k]) sf.elements[k].value = v; });
    document.querySelectorAll('form[data-page]').forEach(f => {
      const slug = f.dataset.page, pg = data.pages[slug] || { title: slug === 'about' ? 'درباره ما' : 'خدمات', content: '', meta_title: '', meta_description: '' };
      ['title', 'content', 'meta_title', 'meta_description'].forEach(k => { f.elements[k].value = pg[k] || ''; });
    });
  }

  /* ---------- project edit state ---------- */
  const projectForm = $('#project-form');
  function setProjectMode(project) {
    $('#project-form-title').textContent = project ? 'ویرایش ساختمان' : 'افزودن ساختمان جدید';
    $('#project-submit').textContent = project ? 'ذخیره تغییرات' : '＋ افزودن ساختمان';
    $('#cancel-edit').hidden = !project;
    const gallery = $('#current-gallery');
    if (project && project.images.length) {
      gallery.hidden = false;
      $('#current-gallery-list').innerHTML = project.images.map(img => `<label class="current-image"><img src="${esc(img.url)}" alt=""><span><input type="checkbox" name="remove_images[]" value="${esc(img.name)}"> حذف</span></label>`).join('');
    } else { gallery.hidden = true; $('#current-gallery-list').innerHTML = ''; }
  }
  function resetProjectForm() { projectForm.reset(); projectForm.elements.id.value = '0'; setProjectMode(null); }
  function editProject(id) {
    const p = data.projects.find(x => x.id === id); if (!p) return;
    const f = projectForm.elements;
    f.id.value = String(p.id);
    ['title', 'slug', 'location', 'status', 'floors', 'area', 'description', 'amenities'].forEach(k => { f[k].value = p[k] || ''; });
    f.featured.checked = !!p.featured;
    setProjectMode(p);
    $('#building-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  $('#cancel-edit').addEventListener('click', e => { e.preventDefault(); resetProjectForm(); });

  /* ---------- events ---------- */
  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-action]'); if (!form) return;
    event.preventDefault();
    const submit = form.querySelector('button[type=submit]'); if (submit) submit.disabled = true;
    try {
      await send(new FormData(form), form.dataset.action);
      if (form.id === 'project-form') resetProjectForm();
      if (form.id === 'service-form') form.reset();
    } catch (error) { notice('error', error.message); }
    finally { if (submit) submit.disabled = false; }
  });

  document.addEventListener('click', async event => {
    const btn = event.target.closest('[data-do]'); if (!btn) return;
    const action = btn.dataset.do, id = Number(btn.dataset.id || 0);
    if (action === 'edit-project') { editProject(id); return; }
    if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;
    const fd = new FormData(); fd.set('id', String(id));
    if (btn.dataset.status) fd.set('status', btn.dataset.status);
    btn.disabled = true;
    try {
      await send(fd, action);
      if (action === 'project_delete' && Number(projectForm.elements.id.value) === id) resetProjectForm();
    } catch (error) { notice('error', error.message); btn.disabled = false; }
  });

  $('#logout-btn').addEventListener('click', async () => {
    const fd = new FormData(); fd.set('csrf', csrf); fd.set('action', 'logout');
    try { await fetch('api/auth.php', { method: 'POST', body: fd, credentials: 'same-origin' }); } catch (e) { /* redirect anyway */ }
    location.replace('login.html');
  });

  /* ---------- boot ---------- */
  request('GET').then(result => {
    csrf = result.csrf; data = result.data;
    fillStaticForms(); renderAll();
    $('#admin-loading').hidden = true; $('#admin-app').hidden = false;
  }).catch(error => { $('#admin-loading').textContent = error.message; });
})();
