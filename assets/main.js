'use strict';
(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const nl2br = value => esc(value).replace(/\n/g, '<br>');
  const phoneHref = value => String(value || '').replace(/[^0-9+]/g, '');
  const statusOptions = ['در حال ساخت','تکمیل‌شده','در مرحله طراحی','پیش‌فروش'];
  let loadedData = null, homeRendered = false;
  const nf = new Intl.NumberFormat('fa-IR');
  const normText = v => String(v ?? '').replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\u200c/g, ' ').toLowerCase();

  const fa = v => String(v ?? '').replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
  function renderProjects(projects, query = '', status = '') {
    const grid = $('#project-grid'), empty = $('#empty-projects');
    $('#results-count').textContent = `${projects.length} پروژه پیدا شد${query || status ? '؛ بر اساس فیلتر انتخابی' : ''}.`;
    $('#clear-filter').hidden = !(query || status);
    if (!projects.length) {
      grid.innerHTML = ''; empty.hidden = false;
      $('#empty-title').textContent = query || status ? 'پروژه‌ای با این مشخصات پیدا نشد' : 'ساختمان‌ها به‌زودی معرفی می‌شوند';
      $('#empty-description').textContent = query || status ? 'عبارت جست‌وجو یا وضعیت انتخابی را تغییر دهید.' : 'اطلاعات ساختمان‌ها از پنل مدیریت منتشر می‌شود.';
      return;
    }
    empty.hidden = true;
    grid.innerHTML = projects.map(p => `<article class="project-card"><a href="${esc(p.url)}">${p.image ? `<img class="project-image" loading="lazy" decoding="async" src="${esc(p.image)}" alt="${esc(p.title)}">` : '<div class="project-placeholder"><span>⌂</span><small>تصویر ساختمان</small></div>'}</a><div class="project-info"><span class="status">${esc(p.status)}</span>${p.featured ? '<span class="featured-label">پروژه شاخص</span>' : ''}<h3><a href="${esc(p.url)}">${esc(p.title)}</a></h3><p class="location">⌖ ${esc(p.location)}</p>${p.facts && p.facts.length ? `<p class="card-facts">${p.facts.map(f => `<span>${esc(fa(f))}</span>`).join('')}</p>` : ''}<p>${esc(p.description)}</p><a class="text-link" href="${esc(p.url)}">مشاهده جزئیات ←</a></div></article>`).join('');
  }


  function renderArticles(list) {
    const sec = $('#articles-section');
    if (!list || !list.length) { sec.hidden = true; return; }
    sec.hidden = false;
    const fmt = iso => { const d = new Date(iso); return isNaN(d) ? '' : d.toLocaleDateString('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }); };
    $('#articles-grid').innerHTML = list.map(a => { const u = `article.html?slug=${encodeURIComponent(a.slug)}`; return `<article class="article-card"><a class="cover" href="${esc(u)}" tabindex="-1" aria-hidden="true">${a.cover ? `<img loading="lazy" decoding="async" src="${esc(a.cover)}" alt="">` : ''}</a><div class="body">${a.published_at ? `<time datetime="${esc(a.published_at)}">${esc(fmt(a.published_at))}</time>` : ''}<h3><a href="${esc(u)}">${esc(a.title)}</a></h3><p>${esc(a.excerpt)}</p><a class="text-link" href="${esc(u)}">ادامه مطلب ←</a></div></article>`; }).join('');
  }

  function renderStats(stats) {
    const sec = $('#stats-section');
    if (!stats || !stats.length) { sec.hidden = true; return; }
    sec.hidden = false;
    $('#stats-grid').innerHTML = stats.map(st => `<div class="stat-card"><strong data-target="${Number(st.value) || 0}">${esc(nf.format(Number(st.value) || 0))}</strong><span>${esc(st.label)}</span></div>`).join('');
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const run = el => { const target = Number(el.dataset.target) || 0, t0 = performance.now(), dur = 900; const tick = now => { const k = Math.min(1, (now - t0) / dur); el.textContent = nf.format(Math.round(target * (1 - Math.pow(1 - k, 3)))); if (k < 1) requestAnimationFrame(tick); }; requestAnimationFrame(tick); };
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(entries => entries.forEach(e => { if (e.isIntersecting) { run(e.target); io.unobserve(e.target); } }), { threshold: .4 });
      sec.querySelectorAll('strong[data-target]').forEach(el => io.observe(el));
    }
  }

  function renderFeatured(list) {
    const sec = $('#featured-section');
    if (!list || !list.length) { sec.hidden = true; return; }
    sec.hidden = false;
    $('#featured-grid').className = `featured-grid count-${Math.min(list.length, 3)}`;
    $('#featured-grid').innerHTML = list.map(p => `<article class="featured-card"><a class="featured-media" href="${esc(p.url)}">${p.image ? `<img loading="lazy" decoding="async" src="${esc(p.image)}" alt="${esc(p.title)}">` : '<div class="project-placeholder"><span>⌂</span></div>'}<span class="status">${esc(p.status)}</span></a><div class="featured-body"><h3><a href="${esc(p.url)}">${esc(p.title)}</a></h3>${p.location ? `<p class="location">⌖ ${esc(fa(p.location))}</p>` : ''}${p.facts && p.facts.length ? `<p class="card-facts">${p.facts.map(f => `<span>${esc(fa(f))}</span>`).join('')}</p>` : ''}<a class="text-link" href="${esc(p.url)}">مشاهده جزئیات ←</a></div></article>`).join('');
  }

  /* پرسش‌های متداول مستقیماً در index.html قرار دارند (بدون PHP و دیتابیس)؛ اینجا فقط جست‌وجو و باز/بسته‌کردن انجام می‌شود. */
  function drawFaq(filter = '') {
    const needle = normText(filter).trim();
    const all = [...document.querySelectorAll('#faq-list .faq-item')];
    let n = 0;
    all.forEach(d => { const ok = !needle || normText(d.textContent).includes(needle); d.hidden = !ok; if (ok) n++; });
    $('#faq-empty').hidden = n > 0;
    $('#faq-count').textContent = needle ? `${nf.format(n)} از ${nf.format(all.length)} پرسش` : `${nf.format(all.length)} پرسش`;
    $('#faq-toggle-all').hidden = !n; $('#faq-toggle-all').textContent = 'باز کردن همه';
  }

  function applySeo() {
    if (!window.SEO) return;
    SEO.page({ title: document.title, description: document.querySelector('meta[name="description"]').content, url: SEO.base(), image: 'assets/logo.webp' });
  }
  applySeo();

  async function loadSite(query = '', status = '') {
    const params = new URLSearchParams(); if (query) params.set('q', query); if (status) params.set('status', status);
    if (!homeRendered) params.set('track', '1');   // بازدید صفحه اصلی فقط در اولین بارگذاری ثبت می‌شود
    const response = await fetch(`api/site.php${params.size ? `?${params}` : ''}`, {headers:{'Accept':'application/json'}, credentials:'same-origin'});
    if (!response.ok) throw new Error('دریافت اطلاعات سایت ناموفق بود.');
    const data = await response.json(); if (!data.ok) throw new Error(data.message || 'خطا در دریافت اطلاعات.');
    loadedData = data;
    // شعار، تلفن، نشانی و داده‌های سئو در index.html ثابت‌اند.
    // «درباره شرکت»: متن ثابت داخل HTML پیش‌فرض است؛ اگر پنل مدیریت متنی ثبت کرده باشد، همان جایگزین می‌شود.
    const s = data.settings || {};
    if (String(s.about || '').trim()) $('#about-text').innerHTML = nl2br(s.about);
    const facts = [['سال آغاز فعالیت',s.founded_year],['شماره ثبت',s.registration_number],['مدیرعامل / مسئول معرفی',s.company_manager],['مجوزها و سوابق',s.credentials]].filter(x => String(x[1] || '').trim());
    if (facts.length) { $('#company-facts').hidden = false; $('#facts-grid').innerHTML = facts.map(([k,v])=>`<article class="fact-card"><span>${esc(k)}</span><strong>${nl2br(v)}</strong></article>`).join(''); }
    renderProjects(data.projects, query, status);
    if (data.services.length) { $('#services-section').hidden = false; $('#services-grid').innerHTML = data.services.map(sv=>`<article class="service-card"><div class="service-icon">${esc(sv.icon || '◆')}</div><h3>${esc(sv.title)}</h3><p>${nl2br(sv.description)}</p></article>`).join(''); }
    if (!homeRendered) { renderStats(data.stats); renderFeatured(data.featured); renderArticles(data.articles); homeRendered = true; }
  }

  const menuButton = $('.menu-toggle'), nav = $('.nav');
  if (menuButton && nav) {
    menuButton.addEventListener('click', () => { const open = nav.classList.toggle('open'); menuButton.setAttribute('aria-expanded', String(open)); });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => { nav.classList.remove('open'); menuButton.setAttribute('aria-expanded','false'); }));
  }
  $('#current-year').textContent = String(new Date().getFullYear());
  drawFaq();
  $('#faq-search').addEventListener('input', e => drawFaq(e.target.value));
  $('#faq-toggle-all').addEventListener('click', e => {
    const items = [...document.querySelectorAll('#faq-list details:not([hidden])')]; const open = items.some(d => !d.open);
    items.forEach(d => { d.open = open; }); e.currentTarget.textContent = open ? 'بستن همه' : 'باز کردن همه';
  });
  $('#project-filters').addEventListener('submit', async event => {
    event.preventDefault(); const form = new FormData(event.currentTarget); const q = String(form.get('q') || '').trim(); const status = String(form.get('status') || '');
    try { $('#results-count').textContent = 'در حال جست‌وجو…'; await loadSite(q, status); } catch (error) { $('#results-count').textContent = error.message; }
  });
  $('#clear-filter').addEventListener('click', async event => { event.preventDefault(); $('#project-filters').reset(); try { await loadSite(); } catch (error) { $('#results-count').textContent = error.message; } });
  loadSite().catch(error => { $('#results-count').textContent = error.message; $('#empty-projects').hidden = false; $('#empty-title').textContent = 'بارگذاری اطلاعات ناموفق بود'; $('#empty-description').textContent = 'لطفاً اتصال به سرور را بررسی و صفحه را تازه‌سازی کنید.'; });
})();
