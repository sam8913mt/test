# نصب دیتابیس MySQL روی cPanel

## ۱) ساخت دیتابیس و کاربر
1. cPanel ← **MySQL® Databases**.
2. در «Create New Database» یک نام بنویسید (مثلاً `borj`) و ساخت را بزنید. نام کامل با پیشوند حساب شما ساخته می‌شود، مثلاً `cpaneluser_borj`.
3. در «MySQL Users» یک کاربر با رمز قوی بسازید (مثلاً `cpaneluser_borjuser`).
4. در «Add User To Database» همان کاربر را به همان دیتابیس اضافه کنید و **ALL PRIVILEGES** بدهید.

## ۲) Import فایل‌های SQL
1. cPanel ← **phpMyAdmin**. از ستون سمت چپ دیتابیس خود را انتخاب کنید.
2. زبانه **Import** ← فایل `migrations/mysql/00_install_all.sql` را انتخاب کنید ← Character set روی `utf-8` ← **Import**.
3. همین فایل همه‌ی جدول‌ها و داده‌های اولیه (تنظیمات، دو صفحه، ۸ پرسش آماده) را می‌سازد.

اگر ترجیح می‌دهید جدا جدا نصب کنید، به همین ترتیب Import کنید (جدول‌های `units` و `reviews` به `projects` وابسته‌اند):

| ترتیب | فایل | محتوا |
|---|---|---|
| ۱ | `01_settings_pages.sql` | تنظیمات سایت، صفحات «درباره» و «خدمات» |
| ۲ | `02_projects.sql` | ساختمان‌ها و مشخصات |
| ۳ | `03_units.sql` | واحدها |
| ۴ | `04_reviews.sql` | نظرات |
| ۵ | `05_articles.sql` | مقالات |
| ۶ | `06_about_items.sql` | موارد صفحه «درباره ما» |
| ۷ | `07_services.sql` | خدمات |
| ۸ | `08_contact_requests.sql` | درخواست‌های فرم تماس |
| ۹ | `09_content_views.sql` | آمار بازدید |
| ۱۰ | `10_admin_login_attempts.sql` | محدودیت ورود مدیر |
| ۱۱ | `11_faqs.sql` | پرسش‌های متداول و ۸ پرسش آماده |

همه‌ی فایل‌ها با `CREATE TABLE IF NOT EXISTS` نوشته شده‌اند و اجرای دوباره‌شان داده‌ی موجود را پاک نمی‌کند.

## ۳) اتصال سایت
فایل `private_config.example.php` را به `private_config.php` کپی کنید و مقادیر را وارد کنید:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_borj');
define('DB_USER', 'cpaneluser_borjuser');
define('DB_PASS', 'رمز-دیتابیس');
```

## ۴) بررسی
در `private_config.php` یک `SETUP_CHECK_KEY` طولانی بگذارید و `setup_check.php?key=...` را باز کنید. همه‌ی موارد باید `[OK]` باشند. پس از بررسی `setup_check.php` را حذف کنید.

## نکات
- نیاز: PHP 8.1 به بالا با افزونه `pdo_mysql` (در cPanel از Select PHP Version یا MultiPHP).
- زمان‌ها در دیتابیس به وقت UTC ذخیره می‌شوند (مثل نسخه‌ی قبلی).
- پشتیبان‌گیری: phpMyAdmin ← Export (روش Quick، قالب SQL) و پوشه `uploads/`.
