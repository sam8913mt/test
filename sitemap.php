<?php
declare(strict_types=1);
require_once __DIR__.'/includes/functions.php';
header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
$urls = [site_url('index.html'), site_url('page.html?slug=about'), site_url('page.html?slug=services')];
foreach (db()->query('SELECT id,slug FROM projects ORDER BY id DESC') as $project) {
    $slug = (string)($project['slug'] ?: (string)$project['id']);
    $urls[] = site_url('building.html?slug=' . rawurlencode($slug));
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach (array_unique($urls) as $url) {
    echo '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
}
echo '</urlset>';
