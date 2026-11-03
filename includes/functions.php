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
 if (DB_NAME==='' || DB_USER==='') throw new RuntimeException('اتصال دیتابیس تنظیم نشده است (DB_NAME / DB_USER را در private_config.php تعریف کنید).');
 $dsn='mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
 $pdo=new PDO($dsn,DB_USER,DB_PASS,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES=>false,
  // زمان دیتابیس UTC است (مثل نسخه‌ی قبلی)؛ حالت غیرسخت‌گیر باعث می‌شود فیلد خالی به‌جای خطا مقدار پیش‌فرض بگیرد.
  PDO::MYSQL_ATTR_INIT_COMMAND=>"SET time_zone='+00:00', SESSION sql_mode='NO_ENGINE_SUBSTITUTION'",
 ]);
 return $pdo;
}
function settingRaw(PDO $pdo,string $key): string { $s=$pdo->prepare('SELECT `value` FROM settings WHERE `key`=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?'':(string)$v; }
function setting(string $key,string $fallback=''): string { $s=db()->prepare('SELECT `value` FROM settings WHERE `key`=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$fallback:(string)$v; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf']; }
function verify_csrf(): void {
 if($_SERVER['REQUEST_METHOD']!=='POST') return;
 $v=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
 if(!is_string($v) || $v==='' || !hash_equals(csrf_token(),$v)) json_response(['ok'=>false,'message'=>'درخواست نامعتبر است. صفحه را تازه‌سازی کنید.'],403);
}
function site_secret(): string {
 static $secret=null;
 if($secret!==null) return $secret;
 $v=settingRaw(db(),'site_secret');
 if($v===''){ db()->prepare('INSERT IGNORE INTO settings(`key`,`value`) VALUES(?,?)')->execute(['site_secret',bin2hex(random_bytes(32))]); $v=settingRaw(db(),'site_secret'); }
 return $secret=$v;
}
function admin_session_reset(): void { $_SESSION=[]; if(session_status()===PHP_SESSION_ACTIVE) session_regenerate_id(true); }
function is_admin(): bool {
 if(empty($_SESSION['admin_logged_in'])) return false;
 $now=time(); $last=(int)($_SESSION['admin_last'] ?? 0); $start=(int)($_SESSION['admin_start'] ?? 0);
 if(($last>0 && $now-$last>ADMIN_IDLE_TIMEOUT) || ($start>0 && $now-$start>ADMIN_MAX_SESSION)){ admin_session_reset(); return false; }
 $_SESSION['admin_last']=$now;
 return true;
}
function require_admin(): void { if(!is_admin()) json_response(['ok'=>false,'auth'=>false,'message'=>'برای ادامه باید وارد پنل مدیریت شوید.'],401); }
function slugify(string $text): string {
 $text=trim(mb_strtolower($text,'UTF-8'));
 $text=preg_replace('/[^\p{L}\p{N}]+/u','-',$text);
 $text=trim((string)$text,'-');
 if($text==='') return 'building-'.bin2hex(random_bytes(3));
 if(ctype_digit($text)) $text='building-'.$text;
 return mb_substr($text,0,160,'UTF-8');
}
function upload_images(string $field='images', int $maxNew=12): array {
 if(empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) return [];
 $out=[]; $f=$_FILES[$field]; $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
 $count=count($f['name']);
 $real=0; for($i=0;$i<$count;$i++) if((int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $real++;
 if($real>$maxNew) throw new RuntimeException('در هر بار حداکثر '.$maxNew.' تصویر قابل انتخاب است.');
 try {
  for($i=0;$i<$count;$i++){
   $error=(int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
   if($error===UPLOAD_ERR_NO_FILE) continue;
   $label='«'.mb_substr((string)($f['name'][$i] ?? ''),0,40,'UTF-8').'»';
   if($error===UPLOAD_ERR_INI_SIZE || $error===UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('حجم تصویر '.$label.' بیش از حد مجاز سرور است.');
   if($error!==UPLOAD_ERR_OK) throw new RuntimeException('بارگذاری تصویر '.$label.' ناموفق بود.');
   $tmp=(string)($f['tmp_name'][$i] ?? ''); $size=(int)($f['size'][$i] ?? 0);
   if($size<1 || $size>5*1024*1024) throw new RuntimeException('حجم هر تصویر باید بیشتر از صفر و حداکثر ۵ مگابایت باشد ('.$label.').');
   if(!is_uploaded_file($tmp)) throw new RuntimeException('فایل بارگذاری‌شده معتبر نیست.');
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
   if(!is_string($mime) || !isset($allowed[$mime])) throw new RuntimeException('فقط تصویر JPG، PNG یا WEBP مجاز است ('.$label.').');
   // علاوه بر نوع MIME، ساختار واقعی تصویر و ابعاد آن نیز بررسی می‌شود.
   $info=@getimagesize($tmp);
   if($info===false || ($info['mime'] ?? '')!==$mime) throw new RuntimeException('فایل '.$label.' تصویر معتبر نیست.');
   if($info[0]<200 || $info[1]<200) throw new RuntimeException('ابعاد تصویر '.$label.' باید حداقل ۲۰۰×۲۰۰ پیکسل باشد.');
   if($info[0]>8000 || $info[1]>8000) throw new RuntimeException('ابعاد تصویر '.$label.' بیش از ۸۰۰۰ پیکسل است.');
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
    return hash('sha256', $ip . '|login|' . site_secret());
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

require_once __DIR__ . '/building.php';
require_once __DIR__ . '/faq.php';
require_once __DIR__ . '/units.php';
require_once __DIR__ . '/reviews.php';
require_once __DIR__ . '/articles.php';
require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/about.php';
