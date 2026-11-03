<?php
declare(strict_types=1);
// در محیط واقعی خطاها نباید به بازدیدکننده نمایش داده شوند؛ فقط در لاگ سرور ثبت می‌شوند.
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
// مرز روز آمار بازدید بر اساس وقت ایران محاسبه می‌شود.
date_default_timezone_set('Asia/Tehran');
// نشست مدیر: حداکثر ۲ ساعت بی‌فعالیتی و ۸ ساعت از زمان ورود (ثانیه)
define('ADMIN_IDLE_TIMEOUT', 7200);
define('ADMIN_MAX_SESSION', 28800);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)ADMIN_IDLE_TIMEOUT);
    // پشت پروکسی/CDN (مثل Cloudflare) هم کوکی باید Secure باشد.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($isHttps) ini_set('session.cookie_secure', '1');
    session_start();
}
define('SITE_NAME', 'برج افراز الماس پردیس');
define('SITE_TAGLINE', 'ساخت آینده، با اعتماد');
// نام‌های دیگری که کاربران برای جست‌وجوی شرکت استفاده می‌کنند؛ در داده ساختاریافته (alternateName) ثبت می‌شود.
define('SITE_ALT_NAMES', ['برج افراز پردیس', 'برج افراز الماس']);
$externalPrivateConfig = dirname(__DIR__) . '/borj_afraz_private/private_config.php';
$localPrivateConfig = __DIR__ . '/private_config.php';
$adminConfigLoaded = false;
if (is_file($externalPrivateConfig)) {
    require_once $externalPrivateConfig;
    $adminConfigLoaded = true;
} elseif (is_file($localPrivateConfig)) {
    require_once $localPrivateConfig;
    $adminConfigLoaded = true;
} else {
    // Fail closed: the public package deliberately contains no working admin credentials.
    if (getenv('ADMIN_USERNAME') !== false && getenv('ADMIN_PASSWORD_HASH') !== false) {
        define('ADMIN_USERNAME', (string)getenv('ADMIN_USERNAME'));
        define('ADMIN_PASSWORD_HASH', (string)getenv('ADMIN_PASSWORD_HASH'));
        $adminConfigLoaded = true;
    } else {
        define('ADMIN_USERNAME', '__configure_admin_first__');
        define('ADMIN_PASSWORD_HASH', '!admin-login-disabled-until-configured!');
    }
}
define('ADMIN_CONFIGURED', $adminConfigLoaded);
if (!defined('SITE_PHONE')) define('SITE_PHONE', (string)(getenv('SITE_PHONE') ?: '+989123365460'));
define('SITE_EMAIL', '');
define('SITE_ADDRESS', 'پردیس، ایران');
// اتصال MySQL (cPanel): مقادیر را در private_config.php تعریف کنید (نمونه در private_config.example.php).
if (!defined('DB_HOST')) define('DB_HOST', (string)(getenv('DB_HOST') ?: 'localhost'));
if (!defined('DB_PORT')) define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
if (!defined('DB_NAME')) define('DB_NAME', (string)(getenv('DB_NAME') ?: ''));
if (!defined('DB_USER')) define('DB_USER', (string)(getenv('DB_USER') ?: ''));
if (!defined('DB_PASS')) define('DB_PASS', (string)(getenv('DB_PASS') ?: ''));
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
