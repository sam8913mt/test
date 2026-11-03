<?php
declare(strict_types=1);
/*
 * ابزار بررسی محیط هاست (cPanel). فقط وقتی کار می‌کند که SETUP_CHECK_KEY در private_config.php تعریف شده باشد
 * و با آدرس  setup_check.php?key=کلید  صدا زده شود؛ در غیر این صورت ۴۰۴ می‌دهد.
 * پس از پایان بررسی، این فایل را حتماً حذف کنید.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$expected = defined('SETUP_CHECK_KEY') ? (string)SETUP_CHECK_KEY : '';
$given = is_string($_GET['key'] ?? null) ? (string)$_GET['key'] : '';
if (strlen($expected) < 16 || !hash_equals($expected, $given)) { http_response_code(404); echo 'Not found'; exit; }

$fails = 0; $warns = 0;
function chk(string $label, bool $ok, string $detail = '', bool $warnOnly = false): void {
    global $fails, $warns;
    if ($ok) { echo "[OK]   $label" . ($detail !== '' ? " — $detail" : '') . "\n"; return; }
    if ($warnOnly) { $warns++; echo "[WARN] $label" . ($detail !== '' ? " — $detail" : '') . "\n"; return; }
    $fails++; echo "[FAIL] $label" . ($detail !== '' ? " — $detail" : '') . "\n";
}
echo "== بررسی محیط ==\n";
chk('PHP >= 8.1', PHP_VERSION_ID >= 80100, PHP_VERSION);
foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'ctype', 'json', 'session', 'filter'] as $ext) chk("extension: $ext", extension_loaded($ext));
chk('ADMIN_CONFIGURED (private_config.php با هش رمز)', defined('ADMIN_CONFIGURED') && ADMIN_CONFIGURED);
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
chk('درخواست فعلی روی HTTPS است', $https, (string)($_SERVER['HTTP_HOST'] ?? ''), true);
chk('upload_max_filesize >= 5M', (int)ini_get('upload_max_filesize') >= 5, (string)ini_get('upload_max_filesize'), true);
chk('post_max_size >= 32M', (int)ini_get('post_max_size') >= 32, (string)ini_get('post_max_size'), true);
chk('display_errors خاموش است', in_array(strtolower((string)ini_get('display_errors')), ['', '0', 'off'], true), (string)ini_get('display_errors'));

echo "\n== مسیرها و دسترسی‌ها ==\n";
chk('پوشه uploads قابل نوشتن است', is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR), UPLOAD_DIR);
$tmp = UPLOAD_DIR . '.write-test-' . bin2hex(random_bytes(4));
$wrote = @file_put_contents($tmp, 'x') !== false; if ($wrote) @unlink($tmp);
chk('نوشتن و حذف فایل در uploads', $wrote);
$sess = (string)session_save_path();
chk('مسیر ذخیره نشست قابل نوشتن است', $sess === '' || is_writable($sess), $sess === '' ? '(پیش‌فرض سرور)' : $sess, true);

echo "\n== دیتابیس ==\n";
try {
    require_once __DIR__ . '/includes/functions.php';
    $pdo = db();
    chk('اتصال به MySQL', true, (string)$pdo->query('SELECT VERSION()')->fetchColumn());
    foreach (['projects', 'settings', 'services', 'pages', 'contact_requests', 'admin_login_attempts', 'faqs', 'units', 'reviews', 'articles', 'content_views', 'about_items'] as $t) {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        chk("جدول $t", true, "$n ردیف");
    }
    $enc = $pdo->query("SELECT 'برج افراز الماس پردیس'")->fetchColumn();
    chk('کدگذاری فارسی (UTF-8)', $enc === 'برج افراز الماس پردیس');
} catch (Throwable $e) {
    chk('اتصال به دیتابیس یا نبودن جدول‌ها (فایل‌های SQL را import کرده‌اید؟)', false, get_class($e) . ': ' . $e->getMessage());
}
echo "\nنتیجه: $fails خطا، $warns هشدار\n";
echo "یادآوری: این فایل را بعد از بررسی حذف کنید. همچنین دسترسی مستقیم به /includes/functions.php و /config.php باید 403 یا خالی باشد (در مرورگر امتحان کنید).\n";
