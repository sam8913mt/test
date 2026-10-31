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
if (hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH)) {
    admin_login_clear_failures();
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    json_response(['ok' => true, 'csrf' => csrf_token()]);
}
admin_login_record_failure();
json_response(['ok' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است.'], 401);
