'use strict';
/* ابزار مشترک سئو: meta، canonical، Open Graph و JSON-LD را در سمت مرورگر تنظیم می‌کند.
   (گوگل جاوااسکریپت را اجرا می‌کند و این مقادیر را می‌خواند.) */
window.SEO = (() => {
  const head = document.head;
  const abs = u => { try { return new URL(u, location.href).href; } catch (e) { return u; } };
  const clean = s => String(s ?? '').replace(/\s+/g, ' ').trim();
  const trim = (s, n) => { s = clean(s); return s.length <= n ? s : s.slice(0, n - 1).replace(/\s+\S*$/, '') + '…'; };
  const meta = (attr, key, content) => {
    let el = head.querySelector(`meta[${attr}="${key}"]`);
    if (!el) { el = document.createElement('meta'); el.setAttribute(attr, key); head.appendChild(el); }
    el.setAttribute('content', content);
  };
  const canonical = href => {
    let el = head.querySelector('link[rel="canonical"]');
    if (!el) { el = document.createElement('link'); el.rel = 'canonical'; head.appendChild(el); }
    el.href = href;
  };
  const jsonLd = (id, obj) => {
    let el = document.getElementById(id);
    if (!el) { el = document.createElement('script'); el.type = 'application/ld+json'; el.id = id; head.appendChild(el); }
    el.textContent = JSON.stringify(obj);
  };
  const noindex = () => meta('name', 'robots', 'noindex,follow');
  /** آدرس ریشه سایت (پوشه‌ای که صفحه در آن است)، مثل https://example.ir/ */
  const base = () => new URL('./', location.href).href;
  const page = ({ title, description, url, image, type = 'website' }) => {
    document.title = title;
    meta('name', 'description', description);
    canonical(url);
    meta('property', 'og:title', title); meta('property', 'og:description', description); meta('property', 'og:url', url);
    meta('property', 'og:type', type); meta('property', 'og:locale', 'fa_IR');
    if (image) meta('property', 'og:image', abs(image));
    meta('name', 'twitter:card', image ? 'summary_large_image' : 'summary');
    meta('name', 'twitter:title', title); meta('name', 'twitter:description', description);
    if (image) meta('name', 'twitter:image', abs(image));
  };
  return { abs, clean, trim, meta, canonical, jsonLd, noindex, base, page };
})();
