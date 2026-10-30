<?php
require_once __DIR__ . '/../config.php';
const JSON_FLAGS = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT;
function json_response(array $data, int $status = 200) {
 http_response_code($status);
 header('Content-Type: application/json; charset=utf-8');
 header('Cache-Control: no-store, max-age=0');
 header('X-Content-Type-Options: nosniff');
 echo json_encode($data, JSON_FLAGS);
 exit;
}
const SCHEMA_VERSION = 5;
function db(): PDO {
 static $pdo=null;
 if ($pdo instanceof PDO) return $pdo;
 $conn=new PDO('sqlite:'.DB_FILE);
 $conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $conn->setAttribute(PDO::ATTR_TIMEOUT,10);
 $conn->exec('PRAGMA busy_timeout = 8000');
 $conn->exec('PRAGMA foreign_keys = ON');
 try { $conn->exec('PRAGMA journal_mode = WAL'); $conn->exec('PRAGMA synchronous = NORMAL'); } catch(Throwable $e) { /* e.g. network file systems */ }
 if ((int)$conn->query('PRAGMA user_version')->fetchColumn() < SCHEMA_VERSION) migrate_schema($conn);
 return $pdo=$conn;
}
/** Creates/updates the schema once per SCHEMA_VERSION (not on every request). Safe to run on an existing database. */
function migrate_schema(PDO $pdo): void {
 $pdo->exec('BEGIN IMMEDIATE');
 try {
  if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() >= SCHEMA_VERSION) { $pdo->exec('COMMIT'); return; }  // another request did it first
  $pdo->exec('CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT DEFAULT "",
    location TEXT DEFAULT "", status TEXT DEFAULT "در حال ساخت", description TEXT DEFAULT "",
    image TEXT DEFAULT "", images TEXT DEFAULT "[]", floors TEXT DEFAULT "", area TEXT DEFAULT "",
    amenities TEXT DEFAULT "", featured INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP
  )');
  $cols=$pdo->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_ASSOC);
  $names=array_column($cols,'name');
  $migrations=['slug'=>"TEXT DEFAULT ''",'images'=>"TEXT DEFAULT '[]'",'floors'=>"TEXT DEFAULT ''",'area'=>"TEXT DEFAULT ''",'amenities'=>"TEXT DEFAULT ''",'featured'=>"INTEGER DEFAULT 0"]+building_new_columns()+['images_alt'=>"TEXT DEFAULT '{}'"];
  foreach($migrations as $col=>$def) if(!in_array($col,$names,true)) {
   try { $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def"); }
   catch(PDOException $e) { if(stripos($e->getMessage(),'duplicate column')===false) throw $e; } // another request migrated first
  }
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_slug ON projects(slug)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_projects_published ON projects(is_published)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, action TEXT NOT NULL, detail TEXT DEFAULT "", ip_hash TEXT DEFAULT "")');
  $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS services (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT DEFAULT "", icon TEXT DEFAULT "◆", sort_order INTEGER DEFAULT 0)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS pages (slug TEXT PRIMARY KEY, title TEXT NOT NULL, content TEXT DEFAULT "", meta_title TEXT DEFAULT "", meta_description TEXT DEFAULT "")');
  $pdo->exec('CREATE TABLE IF NOT EXISTS contact_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, phone TEXT NOT NULL, email TEXT DEFAULT "", topic TEXT NOT NULL, project_title TEXT DEFAULT "", message TEXT NOT NULL, status TEXT NOT NULL DEFAULT "new", ip_hash TEXT DEFAULT "", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contact_requests_created_at ON contact_requests(created_at)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contact_requests_ip_hash ON contact_requests(ip_hash, created_at)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS admin_login_attempts (ip_hash TEXT NOT NULL, attempted_at INTEGER NOT NULL)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_admin_login_attempts_ip_time ON admin_login_attempts(ip_hash, attempted_at)');
  $stmt=$pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES (?,?)');
  foreach(['about'=>'شرکت برج افراز الماس پردیس با تمرکز بر کیفیت ساخت، طراحی اصولی و جلب اعتماد مشتریان فعالیت می‌کند. این متن از پنل مدیریت قابل ویرایش است.','phone'=>SITE_PHONE,'email'=>'','address'=>SITE_ADDRESS,'tagline'=>SITE_TAGLINE,'founded_year'=>'','registration_number'=>'','company_manager'=>'','credentials'=>''] as $k=>$v) $stmt->execute([$k,$v]);
  $pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)')->execute(['ip_salt',bin2hex(random_bytes(16))]);
  $stmt=$pdo->prepare('INSERT OR IGNORE INTO pages(slug,title,content,meta_title,meta_description) VALUES(?,?,?,?,?)');
  $stmt->execute(['about','درباره شرکت',settingRaw($pdo,'about'),'درباره برج افراز الماس پردیس','معرفی شرکت برج افراز الماس پردیس']);
  $stmt->execute(['services','خدمات شرکت','خدمات شرکت را از پنل مدیریت ویرایش کنید.','خدمات برج افراز الماس پردیس','خدمات ساختمانی برج افراز الماس پردیس']);
  $stmt->execute(['privacy','حریم خصوصی',default_privacy_text(),'حریم خصوصی | برج افراز الماس پردیس','نحوه نگهداری و استفاده از اطلاعات تماس کاربران']);
  $pdo->exec('PRAGMA user_version = '.SCHEMA_VERSION);
  $pdo->exec('COMMIT');
 } catch(Throwable $e) {
  if($pdo->inTransaction()) $pdo->exec('ROLLBACK');
  throw $e;
 }
}
function default_privacy_text(): string {
 return "این صفحه توضیح می‌دهد اطلاعاتی که از طریق فرم تماس ثبت می‌کنید چگونه استفاده می‌شود. (این متن نمونه است؛ پیش از انتشار آن را بازبینی و در صورت نیاز با مشاور حقوقی نهایی کنید.)\n\nچه اطلاعاتی ثبت می‌شود؟\nنام، شماره تماس، ایمیل (اختیاری)، موضوع و متن درخواست و در صورت انتخاب، پروژه مرتبط. آدرس آی‌پی شما ذخیره نمی‌شود؛ فقط یک نشانه رمزشده و یک‌طرفه از آن برای جلوگیری از ارسال انبوه نگهداری می‌شود.\n\nاطلاعات برای چه استفاده می‌شود؟\nفقط برای پاسخ‌گویی و پیگیری همین درخواست. اطلاعات شما برای تبلیغات به شخص ثالث فروخته یا واگذار نمی‌شود.\n\nنگهداری و حذف\nدرخواست‌ها در پنل مدیریت نگهداری می‌شوند و مدیر می‌تواند هر درخواست را حذف کند. برای درخواست حذف اطلاعات خود با شماره تماس شرکت در ارتباط باشید.\n\nکوکی‌ها\nسایت برای بازدیدکنندگان عادی کوکی ایجاد نمی‌کند؛ کوکی نشست فقط هنگام ورود مدیر استفاده می‌شود.";
}
function settingRaw(PDO $pdo,string $key): string { $s=$pdo->prepare('SELECT value FROM settings WHERE key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?'':(string)$v; }
function setting(string $key,string $fallback=''): string { $s=db()->prepare('SELECT value FROM settings WHERE key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$fallback:(string)$v; }
function csrf_token(): string { ensure_session(); if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void {
 if($_SERVER['REQUEST_METHOD']!=='POST') return;
 $v=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
 if(!is_string($v) || $v==='' || !hash_equals(csrf_token(),$v)) json_response(['ok'=>false,'message'=>'درخواست نامعتبر است. صفحه را تازه‌سازی کنید.'],403);
}
const ADMIN_IDLE_SECONDS = 3600, ADMIN_MAX_SECONDS = 43200;
/** Admin session with an idle timeout (1 h) and an absolute lifetime (12 h). Visitors without a session cookie never start a session. */
function is_admin(): bool {
 if (session_status() !== PHP_SESSION_ACTIVE) { if (!isset($_COOKIE[SESSION_COOKIE])) return false; ensure_session(); }
 if (empty($_SESSION['admin_logged_in'])) return false;
 $now=time();
 if ($now-(int)($_SESSION['last_seen']??0) > ADMIN_IDLE_SECONDS || $now-(int)($_SESSION['login_at']??0) > ADMIN_MAX_SECONDS) { $_SESSION=[]; return false; }
 $_SESSION['last_seen']=$now;
 return true;
}
function require_admin(): void { if(!is_admin()) json_response(['ok'=>false,'auth'=>false,'message'=>'برای ادامه باید وارد پنل مدیریت شوید.'],401); }
function slugify(string $text): string {
 $text=trim(mb_strtolower($text,'UTF-8'));
 $text=preg_replace('/[^\p{L}\p{N}]+/u','-',$text);
 return trim($text,'-') ?: ('building-'.bin2hex(random_bytes(3)));
}
function upload_images(string $field='images'): array {
 if(empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) return [];
 $f=$_FILES[$field]; $out=[]; $count=count($f['name']);
 if($count>12) throw new RuntimeException('در هر بار حداکثر ۱۲ تصویر قابل انتخاب است.');
 try {
  for($i=0;$i<$count;$i++){
   $error=(int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
   if($error===UPLOAD_ERR_NO_FILE) continue;
   $out[]=save_uploaded_image((string)($f['tmp_name'][$i] ?? ''),(int)($f['size'][$i] ?? 0),$error);
  }
  return $out;
 } catch(Throwable $ex) {
  foreach($out as $name) image_files_delete($name);
  throw $ex;
 }
}
function all_project_images(array $p): array {
 $arr=json_decode((string)($p['images']??'[]'),true);
 if(!is_array($arr))$arr=[];
 if(!empty($p['image'])&&!in_array($p['image'],$arr,true))array_unshift($arr,$p['image']);
 return array_values(array_unique(array_filter($arr, static fn($v)=>is_string($v) && preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $v) === 1))); 
}
function project_url(array $p): string { return 'building.html?slug='.rawurlencode((string)($p['slug']?:$p['id'])); }
function site_url(string $path=''): string {
 $configured=trim((string)(getenv('SITE_BASE_URL')?:''));
 if($configured!=='' && filter_var($configured,FILTER_VALIDATE_URL) && in_array(parse_url($configured,PHP_URL_SCHEME),['http','https'],true)){
  $base=rtrim($configured,'/');
 } else {
  $host=(string)($_SERVER['HTTP_HOST']??'localhost');
  if(!preg_match('/^(?:[a-z0-9.-]+|\[[0-9a-f:]+\])(?::[0-9]{1,5})?$/i',$host)) $host='localhost';
  $https=is_https()?'https':'http';
  $dir=str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/')));
  $dir=($dir==='.'||$dir==='/')?'':rtrim($dir,'/');
  if(substr($dir,-4)==='/api') $dir=substr($dir,0,-4);
  $base=$https.'://'.$host.$dir;
 }
 return $base.'/'.ltrim($path,'/');
}

function admin_login_ip_hash(): string { return ip_hash(); }
function admin_login_is_limited(): bool {
    $q = db()->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE ip_hash=? AND attempted_at >= ?');
    $q->execute([admin_login_ip_hash(), time() - 900]);
    return (int)$q->fetchColumn() >= 5;
}
function admin_login_record_failure(): void {
    $pdo = db(); $pdo->prepare('DELETE FROM admin_login_attempts WHERE attempted_at < ?')->execute([time() - 86400]);
    $pdo->prepare('INSERT INTO admin_login_attempts(ip_hash,attempted_at) VALUES(?,?)')->execute([admin_login_ip_hash(), time()]);
}
function admin_login_clear_failures(): void {
    db()->prepare('DELETE FROM admin_login_attempts WHERE ip_hash=?')->execute([admin_login_ip_hash()]);
}

// ================= Building specifications (generated spec; keep in sync with admin.html) =================
const BUILDING_STATUSES = ['در حال ساخت','تکمیل‌شده','در مرحله طراحی','پیش‌فروش'];
const BUILDING_TYPES = ['مسکونی','تجاری','اداری','مسکونی و تجاری','سایر'];
const BUILDING_STRUCTURES = ['اسکلت فلزی','اسکلت بتنی','سازه مختلط','بنایی','سایر'];
const BUILDING_PARKING_TYPES = ['زیرزمینی','طبقه‌ای','روباز','ندارد'];
const BUILDING_HEATING = ['موتورخانه مرکزی','پکیج هر واحد','گرمایش از کف','بخاری گازی','سایر'];
const BUILDING_COOLING = ['چیلر و فن‌کویل','کولر گازی (اسپلیت)','کولر آبی','سایر'];
const BUILDING_ELECTRICITY = ['تک‌فاز','سه‌فاز'];
const BUILDING_FACILITIES = ['لابی و پذیرش','نگهبانی ۲۴ ساعته','دوربین مداربسته','سالن ورزش','استخر','روف‌گاردن','سالن اجتماعات','فضای سبز و حیاط','پارکینگ مهمان','فضای بازی کودکان','ژنراتور اضطراری','آسانسور باربر','سیستم اطفای حریق','ایفون تصویری'];
const BUILDING_UTILITIES = ['برق','آب شهری','گاز شهری','فاضلاب شهری','تلفن','اینترنت پرسرعت / فیبر نوری','منبع آب ذخیره','انشعاب جداگانه برای هر واحد'];
function building_spec(): array {
 return [
  'title'=>['type'=>'text','label'=>'نام ساختمان','required'=>true,'max'=>150],
  'slug'=>['type'=>'slug','label'=>'شناسه آدرس (Slug)','max'=>160],
  'project_type'=>['type'=>'enum','label'=>'نوع کاربری','required'=>true,'options'=>BUILDING_TYPES],
  'status'=>['type'=>'enum','label'=>'وضعیت پروژه','required'=>true,'options'=>BUILDING_STATUSES],
  'builder'=>['type'=>'text','label'=>'سازنده / مجری','max'=>150],
  'architect'=>['type'=>'text','label'=>'طراح / معمار','max'=>150],
  'start_year'=>['type'=>'int','label'=>'سال شروع ساخت (شمسی)','lo'=>1300,'hi'=>1500],
  'completion_year'=>['type'=>'int','label'=>'سال تکمیل یا تحویل (شمسی)','lo'=>1300,'hi'=>1500],
  'delivery_date'=>['type'=>'text','label'=>'تاریخ تحویل (توضیح متنی)','max'=>60],
  'progress_percent'=>['type'=>'int','label'=>'درصد پیشرفت فیزیکی','lo'=>0,'hi'=>100],
  'permit_number'=>['type'=>'text','label'=>'شماره پروانه ساخت (اختیاری)','max'=>100],
  'short_description'=>['type'=>'textarea','label'=>'خلاصه معرفی (برای کارت‌ها)','max'=>300],
  'description'=>['type'=>'textarea','label'=>'توضیحات کامل','max'=>5000],
  'province'=>['type'=>'text','label'=>'استان','max'=>80],
  'city'=>['type'=>'text','label'=>'شهر','required'=>true,'max'=>80],
  'location'=>['type'=>'text','label'=>'محدوده / فاز / محله','max'=>150],
  'postal_code'=>['type'=>'digits','label'=>'کد پستی (۱۰ رقم)','digits'=>10],
  'address'=>['type'=>'text','label'=>'نشانی کامل','max'=>300],
  'latitude'=>['type'=>'coord','label'=>'عرض جغرافیایی','lo'=>-90,'hi'=>90],
  'longitude'=>['type'=>'coord','label'=>'طول جغرافیایی','lo'=>-180,'hi'=>180],
  'floors'=>['type'=>'int','label'=>'تعداد طبقات (روی زمین)','lo'=>1,'hi'=>200,'legacy'=>true],
  'underground_floors'=>['type'=>'int','label'=>'تعداد طبقات زیرزمین','lo'=>0,'hi'=>20],
  'units_count'=>['type'=>'int','label'=>'تعداد کل واحدها','lo'=>1,'hi'=>5000],
  'units_per_floor'=>['type'=>'int','label'=>'تعداد واحد در هر طبقه','lo'=>1,'hi'=>50],
  'area'=>['type'=>'decimal','label'=>'زیربنای کل (مترمربع)','lo'=>1,'hi'=>10000000,'legacy'=>true],
  'land_area'=>['type'=>'decimal','label'=>'مساحت زمین (مترمربع)','lo'=>1,'hi'=>10000000],
  'unit_area_min'=>['type'=>'decimal','label'=>'کمترین متراژ واحد (مترمربع)','lo'=>1,'hi'=>100000],
  'unit_area_max'=>['type'=>'decimal','label'=>'بیشترین متراژ واحد (مترمربع)','lo'=>1,'hi'=>100000],
  'structure_type'=>['type'=>'enum','label'=>'نوع سازه','options'=>BUILDING_STRUCTURES],
  'facade'=>['type'=>'text','label'=>'نمای ساختمان','max'=>150],
  'parking_spaces'=>['type'=>'int','label'=>'تعداد پارکینگ','lo'=>0,'hi'=>5000],
  'parking_type'=>['type'=>'enum','label'=>'نوع پارکینگ','options'=>BUILDING_PARKING_TYPES],
  'elevators'=>['type'=>'int','label'=>'تعداد آسانسور','lo'=>0,'hi'=>50],
  'storage_rooms'=>['type'=>'int','label'=>'تعداد انباری','lo'=>0,'hi'=>5000],
  'facilities'=>['type'=>'list','label'=>'امکانات رفاهی و ایمنی','options'=>BUILDING_FACILITIES],
  'amenities'=>['type'=>'textarea','label'=>'سایر امکانات (توضیح آزاد)','max'=>2000],
  'utilities'=>['type'=>'list','label'=>'انشعابات و خدمات','options'=>BUILDING_UTILITIES],
  'heating_system'=>['type'=>'enum','label'=>'سیستم گرمایش','options'=>BUILDING_HEATING],
  'cooling_system'=>['type'=>'enum','label'=>'سیستم سرمایش','options'=>BUILDING_COOLING],
  'electricity_type'=>['type'=>'enum','label'=>'نوع برق','options'=>BUILDING_ELECTRICITY],
  'utilities_notes'=>['type'=>'textarea','label'=>'توضیحات تأسیسات','max'=>1000],
  'notes'=>['type'=>'textarea','label'=>'سایر توضیحات و نکات','max'=>3000],
  'featured'=>['type'=>'bool','label'=>'نمایش به‌عنوان پروژه شاخص'],
  'is_published'=>['type'=>'bool','label'=>'منتشر شود (در سایت عمومی دیده شود)'],
 ];
}
function building_new_columns(): array {
 return [
  'project_type'=>"TEXT DEFAULT ''",
  'builder'=>"TEXT DEFAULT ''",
  'architect'=>"TEXT DEFAULT ''",
  'start_year'=>"TEXT DEFAULT ''",
  'completion_year'=>"TEXT DEFAULT ''",
  'delivery_date'=>"TEXT DEFAULT ''",
  'progress_percent'=>"TEXT DEFAULT ''",
  'permit_number'=>"TEXT DEFAULT ''",
  'short_description'=>"TEXT DEFAULT ''",
  'province'=>"TEXT DEFAULT ''",
  'city'=>"TEXT DEFAULT ''",
  'postal_code'=>"TEXT DEFAULT ''",
  'address'=>"TEXT DEFAULT ''",
  'latitude'=>"TEXT DEFAULT ''",
  'longitude'=>"TEXT DEFAULT ''",
  'underground_floors'=>"TEXT DEFAULT ''",
  'units_count'=>"TEXT DEFAULT ''",
  'units_per_floor'=>"TEXT DEFAULT ''",
  'land_area'=>"TEXT DEFAULT ''",
  'unit_area_min'=>"TEXT DEFAULT ''",
  'unit_area_max'=>"TEXT DEFAULT ''",
  'structure_type'=>"TEXT DEFAULT ''",
  'facade'=>"TEXT DEFAULT ''",
  'parking_spaces'=>"TEXT DEFAULT ''",
  'parking_type'=>"TEXT DEFAULT ''",
  'elevators'=>"TEXT DEFAULT ''",
  'storage_rooms'=>"TEXT DEFAULT ''",
  'facilities'=>"TEXT DEFAULT '[]'",
  'utilities'=>"TEXT DEFAULT '[]'",
  'heating_system'=>"TEXT DEFAULT ''",
  'cooling_system'=>"TEXT DEFAULT ''",
  'electricity_type'=>"TEXT DEFAULT ''",
  'utilities_notes'=>"TEXT DEFAULT ''",
  'notes'=>"TEXT DEFAULT ''",
  'is_published'=>"INTEGER DEFAULT 1",
  'updated_at'=>"TEXT DEFAULT ''",
 ];
}

final class ValidationException extends RuntimeException {
    /** @var array<string,string> */
    public array $errors;
    public function __construct(array $errors) {
        $this->errors = $errors;
        parent::__construct('لطفاً خطاهای مشخص‌شده در فرم را اصلاح کنید.');
    }
}

/** Converts Persian/Arabic digits and separators to ASCII so numbers can be validated reliably. */
function normalize_digits(string $v): string {
    return strtr($v, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        '٫'=>'.','٬'=>'','،'=>'',','=>'',' '=>'',"\u{200C}"=>'',
    ]);
}

/** Trims text, unifies line breaks and removes control characters (keeps \n, \t and the Persian half-space U+200C). */
function clean_text(string $v): string {
    if (!mb_check_encoding($v, 'UTF-8')) return '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[^\P{Cc}\n\t]/u', '', $v) ?? '';
    return trim($v);
}

/**
 * Validates and normalizes the building form.
 * @return array{0: array<string,mixed>, 1: array<string,string>} [clean values, errors by field]
 */
function validate_building(array $in, array $existing = []): array {
    $vals = []; $errs = [];
    foreach (building_spec() as $key => $f) {
        $label = (string)$f['label']; $type = (string)$f['type'];
        if ($type === 'bool') {
            $raw = $in[$key] ?? '';
            $vals[$key] = (is_scalar($raw) && (string)$raw !== '' && (string)$raw !== '0') ? 1 : 0;
            continue;
        }
        if ($type === 'list') {
            $raw = $in[$key] ?? [];
            if (!is_array($raw)) $raw = [];
            $picked = [];
            foreach ($raw as $item) {
                $item = is_scalar($item) ? (string)$item : '';
                if (!in_array($item, $f['options'], true)) { $errs[$key] = $label . ' شامل گزینه نامعتبر است.'; break; }
                $picked[$item] = true;
            }
            $vals[$key] = json_encode(array_values(array_filter($f['options'], static fn($o) => isset($picked[$o]))), JSON_UNESCAPED_UNICODE);
            continue;
        }
        $raw = $in[$key] ?? '';
        $v = is_scalar($raw) ? clean_text((string)$raw) : '';
        if ($type !== 'textarea') $v = preg_replace('/\s*\n\s*/u', ' ', $v) ?? $v;
        if ($v === '') {
            if (!empty($f['required'])) $errs[$key] = $label . ' الزامی است.';
            $vals[$key] = '';
            continue;
        }
        switch ($type) {
            case 'text': case 'textarea': case 'slug':
                if (mb_strlen($v, 'UTF-8') > (int)$f['max']) $errs[$key] = $label . ' نباید بیشتر از ' . $f['max'] . ' نویسه باشد.';
                $vals[$key] = $v; break;
            case 'enum':
                if (!in_array($v, $f['options'], true)) $errs[$key] = $label . ': گزینه انتخاب‌شده معتبر نیست.';
                $vals[$key] = $v; break;
            case 'int': case 'decimal': case 'digits': case 'coord':
                $n = normalize_digits($v);
                if (!empty($f['legacy']) && $v === (string)($existing[$key] ?? '')) { $vals[$key] = $v; break; } // keep pre-existing free-text value untouched
                if ($type === 'digits') {
                    if (!preg_match('/^\d{' . (int)$f['digits'] . '}$/', $n)) { $errs[$key] = $label . ' باید دقیقاً ' . $f['digits'] . ' رقم باشد.'; $vals[$key] = $v; break; }
                    $vals[$key] = $n; break;
                }
                if ($type === 'int') $ok = preg_match('/^\d{1,9}$/', $n) === 1;
                elseif ($type === 'decimal') $ok = preg_match('/^\d{1,9}(\.\d{1,2})?$/', $n) === 1;
                else $ok = preg_match('/^-?\d{1,3}(\.\d{1,8})?$/', $n) === 1;
                if (!$ok) { $errs[$key] = $label . ($type === 'int' ? ' باید عدد صحیح (بدون اعشار) باشد.' : ' باید عدد معتبر باشد.'); $vals[$key] = $v; break; }
                $num = (float)$n;
                if (isset($f['lo']) && $num < $f['lo']) $errs[$key] = $label . ' نباید کمتر از ' . $f['lo'] . ' باشد.';
                elseif (isset($f['hi']) && $num > $f['hi']) $errs[$key] = $label . ' نباید بیشتر از ' . $f['hi'] . ' باشد.';
                $vals[$key] = ($type === 'int') ? (string)(int)$n : (string)$num;
                break;
            default:
                $vals[$key] = $v;
        }
    }
    // cross-field rules
    if (!isset($errs['latitude']) && !isset($errs['longitude']) && (($vals['latitude'] ?? '') === '') !== (($vals['longitude'] ?? '') === '')) {
        $errs[($vals['latitude'] ?? '') === '' ? 'latitude' : 'longitude'] = 'عرض و طول جغرافیایی باید با هم وارد شوند.';
    }
    if (!isset($errs['unit_area_min']) && !isset($errs['unit_area_max']) && ($vals['unit_area_min'] ?? '') !== '' && ($vals['unit_area_max'] ?? '') !== '' && (float)$vals['unit_area_min'] > (float)$vals['unit_area_max']) {
        $errs['unit_area_max'] = 'بیشترین متراژ واحد نباید کمتر از کمترین متراژ باشد.';
    }
    if (!isset($errs['start_year']) && !isset($errs['completion_year']) && ($vals['start_year'] ?? '') !== '' && ($vals['completion_year'] ?? '') !== '' && (int)$vals['completion_year'] < (int)$vals['start_year']) {
        $errs['completion_year'] = 'سال تکمیل نمی‌تواند قبل از سال شروع باشد.';
    }
    if (!isset($errs['units_count']) && !isset($errs['units_per_floor']) && ($vals['units_count'] ?? '') !== '' && ($vals['units_per_floor'] ?? '') !== '' && (int)$vals['units_per_floor'] > (int)$vals['units_count']) {
        $errs['units_per_floor'] = 'تعداد واحد در هر طبقه نمی‌تواند از تعداد کل واحدها بیشتر باشد.';
    }
    return [$vals, $errs];
}

/** Public/admin representation of a building row (lists decoded, flags cast, strings only otherwise). */
function building_export(array $p): array {
    $out = ['id' => (int)$p['id'], 'slug' => (string)(($p['slug'] ?? '') ?: $p['id']), 'updated_at' => (string)($p['updated_at'] ?? '')];
    foreach (building_spec() as $k => $f) {
        $v = $p[$k] ?? null;
        if ($f['type'] === 'bool') $out[$k] = (bool)(int)$v;
        elseif ($f['type'] === 'list') {
            $arr = json_decode((string)$v, true);
            $out[$k] = is_array($arr) ? array_values(array_filter($arr, 'is_string')) : [];
        } else $out[$k] = (string)($v ?? '');
    }
    $out['slug'] = (string)(($p['slug'] ?? '') ?: $p['id']);
    return $out;
}

/** Validates ONE uploaded image file and stores it under a random name in UPLOAD_DIR. */
function save_uploaded_image(string $tmp, int $size, int $error): string {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('حجم یکی از تصاویر بیشتر از حد مجاز سرور است (حداکثر ۵ مگابایت برای هر تصویر).');
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری یکی از تصاویر ناموفق بود.');
    if ($size < 1 || $size > 5 * 1024 * 1024) throw new RuntimeException('حجم هر تصویر باید بیشتر از صفر و حداکثر ۵ مگابایت باشد.');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('فایل بارگذاری‌شده معتبر نیست.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!is_string($mime) || !isset($allowed[$mime])) throw new RuntimeException('فقط تصویر JPG، PNG یا WEBP مجاز است.');
    $info = @getimagesize($tmp);
    if ($info === false || ($info['mime'] ?? '') !== $mime) throw new RuntimeException('فایل انتخاب‌شده یک تصویر معتبر نیست.');
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > 12000 || $info[1] > 12000) throw new RuntimeException('ابعاد تصویر معتبر نیست (حداکثر ۱۲٬۰۰۰ پیکسل در هر بعد).');
    if (gd_available() && !ensure_memory_for_image((int)$info[0], (int)$info[1])) throw new RuntimeException('این تصویر برای پردازش روی سرور بیش از حد بزرگ است؛ آن را کوچک‌تر کنید (مثلاً حداکثر ۴۰۰۰ پیکسل).');
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, UPLOAD_DIR . $name)) throw new RuntimeException('ذخیره تصویر انجام نشد.');
    @chmod(UPLOAD_DIR . $name, 0644);
    if (!optimize_image($name, $mime)) app_log('INFO', 'image stored without optimisation (GD unavailable or image not processable): ' . $name);
    return $name;
}

/**
 * Works out the final gallery of a building from the form: removals, new order, per-image replacements and new uploads.
 * Files created here must be deleted by the caller if the database write fails; 'obsolete' files are deleted after commit.
 * @return array{final: string[], created: string[], obsolete: string[], replaced: array<string,string>}
 */
function plan_building_images(array $old): array {
    $removeIn = $_POST['remove_images'] ?? [];
    if (!is_array($removeIn)) $removeIn = [];
    $remove = array_values(array_unique(array_filter(array_map('strval', $removeIn), static fn($v) => in_array($v, $old, true))));
    $keep = array_values(array_diff($old, $remove));
    $orderIn = $_POST['image_order'] ?? [];
    if (!is_array($orderIn)) $orderIn = [];
    $ordered = [];
    foreach ($orderIn as $n) { $n = is_scalar($n) ? (string)$n : ''; if (in_array($n, $keep, true) && !in_array($n, $ordered, true)) $ordered[] = $n; }
    foreach ($keep as $n) if (!in_array($n, $ordered, true)) $ordered[] = $n;
    $created = []; $obsolete = $remove; $replaced = [];
    try {
        $rf = $_FILES['replace_images'] ?? null;
        if (is_array($rf) && is_array($rf['name'] ?? null)) {
            foreach (array_keys($rf['name']) as $key) {
                $key = (string)$key;
                if (!preg_match('/^[a-f0-9]{32}$/', $key)) continue;
                $err = (int)($rf['error'][$key] ?? UPLOAD_ERR_NO_FILE);
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                $pos = null;
                foreach ($ordered as $i => $n) { if (strncmp($n, $key, 32) === 0) { $pos = $i; break; } }
                if ($pos === null) continue;
                $new = save_uploaded_image((string)($rf['tmp_name'][$key] ?? ''), (int)($rf['size'][$key] ?? 0), $err);
                $created[] = $new; $obsolete[] = $ordered[$pos]; $replaced[$ordered[$pos]] = $new; $ordered[$pos] = $new;
            }
        }
        $added = upload_images('images');
        foreach ($added as $n) $created[] = $n;
        $final = array_merge($ordered, $added);
        if (count($final) > 12) throw new RuntimeException('هر ساختمان حداکثر می‌تواند ۱۲ تصویر داشته باشد. ابتدا چند تصویر قدیمی را حذف کنید.');
        return ['final' => $final, 'created' => $created, 'obsolete' => array_values(array_unique($obsolete)), 'replaced' => $replaced];
    } catch (Throwable $e) {
        foreach ($created as $n) image_files_delete($n);
        throw $e;
    }
}

// ================= Logging, audit trail, hashing =================
function app_log(string $level, string $message): void {
    try {
        $file = LOG_FILE;
        if (is_file($file) && @filesize($file) > 1048576) @rename($file, $file . '.1');   // simple size-based rotation (1 MB)
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $level . ' ' . str_replace(["\r", "\n"], ' ', $message) . "\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { /* logging must never break a request */ }
}
function log_exception(Throwable $e, string $context = ''): void {
    app_log('ERROR', ($context !== '' ? $context . ': ' : '') . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}
/** Secret salt for pseudonymous IP hashes (random, stored in the private database, not guessable from the file path). */
function ip_salt(): string {
    static $salt = null;
    if ($salt === null) $salt = settingRaw(db(), 'ip_salt') ?: 'fallback-salt';
    return $salt;
}
function ip_hash(): string { return hash_hmac('sha256', client_ip(), ip_salt()); }
/** Writes an entry to the admin audit trail (never throws). */
function audit(string $action, string $detail = ''): void {
    try {
        $pdo = db();
        $pdo->prepare('INSERT INTO audit_log(action,detail,ip_hash) VALUES(?,?,?)')->execute([$action, mb_substr($detail, 0, 300, 'UTF-8'), substr(ip_hash(), 0, 12)]);
        $pdo->exec('DELETE FROM audit_log WHERE id < (SELECT MAX(id) - 2000 FROM audit_log)');
    } catch (Throwable $e) { log_exception($e, 'audit'); }
}
function utc_now(): string { return gmdate('Y-m-d H:i:s'); }
function utc_to_iso(string $dt): string {
    $ts = strtotime($dt . ' UTC');
    return $ts === false ? '' : gmdate('c', $ts);
}

// ================= Notifications (email + Telegram/Bale-compatible bot) =================
function http_post_json(string $url, array $payload, int $timeout = 4): bool {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_FOLLOWLOCATION => false]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $res !== false && $code >= 200 && $code < 300;
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $body, 'timeout' => $timeout, 'ignore_errors' => true]]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) return false;
    $code = 0;
    foreach (($http_response_header ?? []) as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
    return $code >= 200 && $code < 300;
}
function mail_header_value(string $v): string { return trim(str_replace(["\r", "\n", '<', '>'], ' ', $v)); }
function notifications_configured(): array {
    return ['email' => cfg('NOTIFY_EMAIL') !== '', 'bot' => cfg('TELEGRAM_BOT_TOKEN') !== '' && cfg('TELEGRAM_CHAT_ID') !== ''];
}
/** Best-effort alert for a new contact request. Failures are logged, never shown to the visitor. */
function notify_new_contact(array $c): void {
    $lines = ["درخواست جدید در سایت " . SITE_NAME, 'نام: ' . $c['name'], 'تلفن: ' . $c['phone'], 'موضوع: ' . $c['topic']];
    if (($c['project_title'] ?? '') !== '') $lines[] = 'پروژه: ' . $c['project_title'];
    if (($c['email'] ?? '') !== '') $lines[] = 'ایمیل: ' . $c['email'];
    $lines[] = "پیام:\n" . $c['message'];
    $text = implode("\n", $lines);
    $to = cfg('NOTIFY_EMAIL');
    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        $from = cfg('MAIL_FROM', 'noreply@' . preg_replace('/^www\./i', '', explode(':', $host)[0]));
        $headers = ['From: ' . mail_header_value(SITE_NAME) . ' <' . mail_header_value($from) . '>', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit'];
        if (($c['email'] ?? '') !== '' && filter_var($c['email'], FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . mail_header_value($c['email']);
        $ok = @mail($to, mb_encode_mimeheader('درخواست جدید: ' . mail_header_value($c['name']), 'UTF-8'), $text, implode("\r\n", $headers));
        if (!$ok) app_log('WARN', 'notify: mail() failed (host may not support PHP mail)');
    }
    $token = cfg('TELEGRAM_BOT_TOKEN'); $chat = cfg('TELEGRAM_CHAT_ID');
    if ($token !== '' && $chat !== '') {
        $base = rtrim(cfg('TELEGRAM_API_BASE', 'https://api.telegram.org'), '/');
        if (!preg_match('#^https://#i', $base)) $base = 'https://api.telegram.org';
        if (!http_post_json($base . '/bot' . $token . '/sendMessage', ['chat_id' => $chat, 'text' => mb_substr($text, 0, 3800, 'UTF-8')])) app_log('WARN', 'notify: bot message failed');
    }
}
/** Sends the JSON response to the visitor first, then keeps running $after (slow work such as notifications). */
function respond_and_continue(array $data, callable $after, int $status = 200) {
    ignore_user_abort(true);
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store, max-age=0'); header('X-Content-Type-Options: nosniff');
    $json = json_encode($data, JSON_FLAGS);
    header('Content-Length: ' . strlen($json)); header('Connection: close');
    echo $json;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else { @ob_flush(); flush(); }
    try { $after(); } catch (Throwable $e) { log_exception($e, 'after-response'); }
    exit;
}

// ================= Image optimisation (EXIF removal, resize, thumbnails) =================
function gd_available(): bool { return extension_loaded('gd') && function_exists('imagecreatetruecolor') && function_exists('imagecreatefromjpeg'); }
function thumb_name(string $name): string { return 't_' . $name; }
function image_files_delete(string $name): void {
    foreach ([$name, thumb_name($name)] as $f) if (preg_match('/^(?:t_)?[a-f0-9]{32}\.(?:jpg|png|webp)$/', $f) && is_file(UPLOAD_DIR . $f)) @unlink(UPLOAD_DIR . $f);
}
function image_url(string $name, bool $thumb = false): string {
    return UPLOAD_URL . (($thumb && is_file(UPLOAD_DIR . thumb_name($name))) ? thumb_name($name) : $name);
}
function memory_limit_bytes(): int {
    $v = trim((string)ini_get('memory_limit'));
    if ($v === '' || $v === '-1') return PHP_INT_MAX;
    $n = (int)$v; $u = strtolower(substr($v, -1));
    return $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
}
function ensure_memory_for_image(int $w, int $h): bool {
    $need = (int)($w * $h * 5.2) + 48 * 1048576;
    if (memory_limit_bytes() >= $need) return true;
    @ini_set('memory_limit', (string)(int)ceil($need / 1048576) . 'M');
    return memory_limit_bytes() >= $need;
}
/** @return \GdImage|resource|false */
function image_load(string $path, string $mime) {
    if ($mime === 'image/jpeg') return @imagecreatefromjpeg($path);
    if ($mime === 'image/png' && function_exists('imagecreatefrompng')) return @imagecreatefrompng($path);
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($path);
    return false;
}
function image_write($im, string $path, string $mime, int $quality): bool {
    if ($mime === 'image/jpeg') { imageinterlace($im, true); return @imagejpeg($im, $path, $quality); }
    imagealphablending($im, false); imagesavealpha($im, true);
    if ($mime === 'image/png') return @imagepng($im, $path, 6);
    if ($mime === 'image/webp' && function_exists('imagewebp')) return @imagewebp($im, $path, $quality);
    return false;
}
/**
 * Re-encodes an image in place (this drops EXIF/GPS metadata), limits the longest side to 2000 px and writes a 520 px thumbnail.
 * Returns false (leaving the original untouched) when GD is unavailable or the image cannot be processed.
 */
function optimize_image(string $name, string $mime): bool {
    if (!gd_available()) return false;
    $path = UPLOAD_DIR . $name;
    $info = @getimagesize($path);
    if ($info === false) return false;
    if (!ensure_memory_for_image((int)$info[0], (int)$info[1])) { app_log('WARN', 'optimize: not enough memory for ' . $info[0] . 'x' . $info[1]); return false; }
    $im = image_load($path, $mime);
    if (!$im) return false;
    try {
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $o = is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1;
            $angle = $o === 3 ? 180 : ($o === 6 ? -90 : ($o === 8 ? 90 : 0));
            if ($angle !== 0) { $rot = imagerotate($im, $angle, 0); if ($rot) $im = $rot; }
        }
        $w = imagesx($im); $h = imagesy($im);
        if (max($w, $h) > 2000) { $scaled = imagescale($im, $w >= $h ? 2000 : (int)round($w * 2000 / $h), $w >= $h ? (int)round($h * 2000 / $w) : 2000, IMG_BICUBIC); if ($scaled) { $im = $scaled; $w = imagesx($im); $h = imagesy($im); } }
        $tmp = $path . '.tmp';
        if (!image_write($im, $tmp, $mime, 82)) { @unlink($tmp); return false; }
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        $thumb = $w > 520 ? imagescale($im, 520, (int)round($h * 520 / $w), IMG_BICUBIC) : $im;   // a thumbnail file always exists once an image is optimised
        if ($thumb) image_write($thumb, UPLOAD_DIR . thumb_name($name), $mime, 76);
        return true;
    } catch (Throwable $e) {
        log_exception($e, 'optimize_image');
        return false;
    }
}

// ================= Admin security helpers =================
/** Active password hash: a hash changed from the panel (stored in the private DB) wins over the config file. */
function admin_password_hash(): string {
    $stored = settingRaw(db(), 'admin_password_hash');
    return $stored !== '' ? $stored : (string)ADMIN_PASSWORD_HASH;
}
/** @return array<int,array{level:string,text:string}> */
function security_warnings(): array {
    $w = [];
    if (!STORAGE_OUTSIDE_WEBROOT) $w[] = ['level' => 'warn', 'text' => 'دیتابیس داخل پوشه سایت ذخیره می‌شود و فقط با .htaccess محافظت می‌شود. روی هاست Nginx یا بدون .htaccess ممکن است قابل دانلود باشد؛ یک پوشه خصوصی بیرون از public_html به نام borj_afraz_private بسازید (راهنما در README).'];
    $host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'))[0]);
    if (!is_https() && !in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) $w[] = ['level' => 'danger', 'text' => 'سایت با HTTPS باز نشده است؛ رمز مدیر بدون رمزنگاری ارسال می‌شود. گواهی SSL را فعال کنید.'];
    if (!gd_available()) $w[] = ['level' => 'warn', 'text' => 'افزونه GD در PHP فعال نیست؛ تصاویر فشرده نمی‌شوند، EXIF/GPS آن‌ها حذف نمی‌شود و بندانگشتی ساخته نمی‌شود.'];
    $n = notifications_configured();
    if (!$n['email'] && !$n['bot']) $w[] = ['level' => 'info', 'text' => 'اعلان درخواست‌های جدید (ایمیل یا ربات پیام‌رسان) تنظیم نشده است؛ فقط با ورود به پنل متوجه درخواست‌ها می‌شوید.'];
    if (!trusted_proxies() && (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) || !empty($_SERVER['HTTP_X_FORWARDED_FOR']))) $w[] = ['level' => 'warn', 'text' => 'درخواست‌ها از پشت پروکسی/CDN می‌رسند ولی TRUSTED_PROXIES تنظیم نشده؛ محدودیت‌های تعداد درخواست بر اساس آی‌پی پروکسی اعمال می‌شود.'];
    if (!is_writable(UPLOAD_DIR)) $w[] = ['level' => 'danger', 'text' => 'پوشه uploads قابل نوشتن نیست؛ آپلود تصویر کار نمی‌کند.'];
    return $w;
}

// ================= SEO: server-rendered HTML shell around the static pages =================
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/**
 * Serves one of the static .html templates with server-side title/description/canonical/Open Graph/JSON-LD and optional fallback content,
 * so crawlers and link previews see real content even without JavaScript.
 * @param array{title:string,description:string,path:string,image?:string,noindex?:bool,jsonld?:array,fallback?:string,status?:int} $o
 */
function render_html_shell(string $template, array $o) {
    $html = (string)@file_get_contents(__DIR__ . '/../' . $template);
    if ($html === '') { http_response_code(500); echo 'template missing'; exit; }
    $title = h($o['title']); $desc = h(mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($o['description'])) ?? ''), 0, 160, 'UTF-8'));
    $url = h(site_url($o['path'])); $img = h(site_url($o['image'] ?? 'assets/logo.webp'));
    $html = preg_replace('#<title>.*?</title>#su', '<title>' . $title . '</title>', $html, 1) ?? $html;
    $html = preg_replace('#<meta\s+(?:property|name)="(?:og:|twitter:)[^>]*>#iu', '', $html) ?? $html;
    $html = preg_replace('#<meta\s+name="description"[^>]*>#iu', '', $html, 1) ?? $html;
    $head = '<meta name="description" content="' . $desc . '"><link rel="canonical" href="' . $url . '">'
        . '<meta property="og:type" content="website"><meta property="og:locale" content="fa_IR"><meta property="og:site_name" content="' . h(SITE_NAME) . '">'
        . '<meta property="og:title" content="' . $title . '"><meta property="og:description" content="' . $desc . '"><meta property="og:url" content="' . $url . '"><meta property="og:image" content="' . $img . '">'
        . '<meta name="twitter:card" content="summary_large_image">' . (!empty($o['noindex']) ? '<meta name="robots" content="noindex,follow">' : '');
    if (!empty($o['jsonld'])) $head .= '<script type="application/ld+json">' . json_encode($o['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    $html = str_replace('</head>', $head . '</head>', $html);
    if (!empty($o['fallback'])) $html = preg_replace('#(<main[^>]*>).*?(</main>)#su', '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $o['fallback']) . '$2', $html, 1) ?? $html;
    http_response_code((int)($o['status'] ?? 200));
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    echo $html; exit;
}

// ================= Stateless CSRF token for the public contact form (visitors never receive a cookie) =================
function public_csrf_token(): string {
    $t = (string)time();
    return $t . '.' . hash_hmac('sha256', 'public-form|' . $t, ip_salt());
}
function verify_public_csrf(): void {
    $v = $_POST['csrf'] ?? '';
    $ok = false;
    if (is_string($v) && preg_match('/^(\d{9,12})\.([a-f0-9]{64})$/', $v, $m)) {
        $age = time() - (int)$m[1];
        $ok = $age >= -60 && $age <= 21600 && hash_equals(hash_hmac('sha256', 'public-form|' . $m[1], ip_salt()), $m[2]);   // valid for 6 hours
    }
    if (!$ok) json_response(['ok' => false, 'message' => 'نشست فرم منقضی شده است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.'], 403);
}
