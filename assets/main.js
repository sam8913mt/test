'use strict';
(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const nl2br = value => esc(value).replace(/\n/g, '<br>');
  const phoneHref = value => String(value || '').replace(/[^0-9+]/g, '');
  const statusOptions = ['در حال ساخت','تکمیل‌شده','در مرحله طراحی','پیش‌فروش'];
  let loadedData = null;

  function renderProjects(projects, query = '', status = '', total = projects.length) {
    const grid = $('#project-grid'), empty = $('#empty-projects');
    $('#results-count').textContent = `${total} پروژه پیدا شد${query || status ? '؛ بر اساس فیلتر انتخابی' : ''}.`;
    $('#clear-filter').hidden = !(query || status);
    if (!projects.length) {
      grid.innerHTML = ''; empty.hidden = false;
      $('#empty-title').textContent = query || status ? 'پروژه‌ای با این مشخصات پیدا نشد' : 'ساختمان‌ها به‌زودی معرفی می‌شوند';
      $('#empty-description').textContent = query || status ? 'عبارت جست‌وجو یا وضعیت انتخابی را تغییر دهید.' : 'اطلاعات ساختمان‌ها از پنل مدیریت منتشر می‌شود.';
      return;
    }
    empty.hidden = true;
    grid.innerHTML = projects.map(p => `<article class="project-card"><a href="${esc(p.url)}">${p.image ? `<img class="project-image" loading="lazy" decoding="async" src="${esc(p.image)}" alt="${esc(p.title)}">` : '<div class="project-placeholder"><span>⌂</span><small>تصویر ساختمان</small></div>'}</a><div class="project-info"><span class="status">${esc(p.status)}</span>${p.featured ? '<span class="featured-label">پروژه شاخص</span>' : ''}<h3><a href="${esc(p.url)}">${esc(p.title)}</a></h3><p class="location">⌖ ${esc(p.location)}</p><p>${esc(p.description)}</p><a class="text-link" href="${esc(p.url)}">مشاهده جزئیات ←</a></div></article>`).join('');
  }

  let shownProjects = [], currentPage = 1, currentQuery = '', currentStatus = '';
  async function loadSite(query = '', status = '', page = 1) {
    const params = new URLSearchParams(); if (query) params.set('q', query); if (status) params.set('status', status); if (page > 1) params.set('page', String(page));
    const response = await fetch(`api/site.php${params.size ? `?${params}` : ''}`, {headers:{'Accept':'application/json'}, credentials:'same-origin'});
    if (!response.ok) throw new Error('دریافت اطلاعات سایت ناموفق بود.');
    const data = await response.json(); if (!data.ok) throw new Error(data.message || 'خطا در دریافت اطلاعات.');
    loadedData = data;
    const s = data.settings;
    ['#brand-tagline','#hero-tagline','#footer-tagline'].forEach(sel => { if ($(sel)) $(sel).textContent = s.tagline || 'ساخت آینده، با اعتماد'; });
    $('#about-text').innerHTML = nl2br(s.about || 'شرکت برج افراز الماس پردیس با تمرکز بر کیفیت ساخت، طراحی اصولی و جلب اعتماد مشتریان فعالیت می‌کند.');
    $('#contact-phone').textContent = s.phone || 'ثبت نشده'; $('#call-link').href = s.phone ? `tel:${phoneHref(s.phone)}` : '#contact';
    $('#footer-phone').textContent = s.phone || 'ثبت نشده'; $('#footer-phone').href = s.phone ? `tel:${phoneHref(s.phone)}` : '#contact';
    $('#contact-address').textContent = s.address || 'ثبت نشده'; $('#footer-address').textContent = s.address || 'ثبت نشده';
    if (s.email) { $('#contact-email').hidden = false; $('#contact-email').href = `mailto:${s.email}`; $('#contact-email').textContent = s.email; }
    $('#csrf-token').value = data.csrf;
    const facts = [['سال آغاز فعالیت',s.founded_year],['شماره ثبت',s.registration_number],['مدیرعامل / مسئول معرفی',s.company_manager],['مجوزها و سوابق',s.credentials]].filter(x => String(x[1] || '').trim());
    if (facts.length) { $('#company-facts').hidden = false; $('#facts-grid').innerHTML = facts.map(([k,v])=>`<article class="fact-card"><span>${esc(k)}</span><strong>${nl2br(v)}</strong></article>`).join(''); }
    $('#project-select').innerHTML = '<option value="0">انتخاب نشده</option>' + data.allProjects.map(p=>`<option value="${p.id}">${esc(p.title)}</option>`).join('');
    const wantedProject = new URLSearchParams(location.search).get('project') || data.selectedProject;
    if (wantedProject) { const target = data.allProjects.find(p => String(p.id) === wantedProject || String(p.title) === wantedProject); if (target) $('#project-select').value = String(target.id); }
    shownProjects = page > 1 ? shownProjects.concat(data.projects) : data.projects; currentPage = page; currentQuery = query; currentStatus = status;
    renderProjects(shownProjects, query, status, data.total);
    $('#load-more').hidden = shownProjects.length >= data.total;
    if (data.services.length) { $('#services-section').hidden = false; $('#services-grid').innerHTML = data.services.map(sv=>`<article class="service-card"><div class="service-icon">${esc(sv.icon || '◆')}</div><h3>${esc(sv.title)}</h3><p>${nl2br(sv.description)}</p></article>`).join(''); }
    if (data.gallery && data.gallery.length) { $('#gallery').hidden = false; $('#gallery-grid').innerHTML = data.gallery.map(g=>`<a class="gallery-item" href="${esc(g.url)}" title="${esc(g.title)}"><img loading="lazy" decoding="async" src="${esc(g.thumb)}" alt="${esc(g.title)}"><span>${esc(g.title)}</span></a>`).join(''); } else { $('#gallery').hidden = true; }
    const reviews = String(s.testimonials || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => { const i = line.indexOf('|'); return i > 0 ? {name: line.slice(0, i).trim(), text: line.slice(i + 1).trim()} : {name: '', text: line}; }).filter(r => r.text).slice(0, 9);
    if (reviews.length) { $('#testimonials').hidden = false; $('#testimonials-grid').innerHTML = reviews.map(r=>`<figure class="testimonial-card"><blockquote>${esc(r.text)}</blockquote>${r.name ? `<figcaption>${esc(r.name)}</figcaption>` : ''}</figure>`).join(''); } else { $('#testimonials').hidden = true; }
    $('#faq-list').innerHTML = data.faq.map(item=>`<details class="faq-item"><summary>${esc(item.q)}</summary><p>${esc(item.a)}</p></details>`).join('');
    const schema = {'@context':'https://schema.org','@type':'Organization','name':data.site.name,'telephone':s.phone,'address':{'@type':'PostalAddress','addressLocality':'پردیس','addressCountry':'IR'},'url':data.site.url};
    $('#organization-schema').textContent = JSON.stringify(schema);
    document.title = `${data.site.name} | پروژه‌ها و خدمات ساختمانی`;
  }

  const menuButton = $('.menu-toggle'), nav = $('.nav');
  if (menuButton && nav) {
    menuButton.addEventListener('click', () => { const open = nav.classList.toggle('open'); menuButton.setAttribute('aria-expanded', String(open)); });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => { nav.classList.remove('open'); menuButton.setAttribute('aria-expanded','false'); }));
  }
  $('#current-year').textContent = String(new Date().getFullYear());
  $('#project-filters').addEventListener('submit', async event => {
    event.preventDefault(); const form = new FormData(event.currentTarget); const q = String(form.get('q') || '').trim(); const status = String(form.get('status') || '');
    try { $('#results-count').textContent = 'در حال جست‌وجو…'; await loadSite(q, status); } catch (error) { $('#results-count').textContent = error.message; }
  });
  $('#load-more').addEventListener('click', async event => { const b = event.currentTarget; b.disabled = true; try { await loadSite(currentQuery, currentStatus, currentPage + 1); } catch (error) { $('#results-count').textContent = error.message; } finally { b.disabled = false; } });
  $('#clear-filter').addEventListener('click', async event => { event.preventDefault(); $('#project-filters').reset(); try { await loadSite(); } catch (error) { $('#results-count').textContent = error.message; } });
  $('#contact-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget, box = $('#contact-message'), submit = form.querySelector('button[type=submit]');
    const show = (type, text) => { box.hidden = false; box.className = type; box.textContent = text; box.scrollIntoView({behavior:'smooth', block:'nearest'}); };
    if (!$('#csrf-token').value) { show('alert', 'ارتباط با سرور برقرار نشد. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.'); return; }
    submit.disabled = true;
    try {
      const response = await fetch('api/contact.php', {method:'POST', body:new FormData(form), credentials:'same-origin', headers:{'Accept':'application/json'}});
      let result = {}; try { result = await response.json(); } catch (e) { /* non-JSON response */ }
      if (response.ok && result.ok) { show('success', result.message || 'درخواست شما ثبت شد.'); const token = $('#csrf-token').value; form.reset(); $('#csrf-token').value = token; }
      else show('alert', result.message || 'ثبت درخواست ناموفق بود.');
    } catch (error) { show('alert', 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.'); }
    finally { submit.disabled = false; }
  });
  // ---- full pages shown on the home page (content comes from the admin panel > pages) ----
  // Edit this list to choose which pages appear and in what order: 'about', 'services', 'privacy'
  const HOME_PAGES = ['about', 'services'];
  async function loadHomePages() {
    const box = $('#home-pages'); if (!box) return;
    const results = await Promise.all(HOME_PAGES.map(slug =>
      fetch(`api/page.php?slug=${encodeURIComponent(slug)}`, {headers:{'Accept':'application/json'}, credentials:'same-origin'})
        .then(r => r.ok ? r.json() : null).catch(() => null)));
    box.innerHTML = results.filter(d => d && d.ok && String(d.page.content || '').trim()).map(d =>
      `<section class="section home-page" id="page-${esc(d.page.slug)}"><div class="section-heading"><div><span class="eyebrow blue">${esc(d.site.name)}</span><h2>${esc(d.page.title)}</h2></div></div><article class="content-body">${nl2br(d.page.content)}</article></section>`).join('');
  }
  loadHomePages();
  loadSite().catch(error => { $('#results-count').textContent = error.message; $('#empty-projects').hidden = false; $('#empty-title').textContent = 'بارگذاری اطلاعات ناموفق بود'; $('#empty-description').textContent = 'لطفاً اتصال به سرور را بررسی و صفحه را تازه‌سازی کنید.'; });
})();

// ---- scroll reveal (cosmetic; content stays visible if IntersectionObserver is missing) ----
(function () {
  if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.documentElement.classList.add('js-reveal');
  const io = new IntersectionObserver(entries => entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), {threshold: 0.12, rootMargin: '0px 0px -40px 0px'});
  const SEL = '.section-heading,.project-card,.fact-card,.testimonial-card,.gallery-item,.faq-item,.contact-card,.contact-form-wrap,.about-copy,.about-art,.content-body,.trust-strip>*';
  const seen = new WeakSet();
  const scan = root => (root.querySelectorAll ? root.querySelectorAll(SEL) : []).forEach(el => { if (seen.has(el)) return; seen.add(el); el.classList.add('reveal'); io.observe(el); });
  scan(document);
  new MutationObserver(list => list.forEach(r => r.addedNodes.forEach(n => { if (n.nodeType === 1) { if (n.matches && n.matches(SEL) && !seen.has(n)) { seen.add(n); n.classList.add('reveal'); io.observe(n); } scan(n); } }))).observe(document.body, {childList: true, subtree: true});
})();
