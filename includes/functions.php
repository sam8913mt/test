<?php
require_once __DIR__ . '/../config.php';
const JSON_FLAGS = JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT;
function json_response(array $data, int $status = 200): never {
 http_response_code($status);
 header('Content-Type: application/json; charset=utf-8');
 header('Cache-Control: no-store, max-age=0');
 header('X-Content-Type-Options: nosniff');
 echo json_encode($data, JSON_FLAGS);
 exit;
}
function db(): PDO {
 static $pdo=null;
 if ($pdo instanceof PDO) return $pdo;
 $pdo=new PDO('sqlite:'.DB_FILE);
 $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $pdo->exec('PRAGMA foreign_keys = ON');
 $pdo->exec('CREATE TABLE IF NOT EXISTS projects (
   id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT DEFAULT "",
   location TEXT DEFAULT "", status TEXT DEFAULT "در حال ساخت", description TEXT DEFAULT "",
   image TEXT DEFAULT "", images TEXT DEFAULT "[]", floors TEXT DEFAULT "", area TEXT DEFAULT "",
   amenities TEXT DEFAULT "", featured INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP
 )');
 $cols=$pdo->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_ASSOC);
 $names=array_column($cols,'name');
 $migrations=['slug'=>"TEXT DEFAULT ''",'images'=>"TEXT DEFAULT '[]'",'floors'=>"TEXT DEFAULT ''",'area'=>"TEXT DEFAULT ''",'amenities'=>"TEXT DEFAULT ''",'featured'=>"INTEGER DEFAULT 0"];
 foreach($migrations as $col=>$def) if(!in_array($col,$names,true)) $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
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
 $stmt=$pdo->prepare('INSERT OR IGNORE INTO pages(slug,title,content,meta_title,meta_description) VALUES(?,?,?,?,?)');
 $stmt->execute(['about','درباره شرکت',settingRaw($pdo,'about'),'درباره برج افراز الماس پردیس','معرفی شرکت برج افراز الماس پردیس']);
 $stmt->execute(['services','خدمات شرکت','خدمات شرکت را از پنل مدیریت ویرایش کنید.','خدمات برج افراز الماس پردیس','خدمات ساختمانی برج افراز الماس پردیس']);
 return $pdo;
}
function settingRaw(PDO $pdo,string $key): string { $s=$pdo->prepare('SELECT value FROM settings WHERE key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?'':(string)$v; }
function setting(string $key,string $fallback=''): string { $s=db()->prepare('SELECT value FROM settings WHERE key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$fallback:(string)$v; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf']; }
function verify_csrf(): void {
 if($_SERVER['REQUEST_METHOD']!=='POST') return;
 $v=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
 if(!is_string($v) || $v==='' || !hash_equals(csrf_token(),$v)) json_response(['ok'=>false,'message'=>'درخواست نامعتبر است. صفحه را تازه‌سازی کنید.'],403);
}
function is_admin(): bool { return !empty($_SESSION['admin_logged_in']); }
function require_admin(): void { if(!is_admin()) json_response(['ok'=>false,'auth'=>false,'message'=>'برای ادامه باید وارد پنل مدیریت شوید.'],401); }
function slugify(string $text): string {
 $text=trim(mb_strtolower($text,'UTF-8'));
 $text=preg_replace('/[^\p{L}\p{N}]+/u','-',$text);
 return trim($text,'-') ?: ('building-'.bin2hex(random_bytes(3)));
}
function upload_images(string $field='images'): array {
 if(empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) return [];
 $out=[]; $f=$_FILES[$field]; $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
 $count=count($f['name']);
 if($count>12) throw new RuntimeException('در هر بار حداکثر ۱۲ تصویر قابل انتخاب است.');
 try {
  for($i=0;$i<$count;$i++){
   $error=(int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
   if($error===UPLOAD_ERR_NO_FILE) continue;
   if($error!==UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری یکی از تصاویر ناموفق بود.');
   $tmp=(string)($f['tmp_name'][$i] ?? ''); $size=(int)($f['size'][$i] ?? 0);
   if($size<1 || $size>5*1024*1024) throw new RuntimeException('حجم هر تصویر باید بیشتر از صفر و حداکثر ۵ مگابایت باشد.');
   if(!is_uploaded_file($tmp)) throw new RuntimeException('فایل بارگذاری‌شده معتبر نیست.');
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
   if(!isset($allowed[$mime])) throw new RuntimeException('فقط تصویر JPG، PNG یا WEBP مجاز است.');
   $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
   if(!move_uploaded_file($tmp,UPLOAD_DIR.$name)) throw new RuntimeException('ذخیره تصویر انجام نشد.');
   $out[]=$name;
  }
  return $out;
 } catch(Throwable $ex) {
  foreach($out as $name) if(is_file(UPLOAD_DIR.$name)) @unlink(UPLOAD_DIR.$name);
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
  $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
  $dir=str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/')));
  $dir=($dir==='.'||$dir==='/')?'':rtrim($dir,'/');
  if(substr($dir,-4)==='/api') $dir=substr($dir,0,-4);
  $base=$https.'://'.$host.$dir;
 }
 return $base.'/'.ltrim($path,'/');
}

function admin_login_ip_hash(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $ip . '|' . (string)(getenv('LOGIN_RATE_LIMIT_SALT') ?: __FILE__));
}
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
