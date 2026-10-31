'use strict';
(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const nl2br = v => esc(v).replace(/\n/g, '<br>');
  const phoneHref = v => String(v || '').replace(/[^0-9+]/g, '');
  const normDigits = v => String(v ?? '').replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/[٫]/g, '.').replace(/[٬,،]/g, '');
  const MAX_IMAGES = 12, MAX_BYTES = 5 * 1024 * 1024, TYPES = ['image/jpeg', 'image/png', 'image/webp'];
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
    if (!response.ok || !payload.ok) {
      const err = new Error(payload.message || (response.status === 413 ? 'حجم فایل‌های ارسالی بیش از حد مجاز است.' : 'عملیات ناموفق بود.'));
      err.errors = payload.errors || null;
      throw err;
    }
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

  /* ---------- lists ---------- */
  function renderProjects() {
    $('#project-count').textContent = String(data.projects.length);
    const list = $('#project-list');
    if (!data.projects.length) { list.innerHTML = '<p class="muted">هنوز ساختمانی ثبت نشده است.</p>'; return; }
    list.innerHTML = '<div class="admin-project-list">' + data.projects.map(p => {
      const bits = [p.location, p.status, p.floors ? p.floors + ' طبقه' : '', p.units_count ? p.units_count + ' واحد' : '', p.images.length + ' تصویر'].filter(Boolean).join(' · ');
      return `<div class="admin-project">${p.images.length ? `<img src="${esc(p.images[0].url)}" alt="">` : '<div class="mini-placeholder">⌂</div>'}<div class="admin-project-info"><b>${esc(p.title)}</b><small>${esc(bits)}</small><a class="text-link" href="${esc(p.url)}" target="_blank" rel="noopener">مشاهده صفحه عمومی ↗</a></div><button type="button" class="edit-btn" data-do="edit-project" data-id="${p.id}">ویرایش</button><button type="button" class="delete-btn" data-do="project_delete" data-id="${p.id}" data-confirm="این ساختمان و همه تصاویر آن حذف شود؟">حذف</button></div>`;
    }).join('') + '</div>';
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
  function renderFaqs() {
    $('#faq-count').textContent = String(data.faqs.length);
    const list = $('#faq-admin-list');
    if (!data.faqs.length) { list.innerHTML = '<p class="muted">هنوز پرسشی ثبت نشده است؛ بخش پرسش‌های متداول در صفحه اصلی نمایش داده نمی‌شود.</p>'; return; }
    list.innerHTML = data.faqs.map(f => `<div class="faq-admin-item${f.is_active ? '' : ' off'}"><div class="faq-admin-text"><b>${esc(f.question)}</b><small>${esc(f.answer.length > 120 ? f.answer.slice(0, 120) + '…' : f.answer)}</small><span class="faq-meta">ترتیب: ${f.sort_order} · ${f.is_active ? 'نمایش داده می‌شود' : 'مخفی'}</span></div><div class="faq-admin-actions"><button type="button" class="edit-btn" data-do="edit-faq" data-id="${f.id}">ویرایش</button><button type="button" class="edit-btn" data-do="faq_toggle" data-id="${f.id}">${f.is_active ? 'مخفی‌کردن' : 'نمایش'}</button><button type="button" class="delete-btn" data-do="faq_delete" data-id="${f.id}" data-confirm="این پرسش حذف شود؟">حذف</button></div></div>`).join('');
  }
  function renderAll() { renderProjects(); renderContacts(); renderServices(); renderFaqs(); }

  /* ---------- FAQ form ---------- */
  const faqForm = $('#faq-form');
  let editingFaq = null;
  function setFaqMode(f) {
    editingFaq = f || null;
    $('#faq-submit').textContent = f ? 'ذخیره تغییرات پرسش' : '＋ افزودن پرسش';
    $('#faq-cancel').hidden = !f;
  }
  function resetFaqForm() { faqForm.reset(); clearErrors(faqForm); faqForm.elements.id.value = '0'; setFaqMode(null); }
  function editFaq(id) {
    const f = data.faqs.find(x => x.id === id); if (!f) return;
    clearErrors(faqForm);
    faqForm.elements.id.value = String(f.id); faqForm.elements.question.value = f.question; faqForm.elements.answer.value = f.answer;
    faqForm.elements.sort_order.value = String(f.sort_order); faqForm.elements.is_active.checked = f.is_active;
    setFaqMode(f); faqForm.scrollIntoView({ behavior: 'smooth', block: 'center' }); faqForm.elements.question.focus({ preventScroll: true });
  }
  $('#faq-cancel').addEventListener('click', e => { e.preventDefault(); resetFaqForm(); });
  faqForm.addEventListener('input', e => { if (e.target.name) clearFieldError(e.target.name, faqForm); });

  function fillStaticForms() {
    const sf = $('#settings-form');
    Object.entries(data.settings).forEach(([k, v]) => { if (sf.elements[k]) sf.elements[k].value = v; });
    document.querySelectorAll('form[data-page]').forEach(f => {
      const slug = f.dataset.page, pg = data.pages[slug] || { title: slug === 'about' ? 'درباره ما' : 'خدمات', content: '', meta_title: '', meta_description: '' };
      ['title', 'content', 'meta_title', 'meta_description'].forEach(k => { f.elements[k].value = pg[k] || ''; });
    });
  }

  /* ---------- building form ---------- */
  const projectForm = $('#project-form');
  let editing = null;           // پروژه‌ای که در حال ویرایش است (یا null برای ساختمان جدید)
  let selectedFiles = [];       // تصاویر جدید انتخاب‌شده
  let previewUrls = [];

  function buildOptions() {
    const o = data.options;
    projectForm.querySelectorAll('select[data-options]').forEach(sel => {
      const list = o[sel.dataset.options] || [];
      const blank = sel.name === 'status' ? '' : '<option value="">— انتخاب نشده —</option>';
      sel.innerHTML = blank + list.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
    });
    $('#facility-list').innerHTML = Object.entries(o.facilities).map(([k, label]) => `<label class="facility-item"><input type="checkbox" name="facilities[]" value="${esc(k)}"> <span>${esc(label)}</span></label>`).join('');
  }

  function setSelect(el, value) {
    if (value && ![...el.options].some(o => o.value === value)) el.add(new Option(value + ' (مقدار قدیمی)', value));
    el.value = value || (el.name === 'status' ? el.options[0].value : '');
  }

  function clearErrors(scope = projectForm) {
    scope.querySelectorAll('.field-error').forEach(n => n.remove());
    scope.querySelectorAll('.invalid').forEach(n => n.classList.remove('invalid'));
  }
  function clearFieldError(name, form = projectForm) {
    const el = form.elements[name];
    const holder = el && el.closest ? el.closest('label') : null;
    if (holder) { holder.classList.remove('invalid'); holder.querySelectorAll('.field-error').forEach(n => n.remove()); }
    const slot = form.querySelector(`[data-slot="${name}"]`);
    if (slot) slot.innerHTML = '';
  }
  function showErrors(errors, form = projectForm) {
    let first = null;
    Object.entries(errors).forEach(([name, msg]) => {
      const slot = form.querySelector(`[data-slot="${name}"]`);
      const el = form.elements[name];
      if (slot) { slot.innerHTML = `<small class="field-error">${esc(msg)}</small>`; first = first || slot; return; }
      const holder = el && el.closest ? el.closest('label') : null;
      if (!holder) return;
      holder.classList.add('invalid');
      holder.querySelectorAll('.field-error').forEach(n => n.remove());
      holder.insertAdjacentHTML('beforeend', `<small class="field-error">${esc(msg)}</small>`);
      first = first || el;
    });
    if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (first.focus && first.tagName !== 'SPAN') first.focus({ preventScroll: true }); }
  }

  function clientValidate() {
    const errors = {};
    const f = projectForm.elements;
    if (String(f.title.value).trim().length < 2) errors.title = 'نام ساختمان الزامی است (حداقل ۲ نویسه).';
    if (!f.status.value) errors.status = 'وضعیت پروژه را انتخاب کنید.';
    projectForm.querySelectorAll('input[inputmode="numeric"]').forEach(el => {
      const v = normDigits(el.value).trim();
      if (v !== '' && !/^\d+$/.test(v)) errors[el.name] = 'فقط عدد صحیح وارد کنید.';
    });
    projectForm.querySelectorAll('input[inputmode="decimal"]').forEach(el => {
      const v = normDigits(el.value).trim();
      if (v !== '' && !/^-?\d+(\.\d+)?$/.test(v)) errors[el.name] = 'یک عدد معتبر وارد کنید.';
    });
    const kept = currentKeptCount();
    if (kept + selectedFiles.length > MAX_IMAGES) errors.images = `هر ساختمان حداکثر ${MAX_IMAGES} تصویر می‌تواند داشته باشد (اکنون ${kept + selectedFiles.length}).`;
    return errors;
  }

  function currentKeptCount() {
    if (!editing) return 0;
    return editing.images.length - projectForm.querySelectorAll('input[name="remove_images[]"]:checked').length;
  }

  function renderCurrentGallery() {
    const wrap = $('#current-gallery'), list = $('#current-gallery-list');
    if (!editing || !editing.images.length) { wrap.hidden = true; list.innerHTML = ''; return; }
    wrap.hidden = false;
    list.innerHTML = editing.images.map((img, i) => `<div class="image-tile"><img src="${esc(img.url)}" alt=""><label class="tile-opt"><input type="radio" name="cover_image" value="${esc(img.name)}" ${i === 0 ? 'checked' : ''}> تصویر اصلی</label><label class="tile-opt danger"><input type="checkbox" name="remove_images[]" value="${esc(img.name)}"> حذف</label></div>`).join('');
  }

  function revokePreviews() { previewUrls.forEach(u => URL.revokeObjectURL(u)); previewUrls = []; }
  function renderNewImages() {
    revokePreviews();
    $('#new-images').innerHTML = selectedFiles.map((file, i) => { const u = URL.createObjectURL(file); previewUrls.push(u); return `<div class="image-tile new"><img src="${u}" alt=""><span class="tile-name">${esc(file.name)} (${(file.size / 1048576).toFixed(1)} مگابایت)</span><button type="button" class="tile-remove" data-do="remove-new" data-index="${i}">✕ حذف از انتخاب</button></div>`; }).join('');
  }

  $('#image-input').addEventListener('change', event => {
    clearFieldError('images');
    const problems = [];
    [...event.target.files].forEach(file => {
      if (!TYPES.includes(file.type)) { problems.push(`«${file.name}»: فقط JPG، PNG یا WEBP مجاز است.`); return; }
      if (file.size > MAX_BYTES) { problems.push(`«${file.name}»: حجم بیش از ۵ مگابایت است.`); return; }
      if (selectedFiles.some(x => x.name === file.name && x.size === file.size && x.lastModified === file.lastModified)) return;
      if (currentKeptCount() + selectedFiles.length >= MAX_IMAGES) { problems.push(`حداکثر ${MAX_IMAGES} تصویر مجاز است؛ «${file.name}» اضافه نشد.`); return; }
      selectedFiles.push(file);
    });
    event.target.value = '';
    renderNewImages();
    if (problems.length) showErrors({ images: problems.join(' ') });
  });
  projectForm.addEventListener('input', e => { if (e.target.name) clearFieldError(e.target.name.replace('[]', '')); });
  projectForm.addEventListener('change', e => { if (e.target.name) clearFieldError(e.target.name.replace('[]', '')); });

  function setMode(project) {
    editing = project || null;
    $('#project-form-title').textContent = project ? `ویرایش ساختمان: ${project.title}` : 'افزودن ساختمان جدید';
    $('#project-submit').textContent = project ? 'ذخیره تغییرات' : '＋ افزودن ساختمان';
    $('#cancel-edit').hidden = !project;
    renderCurrentGallery();
  }

  function resetProjectForm() {
    projectForm.reset(); clearErrors();
    projectForm.elements.id.value = '0';
    selectedFiles = []; renderNewImages();
    setMode(null);
  }

  function editProject(id, scroll = true) {
    const p = data.projects.find(x => x.id === id); if (!p) return;
    projectForm.reset(); clearErrors(); selectedFiles = []; renderNewImages();
    [...projectForm.elements].forEach(el => {
      if (!el.name || ['id', 'images', 'cover_image', 'remove_images[]', 'facilities[]'].includes(el.name) || !(el.name in p)) return;
      if (el.type === 'checkbox') el.checked = !!p[el.name];
      else if (el.tagName === 'SELECT') setSelect(el, p[el.name]);
      else el.value = p[el.name] ?? '';
    });
    projectForm.elements.id.value = String(p.id);
    projectForm.querySelectorAll('input[name="facilities[]"]').forEach(c => { c.checked = p.facilities.includes(c.value); });
    setMode(p);
    if (scroll) $('#building-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  $('#cancel-edit').addEventListener('click', e => { e.preventDefault(); resetProjectForm(); });

  async function submitProject(submitBtn) {
    clearErrors();
    const errors = clientValidate();
    if (Object.keys(errors).length) { showErrors(errors); notice('error', 'لطفاً خطاهای مشخص‌شده در فرم را برطرف کنید.'); return; }
    const fd = new FormData(projectForm);
    selectedFiles.forEach(file => fd.append('images[]', file));
    submitBtn.disabled = true;
    try {
      const result = await send(fd, 'project_save');
      selectedFiles = []; renderNewImages();
      editProject(result.saved_id, false);   // رکورد ذخیره‌شده از روی داده‌های سرور دوباره در فرم بارگذاری می‌شود
    } catch (error) {
      notice('error', error.message);
      if (error.errors) showErrors(error.errors);
    } finally { submitBtn.disabled = false; }
  }

  /* ---------- events ---------- */
  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-action]'); if (!form) return;
    event.preventDefault();
    const submit = form.querySelector('button[type=submit]');
    if (form === projectForm) { await submitProject(submit); return; }
    if (form === faqForm) {
      clearErrors(faqForm);
      const ce = {};
      if (faqForm.elements.question.value.trim().length < 5) ce.question = 'پرسش باید حداقل ۵ نویسه باشد.';
      if (faqForm.elements.answer.value.trim().length < 5) ce.answer = 'پاسخ باید حداقل ۵ نویسه باشد.';
      if (!/^-?\d{0,4}$/.test(normDigits(faqForm.elements.sort_order.value).trim())) ce.sort_order = 'ترتیب نمایش باید عدد صحیح باشد.';
      if (Object.keys(ce).length) { showErrors(ce, faqForm); notice('error', 'لطفاً خطاهای مشخص‌شده در فرم را برطرف کنید.'); return; }
    }
    if (submit) submit.disabled = true;
    try {
      await send(new FormData(form), form.dataset.action);
      if (form.id === 'service-form') form.reset();
      if (form === faqForm) resetFaqForm();
    } catch (error) { notice('error', error.message); if (error.errors) showErrors(error.errors, form); }
    finally { if (submit) submit.disabled = false; }
  });

  document.addEventListener('click', async event => {
    const btn = event.target.closest('[data-do]'); if (!btn) return;
    const action = btn.dataset.do, id = Number(btn.dataset.id || 0);
    if (action === 'edit-project') { editProject(id); return; }
    if (action === 'edit-faq') { editFaq(id); return; }
    if (action === 'remove-new') { selectedFiles.splice(Number(btn.dataset.index), 1); renderNewImages(); clearFieldError('images'); return; }
    if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;
    const fd = new FormData(); fd.set('id', String(id));
    if (btn.dataset.status) fd.set('status', btn.dataset.status);
    btn.disabled = true;
    try {
      await send(fd, action);
      if (action === 'project_delete' && editing && editing.id === id) resetProjectForm();
      if (action === 'faq_delete' && editingFaq && editingFaq.id === id) resetFaqForm();
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
    buildOptions(); fillStaticForms(); renderAll();
    $('#admin-loading').hidden = true; $('#admin-app').hidden = false;
  }).catch(error => { $('#admin-loading').textContent = error.message; });
})();
