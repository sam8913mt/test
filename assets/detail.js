'use strict';
(() => {
 const $=s=>document.querySelector(s), esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])), nl=v=>esc(v).replace(/\n/g,'<br>');
 const qs=new URLSearchParams(location.search); $('#year').textContent=String(new Date().getFullYear());
 const nav=$('.nav'), btn=$('.menu-toggle'); if(btn&&nav)btn.addEventListener('click',()=>{const o=nav.classList.toggle('open');btn.setAttribute('aria-expanded',String(o));});
 async function get(url){const r=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'دریافت اطلاعات ناموفق بود.');return d;}
 if($('#building-content')){
  const FA='۰۱۲۳۴۵۶۷۸۹', fa=v=>String(v??'').replace(/[0-9]/g,d=>FA[d]);
  const slug=qs.get('slug')||'';
  const fact=(label,value,unit)=>value===''||value==null?'':`<div><small>${esc(label)}</small><b>${esc(fa(value))}${unit?' '+esc(unit):''}</b></div>`;
  const section=sec=>{
   if(sec.type==='list') return `<section class="spec-section"><h2>${esc(sec.title)}</h2><dl class="spec-grid">${sec.items.map(i=>`<div><dt>${esc(i.label)}</dt><dd>${esc(fa(i.value))}${i.unit?' '+esc(i.unit):''}</dd></div>`).join('')}</dl></section>`;
   if(sec.type==='chips') return `<section class="spec-section"><h2>${esc(sec.title)}</h2>${sec.chips.length?`<ul class="chip-list">${sec.chips.map(c=>`<li>✓ ${esc(c)}</li>`).join('')}</ul>`:''}${sec.text?`<p>${nl(sec.text)}</p>`:''}</section>`;
   return `<section class="spec-section"><h2>${esc(sec.title)}</h2><p>${nl(sec.text)}</p></section>`;
  };
  get(`api/building.php?slug=${encodeURIComponent(slug)}`).then(d=>{
   const b=d.building; document.title=`${b.title} | ${d.site.name}`;
   document.querySelector('meta[name="description"]').content=(b.description||`${b.title} - ${b.status}`).replace(/\s+/g,' ').slice(0,155);
   const progress=b.progress==null?'':`<div class="progress-box"><div class="progress-label"><span>پیشرفت فیزیکی</span><b>${esc(fa(b.progress))}٪</b></div><div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${b.progress}"><span style="width:${b.progress}%"></span></div></div>`;
   const sections=b.sections.map(section).join('');
   $('#building-content').innerHTML=`<section class="building-hero"><a class="back-link" href="index.html#projects">← بازگشت به ساختمان‌ها</a><span class="status">${esc(b.status)}</span>${b.project_type?`<span class="status type-tag">${esc(b.project_type)}</span>`:''}<h1>${esc(b.title)}</h1><p>⌖ ${esc(fa(b.location||'موقعیت ثبت نشده'))}</p></section><section class="building-layout"><div class="building-main">${b.images.length?`<div class="building-gallery">${b.images.map((src,i)=>`<a href="${esc(src)}" target="_blank" rel="noopener"><img src="${esc(src)}" loading="${i?'lazy':'eager'}" alt="${esc(b.title)} - تصویر ${fa(i+1)}"></a>`).join('')}</div>`:'<div class="project-placeholder detail-placeholder">⌂</div>'}<article class="detail-copy"><h2>درباره این ساختمان</h2><p>${nl(b.description||'توضیحی برای این ساختمان ثبت نشده است.')}</p></article>${sections}</div><aside class="building-facts"><h2>مشخصات ساختمان</h2>${progress}${fact('موقعیت',fa(b.location)||'')}${fact('وضعیت',b.status)}${fact('تعداد طبقات',b.floors)}${fact('تعداد واحدها',b.units_count)}${fact('زیربنای کل',b.built_area,'مترمربع')}${b.built_area?'':fact('متراژ',b.area)}${fact('سال تحویل',b.delivery_year)}${b.map?`<a class="text-link" href="${esc(b.map.url)}" target="_blank" rel="noopener">مشاهده روی نقشه ↗</a>`:''}<a class="btn primary" href="index.html?project=${encodeURIComponent(b.id)}#contact">درخواست اطلاعات این ساختمان</a><a class="text-link" href="tel:${esc(String(d.phone||'').replace(/[^0-9+]/g,''))}">تماس مستقیم با شرکت</a></aside></section>`;
  }).catch(e=>{$('#building-content').innerHTML=`<div class="admin-wrap"><h1>${esc(e.message)}</h1><a class="text-link" href="index.html#projects">بازگشت به ساختمان‌ها</a></div>`;});
 }
 if($('#page-content')){
  const slug=qs.get('slug')||'about';
  get(`api/page.php?slug=${encodeURIComponent(slug)}`).then(d=>{const p=d.page;document.title=`${p.meta_title||p.title} | ${d.site.name}`;document.querySelector('meta[name="description"]').content=p.meta_description||p.content.replace(/\s+/g,' ').slice(0,155);$('#page-content').innerHTML=`<div class="content-page-head"><span class="eyebrow blue">برج افراز الماس پردیس</span><h1>${esc(p.title)}</h1></div><article class="content-body">${nl(p.content)}</article>${d.services.length?`<div class="services-grid">${d.services.map(s=>`<article class="service-card"><div class="service-icon">${esc(s.icon||'◆')}</div><h3>${esc(s.title)}</h3><p>${nl(s.description)}</p></article>`).join('')}</div>`:''}`;}).catch(e=>{$('#page-content').innerHTML=`<div class="admin-wrap"><h1>${esc(e.message)}</h1><a class="text-link" href="index.html">بازگشت به خانه</a></div>`;});
 }
})();
