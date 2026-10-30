<?php
declare(strict_types=1);
// Server-rendered shell for building.html?slug=... : real <title>, description, canonical, Open Graph, JSON-LD and no-JS fallback text.
// The interactive page is still rendered by assets/detail.js from api/building.php.
require_once __DIR__ . '/includes/functions.php';
$slug = trim((string)($_GET['slug'] ?? ''));
try {
    $p = false;
    if ($slug !== '' && mb_strlen($slug, 'UTF-8') <= 160) {
        $q = db()->prepare('SELECT * FROM projects WHERE slug=? OR CAST(id AS TEXT)=? ORDER BY (slug=?) DESC LIMIT 1');
        $q->execute([$slug, $slug, $slug]);
        $p = $q->fetch(PDO::FETCH_ASSOC);
    }
    if (!$p || (!(int)($p['is_published'] ?? 1) && !is_admin())) {
        render_html_shell('building.html', ['title' => 'ساختمان پیدا نشد | ' . SITE_NAME, 'description' => 'ساختمان موردنظر پیدا نشد.', 'path' => 'building.html', 'noindex' => true, 'status' => 404]);
    }
    $b = building_export($p);
    $imgs = array_values(array_filter(all_project_images($p), static fn($i) => is_file(UPLOAD_DIR . $i)));
    $place = implode('، ', array_filter([$b['province'], $b['city'], $b['location']]));
    $desc = $b['short_description'] !== '' ? $b['short_description'] : ($b['description'] !== '' ? $b['description'] : ($b['title'] . ($place !== '' ? ' در ' . $place : '')));
    $path = 'building.html?slug=' . rawurlencode($b['slug']);
    $ld = ['@context' => 'https://schema.org', '@type' => 'Place', 'name' => $b['title'], 'description' => mb_substr(strip_tags($desc), 0, 300, 'UTF-8'), 'url' => site_url($path)];
    if ($imgs) $ld['image'] = array_map(static fn($i) => site_url(UPLOAD_URL . $i), array_slice($imgs, 0, 4));
    if ($b['city'] !== '' || $b['address'] !== '') $ld['address'] = array_filter(['@type' => 'PostalAddress', 'addressCountry' => 'IR', 'addressRegion' => $b['province'], 'addressLocality' => $b['city'], 'streetAddress' => $b['address'], 'postalCode' => $b['postal_code']], static fn($v) => $v !== '');
    if (is_numeric($b['latitude']) && is_numeric($b['longitude']) && $b['latitude'] !== '' && $b['longitude'] !== '') $ld['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float)$b['latitude'], 'longitude' => (float)$b['longitude']];
    $facts = '';
    foreach ([['نوع کاربری', $b['project_type']], ['وضعیت', $b['status']], ['تعداد طبقات', $b['floors']], ['تعداد واحدها', $b['units_count']], ['زیربنا (مترمربع)', $b['area']]] as [$k, $v]) if ($v !== '') $facts .= '<li>' . h($k) . ': ' . h($v) . '</li>';
    $fallback = '<section class="building-hero"><h1>' . h($b['title']) . '</h1>' . ($place !== '' ? '<p>' . h($place) . '</p>' : '') . '</section>'
        . '<section class="building-layout"><div class="building-main"><article class="detail-copy"><p>' . nl2br(h($b['description'] !== '' ? $b['description'] : $desc)) . '</p>' . ($facts !== '' ? '<ul>' . $facts . '</ul>' : '') . '</article></div></section>';
    render_html_shell('building.html', [
        'title' => $b['title'] . ' | ' . SITE_NAME, 'description' => $desc, 'path' => $path,
        'image' => $imgs ? UPLOAD_URL . $imgs[0] : 'assets/logo.webp', 'jsonld' => $ld, 'fallback' => $fallback,
        'noindex' => !(int)($p['is_published'] ?? 1),
    ]);
} catch (Throwable $e) {
    log_exception($e, 'building.php(shell)');
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/building.html');
}
