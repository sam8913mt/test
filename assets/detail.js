'use strict';
(() => {
 const $=s=>document.querySelector(s), esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])), nl=v=>esc(v).replace(/\n/g,'<br>');
 const qs=new URLSearchParams(location.search); $('#year').textContent=String(new Date().getFullYear());
 const nav=$('.nav'), btn=$('.menu-toggle'); if(btn&&nav)btn.addEventListener('click',()=>{const o=nav.classList.toggle('open');btn.setAttribute('aria-expanded',String(o));});
 async function get(url){const r=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'دریافت اطلاعات ناموفق بود.');return d;}
 if($('#building-content')){
  const FA='۰۱۲۳۴۵۶۷۸۹', fa=v=>String(v??'').replace(/[0-9]/g,d=>FA[d]);

  const nfmt=new Intl.NumberFormat('fa-IR'), dateFa=v=>{const t=new Date(String(v).replace(' ','T')+'Z');return isNaN(t)?'':t.toLocaleDateString('fa-IR',{year:'numeric',month:'long',day:'numeric'})};
  const starsTxt=r=>'★'.repeat(r)+'☆'.repeat(5-r);
  const unitsHtml=d=>{
   if(!d.units||!d.units.length)return '';
   const sm=d.units_summary, hasPrice=d.units.some(u=>u.price!=null);
   const chips=[['','همه'],['available','موجود'],['reserved','رزرو شده'],['sold','فروخته‌شده']].map(([k,l],i)=>`<button type="button" class="unit-chip${i?'':' active'}" data-unit-filter="${k}">${l}</button>`).join('');
   const rows=d.units.map(u=>`<tr class="${esc(u.status)}" data-status="${esc(u.status)}"><td><b>${esc(fa(u.unit_number))}</b>${u.images.length?` <a class="text-link" href="${esc(u.images[0])}" target="_blank" rel="noopener">تصویر</a>`:''}</td><td>${u.floor!=null?esc(fa(u.floor)):'—'}</td><td>${u.area!=null?esc(fa(u.area))+' مترمربع':'—'}</td><td>${u.bedrooms!=null?esc(fa(u.bedrooms)):'—'}</td><td>${esc(u.direction||'—')}</td>${hasPrice?`<td>${u.price!=null?esc(nfmt.format(u.price))+' تومان':'—'}</td>`:''}<td class="keep"><span class="badge-st ${esc(u.status)}">${esc(u.status_label)}</span></td></tr>`).join('');
   return `<section class="unit-block" id="units"><h2>واحدهای ساختمان</h2><p class="avail-box">از <b>${esc(nfmt.format(sm.total))}</b> واحد ثبت‌شده: <b>${esc(nfmt.format(sm.available))}</b> موجود، <b>${esc(nfmt.format(sm.reserved))}</b> رزرو شده و <b>${esc(nfmt.format(sm.sold))}</b> فروخته‌شده است.</p><div class="unit-filter" role="group" aria-label="فیلتر وضعیت واحد">${chips}</div><div class="table-wrap"><table class="unit-table"><thead><tr><th class="keep">واحد</th><th>طبقه</th><th>متراژ</th><th>اتاق</th><th>جهت</th>${hasPrice?'<th>قیمت</th>':''}<th class="keep">وضعیت</th></tr></thead><tbody>${rows}</tbody></table></div></section>`;
  };
  const reviewsHtml=d=>{
   const rs=d.review_summary, list=d.reviews||[];
   const sum=rs.count?`<div class="rating-summary"><span class="big">${esc(fa(String(rs.average).replace('.','٫')))}</span><div><span class="stars" aria-hidden="true">${starsTxt(Math.round(rs.average))}</span><div class="muted">از ${esc(nfmt.format(rs.count))} نظر تأییدشده</div></div></div>`:'<p class="muted">هنوز نظری برای این ساختمان نمایش داده نمی‌شود.</p>';
   const items=list.map(r=>`<div class="review-item"><div class="who"><b>${esc(r.name)}</b><span class="stars" aria-label="امتیاز ${r.rating} از ۵">${starsTxt(r.rating)}</span></div><p>${nl(r.comment)}</p><small class="muted">${esc(dateFa(r.created_at))}</small></div>`).join('');
   const stars=[5,4,3,2,1].map(v=>`<input type="radio" name="rating" id="rt${v}" value="${v}"><label for="rt${v}" title="${v} از ۵">★</label>`).join('');
   return `<section class="review-block" id="reviews"><h2>نظرات مشتریان</h2>${sum}${items}<form class="review-form" id="review-form" novalidate><h3>ثبت نظر شما</h3><input type="hidden" name="project_id" value="${d.building.id}"><label>نام<input name="name" maxlength="60" autocomplete="name"></label><div><span style="font-size:14px;font-weight:600">امتیاز</span><div class="star-input" id="rating-input" role="radiogroup" aria-label="امتیاز">${stars}</div></div><label>متن نظر<textarea name="comment" rows="4" maxlength="1000"></textarea></label><div class="hp" aria-hidden="true"><label>وب‌سایت<input name="website" tabindex="-1" autocomplete="off"></label></div><div class="form-msg" id="review-msg" role="status" hidden></div><button class="btn primary" type="submit">ارسال نظر</button><small class="muted">نظر شما پس از بررسی مدیر نمایش داده می‌شود. ارسال لینک مجاز نیست.</small></form></section>`;
  };
  const bindUnitsReviews=d=>{
   document.querySelectorAll('[data-unit-filter]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-unit-filter]').forEach(x=>x.classList.toggle('active',x===b));const f=b.dataset.unitFilter;document.querySelectorAll('.unit-table tbody tr').forEach(r=>{r.hidden=!!f&&r.dataset.status!==f})}));
   const form=$('#review-form'); if(!form)return;
   form.addEventListener('submit',async ev=>{
    ev.preventDefault(); const box=$('#review-msg'), btn=form.querySelector('button[type=submit]');
    const show=(t,m)=>{box.hidden=false;box.className='form-msg '+t;box.textContent=m};
    const name=form.elements.name.value.trim(), comment=form.elements.comment.value.trim(), rating=form.querySelector('input[name=rating]:checked');
    if(name.length<2)return show('error','نام را وارد کنید (حداقل ۲ نویسه).'); if(!rating)return show('error','امتیاز را انتخاب کنید.'); if(comment.length<10)return show('error','متن نظر باید حداقل ۱۰ نویسه باشد.');
    const fd=new FormData(form); fd.set('csrf',d.csrf||''); btn.disabled=true;
    try{
     const r=await fetch('api/review.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}}); const j=await r.json().catch(()=>({}));
     if(r.ok&&j.ok){show('success',j.message);form.reset()} else show('error',(j.errors&&Object.values(j.errors)[0])||j.message||'ثبت نظر ناموفق بود.');
    }catch(e){show('error','ارتباط با سرور برقرار نشد. دوباره تلاش کنید.')} finally{btn.disabled=false}
   });
  };
  const slug=qs.get('slug')||'';
  const fact=(label,value,unit)=>value===''||value==null?'':`<div><small>${esc(label)}</small><b>${esc(fa(value))}${unit?' '+esc(unit):''}</b></div>`;
  const section=sec=>{
   if(sec.type==='list') return `<section class="spec-section"><h2>${esc(sec.title)}</h2><dl class="spec-grid">${sec.items.map(i=>`<div><dt>${esc(i.label)}</dt><dd>${esc(fa(i.value))}${i.unit?' '+esc(i.unit):''}</dd></div>`).join('')}</dl></section>`;
   if(sec.type==='chips') return `<section class="spec-section"><h2>${esc(sec.title)}</h2>${sec.chips.length?`<ul class="chip-list">${sec.chips.map(c=>`<li>✓ ${esc(c)}</li>`).join('')}</ul>`:''}${sec.text?`<p>${nl(sec.text)}</p>`:''}</section>`;
   return `<section class="spec-section"><h2>${esc(sec.title)}</h2><p>${nl(sec.text)}</p></section>`;
  };
  get(`api/building.php?slug=${encodeURIComponent(slug)}`).then(d=>{
   const b=d.building;
   if(window.SEO){
    const base=SEO.base(), url=new URL('building.html?slug='+encodeURIComponent(b.slug),location.href).href, place=b.address.city||b.location||'پردیس';
    const composed=`${b.title}؛ ${b.project_type||'ساختمان'} ${b.status} در ${place}. مشخصات، تصاویر و ثبت درخواست مشاوره در وب‌سایت ${d.site.name}.`;
    const own=SEO.clean(b.description);
    const desc=SEO.trim(own.length>=70?own:(own?own.replace(/[.؛،\s]+$/,'')+'. '+composed:composed),155);
    SEO.page({title:`${b.title} | ${d.site.name}`,description:desc,url,image:b.images[0]||d.site.logo||'assets/logo.webp',type:'website'});
    const addr={'@type':'PostalAddress',addressCountry:'IR',addressLocality:b.address.city||'پردیس',addressRegion:b.address.province||undefined,streetAddress:b.address.street||undefined,postalCode:b.address.postal_code||undefined};
    const complex={'@type':'ApartmentComplex','@id':url+'#building',name:b.title,description:desc,url,image:b.images.length?b.images.map(SEO.abs):undefined,address:addr,
     geo:b.map?{'@type':'GeoCoordinates',latitude:b.map.lat,longitude:b.map.lng}:undefined,numberOfAccommodationUnits:b.units_count?Number(b.units_count):undefined};
    const crumbs={'@type':'BreadcrumbList',itemListElement:[{'@type':'ListItem',position:1,name:d.site.name,item:base},{'@type':'ListItem',position:2,name:'ساختمان‌ها',item:base+'#projects'},{'@type':'ListItem',position:3,name:b.title,item:url}]};
    SEO.jsonLd('building-schema',{'@context':'https://schema.org','@graph':[complex,crumbs]});
   }else{document.title=`${b.title} | ${d.site.name}`;}
   const progress=b.progress==null?'':`<div class="progress-box"><div class="progress-label"><span>پیشرفت فیزیکی</span><b>${esc(fa(b.progress))}٪</b></div><div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${b.progress}"><span style="width:${b.progress}%"></span></div></div>`;
   const sections=b.sections.map(section).join('');
   $('#building-content').innerHTML=`<section class="building-hero"><a class="back-link" href="index.html#projects">← بازگشت به ساختمان‌ها</a><span class="status">${esc(b.status)}</span>${b.project_type?`<span class="status type-tag">${esc(b.project_type)}</span>`:''}<h1>${esc(b.title)}</h1><p>⌖ ${esc(fa(b.location||'موقعیت ثبت نشده'))}</p></section><section class="building-layout"><div class="building-main">${b.images.length?`<div class="building-gallery">${b.images.map((src,i)=>`<a href="${esc(src)}" target="_blank" rel="noopener"><img src="${esc(src)}" loading="${i?'lazy':'eager'}" alt="${esc(b.title)} - تصویر ${fa(i+1)}"></a>`).join('')}</div>`:'<div class="project-placeholder detail-placeholder">⌂</div>'}<article class="detail-copy"><h2>درباره این ساختمان</h2><p>${nl(b.description||'توضیحی برای این ساختمان ثبت نشده است.')}</p></article>${sections}${unitsHtml(d)}${reviewsHtml(d)}</div><aside class="building-facts"><h2>مشخصات ساختمان</h2>${progress}${fact('موقعیت',fa(b.location)||'')}${fact('وضعیت',b.status)}${fact('تعداد طبقات',b.floors)}${fact('تعداد واحدها',b.units_count)}${fact('زیربنای کل',b.built_area,'مترمربع')}${b.built_area?'':fact('متراژ',b.area)}${fact('سال تحویل',b.delivery_year)}${d.units_summary&&d.units_summary.total?fact('واحدهای موجود',d.units_summary.available+' از '+d.units_summary.total):''}${b.map?`<a class="text-link" href="${esc(b.map.url)}" target="_blank" rel="noopener">مشاهده روی نقشه ↗</a>`:''}<a class="btn primary" href="index.html?project=${encodeURIComponent(b.id)}#contact">درخواست اطلاعات این ساختمان</a><a class="text-link" href="tel:${esc(String(d.phone||'').replace(/[^0-9+]/g,''))}">تماس مستقیم با شرکت</a></aside></section>`;
   bindUnitsReviews(d);
  }).catch(e=>{if(window.SEO)SEO.noindex();$('#building-content').innerHTML=`<div class="admin-wrap"><h1>${esc(e.message)}</h1><a class="text-link" href="index.html#projects">بازگشت به ساختمان‌ها</a></div>`;});
 }
 if($('#page-content')){
  const slug=qs.get('slug')||'about';
  const fa=v=>String(v??'').replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const aboutExtras=a=>{
   if(!a)return '';
   const nf=new Intl.NumberFormat('fa-IR'); let h='';
   if(a.image)h+=`<img class="about-image" src="${esc(a.image)}" alt="تصویر معرفی شرکت" loading="lazy">`;
   const block=(t,body)=>`<section class="about-block"><h2>${esc(t)}</h2>${body}</section>`;
   if(a.mission)h+=block('ماموریت',`<p>${nl(a.mission)}</p>`);
   if(a.vision)h+=block('چشم‌انداز',`<p>${nl(a.vision)}</p>`);
   if(a.history)h+=block('تاریخچه شرکت',`<p>${nl(a.history)}</p>`);
   if(a.milestones.length)h+=block('مسیر شرکت',`<ul class="timeline">${a.milestones.map(m=>`<li>${m.value?`<b>${esc(fa(m.value))}</b> — `:''}<b>${esc(m.title)}</b>${m.description?`<p>${nl(m.description)}</p>`:''}</li>`).join('')}</ul>`);
   if(a.stats.length)h+=block('شرکت در یک نگاه',`<div class="about-stats">${a.stats.map(x=>`<div class="about-stat"><strong>${esc(fa(x.value))}</strong><span>${esc(x.title)}</span></div>`).join('')}</div>`);
   if(a.achievements.length)h+=block('دستاوردها',`<ul class="chip-list">${a.achievements.map(x=>`<li>✓ ${esc(x.title)}${x.value?` (${esc(fa(x.value))})`:''}</li>`).join('')}</ul>${a.achievements.some(x=>x.description)?a.achievements.filter(x=>x.description).map(x=>`<p><b>${esc(x.title)}:</b> ${nl(x.description)}</p>`).join(''):''}`);
   if(a.team.length)h+=block('تیم',`<div class="team-grid">${a.team.map(x=>`<div class="team-card">${x.image?`<img src="${esc(x.image)}" alt="${esc(x.title)}" loading="lazy">`:''}<b>${esc(x.title)}</b>${x.value?`<small>${esc(x.value)}</small>`:''}${x.description?`<p>${nl(x.description)}</p>`:''}</div>`).join('')}</div>`);
   const facts=[['سال آغاز فعالیت',a.founded_year],['شماره ثبت',a.registration_number],['مدیرعامل / مسئول',a.company_manager]].filter(x=>x[1]);
   if(facts.length||a.credentials)h+=block('اطلاعات قابل بررسی',`${facts.length?`<dl class="spec-grid">${facts.map(([k,v])=>`<div><dt>${esc(k)}</dt><dd>${esc(fa(v))}</dd></div>`).join('')}</dl>`:''}${a.credentials?`<p>${nl(a.credentials)}</p>`:''}`);
   return h;
  };

  get(`api/page.php?slug=${encodeURIComponent(slug)}`).then(d=>{const p=d.page;const pTitle=`${p.meta_title||p.title} | ${d.site.name}`,pDesc=p.meta_description||p.content.replace(/\s+/g,' ').slice(0,155);if(window.SEO){const base=SEO.base(),url=new URL('page.html?slug='+encodeURIComponent(p.slug),location.href).href;SEO.page({title:pTitle,description:pDesc,url,image:d.site.logo});SEO.jsonLd('page-schema',{'@context':'https://schema.org','@graph':[{'@type':p.slug==='about'?'AboutPage':'WebPage',name:p.title,url,inLanguage:'fa-IR',isPartOf:{'@type':'WebSite',name:d.site.name,alternateName:d.site.alternateNames||[],url:base}},{'@type':'BreadcrumbList',itemListElement:[{'@type':'ListItem',position:1,name:d.site.name,item:base},{'@type':'ListItem',position:2,name:p.title,item:url}]}]});}else{document.title=pTitle;}$('#page-content').innerHTML=`<div class="content-page-head"><span class="eyebrow blue">برج افراز الماس پردیس</span><h1>${esc(p.title)}</h1></div><article class="content-body">${nl(p.content)}</article>${aboutExtras(d.about)}${d.services.length?`<div class="services-grid">${d.services.map(s=>`<article class="service-card"><div class="service-icon">${esc(s.icon||'◆')}</div><h2>${esc(s.title)}</h2><p>${nl(s.description)}</p></article>`).join('')}</div>`:''}`;}).catch(e=>{$('#page-content').innerHTML=`<div class="admin-wrap"><h1>${esc(e.message)}</h1><a class="text-link" href="index.html">بازگشت به خانه</a></div>`;});
 }
})();
