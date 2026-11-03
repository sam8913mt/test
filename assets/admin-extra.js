'use strict';
/* ماژول‌های تکمیلی پنل مدیریت: ناوبری زبانه‌ای، داشبورد، واحدها، نظرات، مقالات و «درباره ما».
   هسته‌ی مشترک (احراز هویت، درخواست‌ها، خطاها) از admin.js می‌آید. */
(() => {
  const C = window.AdminCore; if (!C) return;
  const { $, esc, nl2br, notice, send, showErrors, clearErrors, clearFieldError, getData, normDigits, MAX_BYTES, TYPES } = C;
  const nf = new Intl.NumberFormat('fa-IR');
  const n = v => nf.format(Number(v) || 0);
  const parseDate = s => { const d = new Date(String(s || '').replace(' ', 'T') + (String(s || '').includes('T') ? '' : 'Z')); return isNaN(d) ? null : d; };
  const fmtDate = s => { const d = parseDate(s); return d ? d.toLocaleDateString('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }) : ''; };
  const fmtDay = s => { const d = new Date(s + 'T12:00:00'); return isNaN(d) ? s : d.toLocaleDateString('fa-IR', { month: 'long', day: 'numeric' }); };
  // گروه‌بندی ماهانه بر اساس ماه میلادی است؛ برچسب هم میلادی نوشته می‌شود تا با ماه شمسی اشتباه نشود.
  const fmtMonth = m => { const d = new Date(m + '-15T12:00:00'); return isNaN(d) ? m : d.toLocaleDateString('fa-IR-u-ca-gregory', { year: 'numeric', month: 'long' }); };
  const stars = r => '★'.repeat(r) + '☆'.repeat(5 - r);
  const optionHtml = (src, blank) => (blank ? '<option value="">— انتخاب نشده —</option>' : '') + (Array.isArray(src) ? src.map(v => [v, v]) : Object.entries(src)).map(([k, l]) => `<option value="${esc(k)}">${esc(l)}</option>`).join('');

  /* ===== ناوبری زبانه‌ای ===== */
  const TABS = ['dashboard', 'buildings', 'units', 'reviews', 'articles', 'about', 'requests', 'settings'];
  function activate(name, updateHash = true) {
    if (!TABS.includes(name)) name = 'dashboard';
    document.querySelectorAll('.admin-tab').forEach(b => { const on = b.dataset.tab === name; b.classList.toggle('active', on); b.setAttribute('aria-current', on ? 'page' : 'false'); });
    document.querySelectorAll('.tab-panel').forEach(p => { p.hidden = p.dataset.panel !== name; });
    if (updateHash) history.replaceState(null, '', '#' + name);
    window.scrollTo({ top: 0 });
    // داده‌ی قدیمی‌تر از ۳۰ ثانیه هنگام تعویض زبانه تازه می‌شود (نظر/درخواست جدید ممکن است در این فاصله رسیده باشد).
    if (tabsReady && Date.now() - C.lastRefresh() > 30000) C.refresh().catch(() => {});
  }
  C.openTab = activate;
  document.querySelectorAll('.admin-tab').forEach(b => b.addEventListener('click', () => activate(b.dataset.tab)));
  let tabsReady = false;
  // دکمه‌های بازگشت/جلوی مرورگر و تغییر دستی #آدرس هم باید زبانه را عوض کنند.
  document.addEventListener('visibilitychange', () => { if (!document.hidden && tabsReady && Date.now() - C.lastRefresh() > 30000) C.refresh().catch(() => {}); });
  window.addEventListener('hashchange', () => { const h = location.hash.slice(1); if (tabsReady && TABS.includes(h)) activate(h, false); });

  function badges(d) {
    const set = (k, v) => { const el = document.querySelector(`[data-badge="${k}"]`); if (el) { el.hidden = !v; el.textContent = n(v); } };
    set('reviews', d.stats.reviews.pending); set('requests', d.unread); set('articles', d.stats.articles.draft);
  }

  /* ===== انتخاب فایل و پیش‌نمایش ===== */
  function makePicker(input, preview, { max, form, slot, kept = () => 0 }) {
    let files = [], urls = [];
    const render = () => {
      urls.forEach(u => URL.revokeObjectURL(u)); urls = [];
      preview.innerHTML = files.map((f, i) => { const u = URL.createObjectURL(f); urls.push(u); return `<div class="image-tile new"><img src="${u}" alt=""><span class="tile-name">${esc(f.name)} (${(f.size / 1048576).toFixed(1)} مگابایت)</span><button type="button" class="tile-remove" data-xpick="${i}">✕ حذف از انتخاب</button></div>`; }).join('');
    };
    input.addEventListener('change', () => {
      clearFieldError(slot, form);
      const problems = [];
      [...input.files].forEach(f => {
        if (!TYPES.includes(f.type)) { problems.push(`«${f.name}»: فقط JPG، PNG یا WEBP مجاز است.`); return; }
        if (f.size > MAX_BYTES) { problems.push(`«${f.name}»: حجم بیش از ۵ مگابایت است.`); return; }
        if (max === 1) { files = [f]; return; }
        if (files.some(x => x.name === f.name && x.size === f.size && x.lastModified === f.lastModified)) return;
        if (kept() + files.length >= max) { problems.push(`حداکثر ${max} تصویر مجاز است؛ «${f.name}» اضافه نشد.`); return; }
        files.push(f);
      });
      input.value = ''; render();
      if (problems.length) showErrors({ [slot]: problems.join(' ') }, form);
    });
    preview.addEventListener('click', e => { const b = e.target.closest('[data-xpick]'); if (b) { files.splice(Number(b.dataset.xpick), 1); render(); clearFieldError(slot, form); } });
    return { get files() { return files; }, reset() { files = []; render(); }, render };
  }

  /* ===== ارسال فرم‌های این ماژول ===== */
  async function submitX(form, btn, { files = [], field, clientErrors, onDone }) {
    clearErrors(form);
    const ce = clientErrors ? clientErrors() : {};
    if (Object.keys(ce).length) { showErrors(ce, form); notice('error', 'لطفاً خطاهای مشخص‌شده در فرم را برطرف کنید.'); return; }
    const fd = new FormData(form);
    files.forEach(f => fd.append(field, f));
    btn.disabled = true;
    try { const r = await send(fd, form.dataset.xaction); onDone && onDone(r); }
    catch (err) { notice('error', err.message); if (err.errors) showErrors(err.errors, form); }
    finally { btn.disabled = false; }
  }
  async function xAction(action, id, extra = {}) {
    const fd = new FormData(); fd.set('id', String(id)); Object.entries(extra).forEach(([k, v]) => fd.set(k, v));
    try { return await send(fd, action); } catch (err) { notice('error', err.message); return null; }
  }

  /* ===== داشبورد ===== */
  function renderDashboard(d) {
    const s = d.stats, u = s.units, r = s.reviews, a = s.articles, v = s.views;
    const cards = [
      ['ساختمان‌ها', s.projects, ''], ['کل واحدها', u.total, ''], ['واحدهای موجود', u.available, 'ok'], ['واحدهای رزرو شده', u.reserved, 'warn'], ['واحدهای فروخته‌شده', u.sold, 'sold'],
      ['کل نظرات', r.total, ''], ['نظرات در انتظار تأیید', r.pending, 'warn'], ['مقالات منتشرشده', a.published, 'ok'], ['پیش‌نویس مقالات', a.draft, ''], ['درخواست‌های مشاوره جدید', s.contacts_new, 'warn'],
    ];
    if (r.average !== null) cards.push(['میانگین امتیاز (نظرات تأییدشده)', C.fa(String(r.average).replace('.', '٫')) + ' از ۵', '']);
    $('#dash-cards').innerHTML = cards.map(([l, val, k]) => `<div class="stat-card-admin ${k}"><strong>${typeof val === 'number' ? n(val) : esc(val)}</strong><span>${esc(l)}</span></div>`).join('');

    const note = $('#dash-views-note');
    if (!v.tracking_since) {
      note.textContent = 'هنوز هیچ بازدیدی ثبت نشده است. شمارش بازدید از زمان فعال‌شدن این نسخه آغاز می‌شود و داده‌ای از قبل از آن وجود ندارد.';
      $('#dash-view-cards').innerHTML = ''; $('#dash-chart-wrap').hidden = true; return;
    }
    $('#dash-chart-wrap').hidden = false;
    note.textContent = `ثبت بازدید از ${fmtDate(v.tracking_since + ' 12:00:00')} آغاز شده است. بازدید ربات‌ها و مدیر واردشده شمرده نمی‌شود و بازدید تکراری همان نشست از همان صفحه تا ۳۰ دقیقه یک بار حساب می‌شود.`;
    $('#dash-view-cards').innerHTML = [['امروز', v.today], ['۷ روز اخیر', v.last7], ['۳۰ روز اخیر', v.last30], ['این ماه (میلادی)', v.this_month], ['مجموع از ابتدای ثبت', v.total]]
      .map(([l, val]) => `<div class="stat-card-admin"><strong>${n(val)}</strong><span>${esc(l)}</span></div>`).join('');
    const max = Math.max(1, ...v.daily.map(x => x.views));
    $('#dash-daily').innerHTML = v.daily.map(x => `<div class="bar-col" title="${esc(fmtDay(x.day))}: ${n(x.views)} بازدید"><span class="bar-val">${x.views ? n(x.views) : ''}</span><div class="bar" style="height:${x.views ? Math.max(4, Math.round(x.views / max * 100)) : 2}%"></div><span class="bar-lbl">${esc(fmtDay(x.day))}</span></div>`).join('');
    $('#dash-monthly').innerHTML = '<thead><tr><th>ماه (میلادی)</th><th>بازدید</th></tr></thead><tbody>' + v.monthly.map(m => `<tr><td>${esc(fmtMonth(m.month))}</td><td>${n(m.views)}</td></tr>`).join('') + '</tbody>';
    const rank = (list, kind) => list.length ? list.map(x => `<li><span>${esc(x.title)}</span><b>${n(x.views)}</b></li>`).join('') : `<li class="muted">در ۳۰ روز اخیر بازدیدی برای ${kind} ثبت نشده است.</li>`;
    $('#dash-top-buildings').innerHTML = rank(v.top_buildings, 'ساختمان‌ها'); $('#dash-top-articles').innerHTML = rank(v.top_articles, 'مقالات');
  }
  function renderBuildingStats(d) {
    const rows = d.stats.buildings;
    $('#dash-buildings').innerHTML = rows.length
      ? '<thead><tr><th>ساختمان</th><th>واحدها</th><th>موجود</th><th>رزرو</th><th>فروخته</th><th>بازدید ۷ روز</th><th>بازدید ۳۰ روز</th><th>کل بازدید</th><th></th></tr></thead><tbody>' + rows.map(b => `<tr><td>${esc(b.title)}</td><td>${n(b.units)}</td><td>${n(b.available)}</td><td>${n(b.reserved)}</td><td>${n(b.sold)}</td><td>${n(b.views_7)}</td><td>${n(b.views_30)}</td><td>${n(b.views_total)}</td><td><button type="button" class="edit-btn" data-xdo="goto-units" data-id="${b.id}">واحدها</button></td></tr>`).join('') + '</tbody>'
      : '<tbody><tr><td class="muted">هنوز ساختمانی ثبت نشده است.</td></tr></tbody>';
  }

  /* ===== واحدها ===== */
  const unitForm = $('#unit-form'); let unitProject = 0, editingUnit = null;
  const unitPicker = makePicker($('#unit-image-input'), $('#unit-new'), { max: 6, form: unitForm, slot: 'images', kept: () => editingUnit ? editingUnit.images.length - unitForm.querySelectorAll('input[name="remove_images[]"]:checked').length : 0 });
  function fillSelects(d) {
    document.querySelectorAll('select[data-xoptions]').forEach(sel => { if (sel.dataset.filled) return; sel.innerHTML = optionHtml(d.options[sel.dataset.xoptions] || [], sel.dataset.blank === '1'); sel.dataset.filled = '1'; });
  }
  function renderUnits(d) {
    const sel = $('#unit-project'), cur = unitProject || (d.projects[0] && d.projects[0].id) || 0;
    sel.innerHTML = d.projects.length ? d.projects.map(p => `<option value="${p.id}">${esc(p.title)}</option>`).join('') : '<option value="">ابتدا یک ساختمان ثبت کنید</option>';
    unitProject = d.projects.some(p => p.id === cur) ? cur : (d.projects[0] ? d.projects[0].id : 0);
    sel.value = String(unitProject || ''); unitForm.elements.project_id.value = String(unitProject || '');
    const list = d.units.filter(x => x.project_id === unitProject);
    const c = { available: 0, reserved: 0, sold: 0 }; list.forEach(x => { c[x.status] = (c[x.status] || 0) + 1; });
    $('#unit-summary').innerHTML = unitProject ? `<span class="pill">کل: ${n(list.length)}</span><span class="pill ok">موجود: ${n(c.available)}</span><span class="pill warn">رزرو شده: ${n(c.reserved)}</span><span class="pill sold">فروخته‌شده: ${n(c.sold)}</span>` : '';
    $('#unit-count').textContent = String(list.length);
    $('#unit-form-card').hidden = !unitProject;
    const labels = d.options.unit_statuses;
    $('#unit-list').innerHTML = !unitProject ? '<p class="muted">برای مدیریت واحدها ابتدا از زبانه «ساختمان‌ها» یک ساختمان ثبت کنید.</p>' : !list.length ? '<p class="muted">برای این ساختمان هنوز واحدی ثبت نشده است.</p>'
      : '<div class="table-wrap"><table class="data-table"><thead><tr><th>واحد</th><th>طبقه</th><th>متراژ</th><th>اتاق</th><th>قیمت</th><th>وضعیت فروش</th><th></th></tr></thead><tbody>' + list.map(x => `<tr class="st-${x.status}"><td><b>${esc(x.unit_number)}</b>${x.images.length ? ` <small class="muted">${n(x.images.length)} تصویر</small>` : ''}</td><td>${x.floor !== '' ? esc(C.fa(x.floor)) : '—'}</td><td>${x.area !== '' ? esc(C.fa(x.area)) : '—'}</td><td>${x.bedrooms !== '' ? esc(C.fa(x.bedrooms)) : '—'}</td><td>${x.price !== '' ? n(x.price) + (x.price_visible ? '' : ' <small class="muted">(مخفی)</small>') : '—'}</td><td><select class="status-select st-${x.status}" data-xdo="unit-status" data-id="${x.id}" aria-label="وضعیت فروش واحد ${esc(x.unit_number)}">${Object.entries(labels).map(([k, l]) => `<option value="${k}"${k === x.status ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></td><td class="row-actions"><button type="button" class="edit-btn" data-xdo="edit-unit" data-id="${x.id}">ویرایش</button><button type="button" class="delete-btn" data-xdo="unit_delete" data-id="${x.id}" data-confirm="این واحد حذف شود؟">حذف</button></td></tr>`).join('') + '</tbody></table></div>';
    if (editingUnit) { const fresh = d.units.find(x => x.id === editingUnit.id); if (!fresh) resetUnit(); else { editingUnit = fresh; renderUnitCurrent(); } }
  }
  function renderUnitCurrent() {
    const wrap = $('#unit-current'), list = $('#unit-current-list');
    if (!editingUnit || !editingUnit.images.length) { wrap.hidden = true; list.innerHTML = ''; return; }
    wrap.hidden = false;
    list.innerHTML = editingUnit.images.map((img, i) => `<div class="image-tile"><img src="${esc(img.url)}" alt=""><label class="tile-opt"><input type="radio" name="cover_image" value="${esc(img.name)}" ${i === 0 ? 'checked' : ''}> تصویر اصلی</label><label class="tile-opt danger"><input type="checkbox" name="remove_images[]" value="${esc(img.name)}"> حذف</label></div>`).join('');
  }
  function setUnitMode(u) { editingUnit = u || null; $('#unit-form-title').textContent = u ? `ویرایش واحد ${u.unit_number}` : 'افزودن واحد جدید'; $('#unit-submit').textContent = u ? 'ذخیره تغییرات' : '＋ افزودن واحد'; $('#unit-cancel').hidden = !u; renderUnitCurrent(); }
  function resetUnit() { unitForm.reset(); clearErrors(unitForm); unitPicker.reset(); unitForm.elements.id.value = '0'; unitForm.elements.project_id.value = String(unitProject || ''); setUnitMode(null); }
  function editUnit(id) {
    const u = getData().units.find(x => x.id === id); if (!u) return;
    unitForm.reset(); clearErrors(unitForm); unitPicker.reset();
    ['unit_number', 'status', 'floor', 'area', 'bedrooms', 'bathrooms', 'parking', 'direction', 'sort_order', 'price', 'description'].forEach(k => { unitForm.elements[k].value = u[k] ?? ''; });
    unitForm.elements.price_visible.checked = !!u.price_visible; unitForm.elements.storage.checked = !!u.storage;
    unitForm.elements.id.value = String(u.id); unitForm.elements.project_id.value = String(u.project_id);
    setUnitMode(u); unitForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  $('#unit-project').addEventListener('change', e => { unitProject = Number(e.target.value) || 0; resetUnit(); renderUnits(getData()); });
  $('#unit-cancel').addEventListener('click', e => { e.preventDefault(); resetUnit(); });
  unitForm.addEventListener('input', e => { if (e.target.name) clearFieldError(e.target.name, unitForm); });
  unitForm.addEventListener('submit', e => {
    e.preventDefault();
    submitX(unitForm, $('#unit-submit'), { files: unitPicker.files, field: 'images[]', clientErrors: () => { const er = {}; if (!String(unitForm.elements.unit_number.value).trim()) er.unit_number = 'شماره یا نام واحد الزامی است.'; if (!unitForm.elements.project_id.value) er.project_id = 'ساختمان را انتخاب کنید.'; return er; },
      onDone: () => resetUnit() });
  });

  /* ===== نظرات ===== */
  let reviewStatus = '', reviewProject = 0;
  function renderReviews(d) {
    const sel = $('#review-project'); const keep = reviewProject;
    sel.innerHTML = '<option value="0">همه ساختمان‌ها</option>' + d.projects.map(p => `<option value="${p.id}">${esc(p.title)}</option>`).join(''); sel.value = String(keep);
    const byProject = d.reviews.filter(r => !reviewProject || r.project_id === reviewProject);
    const cnt = { all: byProject.length, pending: 0, approved: 0, hidden: 0 }; byProject.forEach(r => { cnt[r.status]++; });
    document.querySelectorAll('#review-filter [data-n]').forEach(el => { el.textContent = n(cnt[el.dataset.n]); });
    const rows = byProject.filter(r => !reviewStatus || r.status === reviewStatus);
    const label = d.options.review_statuses;
    $('#review-list').innerHTML = rows.length ? rows.map(r => {
      const btns = r.status === 'pending' ? `<button type="button" class="btn-ok" data-xdo="review_approve" data-id="${r.id}">تأیید</button><button type="button" class="edit-btn" data-xdo="review_hide" data-id="${r.id}">مخفی‌کردن</button>`
        : r.status === 'approved' ? `<button type="button" class="edit-btn" data-xdo="review_hide" data-id="${r.id}">مخفی‌کردن</button>` : `<button type="button" class="btn-ok" data-xdo="review_restore" data-id="${r.id}">بازگردانی</button>`;
      return `<article class="review-card st-${r.status}"><div class="review-head"><div><b>${esc(r.name)}</b><span class="stars" aria-label="امتیاز ${r.rating} از ۵">${stars(r.rating)}</span></div><span class="pill st-${r.status}">${esc(label[r.status])}</span></div><p class="review-meta">${esc(r.project_title)} · ${esc(fmtDate(r.created_at))}</p><p class="review-text">${nl2br(r.comment)}</p><div class="row-actions">${btns}<button type="button" class="delete-btn" data-xdo="review_delete" data-id="${r.id}" data-confirm="این نظر برای همیشه حذف شود؟">حذف</button></div></article>`;
    }).join('') : '<p class="muted">نظری با این فیلتر وجود ندارد.</p>';
  }
  $('#review-project').addEventListener('change', e => { reviewProject = Number(e.target.value) || 0; renderReviews(getData()); });
  $('#review-filter').addEventListener('click', e => { const b = e.target.closest('.seg-btn'); if (!b) return; reviewStatus = b.dataset.status; document.querySelectorAll('#review-filter .seg-btn').forEach(x => x.classList.toggle('active', x === b)); renderReviews(getData()); });

  /* ===== مقالات ===== */
  const articleForm = $('#article-form'); let editingArticle = null;
  const coverPicker = makePicker($('#article-cover-input'), $('#article-cover-new'), { max: 1, form: articleForm, slot: 'cover' });
  function renderArticles(d) {
    $('#article-count').textContent = String(d.articles.length);
    const label = d.options.article_statuses;
    $('#article-list').innerHTML = d.articles.length ? '<div class="table-wrap"><table class="data-table"><thead><tr><th>عنوان</th><th>وضعیت</th><th>تاریخ انتشار</th><th></th></tr></thead><tbody>' + d.articles.map(a => `<tr><td><b>${esc(a.title)}</b>${a.cover ? ' <small class="muted">با تصویر</small>' : ''}</td><td><span class="pill ${a.status === 'published' ? 'ok' : ''}">${esc(label[a.status])}</span></td><td>${a.published_at ? esc(fmtDate(a.published_at)) : '—'}</td><td class="row-actions"><button type="button" class="edit-btn" data-xdo="edit-article" data-id="${a.id}">ویرایش</button>${a.status === 'published' ? `<a class="edit-btn" href="${esc(a.url)}" target="_blank" rel="noopener">مشاهده ↗</a><button type="button" class="edit-btn" data-xdo="article_publish" data-id="${a.id}" data-status="draft">خروج از انتشار</button>` : `<button type="button" class="btn-ok" data-xdo="article_publish" data-id="${a.id}" data-status="published">انتشار</button>`}<button type="button" class="delete-btn" data-xdo="article_delete" data-id="${a.id}" data-confirm="این مقاله حذف شود؟">حذف</button></td></tr>`).join('') + '</tbody></table></div>' : '<p class="muted">هنوز مقاله‌ای ثبت نشده است.</p>';
    if (editingArticle) { const fresh = d.articles.find(x => x.id === editingArticle.id); if (!fresh) resetArticle(); else { editingArticle = fresh; renderArticleCover(); } }
  }
  function renderArticleCover() {
    const wrap = $('#article-cover-current'); wrap.hidden = !(editingArticle && editingArticle.cover);
    $('#article-cover-img').innerHTML = editingArticle && editingArticle.cover ? `<div class="image-tile"><img src="${esc(editingArticle.cover)}" alt=""></div>` : '';
  }
  function setArticleMode(a) { editingArticle = a || null; $('#article-form-title').textContent = a ? `ویرایش مقاله: ${a.title}` : 'نوشتن مقاله جدید'; $('#article-submit').textContent = a ? 'ذخیره تغییرات' : '＋ ذخیره مقاله'; $('#article-cancel').hidden = !a; renderArticleCover(); }
  function resetArticle() { articleForm.reset(); clearErrors(articleForm); coverPicker.reset(); articleForm.elements.id.value = '0'; setArticleMode(null); }
  function editArticle(id) {
    const a = getData().articles.find(x => x.id === id); if (!a) return;
    articleForm.reset(); clearErrors(articleForm); coverPicker.reset();
    ['title', 'slug', 'excerpt', 'content', 'meta_title', 'meta_description', 'status'].forEach(k => { articleForm.elements[k].value = a[k] ?? ''; });
    articleForm.elements.id.value = String(a.id); setArticleMode(a); articleForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  $('#article-cancel').addEventListener('click', e => { e.preventDefault(); resetArticle(); });
  articleForm.addEventListener('input', e => { if (e.target.name) clearFieldError(e.target.name, articleForm); });
  articleForm.addEventListener('submit', e => {
    e.preventDefault();
    submitX(articleForm, $('#article-submit'), { files: coverPicker.files, field: 'cover[]', clientErrors: () => { const er = {}; if (articleForm.elements.title.value.trim().length < 5) er.title = 'عنوان باید حداقل ۵ نویسه باشد.'; if (articleForm.elements.content.value.trim().length < 50) er.content = 'متن مقاله باید حداقل ۵۰ نویسه باشد.'; return er; }, onDone: () => resetArticle() });
  });

  /* ===== درباره ما ===== */
  const aboutImgForm = $('#about-image-form'), itemForm = $('#about-item-form'); let editingItem = null;
  const aboutPicker = makePicker($('#about-image-input'), $('#about-image-new'), { max: 1, form: aboutImgForm, slot: 'about_image' });
  const itemPicker = makePicker($('#about-item-input'), $('#about-item-new'), { max: 1, form: itemForm, slot: 'image' });
  const VALUE_LABELS = { milestone: 'سال یا تاریخ', stat: 'مقدار (مثلاً ۱۲۰) *', team: 'سمت', achievement: 'مقدار (اختیاری)' };
  function syncKind() { const k = itemForm.elements.kind.value; $('#about-value-label').textContent = VALUE_LABELS[k] || 'مقدار'; $('#about-item-photo').hidden = k !== 'team'; }
  itemForm.elements.kind.addEventListener('change', syncKind);
  function renderAbout(d) {
    $('#about-image-current').innerHTML = d.about_image ? `<div class="image-tile"><img src="${esc(d.about_image)}" alt=""></div>` : '<p class="muted">تصویری ثبت نشده است.</p>';
    $('#about-image-remove-wrap').hidden = !d.about_image;
    const label = d.options.about_kinds;
    const groups = Object.keys(label).map(k => ({ k, rows: d.about_items.filter(x => x.kind === k) })).filter(g => g.rows.length);
    $('#about-item-list').innerHTML = groups.length ? groups.map(g => `<h3>${esc(label[g.k])}</h3><div class="faq-admin-list">${g.rows.map(x => `<div class="faq-admin-item${x.is_active ? '' : ' off'}"><div class="faq-admin-text"><b>${esc(x.title)}</b><small>${esc([x.value, x.description].filter(Boolean).join(' — '))}</small><span class="faq-meta">ترتیب: ${n(x.sort_order)} · ${x.is_active ? 'نمایش داده می‌شود' : 'مخفی'}${x.image ? ' · با عکس' : ''}</span></div><div class="faq-admin-actions"><button type="button" class="edit-btn" data-xdo="edit-item" data-id="${x.id}">ویرایش</button><button type="button" class="edit-btn" data-xdo="about_item_toggle" data-id="${x.id}">${x.is_active ? 'مخفی‌کردن' : 'نمایش'}</button><button type="button" class="delete-btn" data-xdo="about_item_delete" data-id="${x.id}" data-confirm="این مورد حذف شود؟">حذف</button></div></div>`).join('')}</div>`).join('') : '<p class="muted">هنوز موردی ثبت نشده است؛ تا زمانی که چیزی اضافه نکنید، این بخش‌ها در سایت نمایش داده نمی‌شوند.</p>';
    if (editingItem) { const fresh = d.about_items.find(x => x.id === editingItem.id); if (!fresh) resetItem(); else { editingItem = fresh; renderItemCurrent(); } }
  }
  function renderItemCurrent() { $('#about-item-current').innerHTML = editingItem && editingItem.image ? `<div class="image-tile"><img src="${esc(editingItem.image)}" alt=""></div>` : ''; $('#about-item-remove-wrap').hidden = !(editingItem && editingItem.image); }
  function setItemMode(x) { editingItem = x || null; $('#about-item-submit').textContent = x ? 'ذخیره تغییرات' : '＋ افزودن'; $('#about-item-cancel').hidden = !x; renderItemCurrent(); syncKind(); }
  function resetItem() { itemForm.reset(); clearErrors(itemForm); itemPicker.reset(); itemForm.elements.id.value = '0'; setItemMode(null); }
  function editItem(id) {
    const x = getData().about_items.find(i => i.id === id); if (!x) return;
    itemForm.reset(); clearErrors(itemForm); itemPicker.reset();
    ['kind', 'title', 'value', 'description', 'sort_order'].forEach(k => { itemForm.elements[k].value = x[k] ?? ''; }); itemForm.elements.is_active.checked = !!x.is_active; itemForm.elements.id.value = String(x.id);
    setItemMode(x); itemForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  $('#about-item-cancel').addEventListener('click', e => { e.preventDefault(); resetItem(); });
  itemForm.addEventListener('input', e => { if (e.target.name) clearFieldError(e.target.name, itemForm); });
  itemForm.addEventListener('submit', e => { e.preventDefault(); submitX(itemForm, $('#about-item-submit'), { files: itemPicker.files, field: 'image[]', clientErrors: () => { const er = {}; if (itemForm.elements.title.value.trim().length < 2) er.title = 'عنوان الزامی است.'; return er; }, onDone: () => resetItem() }); });
  aboutImgForm.addEventListener('submit', e => { e.preventDefault(); submitX(aboutImgForm, aboutImgForm.querySelector('button[type=submit]'), { files: aboutPicker.files, field: 'about_image[]', onDone: () => { aboutPicker.reset(); aboutImgForm.reset(); } }); });

  /* ===== رویدادهای عمومی این ماژول ===== */
  document.addEventListener('click', async event => {
    const btn = event.target.closest('[data-xdo]'); if (!btn || btn.tagName === 'SELECT') return;
    const act = btn.dataset.xdo, id = Number(btn.dataset.id || 0);
    if (act === 'goto-units') { unitProject = id; activate('units'); resetUnit(); renderUnits(getData()); return; }
    if (act === 'edit-unit') { editUnit(id); return; }
    if (act === 'edit-article') { editArticle(id); return; }
    if (act === 'edit-item') { editItem(id); return; }
    if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;
    btn.disabled = true;
    const extra = btn.dataset.status ? { status: btn.dataset.status } : {};
    await xAction(act, id, extra);
    btn.disabled = false;   // دکمه‌های ثابت (مثل «افزودن پرسش‌های آماده») بعد از موفقیت هم باید دوباره فعال شوند
  });
  document.addEventListener('change', async event => {
    const sel = event.target.closest('select[data-xdo="unit-status"]'); if (!sel) return;
    sel.disabled = true;
    const r = await xAction('unit_status', Number(sel.dataset.id), { status: sel.value });
    if (!r) renderUnits(getData());   // در صورت خطا، وضعیت واقعی دوباره نمایش داده می‌شود
  });

  /* ===== اتصال به رندر مرکزی ===== */
  C.onRender(d => {
    fillSelects(d); renderDashboard(d); renderBuildingStats(d); renderUnits(d); renderReviews(d); renderArticles(d); renderAbout(d); badges(d);
    if (!tabsReady) { tabsReady = true; const h = location.hash.slice(1); activate(TABS.includes(h) ? h : 'dashboard', false); syncKind(); }
  });
})();
