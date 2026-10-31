<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
echo "User-agent: *\nDisallow: /admin.html\nDisallow: /login.html\nDisallow: /api/admin.php\nDisallow: /api/auth.php\nDisallow: /private/\nDisallow: /data/\nSitemap: " . site_url('sitemap.php') . "\n";
