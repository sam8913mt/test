<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

function contact_fail(string $message, int $status = 422): never {
    json_response(['ok' => false, 'message' => $message], $status);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'message' => 'روش درخواست مجاز نیست.'], 405);
}
verify_csrf();

try {
    // Honeypot field: real visitors leave it empty. Bots get a fake success.
    if (trim((string)($_POST['company_website'] ?? '')) !== '') {
        json_response(['ok' => true, 'message' => 'درخواست شما ثبت شد.']);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $phoneDigits = strtr($phoneRaw, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'
    ]);
    $phone = preg_replace('/[^0-9+]/', '', $phoneDigits) ?? '';
    $email = trim((string)($_POST['email'] ?? ''));
    $topic = trim((string)($_POST['topic'] ?? 'اطلاعات پروژه'));
    $message = trim((string)($_POST['message'] ?? ''));
    $projectId = filter_var($_POST['project_id'] ?? 0, FILTER_VALIDATE_INT);
    $allowedTopics = ['اطلاعات پروژه', 'درخواست مشاوره خرید', 'مشارکت در ساخت', 'سایر موارد'];

    if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 100) contact_fail('نام باید بین ۲ تا ۱۰۰ نویسه باشد.');
    if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) contact_fail('شماره تلفن را با ۷ تا ۱۵ رقم وارد کنید.');
    if ($email !== '' && (mb_strlen($email, 'UTF-8') > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) contact_fail('آدرس ایمیل واردشده معتبر نیست.');
    if (!in_array($topic, $allowedTopics, true)) contact_fail('موضوع درخواست معتبر نیست.');
    if (mb_strlen($message, 'UTF-8') < 5 || mb_strlen($message, 'UTF-8') > 2000) contact_fail('توضیحات درخواست باید بین ۵ تا ۲۰۰۰ نویسه باشد.');

    $projectTitle = '';
    if ($projectId === false || $projectId === null || $projectId < 0) contact_fail('پروژه انتخاب‌شده معتبر نیست.');
    if ($projectId > 0) {
        $projectStmt = db()->prepare('SELECT title FROM projects WHERE id = ?');
        $projectStmt->execute([$projectId]);
        $foundTitle = $projectStmt->fetchColumn();
        if ($foundTitle === false) contact_fail('پروژه انتخاب‌شده پیدا نشد؛ لطفاً دوباره انتخاب کنید.');
        $projectTitle = (string)$foundTitle;
    }

    // One-way pseudonymous IP hash for modest abuse prevention; raw IPs are not retained.
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ipHash = hash('sha256', $ip . '|' . DB_FILE);
    $rate = db()->prepare("SELECT COUNT(*) FROM contact_requests WHERE ip_hash = ? AND created_at >= datetime('now', '-1 hour')");
    $rate->execute([$ipHash]);
    if ((int)$rate->fetchColumn() >= 5) contact_fail('تعداد درخواست‌ها از این اتصال بیش از حد مجاز است. لطفاً کمی بعد دوباره تلاش کنید.', 429);
    $recent = db()->prepare("SELECT COUNT(*) FROM contact_requests WHERE ip_hash = ? AND created_at >= datetime('now', '-45 seconds')");
    $recent->execute([$ipHash]);
    if ((int)$recent->fetchColumn() > 0) contact_fail('درخواست قبلی شما تازه ثبت شده است؛ لطفاً پیش از ارسال دوباره کمی صبر کنید.', 429);

    $insert = db()->prepare('INSERT INTO contact_requests(name, phone, email, topic, project_title, message, ip_hash) VALUES(?,?,?,?,?,?,?)');
    $insert->execute([$name, $phone, $email, $topic, $projectTitle, $message, $ipHash]);
    json_response(['ok' => true, 'message' => 'درخواست شما با موفقیت ثبت شد. برای پیگیری، همکاران شرکت می‌توانند از اطلاعات تماس واردشده استفاده کنند.']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'ثبت درخواست ناموفق بود. لطفاً بعداً دوباره تلاش کنید.'], 500);
}
