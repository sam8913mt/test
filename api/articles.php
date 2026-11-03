<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

$site = ['name' => SITE_NAME, 'alternateNames' => SITE_ALT_NAMES, 'url' => site_url(), 'logo' => 'assets/logo.webp'];
try {
    $slug = trim(input_str($_GET, 'slug'));
    if ($slug !== '') {
        if (mb_strlen($slug, 'UTF-8') > 160) json_response(['ok' => false, 'message' => 'مقاله پیدا نشد.'], 404);
        // فقط مقالات منتشرشده عمومی‌اند؛ پیش‌نویس‌ها به‌عنوان «پیدا نشد» پاسخ می‌دهند.
        $q = db()->prepare("SELECT * FROM articles WHERE slug=? AND status='published'"); $q->execute([$slug]);
        $a = $q->fetch(PDO::FETCH_ASSOC);
        if (!$a) json_response(['ok' => false, 'message' => 'مقاله پیدا نشد.'], 404);
        record_view('article', (int)$a['id']);
        $rq = db()->prepare("SELECT * FROM articles WHERE status='published' AND id<>? ORDER BY published_at DESC, id DESC LIMIT 3"); $rq->execute([(int)$a['id']]);
        $card = article_card($a);
        json_response(['ok' => true, 'site' => $site, 'article' => $card + ['content' => (string)$a['content'], 'meta_title' => (string)$a['meta_title'], 'meta_description' => (string)$a['meta_description'], 'updated_at' => article_iso((string)$a['updated_at'])],
                      'related' => array_map('article_card', $rq->fetchAll(PDO::FETCH_ASSOC))]);
    }
    $per = 9; $page = max(1, (int)($_GET['page'] ?? 1));
    $total = (int)db()->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn();
    $pages = max(1, (int)ceil($total / $per)); if ($page > $pages) $page = $pages;
    $q = db()->prepare("SELECT * FROM articles WHERE status='published' ORDER BY published_at DESC, id DESC LIMIT ? OFFSET ?");
    $q->bindValue(1, $per, PDO::PARAM_INT); $q->bindValue(2, ($page - 1) * $per, PDO::PARAM_INT); $q->execute();
    json_response(['ok' => true, 'site' => $site, 'articles' => array_map('article_card', $q->fetchAll(PDO::FETCH_ASSOC)), 'page' => $page, 'pages' => $pages, 'total' => $total]);
} catch (Throwable $e) {
    error_log('[articles.php] ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'دریافت مقالات ناموفق بود.'], 500);
}
