<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
    session_start();
}
define('SITE_NAME', 'برج افراز الماس پردیس');
define('SITE_TAGLINE', 'ساخت آینده، با اعتماد');
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
define('DB_FILE', __DIR__ . '/data/site.sqlite');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');
if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0750, true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
