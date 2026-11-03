# راهنمای تنظیم دامنه، DNS و HTTPS (فارسی)

> وضعیت: دامنه‌ی نهایی در این پروژه مشخص نیست و **هیچ تست زنده‌ای روی DNS، SSL یا هاست انجام نشده است.** این سند فقط مراحل و آزمایش‌هایی است که شما باید انجام دهید.

هدف: نسخه‌ی رسمی سایت **`https://YOUR-DOMAIN.ir`** (بدون www). همه‌ی نسخه‌های دیگر باید با ریدایرکت ۳۰۱ به آن بروند.

## ۱) DNS
در تنظیمات دامنه (ثبت‌کننده یا cPanel → *Zone Editor*):
- رکورد `A` برای `@` به IP هاست.
- رکورد `A` یا `CNAME` برای `www` (به همان IP/دامنه) — تا ریدایرکت www به non-www کار کند.
- تا ۲۴ تا ۴۸ ساعت برای انتشار DNS صبر کنید.

## ۲) گواهی SSL
- cPanel → *SSL/TLS Status* → گزینه‌ی **Run AutoSSL** برای هر دو `YOUR-DOMAIN.ir` و `www.YOUR-DOMAIN.ir`.
- مطمئن شوید گواهی هر دو نام را پوشش می‌دهد (وگرنه کاربرِ www قبل از ریدایرکت خطای گواهی می‌بیند).
- مسیر `/.well-known/` در `.htaccess` از ریدایرکت مستثنا شده تا تمدید خودکار گواهی خراب نشود.

## ۳) تنظیم آدرس دامنه در پروژه
1. در `.htaccess` خط زیر را فعال کنید (دامنه‌ی واقعی را بگذارید):
   `SetEnv SITE_BASE_URL https://YOUR-DOMAIN.ir`
   این مقدار برای `sitemap.xml` و `robots.txt` استفاده می‌شود.
2. قوانین ریدایرکت `https` و حذف `www` از قبل در `.htaccess` نوشته شده‌اند (برای localhost اعمال نمی‌شوند). اگر پروژه در زیرپوشه نصب می‌شود، فقط همین سایت‌های زیرپوشه را هم تست کنید.
3. پس از اطمینان از کارکرد کامل HTTPS (فقط بعد از آن!)، خط `Strict-Transport-Security` در `.htaccess` را فعال کنید؛ ابتدا با `max-age=300` امتحان کنید.

## ۴) آزمایش ریدایرکت (با `curl -I`)
```
curl -sI http://www.YOUR-DOMAIN.ir/   | grep -iE "^(HTTP|location)"
curl -sI https://www.YOUR-DOMAIN.ir/  | grep -iE "^(HTTP|location)"
curl -sI http://YOUR-DOMAIN.ir/       | grep -iE "^(HTTP|location)"
curl -sI https://YOUR-DOMAIN.ir/      | grep -iE "^(HTTP|location)"
```
انتظار: سه مورد اول `301` با `Location: https://YOUR-DOMAIN.ir/` ؛ مورد چهارم `200`.
همچنین مسیر با پارامتر را امتحان کنید: `http://www.YOUR-DOMAIN.ir/building.html?slug=test` باید با همان مسیر و پارامتر منتقل شود.

## ۵) بررسی canonical و نقشه سایت
- صفحه اصلی را در مرورگر باز کنید → *View Rendered Source/Inspect* → `<link rel="canonical">` باید `https://YOUR-DOMAIN.ir/` باشد (canonical با جاوااسکریپت و بر اساس آدرس فعلی ساخته می‌شود).
- `https://YOUR-DOMAIN.ir/sitemap.xml` باید فقط آدرس‌های `https://YOUR-DOMAIN.ir/...` داشته باشد.
- `https://YOUR-DOMAIN.ir/robots.txt` باید به همان sitemap اشاره کند.

## ۶) ثبت در ابزارهای گوگل (برای ایندکس)
1. Google Search Console → افزودن ویژگی (Domain یا URL-prefix `https://YOUR-DOMAIN.ir/`).
2. ارسال `https://YOUR-DOMAIN.ir/sitemap.xml`.
3. ابزار *URL Inspection* → *Test Live URL* → *View tested page*: ببینید گوگل بعد از اجرای جاوااسکریپت ساختمان‌ها و پرسش‌ها را می‌بیند یا نه.
4. در *Rich Results Test* آدرس صفحه اصلی و یک صفحه ساختمان را بررسی کنید.

## ۷) سئوی محلی (خارج از کد)
- ثبت کسب‌وکار در Google Business Profile و نقشه‌های نشان/بلد با **نام، آدرس و تلفن یکسان** با سایت.
- پروفایل‌های رسمی شرکت (اینستاگرام، لینکدین و…) را بسازید و بعداً در داده ساختاریافته (`sameAs`) اضافه کنید.
- لینک‌گرفتن از سایت‌ها و دایرکتوری‌های معتبر ساختمانی.
