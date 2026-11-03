
'use strict';
(() => {
  const $ = s => document.querySelector(s);
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const fa = v => String(v ?? '').replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
  const fmt = iso => { const d = new Date(iso); return isNaN(d) ? '' : d.toLocaleDateString('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }); };
  const yr = $('#year'); if (yr) yr.textContent = String(new Date().getFullYear());
  const nav = $('.nav'), btn = $('.menu-toggle');
  if (btn && nav) btn.addEventListener('click', () => { const o = nav.classList.toggle('open'); btn.setAttribute('aria-expanded', String(o)); });
  const get = async url => { const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); const d = await r.json().catch(() => ({})); if (!r.ok || !d.ok) { const e = new Error(d.message || 'خطا در دریافت اطلاعات.'); e.status = r.status; throw e; } return d; };

  /* بدنه‌ی مقاله: متن ساده با «## عنوان» و «- فهرست»؛ همه‌ی متن escape می‌شود. */
  function renderBody(text) {
    let html = '', para = [], list = [];
    const flushP = () => { if (para.length) { html += `<p>${para.map(esc).join('<br>')}</p>`; para = []; } };
    const flushL = () => { if (list.length) { html += `<ul>${list.map(i => `<li>${esc(i)}</li>`).join('')}</ul>`; list = []; } };
    for (const l of String(text).replace(/\r/g, '').split('\n')) {
      if (!l.trim()) { flushP(); flushL(); continue; }
      if (l.startsWith('## ')) { flushP(); flushL(); html += `<h2>${esc(l.slice(3).trim())}</h2>`; }
      else if (l.startsWith('- ')) { flushP(); list.push(l.slice(2).trim()); }
      else { flushL(); para.push(l); }
    }
    flushP(); flushL(); return html;
  }
  const card = a => { const u = `article.html?slug=${encodeURIComponent(a.slug)}`; return `<article class="article-card"><a class="cover" href="${esc(u)}" tabindex="-1" aria-hidden="true">${a.cover ? `<img loading="lazy" decoding="async" src="${esc(a.cover)}" alt="">` : ''}</a><div class="body">${a.published_at ? `<time datetime="${esc(a.published_at)}">${esc(fmt(a.published_at))}</time>` : ''}<h2><a href="${esc(u)}">${esc(a.title)}</a></h2><p>${esc(a.excerpt)}</p><a class="text-link" href="${esc(u)}">ادامه مطلب ←</a></div></article>`; };

  /* ----- فهرست مقالات ----- */
  const list = $('#articles-content');
  if (list) {
    const qs = new URLSearchParams(location.search); const page = Math.max(1, parseInt(qs.get('page') || '1', 10) || 1);
    get(`api/articles.php?page=${page}`).then(d => {
      if (window.SEO) {
        const base = SEO.base(), url = page > 1 ? new URL(`articles.html?page=${d.page}`, location.href).href : new URL('articles.html', location.href).href;
        SEO.page({ title: `مقالات${d.page > 1 ? ` (صفحه ${fa(d.page)})` : ''} | ${d.site.name}`, description: 'مقالات و دانستنی‌های برج افراز الماس پردیس درباره ساختمان‌سازی، خرید و سرمایه‌گذاری در پردیس.', url, image: d.site.logo });
        SEO.jsonLd('articles-schema', { '@context': 'https://schema.org', '@graph': [{ '@type': 'CollectionPage', name: 'مقالات', url, inLanguage: 'fa-IR' }, { '@type': 'BreadcrumbList', itemListElement: [{ '@type': 'ListItem', position: 1, name: d.site.name, item: base }, { '@type': 'ListItem', position: 2, name: 'مقالات', item: url }] }] });
      }
      const pager = d.pages > 1 ? `<nav class="pager" aria-label="صفحه‌بندی">${Array.from({ length: d.pages }, (_, i) => i + 1).map(p => p === d.page ? `<span class="cur" aria-current="page">${fa(p)}</span>` : `<a href="articles.html?page=${p}">${fa(p)}</a>`).join('')}</nav>` : '';
      list.innerHTML = `<div class="content-page-head"><span class="eyebrow blue">${esc(d.site.name)}</span><h1>مقالات</h1></div>${d.articles.length ? `<div class="article-grid">${d.articles.map(card).join('')}</div>${pager}` : '<p class="results-count">هنوز مقاله‌ای منتشر نشده است.</p>'}`;
    }).catch(e => { list.innerHTML = `<div class="admin-wrap"><h1>${esc(e.message)}</h1><a class="text-link" href="index.html">بازگشت به خانه</a></div>`; });
  }

  /* ----- صفحه‌ی یک مقاله ----- */
  const one = $('#article-content');
  if (one) {
    const slug = new URLSearchParams(location.search).get('slug') || '';
    get(`api/articles.php?slug=${encodeURIComponent(slug)}`).then(d => {
      const a = d.article;
      if (window.SEO) {
        const base = SEO.base(), url = new URL(`article.html?slug=${encodeURIComponent(a.slug)}`, location.href).href;
        const desc = SEO.trim(a.meta_description || a.excerpt, 155), title = `${a.meta_title || a.title} | ${d.site.name}`;
        SEO.page({ title, description: desc, url, image: a.cover || d.site.logo, type: 'article' });
        SEO.jsonLd('article-schema', { '@context': 'https://schema.org', '@graph': [
          { '@type': 'BlogPosting', headline: a.title, description: desc, url, mainEntityOfPage: url, inLanguage: 'fa-IR', image: a.cover ? [SEO.abs(a.cover)] : undefined, datePublished: a.published_at || undefined, dateModified: a.updated_at || a.published_at || undefined,
            author: { '@type': 'Organization', name: d.site.name }, publisher: { '@type': 'Organization', name: d.site.name, logo: { '@type': 'ImageObject', url: SEO.abs(d.site.logo) } } },
          { '@type': 'BreadcrumbList', itemListElement: [{ '@type': 'ListItem', position: 1, name: d.site.name, item: base }, { '@type': 'ListItem', position: 2, name: 'مقالات', item: new URL('articles.html', location.href).href }, { '@type': 'ListItem', position: 3, name: a.title, item: url }] }] });
      } else document.title = `${a.title} | ${d.site.name}`;
      one.innerHTML = `<div class="article-wrap"><a class="back-link text-link" href="articles.html">← همه مقالات</a><h1>${esc(a.title)}</h1><div class="meta">${a.published_at ? `<time datetime="${esc(a.published_at)}">${esc(fmt(a.published_at))}</time>` : ''}</div>${a.cover ? `<img class="article-cover" src="${esc(a.cover)}" alt="${esc(a.title)}">` : ''}<div class="article-body">${renderBody(a.content)}</div>${d.related.length ? `<h2 style="margin-top:40px">مقالات بیشتر</h2><div class="article-grid">${d.related.map(card).join('')}</div>` : ''}</div>`;
    }).catch(e => { if (window.SEO) SEO.noindex(); one.innerHTML = `<div class="admin-wrap"><h1>${esc(e.message)}</h1><a class="text-link" href="articles.html">بازگشت به مقالات</a></div>`; });
  }
})();
