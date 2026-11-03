<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
// توجه: پوشه api/ عمداً مسدود نشده است؛ گوگل برای نمایش محتوای صفحات باید api/site.php و api/building.php را بخواند.
echo "User-agent: *\nAllow: /\nDisallow: /admin.html\nDisallow: /login.html\nDisallow: /api/admin.php\nDisallow: /api/auth.php\nDisallow: /api/contact.php\nDisallow: /includes/\nDisallow: /migrations/\nDisallow: /data/\nDisallow: /private/\nSitemap: " . site_url('sitemap.php') . "\n";
