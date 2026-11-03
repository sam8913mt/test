<?php
declare(strict_types=1);

/* مقالات: پیش‌نویس/منتشرشده، تصویر شاخص و نمایش عمومی. */

const ARTICLE_STATUSES = ['draft' => 'پیش‌نویس', 'published' => 'منتشرشده'];

/** اعتبارسنجی فرم مقاله. خروجی: [مقادیر، خطاها]. */
function article_parse(array $in): array {
    $e = []; $v = [];
    $title = building_clean_text(input_str($in, 'title'), false);
    $tl = mb_strlen($title, 'UTF-8');
    if ($title === '') $e['title'] = 'عنوان مقاله الزامی است.';
    elseif ($tl < 5 || $tl > 150) $e['title'] = 'عنوان باید بین ۵ تا ۱۵۰ نویسه باشد.';
    $v['title'] = $title;

    $slug = building_clean_text(input_str($in, 'slug'), false);
    if (mb_strlen($slug, 'UTF-8') > 160) $e['slug'] = 'شناسه آدرس نباید بیش از ۱۶۰ نویسه باشد.';
    $v['slug'] = $slug;

    $excerpt = building_clean_text(input_str($in, 'excerpt'), false);
    if (mb_strlen($excerpt, 'UTF-8') > 300) $e['excerpt'] = 'خلاصه نباید بیش از ۳۰۰ نویسه باشد.';
    $v['excerpt'] = $excerpt;

    $content = building_clean_text(input_str($in, 'content'), true);
    $cl = mb_strlen($content, 'UTF-8');
    if ($content === '') $e['content'] = 'متن مقاله الزامی است.';
    elseif ($cl < 50 || $cl > 30000) $e['content'] = 'متن مقاله باید بین ۵۰ تا ۳۰۰۰۰ نویسه باشد.';
    $v['content'] = $content;

    $mt = building_clean_text(input_str($in, 'meta_title'), false);
    if (mb_strlen($mt, 'UTF-8') > 120) $e['meta_title'] = 'عنوان سئو نباید بیش از ۱۲۰ نویسه باشد.';
    $v['meta_title'] = $mt;
    $md = building_clean_text(input_str($in, 'meta_description'), false);
    if (mb_strlen($md, 'UTF-8') > 300) $e['meta_description'] = 'توضیحات سئو نباید بیش از ۳۰۰ نویسه باشد.';
    $v['meta_description'] = $md;

    $status = building_clean_text(input_str($in, 'status'), false);
    if ($status === '') $status = 'draft';
    if (!isset(ARTICLE_STATUSES[$status])) $e['status'] = 'وضعیت انتشار معتبر نیست.';
    $v['status'] = $status;
    return [$v, $e];
}

function article_excerpt(array $a, int $len = 160): string {
    $ex = trim((string)$a['excerpt']);
    if ($ex !== '') return $ex;
    $plain = preg_replace('/^(##\s+|-\s+)/mu', '', (string)$a['content']) ?? '';
    $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    return mb_strlen($plain, 'UTF-8') <= $len ? $plain : mb_substr($plain, 0, $len - 1, 'UTF-8') . '…';
}

function article_iso(string $dt): string {
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dt) ? str_replace(' ', 'T', $dt) . 'Z' : '';
}

function article_cover_url(array $a): string {
    $c = (string)$a['cover'];
    return ($c !== '' && is_file(UPLOAD_DIR . $c)) ? UPLOAD_URL . $c : '';
}

function article_card(array $a): array {
    return [
        'id' => (int)$a['id'], 'title' => (string)$a['title'], 'slug' => (string)$a['slug'],
        'excerpt' => article_excerpt($a), 'cover' => article_cover_url($a), 'published_at' => article_iso((string)$a['published_at']),
    ];
}

function article_admin_row(array $a): array {
    return [
        'id' => (int)$a['id'], 'title' => (string)$a['title'], 'slug' => (string)$a['slug'], 'excerpt' => (string)$a['excerpt'],
        'content' => (string)$a['content'], 'meta_title' => (string)$a['meta_title'], 'meta_description' => (string)$a['meta_description'],
        'status' => (string)$a['status'], 'cover' => article_cover_url($a), 'cover_name' => (string)$a['cover'],
        'published_at' => (string)$a['published_at'], 'updated_at' => (string)$a['updated_at'], 'created_at' => (string)$a['created_at'],
        'url' => 'article.html?slug=' . rawurlencode((string)$a['slug']),
    ];
}

function articles_latest(int $n): array {
    $q = db()->prepare("SELECT * FROM articles WHERE status='published' ORDER BY published_at DESC, id DESC LIMIT ?");
    $q->bindValue(1, $n, PDO::PARAM_INT); $q->execute();
    return array_map('article_card', $q->fetchAll(PDO::FETCH_ASSOC));
}
