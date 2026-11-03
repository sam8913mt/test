<?php
declare(strict_types=1);

/* نظرات مشتریان: ثبت عمومی (در انتظار تأیید) و مدیریت توسط ادمین. */

const REVIEW_STATUSES = ['pending' => 'در انتظار تأیید', 'approved' => 'تأییدشده', 'hidden' => 'مخفی'];

/** اعتبارسنجی نظر عمومی. خروجی: [مقادیر، خطاها]. */
function review_parse(array $in): array {
    $e = []; $v = [];
    $pid = normalize_digits(building_clean_text(input_str($in, 'project_id'), false));
    $v['project_id'] = preg_match('/^\d{1,9}$/', $pid) && (int)$pid > 0 ? (int)$pid : 0;
    if ($v['project_id'] === 0) $e['project_id'] = 'ساختمان نامعتبر است.';

    $name = building_clean_text(input_str($in, 'name'), false);
    $nl = mb_strlen($name, 'UTF-8');
    if ($name === '') $e['name'] = 'نام الزامی است.';
    elseif ($nl < 2 || $nl > 60) $e['name'] = 'نام باید بین ۲ تا ۶۰ نویسه باشد.';
    $v['name'] = $name;

    $rating = normalize_digits(building_clean_text(input_str($in, 'rating'), false));
    if (!preg_match('/^[1-5]$/', $rating)) { $e['rating'] = 'امتیاز را بین ۱ تا ۵ انتخاب کنید.'; $v['rating'] = 0; }
    else $v['rating'] = (int)$rating;

    $comment = building_clean_text(input_str($in, 'comment'), true);
    $cl = mb_strlen($comment, 'UTF-8');
    if ($comment === '') $e['comment'] = 'متن نظر الزامی است.';
    elseif ($cl < 10 || $cl > 1000) $e['comment'] = 'متن نظر باید بین ۱۰ تا ۱۰۰۰ نویسه باشد.';
    elseif (preg_match('~(https?://|www\.|\.(com|ir|net|org)\b)~iu', $comment)) $e['comment'] = 'ارسال لینک در نظر مجاز نیست.';
    $v['comment'] = $comment;
    return [$v, $e];
}

/** انتقال مجاز وضعیت برای هر عملیات مدیریتی؛ null یعنی مجاز نیست. */
function review_transition(string $action, string $current): ?string {
    return match ($action) {
        'review_approve' => $current === 'pending' ? 'approved' : null,
        'review_hide'    => ($current === 'pending' || $current === 'approved') ? 'hidden' : null,
        'review_restore' => $current === 'hidden' ? 'approved' : null,
        default          => null,
    };
}

function review_summary(int $projectId): array {
    $q = db()->prepare("SELECT COUNT(*) AS c, AVG(rating) AS a FROM reviews WHERE project_id=? AND status='approved'");
    $q->execute([$projectId]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    $count = (int)($r['c'] ?? 0);
    return ['count' => $count, 'average' => $count > 0 ? round((float)$r['a'], 1) : null];
}

function reviews_public(int $projectId, int $limit = 30): array {
    $q = db()->prepare("SELECT id, name, rating, comment, created_at FROM reviews WHERE project_id=? AND status='approved' ORDER BY id DESC LIMIT ?");
    $q->bindValue(1, $projectId, PDO::PARAM_INT); $q->bindValue(2, $limit, PDO::PARAM_INT); $q->execute();
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['name' => (string)$r['name'], 'rating' => (int)$r['rating'], 'comment' => (string)$r['comment'], 'created_at' => (string)$r['created_at']];
    }
    return $out;
}
