<?php
declare(strict_types=1);
// Server-rendered shell for page.html?slug=about|services|privacy (SEO + link previews). Content is still drawn by assets/detail.js.
require_once __DIR__ . '/includes/functions.php';
$slug = trim((string)($_GET['slug'] ?? 'about'));
try {
    $page = false;
    if (in_array($slug, ['about', 'services', 'privacy'], true)) {
        $q = db()->prepare('SELECT * FROM pages WHERE slug=?'); $q->execute([$slug]); $page = $q->fetch(PDO::FETCH_ASSOC);
    }
    if (!$page) render_html_shell('page.html', ['title' => 'صفحه پیدا نشد | ' . SITE_NAME, 'description' => 'صفحه موردنظر پیدا نشد.', 'path' => 'page.html', 'noindex' => true, 'status' => 404]);
    $title = (string)($page['meta_title'] ?: $page['title']);
    $content = (string)$page['content'];
    render_html_shell('page.html', [
        'title' => $title . ' | ' . SITE_NAME,
        'description' => (string)($page['meta_description'] ?: mb_substr(preg_replace('/\s+/u', ' ', $content) ?? '', 0, 155, 'UTF-8')),
        'path' => 'page.html?slug=' . $slug,
        'fallback' => '<div class="content-page-head"><h1>' . h((string)$page['title']) . '</h1></div><article class="content-body">' . nl2br(h($content)) . '</article>',
    ]);
} catch (Throwable $e) {
    log_exception($e, 'page.php(shell)');
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/page.html');
}
