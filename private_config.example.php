<?php
// نمونه تنظیمات مدیر؛ این فایل را به private_config.php کپی کنید و مقادیر را تغییر دهید.
// هرگز private_config.php را به مخزن عمومی Git اضافه نکنید.
declare(strict_types=1);
define('ADMIN_USERNAME', 'change-this-username');
// برای تولید هش، در ترمینال PHP اجرا کنید: php -r "echo password_hash('YOUR-STRONG-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
define('ADMIN_PASSWORD_HASH', 'PASTE_A_REAL_PASSWORD_HASH_HERE');
define('SITE_PHONE', '+989123365460');

// --- اتصال MySQL (از cPanel ← MySQL Databases؛ نام دیتابیس و کاربر معمولاً با پیشوند نام کاربری cPanel شروع می‌شوند) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_borj');
define('DB_USER', 'cpaneluser_borjuser');
define('DB_PASS', 'CHANGE_THIS_DB_PASSWORD');

// --- تنظیمات اختیاری محیط واقعی (cPanel) ---
// ۱) برای استفاده از setup_check.php یک کلید تصادفی حداقل ۱۶ نویسه‌ای بگذارید و بعد از بررسی فایل را حذف کنید:
// define('SETUP_CHECK_KEY', 'یک-رشته-تصادفی-بلند');
// ۲) آدرس دامنه برای sitemap/robots در .htaccess با SetEnv SITE_BASE_URL https://YOUR-DOMAIN.ir تنظیم می‌شود.
