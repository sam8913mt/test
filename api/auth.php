<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    json_response(['ok' => true, 'authenticated' => is_admin(), 'configured' => ADMIN_CONFIGURED, 'csrf' => csrf_token()]);
}
if ($method !== 'POST') {
    header('Allow: GET, POST');
    json_response(['ok' => false, 'message' => 'روش درخواست مجاز نیست.'], 405);
}
verify_csrf();
$action = (string)($_POST['action'] ?? 'login');

if ($action === 'logout') {
    if (is_admin()) audit('logout');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    json_response(['ok' => true]);
}
if ($action !== 'login') json_response(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 400);

if (!ADMIN_CONFIGURED) {
    json_response(['ok' => false, 'message' => 'ورود مدیریت هنوز پیکربندی نشده است.'], 503);
}
if (admin_login_is_limited()) {
    json_response(['ok' => false, 'message' => 'تلاش‌های ورود بیش از حد مجاز است. ۱۵ دقیقه بعد دوباره تلاش کنید.'], 429);
}
$username = (string)($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');
try {
    $okUser = hash_equals(ADMIN_USERNAME, $username);
    $okPass = password_verify($password, admin_password_hash());   // always verified, so timing does not reveal which part was wrong
    if ($okUser && $okPass) {
        admin_login_clear_failures();
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_at'] = $_SESSION['last_seen'] = time();
        audit('login');
        json_response(['ok' => true, 'csrf' => csrf_token()]);
    }
    admin_login_record_failure();
    audit('login_failed');
} catch (Throwable $e) {
    log_exception($e, 'auth.php');
    json_response(['ok' => false, 'message' => 'خطای داخلی سرور؛ ورود انجام نشد.'], 500);
}
json_response(['ok' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است.'], 401);
