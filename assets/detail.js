'use strict';
(() => {
 const $=s=>document.querySelector(s), esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])), nl=v=>esc(v).replace(/\n/g,'<br>');
 const qs=new URLSearchParams(location.search); $('#year').textContent=String(new Date().getFullYear());
 const nav=$('.nav'), btn=$('.menu-toggle'); if(btn&&nav)btn.addEventListener('click',()=>{const o=nav.classList.toggle('open');btn.setAttribute('aria-expanded',String(o));});
 async function get(url){const r=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'دریافت اطلاعات ناموفق بود.');return d;}

 function initLightbox(b){
  const links=[...document.querySelectorAll('#building-content [data-lightbox]')]; if(!links.length) return;
  let box=null,idx=0,opener=null;
  const alt=i=>(b.image_alts&&b.image_alts[i])||`${b.title} - تصویر ${i+1}`;
  function show(i){idx=(i+links.length)%links.length;const im=box.querySelector('img');im.src=b.images[idx];im.alt=alt(idx);box.querySelector('.lb-cap').textContent=`${alt(idx)} (${idx+1} از ${links.length})`;}
  function close(){if(!box)return;box.remove();box=null;document.removeEventListener('keydown',onKey);document.body.classList.remove('lb-open');if(opener)opener.focus();}
  function onKey(e){if(e.key==='Escape')close();else if(e.key==='ArrowLeft')show(idx+1);else if(e.key==='ArrowRight')show(idx-1);else if(e.key==='Tab'){const f=[...box.querySelectorAll('button')];const first=f[0],last=f[f.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}}
  function open(i,from){
   opener=from;box=document.createElement('div');box.className='lightbox';box.setAttribute('role','dialog');box.setAttribute('aria-modal','true');box.setAttribute('aria-label','نمایش بزرگ تصاویر ساختمان');
   box.innerHTML='<button type="button" class="lb-close" aria-label="بستن">×</button><button type="button" class="lb-prev" aria-label="تصویر قبلی">›</button><figure><img alt=""><figcaption class="lb-cap"></figcaption></figure><button type="button" class="lb-next" aria-label="تصویر بعدی">‹</button>';
   document.body.append(box);document.body.classList.add('lb-open');
   box.addEventListener('click',e=>{if(e.target===box||e.target.classList.contains('lb-close'))close();else if(e.target.classList.contains('lb-prev'))show(idx-1);else if(e.target.classList.contains('lb-next'))show(idx+1);});
   document.addEventListener('keydown',onKey);show(i);box.querySelector('.lb-close').focus();
  }
  links.forEach((a,i)=>a.addEventListener('click',e=>{e.preventDefault();open(i,a);}));
 }
 if($('#building-content')){
  const slug=qs.get('slug')||'';
  const faDigits=v=>String(v).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const isNum=v=>/^-?\d+(\.\d+)?$/.test(String(v));
  const fa=v=>Number(v).toLocaleString('fa-IR',{maximumFractionDigits:2});
  const num=(v,unit)=>v===''||v==null?'':(isNum(v)?fa(v)+(unit?' '+unit:''):esc(v));   // legacy free-text values are shown as typed
  const year=v=>v===''?'':(isNum(v)?faDigits(v):esc(v));
  const row=(k,v)=>v?`<div><dt>${k}</dt><dd>${v}</dd></div>`:'';
  const group=(title,rows)=>{const body=rows.filter(Boolean).join('');return body?`<section class="spec-group"><h2>${title}</h2><dl class="spec-list">${body}</dl></section>`:'';};
  const chips=(title,list)=>list&&list.length?`<section class="spec-group"><h2>${title}</h2><ul class="chip-list">${list.map(x=>`<li>${esc(x)}</li>`).join('')}</ul></section>`:'';
  const block=(title,text)=>text?`<section class="spec-group"><h2>${title}</h2><p>${nl(text)}</p></section>`:'';
  get(`api/building.php?slug=${encodeURIComponent(slug)}`).then(d=>{
   const b=d.building;
   document.title=`${b.title} | ${d.site.name}`;
   const meta=document.querySelector('meta[name="description"]'); if(meta) meta.content=String(b.short_description||b.description||meta.content).replace(/\s+/g,' ').slice(0,155);
   const place=[b.city,b.location].filter(Boolean).join('، ');
   const units=b.units_count===''?'':num(b.units_count,'واحد');
   const unitArea=b.unit_area_min!==''&&b.unit_area_max!==''?`${num(b.unit_area_min)} تا ${num(b.unit_area_max,'مترمربع')}`:(b.unit_area_min!==''?`از ${num(b.unit_area_min,'مترمربع')}`:(b.unit_area_max!==''?`تا ${num(b.unit_area_max,'مترمربع')}`:''));
   const pct=b.progress_percent!==''&&isNum(b.progress_percent)?Math.min(100,Math.max(0,Number(b.progress_percent))):null;
   const progress=pct===null?'':`<div class="progress" role="progressbar" aria-label="درصد پیشرفت" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${pct}"><span data-w="${pct}"></span></div><small>${fa(pct)}٪ پیشرفت</small>`;
   const hasMap=isNum(b.latitude)&&isNum(b.longitude)&&b.latitude!==''&&b.longitude!=='';
   const map=hasMap?`<a class="text-link" href="https://www.openstreetmap.org/?mlat=${encodeURIComponent(b.latitude)}&mlon=${encodeURIComponent(b.longitude)}#map=17/${encodeURIComponent(b.latitude)}/${encodeURIComponent(b.longitude)}" target="_blank" rel="noopener noreferrer">مشاهده روی نقشه ↗</a>`:'';
   const specs=[
    group('اطلاعات کلی',[row('نوع کاربری',esc(b.project_type)),row('وضعیت',esc(b.status)),row('سازنده / مجری',esc(b.builder)),row('طراح / معمار',esc(b.architect)),row('سال شروع ساخت',year(b.start_year)),row('سال تکمیل / تحویل',year(b.completion_year)),row('تاریخ تحویل',esc(b.delivery_date)),row('پیشرفت فیزیکی',progress),row('شماره پروانه ساخت',esc(b.permit_number))]),
    group('آدرس و موقعیت',[row('استان',esc(b.province)),row('شهر',esc(b.city)),row('محدوده / فاز',esc(b.location)),row('نشانی',esc(b.address)),row('کد پستی',b.postal_code?faDigits(esc(b.postal_code)):''),row('نقشه',map)]),
    group('مشخصات فنی',[row('تعداد طبقات',num(b.floors)),row('طبقات زیرزمین',b.underground_floors===''?'':num(b.underground_floors)),row('تعداد کل واحدها',num(b.units_count)),row('واحد در هر طبقه',num(b.units_per_floor)),row('زیربنای کل',num(b.area,'مترمربع')),row('مساحت زمین',num(b.land_area,'مترمربع')),row('متراژ واحدها',unitArea),row('نوع سازه',esc(b.structure_type)),row('نما',esc(b.facade))]),
    group('پارکینگ، آسانسور و انباری',[row('تعداد پارکینگ',b.parking_spaces===''?'':num(b.parking_spaces)),row('نوع پارکینگ',esc(b.parking_type)),row('تعداد آسانسور',b.elevators===''?'':num(b.elevators)),row('تعداد انباری',b.storage_rooms===''?'':num(b.storage_rooms))]),
    chips('امکانات رفاهی و ایمنی',b.facilities),
    block('سایر امکانات',b.amenities),
    chips('انشعابات و خدمات',b.utilities),
    group('تأسیسات',[row('سیستم گرمایش',esc(b.heating_system)),row('سیستم سرمایش',esc(b.cooling_system)),row('نوع برق',esc(b.electricity_type))]),
    block('توضیحات تأسیسات',b.utilities_notes),
    block('توضیحات تکمیلی',b.notes)
   ].join('');
   const aside=[['نوع کاربری',esc(b.project_type)],['موقعیت',esc(place||'ثبت نشده')],['وضعیت',esc(b.status||'ثبت نشده')],['تعداد طبقات',num(b.floors)],['تعداد واحدها',units],['زیربنا',num(b.area,'مترمربع')]].filter(x=>x[1]).map(x=>`<div><small>${x[0]}</small><b>${x[1]}</b></div>`).join('');
   const draft=b.is_published?'':'<div class="alert draft-note" role="status">این ساختمان در حالت پیش‌نویس است و فقط برای مدیر قابل مشاهده است.</div>';
   $('#building-content').innerHTML=`<section class="building-hero"><a class="back-link" href="index.html#projects">← بازگشت به ساختمان‌ها</a><span class="status">${esc(b.status)}</span>${b.project_type?`<span class="status type-chip">${esc(b.project_type)}</span>`:''}<h1>${esc(b.title)}</h1><p>⌖ ${esc(place||'موقعیت ثبت نشده')}</p>${b.short_description?`<p class="hero-lead">${nl(b.short_description)}</p>`:''}</section><section class="building-layout"><div class="building-main">${draft}${b.images.length?`<div class="building-gallery">${b.images.map((src,i)=>{const alt=(b.image_alts&&b.image_alts[i])||`${b.title} - تصویر ${i+1}`;return `<a href="${esc(src)}" data-lightbox="${i}" aria-label="بزرگ‌نمایی: ${esc(alt)}"><img src="${esc(src)}" loading="${i?'lazy':'eager'}" alt="${esc(alt)}"></a>`;}).join('')}</div>`:'<div class="project-placeholder detail-placeholder">⌂</div>'}<article class="detail-copy"><h2>درباره این ساختمان</h2><p>${nl(b.description||b.short_description||'توضیحی برای این ساختمان ثبت نشده است.')}</p></article>${specs?`<div class="spec-groups">${specs}</div>`:''}</div><aside class="building-facts"><h2>مشخصات ساختمان</h2>${aside}<a class="btn primary" href="index.html?project=${encodeURIComponent(b.id)}#contact">درخواست اطلاعات این ساختمان</a><a class="text-link" href="tel:${esc(String(d.phone||'').replace(/[^0-9+]/g,''))}">تماس مستقیم با شرکت</a></aside></section>`;
   document.querySelectorAll('#building-content [data-w]').forEach(el=>{el.style.width=el.dataset.w+'%';});
   initLightbox(b);
  }).catch(e=>{$('#building-content').innerHTML=`<div class="admin-wrap"><h1>${esc(e.message)}</h1><a class="text-link" href="index.html#projects">بازگشت به ساختمان‌ها</a></div>`;});
 }
 if($('#page-content')){
  const slug=qs.get('slug')||'about';
  get(`api/page.php?slug=${encodeURIComponent(slug)}`).then(d=>{const p=d.page;document.title=`${p.meta_title||p.title} | ${d.site.name}`;document.querySelector('meta[name="description"]').content=p.meta_description||p.content.replace(/\s+/g,' ').slice(0,155);$('#page-content').innerHTML=`<div class="content-page-head"><span class="eyebrow blue">برج افراز الماس پردیس</span><h1>${esc(p.title)}</h1></div><article class="content-body">${nl(p.content)}</article>${d.services.length?`<div class="services-grid">${d.services.map(s=>`<article class="service-card"><div class="service-icon">${esc(s.icon||'◆')}</div><h3>${esc(s.title)}</h3><p>${nl(s.description)}</p></article>`).join('')}</div>`:''}`;}).catch(e=>{$('#page-content').innerHTML=`<div class="admin-wrap"><h1>${esc(e.message)}</h1><a class="text-link" href="index.html">بازگشت به خانه</a></div>`;});
 }
})();
