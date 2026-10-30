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

  async function request(method, body, query = '') {
    const options = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (body) options.body = body;
    const response = await fetch('api/admin.php' + query, options);
    let payload = {};
    try { payload = await response.json(); } catch (e) { /* non-JSON response */ }
    if (response.status === 401) { showLogin(); throw new Error('برای ادامه باید وارد شوید.'); }
    if (!response.ok || !payload.ok) { const err = new Error(payload.message || (response.status === 413 ? 'حجم داده‌های ارسالی بیش از حد مجاز سرور است.' : 'عملیات ناموفق بود.')); err.errors = payload.errors || null; throw err; }
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
    list.innerHTML = '<div class="admin-project-list">' + data.projects.map(p => `<div class="admin-project">${p.images.length ? `<img src="${esc(p.images[0].url)}" alt="">` : '<div class="mini-placeholder">⌂</div>'}<div class="admin-project-info"><b>${esc(p.title)}</b><small>${esc(p.location)} · ${esc(p.status)} · ${p.images.length} تصویر${p.updated_at_iso ? ' · ویرایش: ' + esc(fmtDate(p.updated_at_iso)) : ''}${p.is_published ? '' : ' · <b class="draft-badge">پیش‌نویس</b>'}</small><a class="text-link" href="${esc(p.url)}" target="_blank" rel="noopener">مشاهده صفحه ↗</a></div><button type="button" class="edit-btn" data-do="edit-project" data-id="${p.id}">ویرایش</button><button type="button" class="delete-btn" data-do="project_delete" data-id="${p.id}" data-confirm="این ساختمان حذف شود؟">حذف</button></div>`).join('') + '</div>';
  }

  function renderContacts() {
    $('#contact-count').textContent = String(data.contacts_total);
    $('#contact-note').textContent = `نمایش ${data.contacts.length} از ${data.contacts_total} درخواست. اطلاعات تماس فقط برای مدیر واردشده قابل مشاهده است.`;
    $('#more-contacts').hidden = data.contacts.length >= data.contacts_total;
    const unread = $('#unread-count');
    unread.hidden = !data.unread; unread.textContent = `${data.unread} درخواست جدید`;
    const list = $('#contact-list');
    if (!data.contacts.length) { list.innerHTML = '<p class="muted">هنوز درخواستی ثبت نشده است.</p>'; return; }
    list.innerHTML = '<div class="lead-list">' + data.contacts.map(l => {
      const isNew = l.status === 'new';
      return `<article class="lead-card"><div class="lead-head"><div><b>${esc(l.name)}</b><small>${esc(fmtDate(l.created_at_iso) || l.created_at)} · ${isNew ? '<span class="lead-new">جدید</span>' : 'پیگیری‌شده'}</small></div><span class="lead-topic">${esc(l.topic)}</span></div><div class="lead-contact"><a href="tel:${esc(phoneHref(l.phone))}" dir="ltr">${esc(l.phone)}</a>${l.email ? `<a href="mailto:${esc(l.email)}">${esc(l.email)}</a>` : ''}${l.project_title ? `<span>پروژه: ${esc(l.project_title)}</span>` : ''}</div><p class="lead-message">${nl2br(l.message)}</p><div class="lead-actions"><button type="button" class="edit-btn" data-do="contact_status" data-id="${l.id}" data-status="${isNew ? 'read' : 'new'}">${isNew ? 'علامت‌گذاری به‌عنوان پیگیری‌شده' : 'بازگردانی به جدید'}</button><button type="button" class="delete-btn" data-do="contact_delete" data-id="${l.id}" data-confirm="این درخواست حذف شود؟">حذف درخواست</button></div></article>`;
    }).join('') + '</div>';
  }

  function renderServices() {
    $('#service-list').innerHTML = data.services.map(s => `<div><span>${esc(s.icon)} ${esc(s.title)}</span><button type="button" class="delete-btn" data-do="service_delete" data-id="${s.id}" data-confirm="این خدمت حذف شود؟">حذف</button></div>`).join('');
  }

  let contactsLimit = 100;
  const dateFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Tehran' });
  const fmtDate = iso => { const d = new Date(iso); return iso && !isNaN(d) ? dateFmt.format(d) : ''; };
  function renderSecurity() {
    const lv = { danger: '⛔', warn: '⚠️', info: 'ℹ️' };
    $('#security-warnings').innerHTML = data.warnings.length
      ? data.warnings.map(w => `<li class="warn-${esc(w.level)}"><span aria-hidden="true">${lv[w.level] || ''}</span> ${esc(w.text)}</li>`).join('')
      : '<li class="warn-ok"><span aria-hidden="true">✅</span> هشدار امنیتی فعالی وجود ندارد.</li>';
    $('#maintenance').hidden = !data.images_pending;
    $('#maint-text').textContent = `${data.images_pending} تصویر هنوز فشرده نشده و بدون بندانگشتی است (بارگذاری سایت را کند می‌کند).`;
    $('#admin-username').textContent = data.username || '';
    $('#audit-list').innerHTML = data.audit.length ? data.audit.map(a => `<li><b>${esc(auditLabel(a.action))}</b> <small>${esc(a.detail)}</small><time>${esc(fmtDate(a.at_iso))}</time></li>`).join('') : '<li class="muted">هنوز فعالیتی ثبت نشده است.</li>';
  }
  const AUDIT = { login: 'ورود', login_failed: 'ورود ناموفق', logout: 'خروج', settings: 'ویرایش اطلاعات شرکت', project_save: 'ذخیره ساختمان', project_delete: 'حذف ساختمان', page_save: 'ویرایش صفحه', service_save: 'ذخیره خدمت', service_delete: 'حذف خدمت', contact_delete: 'حذف درخواست', change_password: 'تغییر رمز عبور', images_optimize: 'بهینه‌سازی تصاویر' };
  const auditLabel = a => AUDIT[a] || a;
  function renderAll() { renderProjects(); renderContacts(); renderServices(); renderSecurity(); }

  function fillStaticForms() {
    const sf = $('#settings-form');
    Object.entries(data.settings).forEach(([k, v]) => { if (sf.elements[k]) sf.elements[k].value = v; });
    document.querySelectorAll('form[data-page]').forEach(f => {
      const slug = f.dataset.page, pg = data.pages[slug] || { title: { about: 'درباره ما', services: 'خدمات', privacy: 'حریم خصوصی' }[slug] || slug, content: '', meta_title: '', meta_description: '' };
      ['title', 'content', 'meta_title', 'meta_description'].forEach(k => { f.elements[k].value = pg[k] || ''; });
    });
  }

  /* ---------- building form: helpers ---------- */
  const projectForm = $('#project-form');
  const MAX_IMAGES = 12, MAX_IMAGE_BYTES = 5 * 1024 * 1024, IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  const normDigits = v => String(v ?? '')
    .replace(/[۰-۹]/g, d => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[٠-٩]/g, d => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .replace(/٫/g, '.').replace(/[٬،,\s\u200c]/g, '');

  /* ---------- building form: validation (mirrors includes/functions.php; the server stays authoritative) ---------- */
  function validateField(el) {
    const label = el.dataset.label || el.name, type = el.dataset.type, raw = String(el.value ?? '').trim();
    if (raw === '') return el.required ? `${label} الزامی است.` : '';
    if (type === 'text' || type === 'textarea' || type === 'slug') {
      const max = Number(el.getAttribute('maxlength') || 0);
      return max && [...raw].length > max ? `${label} نباید بیشتر از ${max} نویسه باشد.` : '';
    }
    if (type === 'enum') return [...el.options].some(o => o.value === raw) ? '' : `${label}: گزینه انتخاب‌شده معتبر نیست.`;
    if (el.dataset.legacy && raw === (el.dataset.orig || '')) return '';
    const n = normDigits(raw);
    if (type === 'digits') return new RegExp(`^\\d{${el.dataset.digits}}$`).test(n) ? '' : `${label} باید دقیقاً ${el.dataset.digits} رقم باشد.`;
    const re = type === 'int' ? /^\d{1,9}$/ : type === 'decimal' ? /^\d{1,9}(\.\d{1,2})?$/ : /^-?\d{1,3}(\.\d{1,8})?$/;
    if (!re.test(n)) return label + (type === 'int' ? ' باید عدد صحیح (بدون اعشار) باشد.' : ' باید عدد معتبر باشد.');
    const num = Number(n);
    if (el.dataset.lo !== undefined && num < Number(el.dataset.lo)) return `${label} نباید کمتر از ${el.dataset.lo} باشد.`;
    if (el.dataset.hi !== undefined && num > Number(el.dataset.hi)) return `${label} نباید بیشتر از ${el.dataset.hi} باشد.`;
    return '';
  }
  const numVal = name => { const v = normDigits(projectForm.elements[name].value); return v === '' ? null : Number(v); };

  function validateProject() {
    const errors = {};
    projectForm.querySelectorAll('[data-type]').forEach(el => {
      if (!el.name || el.type === 'checkbox') return;
      const msg = validateField(el); if (msg) errors[el.name] = msg;
    });
    const pair = (a, b, bad, key, msg) => { if (!errors[a] && !errors[b] && bad(numVal(a), numVal(b))) errors[key] = msg; };
    pair('latitude', 'longitude', (a, b) => (a === null) !== (b === null), numVal('latitude') === null ? 'latitude' : 'longitude', 'عرض و طول جغرافیایی باید با هم وارد شوند.');
    pair('unit_area_min', 'unit_area_max', (a, b) => a !== null && b !== null && a > b, 'unit_area_max', 'بیشترین متراژ واحد نباید کمتر از کمترین متراژ باشد.');
    pair('start_year', 'completion_year', (a, b) => a !== null && b !== null && b < a, 'completion_year', 'سال تکمیل نمی‌تواند قبل از سال شروع باشد.');
    pair('units_count', 'units_per_floor', (a, b) => a !== null && b !== null && b > a, 'units_per_floor', 'تعداد واحد در هر طبقه نمی‌تواند از تعداد کل واحدها بیشتر باشد.');
    const imgMsg = imageProblems(); if (imgMsg) errors.images = imgMsg;
    return errors;
  }

  function clearErrors() {
    projectForm.querySelectorAll('.field-error').forEach(s => { s.hidden = true; s.textContent = ''; });
    projectForm.querySelectorAll('.has-error').forEach(x => x.classList.remove('has-error'));
    const sum = $('#form-errors'); sum.hidden = true; sum.textContent = '';
  }
  function showErrors(errors) {
    clearErrors();
    const keys = Object.keys(errors); if (!keys.length) return;
    let first = null;
    keys.forEach(k => {
      const slot = projectForm.querySelector(`[data-error-for="${k}"]`);
      if (slot) { slot.textContent = errors[k]; slot.hidden = false; const wrap = slot.closest('[data-field]'); if (wrap) wrap.classList.add('has-error'); }
      const field = projectForm.elements[k] || projectForm.elements[k + '[]'];
      const target = field && (field.nodeType === 1 ? field : field[0]);
      if (target && !first) first = target;
    });
    const sum = $('#form-errors');
    sum.textContent = `ذخیره انجام نشد؛ ${keys.length} مورد نیاز به اصلاح دارد: ` + keys.map(k => errors[k]).slice(0, 4).join(' ') + (keys.length > 4 ? ' …' : '');
    sum.hidden = false;
    (first || sum).scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (first && first.focus) first.focus({ preventScroll: true });
  }
  projectForm.addEventListener('input', e => {
    const wrap = e.target.closest && e.target.closest('[data-field]');
    if (wrap && wrap.classList.contains('has-error')) { wrap.classList.remove('has-error'); const s = wrap.querySelector('.field-error'); if (s) { s.hidden = true; s.textContent = ''; } }
  });

  /* ---------- building form: gallery ---------- */
  const keptTiles = () => [...projectForm.querySelectorAll('#current-gallery-list .img-tile')].filter(t => !t.querySelector('[name="remove_images[]"]').checked);
  function imageProblems() {
    const files = [...($('#new-images').files || [])];
    for (const f of files) {
      if (!IMAGE_TYPES.includes(f.type)) return `فایل «${f.name}» مجاز نیست؛ فقط JPG، PNG یا WEBP قابل بارگذاری است.`;
      if (f.size > MAX_IMAGE_BYTES) return `حجم «${f.name}» بیشتر از ۵ مگابایت است.`;
    }
    for (const inp of projectForm.querySelectorAll('input[name^="replace_images["]')) {
      const f = inp.files && inp.files[0]; if (!f) continue;
      if (!IMAGE_TYPES.includes(f.type)) return `فایل جایگزین «${f.name}» مجاز نیست؛ فقط JPG، PNG یا WEBP.`;
      if (f.size > MAX_IMAGE_BYTES) return `حجم فایل جایگزین «${f.name}» بیشتر از ۵ مگابایت است.`;
    }
    if (keptTiles().length + files.length > MAX_IMAGES) return `مجموع تصاویر هر ساختمان حداکثر ${MAX_IMAGES} عدد است (اکنون ${keptTiles().length + files.length}).`;
    return '';
  }
  function updateCoverBadges() {
    const tiles = [...projectForm.querySelectorAll('#current-gallery-list .img-tile')];
    let coverSet = false;
    tiles.forEach(t => {
      const removed = t.querySelector('[name="remove_images[]"]').checked;
      t.classList.toggle('is-removed', removed);
      const badge = t.querySelector('.cover-badge');
      const isCover = !removed && !coverSet; if (isCover) coverSet = true;
      badge.hidden = !isCover;
    });
  }
  function renderCurrentGallery(project) {
    const box = $('#current-gallery'), list = $('#current-gallery-list');
    if (!project || !project.images.length) { box.hidden = true; list.innerHTML = ''; return; }
    box.hidden = false;
    list.innerHTML = project.images.map(img => {
      const hex = String(img.name).split('.')[0];
      return `<div class="img-tile" data-name="${esc(img.name)}"><input type="hidden" name="image_order[]" value="${esc(img.name)}"><span class="cover-badge" hidden>شاخص</span><img src="${esc(img.url)}" alt="" loading="lazy"><div class="img-tools"><button type="button" data-img="prev" title="جابه‌جایی به ابتدا">→ جلوتر</button><button type="button" data-img="next" title="جابه‌جایی به انتها">عقب‌تر ←</button></div><label class="img-alt">توضیح تصویر (alt)<input type="text" name="image_alt[${esc(hex)}]" value="${esc(img.alt || '')}" maxlength="150" placeholder="مثلاً نمای شمالی ساختمان"></label><label class="img-replace">جایگزینی<input type="file" name="replace_images[${esc(hex)}]" accept="image/jpeg,image/png,image/webp"></label><label class="img-remove"><input type="checkbox" name="remove_images[]" value="${esc(img.name)}"> حذف</label></div>`;
    }).join('');
    updateCoverBadges();
  }
  $('#current-gallery-list').addEventListener('click', e => {
    const b = e.target.closest('[data-img]'); if (!b) return;
    const tile = b.closest('.img-tile'), list = tile.parentElement;
    if (b.dataset.img === 'prev' && tile.previousElementSibling) list.insertBefore(tile, tile.previousElementSibling);
    if (b.dataset.img === 'next' && tile.nextElementSibling) list.insertBefore(tile.nextElementSibling, tile);
    updateCoverBadges();
  });
  $('#current-gallery-list').addEventListener('change', e => { if (e.target.name === 'remove_images[]') updateCoverBadges(); });
  let previewUrls = [];
  function clearPreviews() { previewUrls.forEach(u => URL.revokeObjectURL(u)); previewUrls = []; $('#new-previews').innerHTML = ''; }
  $('#new-images').addEventListener('change', e => {
    clearPreviews();
    [...e.target.files].forEach(f => {
      if (!IMAGE_TYPES.includes(f.type)) return;
      const u = URL.createObjectURL(f); previewUrls.push(u);
      const fig = document.createElement('figure'); const im = document.createElement('img'); im.src = u; im.alt = '';
      const cap = document.createElement('figcaption'); cap.textContent = f.name; fig.append(im, cap); $('#new-previews').append(fig);
    });
    const msg = imageProblems(); const slot = projectForm.querySelector('[data-error-for="images"]');
    slot.textContent = msg; slot.hidden = !msg;
  });

  /* ---------- building form: edit state ---------- */
  function setProjectMode(project) {
    $('#project-form-title').textContent = project ? `ویرایش ساختمان: ${project.title}` : 'افزودن ساختمان جدید';
    $('#project-submit').textContent = project ? 'ذخیره تغییرات' : '＋ افزودن ساختمان';
    $('#cancel-edit').hidden = !project;
  }
  function resetProjectForm() {
    projectForm.reset(); projectForm.elements.id.value = '0';
    projectForm.querySelectorAll('[data-orig]').forEach(el => delete el.dataset.orig);
    clearErrors(); clearPreviews(); renderCurrentGallery(null); setProjectMode(null);
  }
  function fillProjectForm(p) {
    [...projectForm.elements].forEach(el => {
      const name = el.name; if (!name || el.type === 'file') return;
      if (name.endsWith('[]')) { const key = name.slice(0, -2); if (Array.isArray(p[key]) && el.type === 'checkbox') el.checked = p[key].includes(el.value); return; }
      if (!(name in p)) return;
      if (el.type === 'checkbox') el.checked = !!p[name]; else el.value = p[name] ?? '';
    });
    projectForm.querySelectorAll('[data-legacy]').forEach(el => { el.dataset.orig = el.value; });
  }
  function editProject(id) {
    const p = data.projects.find(x => x.id === id); if (!p) return;
    resetProjectForm(); fillProjectForm(p); renderCurrentGallery(p); setProjectMode(p);
    $('#building-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  $('#optimize-images').addEventListener('click', async e => {
    const b = e.currentTarget; b.disabled = true;
    try { await send(new FormData(), 'images_optimize'); } catch (error) { notice('error', error.message); } finally { b.disabled = false; }
  });
  $('#more-contacts').addEventListener('click', async e => {
    const b = e.currentTarget; b.disabled = true;
    try { contactsLimit += 200; const r = await request('GET', null, '?contacts_limit=' + contactsLimit); csrf = r.csrf; data = r.data; renderContacts(); } catch (error) { notice('error', error.message); } finally { b.disabled = false; }
  });
  $('#cancel-edit').addEventListener('click', e => { e.preventDefault(); resetProjectForm(); });

  /* ---------- events ---------- */
  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-action]'); if (!form) return;
    event.preventDefault();
    const formId = form.getAttribute('id'); // NOTE: form.id is shadowed by the hidden <input name="id">
    const isProject = formId === 'project-form';
    if (isProject) { const errors = validateProject(); if (Object.keys(errors).length) { showErrors(errors); return; } clearErrors(); }
    const submit = form.querySelector('button[type=submit]'); if (submit) submit.disabled = true;
    try {
      await send(new FormData(form), form.dataset.action);
      if (isProject) resetProjectForm();
      if (formId === 'service-form' || formId === 'password-form') form.reset();
    } catch (error) {
      if (isProject && error.errors) showErrors(error.errors); else notice('error', error.message);
    }
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
    csrf = ''; data = null; await showLoginFromServer();
  });

  /* ---------- login / boot ---------- */
  const loginForm = $('#login-form');
  function showLogin() {
    $('#admin-loading').hidden = true; $('#admin-app').hidden = true; $('#admin-top').hidden = true;
    $('#login-view').hidden = false;
  }
  async function showLoginFromServer() {
    $('#admin-notice').hidden = true;
    try {
      const r = await fetch('api/auth.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (d.authenticated) { await loadPanel(); return; }
      csrf = d.csrf || '';
      showLogin();
      $('#not-configured').hidden = !!d.configured;
      loginForm.hidden = !d.configured;
    } catch (e) {
      showLogin();
      notice('error', 'ارتباط با سرور برقرار نشد. مطمئن شوید سایت روی سرور PHP (مثلاً XAMPP) اجرا می‌شود، نه با باز کردن مستقیم فایل.');
    }
  }
  async function loadPanel() {
    $('#login-view').hidden = true; $('#admin-loading').hidden = false; $('#admin-loading').textContent = 'در حال بارگذاری پنل…';
    try {
      const result = await request('GET');
      csrf = result.csrf; data = result.data;
      fillStaticForms(); renderAll();
      $('#admin-loading').hidden = true; $('#admin-top').hidden = false; $('#admin-app').hidden = false;
    } catch (error) {
      if ($('#login-view').hidden) $('#admin-loading').textContent = error.message;
    }
  }
  loginForm.addEventListener('submit', async event => {
    event.preventDefault();
    $('#admin-notice').hidden = true;
    const fd = new FormData(loginForm); fd.set('csrf', csrf); fd.set('action', 'login');
    const submit = loginForm.querySelector('button'); submit.disabled = true;
    try {
      const r = await fetch('api/auth.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (d.ok) { csrf = d.csrf || csrf; loginForm.reset(); await loadPanel(); }
      else notice('error', d.message || 'ورود ناموفق بود.');
    } catch (e) { notice('error', 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.'); }
    finally { submit.disabled = false; }
  });
  showLoginFromServer();
})();
