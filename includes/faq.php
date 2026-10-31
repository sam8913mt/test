<?php
declare(strict_types=1);

/* پرسش‌های متداول: پیش‌فرض‌ها، کاشت اولیه و اعتبارسنجی. توابع کمکی از building.php استفاده می‌کنند. */

const FAQ_MAX_ITEMS = 100;

function faq_defaults(): array {
    return [
        ['چطور درباره یک ساختمان اطلاعات بیشتری بگیرم؟', 'از فهرست ساختمان‌ها، صفحه پروژه موردنظر را باز کنید و از طریق فرم درخواست مشاوره یا شماره تماس شرکت، درخواست خود را ثبت کنید.'],
        ['چطور درخواست مشاوره ثبت کنم؟', 'فرم همین صفحه را تکمیل کنید، موضوع درخواست را انتخاب کنید و در صورت ارتباط، ساختمان موردنظر را مشخص کنید. اطلاعات فرم برای پیگیری درخواست در پنل مدیریت ثبت می‌شود.'],
        ['آیا می‌توانم تصاویر و مشخصات پروژه‌ها را ببینم؟', 'بله؛ در صورت ثبت و انتشار اطلاعات هر ساختمان، صفحه جزئیات آن شامل تصاویر موجود و مشخصات ثبت‌شده نمایش داده می‌شود.'],
        ['چه اطلاعاتی برای ارسال درخواست لازم است؟', 'نام، شماره تماس و توضیح کوتاه درباره موضوع درخواست ضروری است. ایمیل و انتخاب پروژه اختیاری هستند.'],
        ['وضعیت پروژه‌ها (در حال ساخت، تکمیل‌شده، پیش‌فروش و ...) به چه معناست؟', 'وضعیت هر ساختمان در کارت پروژه و صفحه جزئیات آن نمایش داده می‌شود. با فیلتر «وضعیت پروژه» در بخش ساختمان‌ها می‌توانید فقط ساختمان‌های هر وضعیت را ببینید.'],
        ['آیا می‌توانم پیش از ثبت درخواست تماس تلفنی بگیرم؟', 'بله؛ شماره تماس شرکت در بخش «تماس با ما» و پایین همین صفحه نمایش داده می‌شود و ثبت فرم الزامی نیست.'],
        ['اطلاعات واردشده در فرم برای چه استفاده می‌شود؟', 'اطلاعات فرم فقط برای پیگیری همین درخواست ذخیره می‌شود و فقط مدیر سایت به آن دسترسی دارد. لطفاً اطلاعات حساس غیرضروری ارسال نکنید.'],
    ];
}

/** فقط یک‌بار (در اولین ساخت جدول) نمونه پرسش‌ها ثبت می‌شود؛ حذف همه پرسش‌ها توسط مدیر دوباره آن‌ها را برنمی‌گرداند. */
function faq_seed(PDO $pdo): void {
    if (settingRaw($pdo, 'faq_seeded') === '1') return;
    $pdo->beginTransaction();
    try {
        $empty = (int)$pdo->query('SELECT COUNT(*) FROM faqs')->fetchColumn() === 0;
        if ($empty) {
            $ins = $pdo->prepare('INSERT INTO faqs(question, answer, sort_order, is_active) VALUES(?,?,?,1)');
            foreach (faq_defaults() as $i => [$q, $a]) $ins->execute([$q, $a, ($i + 1) * 10]);
        }
        $pdo->prepare('INSERT OR REPLACE INTO settings(key,value) VALUES(?,?)')->execute(['faq_seeded', '1']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** خروجی: [مقادیر آماده ذخیره، خطاها به‌صورت field => message] */
function faq_parse(array $in): array {
    $errors = [];
    $q = building_clean_text(is_array($in['question'] ?? '') ? '' : (string)($in['question'] ?? ''), false);
    $a = building_clean_text(is_array($in['answer'] ?? '') ? '' : (string)($in['answer'] ?? ''), true);
    $orderRaw = normalize_digits(trim(is_array($in['sort_order'] ?? '') ? '' : (string)($in['sort_order'] ?? '')));
    if ($orderRaw === '') $orderRaw = '0';

    $ql = mb_strlen($q, 'UTF-8'); $al = mb_strlen($a, 'UTF-8');
    if ($q === '') $errors['question'] = 'متن پرسش الزامی است.';
    elseif ($ql < 5 || $ql > 300) $errors['question'] = 'پرسش باید بین ۵ تا ۳۰۰ نویسه باشد.';
    if ($a === '') $errors['answer'] = 'متن پاسخ الزامی است.';
    elseif ($al < 5 || $al > 3000) $errors['answer'] = 'پاسخ باید بین ۵ تا ۳۰۰۰ نویسه باشد.';
    if (!preg_match('/^-?\d{1,4}$/', $orderRaw)) $errors['sort_order'] = 'ترتیب نمایش باید عدد صحیح (بین -۹۹۹۹ تا ۹۹۹۹) باشد.';

    $active = isset($in['is_active']) && !is_array($in['is_active']) && (string)$in['is_active'] !== '0' ? 1 : 0;
    return [['question' => $q, 'answer' => $a, 'sort_order' => preg_match('/^-?\d{1,4}$/', $orderRaw) ? (int)$orderRaw : 0, 'is_active' => $active], $errors];
}
