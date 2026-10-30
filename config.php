<?php
declare(strict_types=1);
// ---------------------------------------------------------------------------------------------
// Core configuration. Secrets live in private_config.php (or in ../borj_afraz_private/), never in Git.
// ---------------------------------------------------------------------------------------------
const APP_VERSION = '2026.10.30';
date_default_timezone_set('Asia/Tehran');   // display/local time; the database stores UTC
ini_set('display_errors', '0');             // never leak stack traces to visitors
ini_set('log_errors', '1');

define('SITE_NAME', 'برج افراز الماس پردیس');
define('SITE_TAGLINE', 'ساخت آینده، با اعتماد');

$externalPrivateDir = dirname(__DIR__) . '/borj_afraz_private';
$externalPrivateConfig = $externalPrivateDir . '/private_config.php';
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

/** Reads a setting from a PHP constant (private_config.php) or an environment variable. */
function cfg(string $name, string $default = ''): string {
    if (defined($name)) { $v = constant($name); return is_scalar($v) ? (string)$v : $default; }
    $e = getenv($name);
    return ($e !== false && $e !== '') ? (string)$e : $default;
}

if (!defined('SITE_PHONE')) define('SITE_PHONE', cfg('SITE_PHONE', '+989123365460'));
define('SITE_EMAIL', '');
define('SITE_ADDRESS', 'پردیس، ایران');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/');

/** True when $path lies inside the web server's document root (i.e. could be downloaded by URL). */
function path_in_docroot(string $path): bool {
    $root = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $rootReal = $root !== '' ? realpath($root) : false;
    $real = realpath($path);
    if ($rootReal === false || $real === false) return false;
    $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/') . '/';
    $real = rtrim(str_replace('\\', '/', $real), '/') . '/';
    return strncasecmp($real, $rootReal, strlen($rootReal)) === 0;
}

// Private storage (database + log) is kept OUTSIDE the web root whenever the host allows it.
$privateDir = '';
foreach (array_filter([cfg('SITE_PRIVATE_DIR'), $externalPrivateDir]) as $candidate) {
    $created = false;
    if (!is_dir($candidate)) {
        if (is_writable(dirname($candidate)) && @mkdir($candidate, 0750)) $created = true; else continue;
    }
    if (path_in_docroot($candidate)) { if ($created) @rmdir($candidate); continue; }
    if (is_writable($candidate)) { $privateDir = rtrim($candidate, '/\\'); break; }
}
$localDataDir = __DIR__ . '/data';
if (!is_dir($localDataDir)) @mkdir($localDataDir, 0750, true);
$storageDir = $privateDir !== '' ? $privateDir : $localDataDir;
define('PRIVATE_STORAGE_DIR', $storageDir);
define('STORAGE_OUTSIDE_WEBROOT', $privateDir !== '');
if (!defined('DB_FILE')) {
    $dbFile = $storageDir . '/site.sqlite';
    $legacyDb = $localDataDir . '/site.sqlite';
    // One-time automatic move of an existing database to the private directory (a backup copy is kept in data/).
    if ($privateDir !== '' && !is_file($dbFile) && is_file($legacyDb) && !is_file($legacyDb . '-wal')) {
        if (@copy($legacyDb, $dbFile) && filesize($dbFile) === filesize($legacyDb)) @rename($legacyDb, $legacyDb . '.moved-backup');
        else { @unlink($dbFile); $dbFile = $legacyDb; }
    }
    define('DB_FILE', $dbFile);
}
define('LOG_FILE', $storageDir . '/app.log');
ini_set('error_log', LOG_FILE);

// ---- network helpers (reverse proxy / CDN aware) ----
const CLOUDFLARE_RANGES = ['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13','104.24.0.0/14','172.64.0.0/13','131.0.72.0/22','2400:cb00::/32','2606:4700::/32','2803:f800::/32','2405:b500::/32','2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32'];

/** Proxy addresses whose X-Forwarded-* / CF-Connecting-IP headers may be trusted. Set TRUSTED_PROXIES in private_config.php. */
function trusted_proxies(): array {
    static $list = null;
    if ($list !== null) return $list;
    $list = [];
    $raw = defined('TRUSTED_PROXIES') ? constant('TRUSTED_PROXIES') : (getenv('TRUSTED_PROXIES') ?: '');
    $items = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($items as $item) {
        $item = trim((string)$item);
        if (strtolower($item) === 'cloudflare') $list = array_merge($list, CLOUDFLARE_RANGES); elseif ($item !== '') $list[] = $item;
    }
    return $list;
}
function ip_in_cidr(string $ip, string $cidr): bool {
    $parts = explode('/', $cidr, 2);
    $a = @inet_pton($ip); $b = @inet_pton($parts[0]);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $max = strlen($a) * 8; $bits = isset($parts[1]) ? (int)$parts[1] : $max;
    if ($bits < 0 || $bits > $max) return false;
    $bytes = intdiv($bits, 8); $rem = $bits % 8;
    if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) return false;
    if ($rem === 0) return true;
    $mask = (0xFF << (8 - $rem)) & 0xFF;
    return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
}
function is_trusted_proxy(string $ip): bool {
    foreach (trusted_proxies() as $cidr) if (ip_in_cidr($ip, $cidr)) return true;
    return false;
}
/** The visitor's real IP: REMOTE_ADDR, or the forwarded address when the request came through a trusted proxy. */
function client_ip(): string {
    static $ip = null;
    if ($ip !== null) return $ip;
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ip = $remote;
    if ($remote === 'unknown' || !is_trusted_proxy($remote)) return $ip;
    $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) return $ip = $cf;
    $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
        $hops = array_reverse(array_map('trim', explode(',', $xff)));
        foreach ($hops as $hop) {
            if (!filter_var($hop, FILTER_VALIDATE_IP)) continue;
            if (!is_trusted_proxy($hop)) return $ip = $hop;   // right-most hop that is not one of our proxies
        }
    }
    return $ip;
}
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return $remote !== '' && is_trusted_proxy($remote) && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

// ---- sessions are started lazily (public pages never create a session file) ----
const SESSION_COOKIE = 'BAPSESS';
function ensure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(SESSION_COOKIE);
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
