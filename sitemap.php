<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

/** تاریخ به‌صورت YYYY-MM-DD؛ در صورت نامعتبر بودن null. */
function sitemap_date($v): ?string {
    $d = substr((string)$v, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
}
$entries = [];
$latest = null;
foreach (db()->query('SELECT id, slug, created_at, updated_at FROM projects ORDER BY id DESC') as $project) {
    $slug = (string)($project['slug'] ?: (string)$project['id']);
    $mod = sitemap_date($project['updated_at'] ?: $project['created_at']);
    if ($mod !== null && ($latest === null || $mod > $latest)) $latest = $mod;
    $entries[] = [site_url('building.html?slug=' . rawurlencode($slug)), $mod];
}
foreach (db()->query("SELECT slug, published_at, updated_at FROM articles WHERE status='published' ORDER BY id DESC") as $article) {
    $entries[] = [site_url('article.html?slug=' . rawurlencode((string)$article['slug'])), sitemap_date($article['updated_at'] ?: $article['published_at'])];
}
array_unshift($entries, [site_url('articles.html'), null]);
// صفحه اصلی با آدرس ریشه (همان canonical) اول می‌آید.
array_unshift($entries, [site_url('page.html?slug=services'), null]);
array_unshift($entries, [site_url('page.html?slug=about'), null]);
array_unshift($entries, [site_url(), $latest]);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
$seen = [];
foreach ($entries as [$url, $mod]) {
    if (isset($seen[$url])) continue;
    $seen[$url] = true;
    echo '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
    if ($mod !== null) echo '<lastmod>' . $mod . '</lastmod>';
    echo '</url>';
}
echo '</urlset>';
