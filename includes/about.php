<?php
declare(strict_types=1);

/* مدیریت «درباره ما»: فیلدهای متنی (settings)، تصویر و آیتم‌های فهرستی (about_items). */

const ABOUT_KINDS = ['achievement' => 'دستاورد', 'stat' => 'آمار شرکت', 'team' => 'عضو تیم', 'milestone' => 'رویداد تاریخچه'];

/** قواعد اعتبارسنجی همه‌ی کلیدهای تنظیمات؛ فقط کلیدهای ارسال‌شده ذخیره می‌شوند، بقیه دست‌نخورده می‌مانند. */
function settings_rules(): array {
    return [
        'tagline' => ['شعار شرکت', 'text', 180], 'phone' => ['شماره تلفن', 'phone', 30], 'email' => ['ایمیل', 'email', 190], 'address' => ['نشانی', 'text', 300],
        'about' => ['معرفی کوتاه', 'long', 3000], 'founded_year' => ['سال آغاز فعالیت', 'year', 4], 'registration_number' => ['شماره ثبت', 'text', 100],
        'company_manager' => ['نام مسئول', 'text', 150], 'credentials' => ['مجوزها و سوابق', 'long', 1500],
        'about_mission' => ['ماموریت', 'long', 1500], 'about_vision' => ['چشم‌انداز', 'long', 1500], 'about_history' => ['تاریخچه شرکت', 'long', 6000],
    ];
}

/** اعتبارسنجی کلیدهای ارسال‌شده. خروجی: [کلید => مقدار، خطاها]. */
function settings_parse(array $in): array {
    $vals = []; $err = [];
    foreach (settings_rules() as $key => [$label, $type, $max]) {
        if (!array_key_exists($key, $in)) continue;
        $v = building_clean_text(input_str($in, $key), $type === 'long');
        $len = mb_strlen($v, 'UTF-8');
        if ($len > $max) { $err[$key] = $label . ' نباید بیش از ' . $max . ' نویسه باشد.'; continue; }
        if ($v !== '') {
            if ($type === 'phone') { $v = normalize_digits($v); if (!preg_match('/^\+?[0-9][0-9\-\s()]{4,24}$/', $v)) { $err[$key] = 'شماره تلفن معتبر نیست (فقط رقم، +، فاصله، پرانتز و خط تیره).'; continue; } }
            if ($type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $err[$key] = 'آدرس ایمیل معتبر نیست.'; continue; }
            if ($type === 'year') { $v = normalize_digits($v); if (!preg_match('/^\d{4}$/', $v) || (int)$v < 1300 || (int)$v > 2100) { $err[$key] = 'سال را چهار رقمی (مثلاً ۱۳۹۸) وارد کنید.'; continue; } }
        }
        $vals[$key] = $v;
    }
    return [$vals, $err];
}

/** اعتبارسنجی یک آیتم «درباره ما». */
function about_item_parse(array $in): array {
    $e = []; $v = [];
    $kind = building_clean_text(input_str($in, 'kind'), false);
    if (!isset(ABOUT_KINDS[$kind])) $e['kind'] = 'نوع مورد معتبر نیست.';
    $v['kind'] = $kind;
    $title = building_clean_text(input_str($in, 'title'), false); $tl = mb_strlen($title, 'UTF-8');
    if ($title === '') $e['title'] = 'عنوان الزامی است.'; elseif ($tl < 2 || $tl > 120) $e['title'] = 'عنوان باید بین ۲ تا ۱۲۰ نویسه باشد.';
    $v['title'] = $title;
    $val = building_clean_text(input_str($in, 'value'), false);
    if (mb_strlen($val, 'UTF-8') > 60) $e['value'] = 'مقدار نباید بیش از ۶۰ نویسه باشد.';
    if ($kind === 'stat' && $val === '' && !isset($e['value'])) $e['value'] = 'برای آمار، مقدار (مثلاً ۱۲۰) الزامی است.';
    $v['value'] = $val;
    $desc = building_clean_text(input_str($in, 'description'), true);
    if (mb_strlen($desc, 'UTF-8') > 600) $e['description'] = 'توضیحات نباید بیش از ۶۰۰ نویسه باشد.';
    $v['description'] = $desc;
    $ord = normalize_digits(building_clean_text(input_str($in, 'sort_order'), false)); if ($ord === '') $ord = '0';
    if (!preg_match('/^-?\d{1,4}$/', $ord)) { $e['sort_order'] = 'ترتیب نمایش باید عدد صحیح باشد.'; $ord = '0'; }
    $v['sort_order'] = (int)$ord;
    $v['is_active'] = isset($in['is_active']) && !is_array($in['is_active']) && (string)$in['is_active'] !== '0' ? 1 : 0;
    return [$v, $e];
}

function about_item_admin_row(array $r): array {
    $img = (string)$r['image'];
    return ['id' => (int)$r['id'], 'kind' => (string)$r['kind'], 'title' => (string)$r['title'], 'value' => (string)$r['value'], 'description' => (string)$r['description'],
            'image' => ($img !== '' && is_file(UPLOAD_DIR . $img)) ? UPLOAD_URL . $img : '', 'image_name' => $img, 'sort_order' => (int)$r['sort_order'], 'is_active' => (bool)$r['is_active']];
}

/** داده‌ی عمومی «درباره ما»؛ فقط موارد پرشده و فعال. */
function about_public(): array {
    $img = setting('about_image', '');
    $items = ['achievement' => [], 'stat' => [], 'team' => [], 'milestone' => []];
    foreach (db()->query('SELECT * FROM about_items WHERE is_active=1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $row = about_item_admin_row($r);
        $items[$row['kind']][] = ['title' => $row['title'], 'value' => $row['value'], 'description' => $row['description'], 'image' => $row['image']];
    }
    return [
        'intro' => setting('about', ''), 'mission' => setting('about_mission', ''), 'vision' => setting('about_vision', ''), 'history' => setting('about_history', ''),
        'image' => ($img !== '' && is_file(UPLOAD_DIR . $img)) ? UPLOAD_URL . $img : '',
        'founded_year' => setting('founded_year', ''), 'registration_number' => setting('registration_number', ''), 'company_manager' => setting('company_manager', ''), 'credentials' => setting('credentials', ''),
        'achievements' => $items['achievement'], 'stats' => $items['stat'], 'team' => $items['team'], 'milestones' => $items['milestone'],
    ];
}
