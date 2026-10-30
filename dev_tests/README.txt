تست‌های توسعه‌دهنده (روی سرور عمومی قرار نگیرد؛ .htaccess این پوشه را مسدود می‌کند و بهتر است قبل از انتشار حذف شود)

  python3 dev_tests/static_checks.py     # هماهنگی فیلدهای فرم با PHP، تعادل پرانتزها، node --check
  python3 dev_tests/migration_test.py    # مهاجرت SQLite روی دیتابیس قدیمی
  PW_PATH=/path/to/node_modules/playwright node dev_tests/ui_test.js   # فرم ادمین، صفحه عمومی، CSP، دسترس‌پذیری

توجه: ui_test.js بک‌اند PHP را با یک شبیه‌ساز داخل مرورگر جایگزین می‌کند؛ کد PHP را اجرا نمی‌کند.
