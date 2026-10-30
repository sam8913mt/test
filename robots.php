<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
// The admin page is protected by login + noindex (meta tag and X-Robots-Tag header); it is deliberately NOT listed here.
echo "User-agent: *\nDisallow: /api/\nSitemap: " . site_url('sitemap.php') . "\n";
