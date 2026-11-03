<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'message' => 'روش درخواست مجاز نیست.'], 405);
}
verify_csrf();
try {
    // فیلد تله برای ربات‌ها: کاربر واقعی آن را خالی می‌گذارد؛ پاسخ موفقیت ظاهری داده می‌شود.
    if (trim(input_str($_POST, 'website')) !== '') json_response(['ok' => true, 'message' => 'نظر شما ثبت شد و پس از بررسی مدیر نمایش داده می‌شود.']);

    [$v, $errors] = review_parse($_POST);
    if ($errors) json_response(['ok' => false, 'message' => 'لطفاً خطاهای مشخص‌شده را برطرف کنید.', 'errors' => $errors], 422);

    $pq = db()->prepare('SELECT id FROM projects WHERE id=?'); $pq->execute([$v['project_id']]);
    if (!$pq->fetch()) json_response(['ok' => false, 'message' => 'ساختمان پیدا نشد.', 'errors' => ['project_id' => 'ساختمان پیدا نشد.']], 404);

    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|review|' . site_secret());
    $c1 = db()->prepare("SELECT COUNT(*) FROM reviews WHERE ip_hash=? AND created_at >= (NOW() - INTERVAL 1 HOUR)"); $c1->execute([$ipHash]);
    if ((int)$c1->fetchColumn() >= 3) json_response(['ok' => false, 'message' => 'تعداد نظرهای ارسالی از این اتصال بیش از حد مجاز است. لطفاً بعداً دوباره تلاش کنید.'], 429);
    $c2 = db()->prepare("SELECT COUNT(*) FROM reviews WHERE ip_hash=? AND project_id=? AND created_at >= (NOW() - INTERVAL 1 DAY)"); $c2->execute([$ipHash, $v['project_id']]);
    if ((int)$c2->fetchColumn() >= 1) json_response(['ok' => false, 'message' => 'شما در ۲۴ ساعت گذشته برای این ساختمان نظر ثبت کرده‌اید.'], 429);

    // نظر همیشه «در انتظار تأیید» ثبت می‌شود؛ عمومی‌شدن فقط با تأیید مدیر ممکن است.
    db()->prepare("INSERT INTO reviews(project_id, name, rating, comment, status, ip_hash) VALUES(?,?,?,?, 'pending', ?)")
        ->execute([$v['project_id'], $v['name'], $v['rating'], $v['comment'], $ipHash]);
    json_response(['ok' => true, 'message' => 'نظر شما ثبت شد و پس از بررسی مدیر نمایش داده می‌شود.']);
} catch (Throwable $e) {
    error_log('[review.php] ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'ثبت نظر ناموفق بود. لطفاً بعداً دوباره تلاش کنید.'], 500);
}
