const { chromium } = require(process.env.PW_PATH || 'playwright');
const fs = require('fs');
const http = require('http'), path = require('path');
const SITE = process.env.SITE_ROOT || require('path').resolve(__dirname, '..');
const CSP = (fs.readFileSync(SITE + '/.htaccess', 'utf8').match(/Content-Security-Policy "([^"]+)"/) || [])[1];
const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.webp': 'image/webp', '.png': 'image/png', '.woff2': 'font/woff2', '.txt': 'text/plain' };
const server = http.createServer((req, res) => {
  const u = decodeURIComponent(req.url.split('?')[0]); const f = path.join(SITE, u === '/' ? 'index.html' : u);
  if (!f.startsWith(SITE) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) { res.writeHead(404); return res.end('nf'); }
  res.writeHead(200, { 'Content-Type': MIME[path.extname(f)] || 'application/octet-stream', 'Content-Security-Policy': CSP }); fs.createReadStream(f).pipe(res);
});
let ROOT = '';
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
const DATAURI = 'data:image/png;base64,' + PNG.toString('base64');
const hex = c => c.repeat(32);
let fails = 0; const log = [];
const t = (name, cond, extra) => { if (!cond) fails++; console.log((cond ? 'PASS ' : 'FAIL ') + name + (!cond && extra !== undefined ? '  -> ' + JSON.stringify(extra) : '')); };

// ---- in-page mock of api/*.php (NOT the real PHP; it only lets us exercise the real HTML/JS) ----
const mockScript = `
(() => {
  const state = window.__state = { requests: [], gets: [], projects: [], csrf: 'tok' };
  const DATAURI = ${JSON.stringify(DATAURI)};
  const mk = (id, extra) => Object.assign({ id, slug: 'b'+id, updated_at:'', title:'', project_type:'', status:'در حال ساخت', builder:'', architect:'', start_year:'', completion_year:'', delivery_date:'', progress_percent:'', permit_number:'', short_description:'', description:'', province:'', city:'', location:'', postal_code:'', address:'', latitude:'', longitude:'', floors:'', underground_floors:'', units_count:'', units_per_floor:'', area:'', land_area:'', unit_area_min:'', unit_area_max:'', structure_type:'', facade:'', parking_spaces:'', parking_type:'', elevators:'', storage_rooms:'', facilities:[], amenities:'', utilities:[], heating_system:'', cooling_system:'', electricity_type:'', utilities_notes:'', notes:'', featured:false, is_published:true, images:[], url:'building.html?slug=b'+id }, extra);
  window.__mk = mk;
  state.projects = (window.__seed || []).map(x => mk(x.id, x));
  const img = c => ({ name: c.repeat(32) + '.jpg', url: DATAURI });
  const entriesOf = fd => { const o = []; for (const [k, v] of fd.entries()) o.push([k, (v instanceof File) ? { file: v.name, size: v.size, type: v.type } : v]); return o; };
  const adminData = () => ({ warnings: [{level:'warn',text:'هشدار آزمایشی'},{level:'danger',text:'خطر آزمایشی'}], audit: [{at:'2026-10-29 10:00:00',at_iso:'2026-10-29T10:00:00+00:00',action:'login',detail:''},{at:'x',at_iso:'2026-10-29T11:00:00+00:00',action:'project_save',detail:'id=1'}], images_pending: 3, notifications: {email:false,bot:false}, username: 'admin', contacts_total: 250, projects: state.projects, services: [], pages: {}, contacts: [{id:1,name:'علی',phone:'0912',email:'',topic:'اطلاعات پروژه',project_title:'',message:'سلام',status:'new',created_at:'2026-10-29 10:00:00',created_at_iso:'2026-10-29T10:00:00+00:00'}], unread: 1, settings: { about:'', phone:'', email:'', address:'', tagline:'', founded_year:'', registration_number:'', company_manager:'', credentials:'' } });
  const json = (obj, status = 200) => new Response(JSON.stringify(obj), { status, headers: { 'Content-Type': 'application/json' } });
  const realFetch = window.fetch.bind(window);
  window.fetch = async (url, opts = {}) => {
    const u = String(url);
    if (u.includes('api/auth.php')) return json({ ok: true, authenticated: true, configured: true, csrf: 'tok' });
    if (u.includes('api/admin.php')) {
      if ((opts.method || 'GET') === 'GET') { state.gets.push(u); return json({ ok: true, csrf: 'tok', data: adminData() }); }
      const ent = entriesOf(opts.body); state.requests.push(ent);
      const get = n => (ent.find(e => e[0] === n) || [])[1];
      const all = n => ent.filter(e => e[0] === n).map(e => e[1]);
      if (get('title') === 'SERVER_ERR') return json({ ok: false, message: 'لطفاً خطاهای مشخص‌شده در فرم را اصلاح کنید.', errors: { title: 'خطای شبیه‌سازی‌شده سرور' } }, 422);
      if (get('action') === 'project_save') {
        const id = Number(get('id')); const old = state.projects.find(p => p.id === id);
        let imgs = old ? old.images.map(i => i.name) : [];
        const remove = all('remove_images[]'); const order = all('image_order[]');
        imgs = imgs.filter(n => !remove.includes(n)); imgs = order.filter(n => imgs.includes(n)).concat(imgs.filter(n => !order.includes(n)));
        ent.filter(e => e[0].startsWith('replace_images[') && e[1].file).forEach(e => { const h = e[0].slice(15, -1); const i = imgs.findIndex(n => n.startsWith(h)); if (i >= 0) imgs[i] = 'f'.repeat(32) + '.jpg'; });
        ent.filter(e => e[0] === 'images[]' && e[1].file).forEach(() => imgs.push('e'.repeat(32) + '.jpg'));
        const p = mk(id || state.projects.length + 1, old || {});
        for (const [k, v] of ent) { if (k.endsWith('[]') || ['id','csrf','action'].includes(k)) continue; if (k in p && typeof p[k] !== 'object') p[k] = (k === 'featured' || k === 'is_published') ? true : v; }
        p.featured = get('featured') === '1'; p.is_published = get('is_published') === '1';
        p.facilities = all('facilities[]'); p.utilities = all('utilities[]');
        p.images = imgs.map(n => ({ name: n, url: DATAURI }));
        if (!old) state.projects.unshift(p); else state.projects[state.projects.indexOf(old)] = p;
        return json({ ok: true, message: 'ذخیره شد.', csrf: 'tok', data: adminData() });
      }
      return json({ ok: true, message: 'انجام شد.', csrf: 'tok', data: adminData() });
    }
    if (u.includes('api/building.php')) return json(window.__building);
    if (u.includes('api/page.php')) return json({ ok: true, site: { name: 'برج افراز الماس پردیس' }, page: { slug: 'privacy', title: 'حریم خصوصی', content: 'متن حریم خصوصی\\nخط دوم', meta_title: '', meta_description: '' }, services: [] });
    if (u.includes('api/site.php')) {
      const pg = Number(new URL(u, location.href).searchParams.get('page') || 1); const all = Array.from({ length: 25 }, (_, i) => ({ id: i + 1, slug: 'p' + (i + 1), title: 'ساختمان ' + (i + 1), location: 'پردیس', status: 'در حال ساخت', description: 'توضیح', image: '', featured: false, url: 'building.html?slug=p' + (i + 1) }));
      return json({ ok: true, csrf: '1.' + 'a'.repeat(64), site: { name: 'برج افراز الماس پردیس', url: 'http://x', logo: '' }, settings: { tagline: 'ت', about: 'درباره', phone: '+989123365460', email: '', address: 'پردیس', founded_year: '', registration_number: '', company_manager: '', credentials: '' }, projects: all.slice((pg - 1) * 12, pg * 12), total: 25, page: pg, perPage: 12, allProjects: all.map(p => ({ id: p.id, title: p.title })), services: [], faq: [{ q: 'پرسش', a: 'پاسخ' }], selectedProject: '' });
    }
    return realFetch(url, opts);
  };
})();`;

async function newPage(browser, viewport, seed) {
  const ctx = await browser.newContext({ viewport: viewport || { width: 1280, height: 900 } });
  const page = await ctx.newPage(); const errors = [];
  page.on('pageerror', e => errors.push(String(e))); page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(m.text()); });
  if (seed) await page.addInitScript(sd => { window.__seed = sd; }, seed);
  await page.addInitScript(mockScript); return { page, errors, ctx };
}
const reqs = page => page.evaluate(() => window.__state.requests);
const setProjects = (page, list) => page.evaluate(l => { window.__state.projects = l.map(x => window.__mk(x.id, x)); }, list);
const legacyImgs = ['a', 'b', 'c'].map(c => ({ name: hex(c) + '.jpg', url: DATAURI }));

(async () => {
  await new Promise(r => server.listen(0, '127.0.0.1', r)); ROOT = 'http://127.0.0.1:' + server.address().port + '/';
  t('CSP header parsed from .htaccess', !!CSP && CSP.includes("script-src 'self'"));
  const browser = await chromium.launch();
  // ================= ADMIN =================
  const seed = [
    { id: 1, title: 'برج قدیمی', status: 'تکمیل‌شده', location: 'پردیس فاز ۳', floors: '۱۲ طبقه', area: '۱۸۰۰ مترمربع', description: 'توضیح', images: legacyImgs, is_published: true },
    { id: 2, title: 'برج دوازده‌تصویری', status: 'در حال ساخت', images: 'abcdefghijkl'.split('').map(c => ({ name: hex(c) + '.jpg', url: DATAURI })), city: 'پردیس', project_type: 'مسکونی' },
  ];
  let { page, errors, ctx } = await newPage(browser, null, seed);
  await page.goto(ROOT + 'admin.html'); await page.waitForSelector('#admin-app:not([hidden])', { timeout: 8000 }).catch(async e => { console.log('BOOT ERRORS', errors, await page.textContent('#admin-loading'), ROOT); throw e; });
  t('admin: login view hidden, panel visible', await page.isHidden('#login-view') && await page.isVisible('#admin-app'));
  t('admin: 7 form sections rendered', await page.locator('.form-section').count() === 7);
  t('admin: project list shows 2 buildings', await page.locator('#project-list .admin-project').count() === 2);
  const required = await page.$$eval('#project-form [required]', els => els.map(e => e.name).sort());
  t('admin: required fields = city,project_type,status,title', JSON.stringify(required) === JSON.stringify(['city', 'project_type', 'status', 'title']), required);

  // T2 empty submit
  const before = (await reqs(page)).length;
  await page.click('#project-submit');
  t('validation: empty form blocked, summary visible', await page.isVisible('#form-errors'));
  t('validation: errors on title/project_type/city', (await page.locator('.field-error:visible').allTextContents()).filter(x => /الزامی/.test(x)).length === 3);
  t('validation: nothing sent to server', (await reqs(page)).length === before);

  // T3 invalid values
  await page.fill('[name=title]', 'برج تست'); await page.selectOption('[name=project_type]', 'مسکونی'); await page.fill('[name=city]', 'پردیس');
  await page.fill('[name=floors]', 'abc'); await page.fill('[name=postal_code]', '123'); await page.fill('[name=latitude]', '35.7');
  await page.fill('[name=unit_area_min]', '100'); await page.fill('[name=unit_area_max]', '50');
  await page.fill('[name=start_year]', '1405'); await page.fill('[name=completion_year]', '1400');
  await page.fill('[name=units_count]', '10'); await page.fill('[name=units_per_floor]', '20'); await page.fill('[name=progress_percent]', '150');
  await page.click('#project-submit');
  const errTexts = await page.locator('.field-error:visible').allTextContents(); const all = errTexts.join('|');
  t('validation: non-numeric floors rejected', /تعداد طبقات.*عدد صحیح/.test(all), errTexts);
  t('validation: postal code must be 10 digits', /کد پستی.*۱۰|کد پستی.*10/.test(all) || /کد پستی/.test(all));
  t('validation: latitude without longitude rejected', /عرض و طول/.test(all));
  t('validation: unit area min>max rejected', /بیشترین متراژ/.test(all));
  t('validation: completion year before start rejected', /سال تکمیل/.test(all));
  t('validation: units/floor > units rejected', /واحد در هر طبقه/.test(all));
  t('validation: progress >100 rejected', /درصد پیشرفت.*100/.test(all));
  t('validation: still nothing sent', (await reqs(page)).length === before);

  // T4 valid create with Persian digits
  await page.fill('[name=floors]', '۱۲'); await page.fill('[name=postal_code]', '۱۲۳۴۵۶۷۸۹۰'); await page.fill('[name=longitude]', '51.7551');
  await page.fill('[name=unit_area_min]', '۶۵'); await page.fill('[name=unit_area_max]', '۱۲۰٫۵'); await page.fill('[name=start_year]', '۱۴۰۳'); await page.fill('[name=completion_year]', '۱۴۰۶');
  await page.fill('[name=units_count]', '۴۸'); await page.fill('[name=units_per_floor]', '۴'); await page.fill('[name=progress_percent]', '۴۵'); await page.fill('[name=area]', '۱۸۰۰٫۵');
  await page.check('input[name="facilities[]"][value="استخر"]'); await page.check('input[name="facilities[]"][value="لابی و پذیرش"]'); await page.check('input[name="utilities[]"][value="گاز شهری"]');
  await page.selectOption('[name=parking_type]', 'زیرزمینی'); await page.fill('[name=parking_spaces]', '۵۰'); await page.fill('[name=elevators]', '۲');
  await page.setInputFiles('#new-images', [{ name: 'a.png', mimeType: 'image/png', buffer: PNG }, { name: 'b.png', mimeType: 'image/png', buffer: PNG }]);
  t('images: previews for 2 new files', await page.locator('#new-previews figure').count() === 2);
  await page.click('#project-submit'); await page.waitForSelector('#admin-notice.success');
  const r1 = (await reqs(page)).filter(r => r.some(e => e[0] === 'action' && e[1] === 'project_save')).pop();
  const g = (r, n) => (r.find(e => e[0] === n) || [])[1]; const ga = (r, n) => r.filter(e => e[0] === n).map(e => e[1]);
  t('create: form submitted as project_save with id 0', g(r1, 'action') === 'project_save' && g(r1, 'id') === '0');
  t('create: Persian digits sent as typed (server normalizes)', g(r1, 'floors') === '۱۲' && g(r1, 'postal_code') === '۱۲۳۴۵۶۷۸۹۰');
  t('create: facilities[] and utilities[] sent', JSON.stringify(ga(r1, 'facilities[]')) === JSON.stringify(['لابی و پذیرش', 'استخر']) && JSON.stringify(ga(r1, 'utilities[]')) === JSON.stringify(['گاز شهری']), ga(r1, 'facilities[]'));
  t('create: 2 image files in images[]', r1.filter(e => e[0] === 'images[]' && e[1].file).length === 2);
  t('create: is_published sent (default checked), featured not sent', g(r1, 'is_published') === '1' && g(r1, 'featured') === undefined);
  t('create: list now has 3 buildings, form reset', await page.locator('#project-list .admin-project').count() === 3 && (await page.inputValue('[name=title]')) === '' && (await page.inputValue('[name=id]')) === '0');
  t('create: new-image previews cleared after save', await page.locator('#new-previews figure').count() === 0);

  // T5 edit legacy
  await page.locator('[data-do="edit-project"][data-id="1"]').click();
  t('edit: title loaded', await page.inputValue('[name=title]') === 'برج قدیمی');
  t('edit: legacy free-text floors/area loaded verbatim', await page.inputValue('[name=floors]') === '۱۲ طبقه' && await page.inputValue('[name=area]') === '۱۸۰۰ مترمربع');
  t('edit: status select loaded', await page.inputValue('[name=status]') === 'تکمیل‌شده');
  t('edit: heading + button switched to edit mode', /ویرایش ساختمان/.test(await page.textContent('#project-form-title')) && (await page.textContent('#project-submit')).includes('ذخیره تغییرات'));
  t('edit: gallery shows 3 tiles, first marked cover', await page.locator('.img-tile').count() === 3 && await page.locator('.img-tile').first().locator('.cover-badge').isVisible());
  t('edit: legacy unchanged values pass validation (city still required)', true);
  await page.click('#project-submit');
  t('edit: city is required even for legacy rows (shown as error)', (await page.locator('[data-error-for=city]').textContent()).includes('الزامی'));
  await page.fill('[name=city]', 'پردیس'); await page.selectOption('[name=project_type]', 'مسکونی');
  // T6 gallery operations
  await page.locator('.img-tile').nth(1).locator('[data-img=prev]').click();
  t('gallery: cover badge moved to the moved tile', (await page.locator('.img-tile').first().getAttribute('data-name')) === hex('b') + '.jpg' && await page.locator('.img-tile').first().locator('.cover-badge').isVisible());
  await page.locator('.img-tile').nth(2).locator('input[type=checkbox]').check();
  t('gallery: removed tile is dimmed', await page.locator('.img-tile').nth(2).evaluate(e => e.classList.contains('is-removed')));
  await page.locator('.img-tile').first().locator('input[type=file]').setInputFiles({ name: 'new.png', mimeType: 'image/png', buffer: PNG });
  await page.setInputFiles('#new-images', [{ name: 'extra.png', mimeType: 'image/png', buffer: PNG }]);
  await page.click('#project-submit'); await page.waitForSelector('#admin-notice.success');
  const r2 = (await reqs(page)).filter(r => g(r, 'action') === 'project_save').pop();
  t('gallery: edit sent id=1', g(r2, 'id') === '1');
  t('gallery: legacy floors sent unchanged', g(r2, 'floors') === '۱۲ طبقه');
  t('gallery: image_order = [b,a,c]', JSON.stringify(ga(r2, 'image_order[]')) === JSON.stringify([hex('b') + '.jpg', hex('a') + '.jpg', hex('c') + '.jpg']), ga(r2, 'image_order[]'));
  t('gallery: remove_images[] = [c]', JSON.stringify(ga(r2, 'remove_images[]')) === JSON.stringify([hex('c') + '.jpg']));
  t('gallery: replacement keyed by image id of b', r2.some(e => e[0] === 'replace_images[' + hex('b') + ']' && e[1].file === 'new.png'));
  const saved = await page.evaluate(() => window.__state.projects.find(p => p.id === 1));
  t('gallery: result = [replaced(b), a, +1 new]', saved.images.length === 3 && saved.images[0].name.startsWith('f') && saved.images[1].name === hex('a') + '.jpg' && saved.images[2].name.startsWith('e'), saved.images.map(i => i.name[0]));
  t('edit: saved values persisted and list updated', saved.city === 'پردیس' && saved.project_type === 'مسکونی');

  // T7 legacy changed to junk
  await page.locator('[data-do="edit-project"][data-id="1"]').click();
  await page.fill('[name=floors]', 'junk');
  await page.click('#project-submit');
  t('legacy: changed to junk -> rejected', /عدد صحیح/.test(await page.locator('[data-error-for=floors]').textContent()));
  await page.fill('[name=floors]', '۱۲'); 
  // T8 too many images
  await page.locator('[data-do="edit-project"][data-id="2"]').click();
  await page.setInputFiles('#new-images', [{ name: 'x.png', mimeType: 'image/png', buffer: PNG }]);
  t('images: 12 existing + 1 new rejected client-side', /حداکثر 12|۱۲/.test(await page.locator('[data-error-for=images]').textContent()));
  await page.locator('.img-tile').nth(0).locator('input[type=checkbox]').check();
  await page.setInputFiles('#new-images', [{ name: 'x.png', mimeType: 'image/png', buffer: PNG }]);
  t('images: after marking one removed, 12 total accepted', await page.locator('[data-error-for=images]').isHidden() || (await page.locator('[data-error-for=images]').textContent()) === '');
  await page.setInputFiles('#new-images', [{ name: 'bad.gif', mimeType: 'image/gif', buffer: PNG }]);
  t('images: unsupported type rejected', /مجاز نیست/.test(await page.locator('[data-error-for=images]').textContent()));
  await page.setInputFiles('#new-images', [{ name: 'big.png', mimeType: 'image/png', buffer: Buffer.alloc(5 * 1024 * 1024 + 10) }]);
  t('images: >5MB rejected', /۵ مگابایت/.test(await page.locator('[data-error-for=images]').textContent()));
  // T9 server errors mapping
  await page.setInputFiles('#new-images', []);
  await page.fill('[name=title]', 'SERVER_ERR');
  await page.click('#project-submit'); await page.waitForSelector('#form-errors:not([hidden])');
  t('server errors: field error shown under title', (await page.locator('[data-error-for=title]').textContent()).includes('شبیه‌سازی'));
  t('server errors: form data kept (not reset)', await page.inputValue('[name=title]') === 'SERVER_ERR');
  // T10 cancel
  await page.click('#cancel-edit');
  t('cancel edit: form reset to create mode', await page.inputValue('[name=title]') === '' && /افزودن/.test(await page.textContent('#project-form-title')) && await page.locator('.img-tile').count() === 0);
  t('cancel edit: is_published default checked again', await page.isChecked('[name=is_published]'));
  t('admin: no JS errors', errors.length === 0, errors);
  await page.setViewportSize({ width: 390, height: 800 }); await page.reload(); await page.waitForSelector('#admin-app:not([hidden])');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  t('admin mobile (390px): no horizontal overflow', overflow <= 1, overflow);
  await page.screenshot({ path: require('path').join(__dirname, 'admin_mobile.png') });
  await page.setViewportSize({ width: 1280, height: 900 }); await page.evaluate(() => document.querySelector('#building-form').scrollIntoView());
  await page.screenshot({ path: require('path').join(__dirname, 'admin_desktop.png') });
  await ctx.close();

  // ================= PUBLIC DETAIL =================
  const full = { ok: true, site: { name: 'برج افراز الماس پردیس', url: '', logo: '' }, phone: '+989123365460', building: Object.assign({}, { id: 7, slug: 'x', title: 'برج <script>alert(1)</script> الماس', project_type: 'مسکونی و تجاری', status: 'در حال ساخت', builder: 'شرکت سازنده', architect: 'مهندس طراح', start_year: '1403', completion_year: '1406', delivery_date: 'پاییز ۱۴۰۶', progress_percent: '45', permit_number: 'P-123', short_description: 'خلاصه کوتاه ساختمان', description: 'توضیح کامل\nخط دوم', province: 'تهران', city: 'پردیس', location: 'فاز ۸', postal_code: '1234567890', address: 'خیابان اصلی، پلاک ۱', latitude: '35.7448', longitude: '51.7551', floors: '12', underground_floors: '2', units_count: '48', units_per_floor: '4', area: '1800.5', land_area: '900', unit_area_min: '65', unit_area_max: '120', structure_type: 'اسکلت فلزی', facade: 'سنگ و شیشه', parking_spaces: '50', parking_type: 'زیرزمینی', elevators: '2', storage_rooms: '48', facilities: ['استخر', 'لابی و پذیرش'], amenities: 'پارکینگ مهمان', utilities: ['برق', 'گاز شهری'], heating_system: 'موتورخانه مرکزی', cooling_system: 'چیلر و فن‌کویل', electricity_type: 'سه‌فاز', utilities_notes: 'توضیح تأسیسات', notes: 'نکته پایانی', featured: true, is_published: true, images: [DATAURI, DATAURI] }) };
  const sparse = { ok: true, site: { name: 'برج افراز الماس پردیس' }, phone: '', building: { id: 8, slug: 's', title: 'ساختمان ساده', status: 'پیش‌فروش', project_type: '', short_description: '', description: '', images: [], floors: '', area: '', facilities: [], utilities: [], is_published: true, location: '', city: '', latitude: '', longitude: '', progress_percent: '', underground_floors: '', parking_spaces: '', elevators: '', storage_rooms: '', units_count: '', unit_area_min: '', unit_area_max: '' } };
  const legacyPub = { ok: true, site: { name: 'x' }, phone: '', building: Object.assign({}, sparse.building, { id: 9, title: 'قدیمی', floors: '۱۲ طبقه', area: '۱۸۰۰ مترمربع', location: 'پردیس فاز ۳', amenities: 'پارکینگ، آسانسور' }) };
  const draft = { ok: true, site: { name: 'x' }, phone: '', building: Object.assign({}, sparse.building, { id: 10, title: 'پیش‌نویس', is_published: false }) };
  for (const [name, fx] of [['full', full], ['sparse', sparse], ['legacy', legacyPub], ['draft', draft]]) {
    const o = await newPage(browser); const p = o.page;
    await p.addInitScript(b => { window.__building = b; }, fx);
    await p.goto(ROOT + 'building.html?slug=x'); await p.waitForSelector('.building-hero');
    const html = await p.innerHTML('#building-content'); const text = await p.innerText('#building-content');
    t(`public[${name}]: no "undefined"/"null"/"NaN" in output`, !/undefined|null|NaN/.test(text), text.match(/.{0,20}(undefined|null|NaN).{0,20}/));
    t(`public[${name}]: no JS errors`, o.errors.length === 0, o.errors);
    if (name === 'full') {
      t('public[full]: script tag escaped (no injected <script>)', !/<script>alert/.test(html) && await p.locator('#building-content script').count() === 0);
      t('public[full]: 6+ spec groups rendered', await p.locator('.spec-group').count() >= 8, await p.locator('.spec-group').count());
      for (const s of ['مسکونی و تجاری', 'شرکت سازنده', 'مهندس طراح', 'P-123', 'تهران', 'پردیس', 'فاز ۸', 'خیابان اصلی', 'اسکلت فلزی', 'سنگ و شیشه', 'زیرزمینی', 'استخر', 'گاز شهری', 'موتورخانه مرکزی', 'چیلر و فن‌کویل', 'سه‌فاز', 'توضیح تأسیسات', 'نکته پایانی', 'پارکینگ مهمان', 'سال شروع']) t('public[full]: shows "' + s + '"', text.includes(s));
      t('public[full]: Persian numerals (۴۸ units, ۱۲ floors)', text.includes('۴۸') && text.includes('۱۲'));
      t('public[full]: area formatted with unit', /۱[٬,]?۸۰۰[٫.]?۵ مترمربع/.test(text), text.match(/زیربنای کل.{0,30}/));
      t('public[full]: unit area range', /۶۵ تا ۱۲۰ مترمربع/.test(text));
      t('public[full]: map link to OSM with coords', (await p.getAttribute('a[href*="openstreetmap"]', 'href')).includes('mlat=35.7448'));
      t('public[full]: progress bar 45', await p.getAttribute('.progress', 'aria-valuenow') === '45');
      t('public[full]: 2 gallery images', await p.locator('.building-gallery img').count() === 2);
      t('public[full]: contact CTA keeps project id', (await p.getAttribute('.building-facts a.btn', 'href')).includes('project=7'));
      await p.screenshot({ path: require('path').join(__dirname, 'public_full.png'), fullPage: true });
      await p.setViewportSize({ width: 390, height: 800 }); await p.waitForTimeout(100);
      t('public[full] mobile (390px): no horizontal overflow', (await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)) <= 1);
      await p.screenshot({ path: require('path').join(__dirname, 'public_mobile.png'), fullPage: true });
    }
    if (name === 'sparse') {
      t('public[sparse]: no empty spec groups', await p.locator('.spec-group').count() === 0 || (await p.locator('.spec-group dl:empty, .spec-group ul:empty').count()) === 0);
      t('public[sparse]: placeholder text when no description', text.includes('توضیحی برای این ساختمان ثبت نشده'));
      t('public[sparse]: no map link, no progress bar', await p.locator('a[href*="openstreetmap"]').count() === 0 && await p.locator('.progress').count() === 0);
    }
    if (name === 'legacy') t('public[legacy]: free-text floors/area shown as typed', text.includes('۱۲ طبقه') && text.includes('۱۸۰۰ مترمربع') && text.includes('پارکینگ، آسانسور'));
    if (name === 'draft') t('public[draft]: draft notice shown (admin preview)', text.includes('پیش‌نویس است'));
    await o.ctx.close();
  }
  // not-found
  { const o = await newPage(browser); await o.page.addInitScript(() => { window.__building = { ok: false, message: 'ساختمان پیدا نشد.' }; });
    // building.php 404 => mock returns 200 with ok:false -> detail.js shows message
    await o.page.goto(ROOT + 'building.html?slug=nope'); await o.page.waitForSelector('h1');
    t('public: not-found message shown', (await o.page.innerText('#building-content')).includes('ساختمان پیدا نشد')); await o.ctx.close(); }

  // ================= NEW: admin security/maintenance/audit/password/alt =================
  { const o = await newPage(browser, null, seed); const p = o.page; const ext = [];
    p.on('request', r => { const u = r.url(); if (!u.startsWith(ROOT) && !u.startsWith('data:') && !u.startsWith('blob:')) ext.push(u); });
    await p.goto(ROOT + 'admin.html'); await p.waitForSelector('#admin-app:not([hidden])');
    t('admin: 2 security warnings rendered with severity classes', await p.locator('#security-warnings li.warn-warn').count() === 1 && await p.locator('#security-warnings li.warn-danger').count() === 1);
    t('admin: maintenance row visible (3 unoptimised images)', await p.isVisible('#maintenance') && (await p.textContent('#maint-text')).includes('3'));
    await p.click('#optimize-images'); await p.waitForSelector('#admin-notice.success');
    t('admin: optimize button posts images_optimize', (await reqs(p)).some(r => g(r, 'action') === 'images_optimize'));
    t('admin: audit list shows 2 entries with Persian (Jalali) dates', await p.locator('#audit-list li').count() === 2 && /[۰-۹]/.test(await p.locator('#audit-list time').first().textContent()), await p.locator('#audit-list time').allTextContents());
    t('admin: audit action labels translated', (await p.textContent('#audit-list')).includes('ورود') && (await p.textContent('#audit-list')).includes('ذخیره ساختمان'));
    t('admin: contacts note shows "1 of 250" and more-button visible', (await p.textContent('#contact-note')).includes('250') && await p.isVisible('#more-contacts'));
    t('admin: contact date rendered in Jalali (Persian digits)', /[۰-۹]/.test(await p.locator('.lead-head small').first().textContent()));
    await p.click('#more-contacts'); await p.waitForTimeout(150);
    t('admin: more-contacts requests contacts_limit=300', (await p.evaluate(() => window.__state.gets)).some(u => u.includes('contacts_limit=300')));
    t('admin: username shown in account card', (await p.textContent('#admin-username')) === 'admin');
    await p.fill('#password-form [name=current_password]', 'old-password-1'); await p.fill('#password-form [name=new_password]', 'New-Strong-Pass-9'); await p.fill('#password-form [name=repeat_password]', 'New-Strong-Pass-9');
    await p.click('#password-form button[type=submit]'); await p.waitForSelector('#admin-notice.success');
    const pw = (await reqs(p)).filter(r => g(r, 'action') === 'change_password').pop();
    t('admin: password form posts 3 fields and resets', pw && g(pw, 'current_password') === 'old-password-1' && g(pw, 'new_password') === 'New-Strong-Pass-9' && (await p.inputValue('#password-form [name=new_password]')) === '');
    t('admin: privacy page form present and prefilled title', (await p.inputValue('form[data-page=privacy] [name=title]')) === 'حریم خصوصی');
    await p.locator('[data-do="edit-project"][data-id="1"]').click();
    const altSel = `input[name="image_alt[${hex('a')}]"]`;
    t('admin: each gallery tile has an alt-text input', await p.locator('.img-tile input[name^="image_alt["]').count() === 3);
    await p.fill(altSel, 'نمای شمالی'); await p.fill('[name=city]', 'پردیس'); await p.selectOption('[name=project_type]', 'مسکونی');
    await p.click('#project-submit'); await p.waitForSelector('#admin-notice.success');
    const alt = (await reqs(p)).filter(r => g(r, 'action') === 'project_save').pop();
    t('admin: image_alt[<hex>] submitted', g(alt, `image_alt[${hex('a')}]`) === 'نمای شمالی');
    t('admin: no external requests', ext.length === 0, ext); t('admin: no JS/CSP errors', o.errors.length === 0, o.errors);
    await o.ctx.close(); }

  // ================= NEW: public index (pagination), page, lightbox, externals, a11y =================
  const pubErrors = [];
  for (const [name, url] of [['index', 'index.html'], ['page', 'page.html?slug=privacy'], ['building', 'building.html?slug=x']]) {
    const o = await newPage(browser); const p = o.page; const ext = [];
    await p.addInitScript(b => { window.__building = b; }, full);
    p.on('request', r => { const u = r.url(); if (!u.startsWith(ROOT) && !u.startsWith('data:') && !u.startsWith('blob:')) ext.push(u); });
    await p.goto(ROOT + url); await p.waitForTimeout(400);
    t(`public[${name}]: no external requests (fonts/images/CDN)`, ext.length === 0, ext);
    t(`public[${name}]: no JS/CSP errors`, o.errors.length === 0, o.errors);
    if (name === 'index') {
      t('index: first page = 12 cards, load-more visible', await p.locator('.project-card').count() === 12 && await p.isVisible('#load-more'));
      t('index: results count uses total (25)', (await p.textContent('#results-count')).includes('25'));
      await p.click('#load-more'); await p.waitForFunction(() => document.querySelectorAll('.project-card').length === 24);
      await p.click('#load-more'); await p.waitForFunction(() => document.querySelectorAll('.project-card').length === 25);
      t('index: load-more appends pages and hides at the end', await p.isHidden('#load-more'));
      t('index: privacy link in footer and in contact form', await p.locator('footer a[href="page.html?slug=privacy"]').count() === 1 && await p.locator('.privacy-note a[href="page.html?slug=privacy"]').count() === 1);
      t('index: hero uses local svg (no unsplash)', !(await p.evaluate(() => getComputedStyle(document.querySelector('.hero')).backgroundImage)).includes('unsplash'));
    }
    if (name === 'page') t('page: privacy content rendered', (await p.innerText('#page-content')).includes('متن حریم خصوصی'));
    if (name === 'building') {
      t('lightbox: progress bar width applied through CSSOM (45%)', await p.evaluate(() => document.querySelector('.progress span').style.width) === '45%');
      await p.locator('[data-lightbox="0"]').click();
      t('lightbox: opens as modal dialog', await p.isVisible('.lightbox[role=dialog][aria-modal=true]'));
      t('lightbox: caption has "1 از 2" and focus is on close', (await p.textContent('.lb-cap')).includes('۱') || (await p.textContent('.lb-cap')).includes('1 از 2'));
      await p.keyboard.press('ArrowLeft'); t('lightbox: arrow key moves to image 2', (await p.textContent('.lb-cap')).includes('2 از 2'));
      await p.keyboard.press('Tab'); t('lightbox: focus stays inside dialog (trap)', await p.evaluate(() => !!document.activeElement.closest('.lightbox')));
      await p.keyboard.press('Escape'); t('lightbox: Esc closes and focus returns to thumbnail', await p.locator('.lightbox').count() === 0 && await p.evaluate(() => document.activeElement.hasAttribute('data-lightbox')));
    }
    // ---- basic accessibility audit ----
    const a11y = await p.evaluate(() => {
      const out = [];
      document.querySelectorAll('img').forEach(i => { if (!i.hasAttribute('alt')) out.push('img without alt: ' + i.src.slice(-30)); });
      document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=file]),select,textarea').forEach(e => { const named = e.getAttribute('aria-label') || e.closest('label') || (e.id && document.querySelector(`label[for="${e.id}"]`)); if (!named) out.push('unlabelled field: ' + (e.name || e.id)); });
      document.querySelectorAll('button,a[href]').forEach(b => { if (!(b.textContent.trim() || b.getAttribute('aria-label') || b.querySelector('img[alt]:not([alt=""])'))) out.push('no accessible name: ' + b.outerHTML.slice(0, 60)); });
      if (document.documentElement.lang !== 'fa') out.push('html lang != fa');
      if (document.querySelectorAll('h1').length !== 1) out.push('h1 count = ' + document.querySelectorAll('h1').length);
      return out;
    });
    t(`a11y[${name}]: labels/alt/names/lang/h1`, a11y.length === 0, a11y);
    // ---- contrast (WCAG AA) for text on solid backgrounds ----
    const low = await p.evaluate(() => {
      const parse = c => { const m = c.match(/rgba?\(([^)]+)\)/); if (!m) return null; const v = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return { r: v[0], g: v[1], b: v[2], a: v[3] === undefined ? 1 : v[3] }; };
      const lum = ({ r, g, b }) => { const f = x => { x /= 255; return x <= .03928 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4; }; return .2126 * f(r) + .7152 * f(g) + .0722 * f(b); };
      const bgOf = el => { let acc = null; for (let e = el; e; e = e.parentElement) { const cs = getComputedStyle(e); if (cs.backgroundImage !== 'none') return null; const c = parse(cs.backgroundColor); if (c && c.a > 0) { if (c.a >= .99) return acc ? { r: acc.r * acc.a + c.r * (1 - acc.a), g: acc.g * acc.a + c.g * (1 - acc.a), b: acc.b * acc.a + c.b * (1 - acc.a) } : c; acc = acc || c; } } return { r: 255, g: 255, b: 255 }; };
      const bad = []; const seen = new Set();
      document.querySelectorAll('body *').forEach(el => {
        if (![...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim())) return;
        const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none' || el.closest('[hidden]') || el.closest('.honeypot')) return;
        const rect = el.getBoundingClientRect(); if (!rect.width || !rect.height) return;
        const fg = parse(cs.color), bg = bgOf(el); if (!fg || !bg) return;
        const op = Number(cs.opacity); const f2 = { r: fg.r * fg.a * op + bg.r * (1 - fg.a * op), g: fg.g * fg.a * op + bg.g * (1 - fg.a * op), b: fg.b * fg.a * op + bg.b * (1 - fg.a * op) };
        const L1 = lum(f2), L2 = lum(bg); const ratio = (Math.max(L1, L2) + .05) / (Math.min(L1, L2) + .05);
        const size = parseFloat(cs.fontSize), bold = Number(cs.fontWeight) >= 700; const large = size >= 24 || (size >= 18.66 && bold);
        if (ratio < (large ? 3 : 4.5)) { const k = (el.className || el.tagName) + cs.color + ratio.toFixed(1); if (!seen.has(k)) { seen.add(k); bad.push(`${ratio.toFixed(2)} ${el.tagName}.${String(el.className).slice(0, 30)} "${el.textContent.trim().slice(0, 20)}" ${cs.color}`); } }
      });
      return bad;
    });
    t(`contrast[${name}]: no text below WCAG AA on solid backgrounds`, low.length === 0, low.slice(0, 12));
    await o.ctx.close();
  }

  await browser.close(); server.close();
  console.log(fails ? `\n${fails} TEST(S) FAILED` : '\nALL UI TESTS PASSED'); process.exit(fails ? 1 : 0);
})().catch(e => { console.error('HARNESS ERROR', e); process.exit(2); });
