<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' || mb_strlen($slug, 'UTF-8') > 160) {
    json_response(['ok' => false, 'message' => 'شناسه ساختمان معتبر نیست.'], 400);
}
try {
    // اگر هم یک slug و هم یک شناسه عددی با این مقدار وجود داشت، slug اولویت دارد.
    $q = db()->prepare('SELECT * FROM projects WHERE slug=? OR CAST(id AS TEXT)=? ORDER BY (slug=?) DESC LIMIT 1');
    $q->execute([$slug, $slug, $slug]);
    $p = $q->fetch(PDO::FETCH_ASSOC);
    if (!$p) json_response(['ok' => false, 'message' => 'ساختمان پیدا نشد.'], 404);

    $images = [];
    foreach (all_project_images($p) as $img) if (is_file(UPLOAD_DIR . $img)) $images[] = UPLOAD_URL . $img;

    $map = null;
    if ($p['latitude'] !== null && $p['longitude'] !== null && $p['latitude'] !== '' && $p['longitude'] !== '') {
        $lat = (float)$p['latitude']; $lng = (float)$p['longitude'];
        $map = ['lat' => $lat, 'lng' => $lng, 'url' => 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lng . '#map=17/' . $lat . '/' . $lng];
    }
    $progress = ($p['progress'] === null || $p['progress'] === '') ? null : (int)$p['progress'];

    json_response([
        'ok' => true,
        'site' => ['name' => SITE_NAME, 'url' => site_url(), 'logo' => 'assets/logo.webp'],
        'building' => [
            'id' => (int)$p['id'], 'title' => (string)$p['title'], 'slug' => (string)($p['slug'] ?: $p['id']),
            'location' => (string)$p['location'], 'status' => (string)$p['status'], 'description' => (string)$p['description'],
            'floors' => (string)$p['floors'], 'area' => (string)$p['area'], 'amenities' => (string)$p['amenities'],
            'project_type' => (string)$p['project_type'], 'units_count' => building_num_text($p['units_count']), 'built_area' => building_num_text($p['built_area']), 'delivery_year' => building_num_text($p['delivery_year']),
            'progress' => $progress, 'map' => $map, 'sections' => building_public_sections($p), 'images' => $images,
        ],
        'phone' => setting('phone', SITE_PHONE),
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'دریافت اطلاعات ساختمان ناموفق بود.'], 500);
}
