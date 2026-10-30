<?php
declare(strict_types=1);
// Server-rendered shell for the home page (adds canonical/Open Graph with absolute URLs). The visible page is still index.html + main.js.
require_once __DIR__ . '/includes/functions.php';
try {
    render_html_shell('index.html', [
        'title' => 'برج افراز الماس پردیس | پروژه‌ها و خدمات ساختمانی',
        'description' => 'معرفی شرکت برج افراز الماس پردیس، خدمات ساختمانی و ساختمان‌های در حال ساخت و تکمیل‌شده.',
        'path' => 'index.html',
    ]);
} catch (Throwable $e) {
    log_exception($e, 'index.php');
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
}
