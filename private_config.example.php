<?php
// نمونه تنظیمات خصوصی؛ این فایل را به private_config.php کپی کنید (یا بهتر: در پوشه ../borj_afraz_private/ بیرون از پوشه وب بگذارید).
// هرگز private_config.php را به مخزن عمومی Git اضافه نکنید.
declare(strict_types=1);

// ---- ورود مدیر (الزامی) ----
define('ADMIN_USERNAME', 'change-this-username');
// تولید هش: php -r "echo password_hash('YOUR-STRONG-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
// (پس از ورود می‌توانید رمز را از داخل پنل تغییر دهید؛ آن رمز در دیتابیس ذخیره می‌شود و بر این مقدار اولویت دارد.)
define('ADMIN_PASSWORD_HASH', 'PASTE_A_REAL_PASSWORD_HASH_HERE');
define('SITE_PHONE', '+989123365460');

// ---- اعلان درخواست‌های جدید (اختیاری، پیشنهاد می‌شود) ----
// ایمیل: روی بعضی هاست‌ها تابع mail() کار نمی‌کند یا ایمیل به اسپم می‌رود.
//define('NOTIFY_EMAIL', 'you@example.com');
//define('MAIL_FROM', 'noreply@your-domain.ir');
// ربات پیام‌رسان (سازگار با API تلگرام). TELEGRAM_API_BASE را فقط وقتی عوض کنید که از سرویس سازگار دیگری
// (مثلاً ربات بله) یا یک رله استفاده می‌کنید؛ آدرس دقیق را از مستندات همان سرویس بگیرید.
//define('TELEGRAM_BOT_TOKEN', '123456:ABC...');
//define('TELEGRAM_CHAT_ID', '123456789');
//define('TELEGRAM_API_BASE', 'https://api.telegram.org');

// ---- پشت CDN / پروکسی (مثلاً Cloudflare) ----
// بدون این تنظیم، همه بازدیدکنندگان یک آی‌پی دیده می‌شوند و محدودیت تعداد درخواست برای همه مشترک می‌شود.
// مقدار 'cloudflare' محدوده‌های رسمی کلودفلر را مجاز می‌کند (فهرست را در cloudflare.com/ips بررسی کنید)،
// یا آدرس/CIDR پروکسی خودتان را بنویسید: ['10.0.0.5', '192.168.0.0/24']
//define('TRUSTED_PROXIES', ['cloudflare']);

// ---- محل ذخیره دیتابیس/لاگ (اختیاری) ----
// به‌صورت پیش‌فرض اگر ../borj_afraz_private بیرون از پوشه وب قابل‌ساخت باشد، همان‌جا استفاده می‌شود.
//define('SITE_PRIVATE_DIR', '/home/USER/borj_afraz_private');
