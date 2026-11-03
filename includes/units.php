<?php
declare(strict_types=1);

/* واحدهای هر ساختمان: وضعیت فروش، مشخصات و تصاویر. */

const UNIT_STATUSES = ['available' => 'موجود', 'reserved' => 'رزرو شده', 'sold' => 'فروخته شده'];
const UNIT_DIRECTIONS = ['شمالی', 'جنوبی', 'شرقی', 'غربی', 'شمال‌شرقی', 'شمال‌غربی', 'جنوب‌شرقی', 'جنوب‌غربی', 'دوکله'];
const UNIT_MAX_IMAGES = 6;

function unit_options(): array {
    // کلیدها پیشوند unit_ دارند تا با گزینه‌های ساختمان (statuses) در عملگر + تداخل نکنند.
    return ['unit_statuses' => UNIT_STATUSES, 'unit_directions' => UNIT_DIRECTIONS];
}

/** اعتبارسنجی فرم واحد. خروجی: [مقادیر، خطاها]. */
function unit_parse(array $in): array {
    $e = []; $v = [];
    $int = static function (string $key, string $label, int $min, int $max) use ($in, &$e, &$v): void {
        $raw = building_clean_text(input_str($in, $key), false);
        $v[$key] = null;
        if ($raw === '') return;
        $n = normalize_digits($raw);
        if (!preg_match('/^-?\d{1,9}$/', $n)) { $e[$key] = $label . ' باید عدد صحیح باشد.'; return; }
        $num = (int)$n;
        if ($num < $min || $num > $max) { $e[$key] = $label . ' باید بین ' . $min . ' تا ' . $max . ' باشد.'; return; }
        $v[$key] = $num;
    };

    $pid = normalize_digits(building_clean_text(input_str($in, 'project_id'), false));
    $v['project_id'] = preg_match('/^\d{1,9}$/', $pid) && (int)$pid > 0 ? (int)$pid : 0;
    if ($v['project_id'] === 0) $e['project_id'] = 'ساختمان را انتخاب کنید.';

    $num = building_clean_text(input_str($in, 'unit_number'), false);
    if ($num === '') $e['unit_number'] = 'شماره یا نام واحد الزامی است.';
    elseif (!preg_match('/^[\p{L}\p{N}\-\/ ]{1,20}$/u', $num)) $e['unit_number'] = 'شماره واحد حداکثر ۲۰ نویسه (حرف، عدد، خط تیره یا /) باشد.';
    $v['unit_number'] = normalize_digits_keep_text($num);

    $int('floor', 'طبقه', -5, 200);
    $int('bedrooms', 'تعداد اتاق خواب', 0, 20);
    $int('bathrooms', 'تعداد سرویس بهداشتی', 0, 20);
    $int('parking', 'تعداد پارکینگ', 0, 10);
    $int('sort_order', 'ترتیب نمایش', -9999, 9999);
    if ($v['sort_order'] === null) $v['sort_order'] = 0;

    $area = normalize_digits(building_clean_text(input_str($in, 'area'), false));
    $v['area'] = null;
    if ($area !== '') {
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $area)) $e['area'] = 'متراژ باید عدد مثبت (حداکثر دو رقم اعشار) باشد.';
        elseif ((float)$area < 1 || (float)$area > 100000) $e['area'] = 'متراژ باید بین ۱ تا ۱۰۰۰۰۰ مترمربع باشد.';
        else $v['area'] = (float)$area;
    }

    $price = normalize_digits(building_clean_text(input_str($in, 'price'), false));
    $v['price'] = null;
    if ($price !== '') {
        if (!preg_match('/^\d{1,13}$/', $price) || (int)$price < 1) $e['price'] = 'قیمت باید عدد صحیح مثبت (به تومان) باشد.';
        else $v['price'] = (int)$price;
    }
    $v['price_visible'] = isset($in['price_visible']) && !is_array($in['price_visible']) && (string)$in['price_visible'] !== '0' ? 1 : 0;
    if ($v['price_visible'] === 1 && $v['price'] === null && !isset($e['price'])) $e['price'] = 'برای نمایش قیمت در سایت، ابتدا قیمت را وارد کنید.';
    $v['storage'] = isset($in['storage']) && !is_array($in['storage']) && (string)$in['storage'] !== '0' ? 1 : 0;

    $dir = building_clean_text(input_str($in, 'direction'), false);
    if ($dir !== '' && !in_array($dir, UNIT_DIRECTIONS, true)) $e['direction'] = 'جهت انتخاب‌شده معتبر نیست.';
    $v['direction'] = $dir;

    $status = building_clean_text(input_str($in, 'status'), false);
    if ($status === '') $status = 'available';
    if (!isset(UNIT_STATUSES[$status])) $e['status'] = 'وضعیت فروش معتبر نیست.';
    $v['status'] = $status;

    $desc = building_clean_text(input_str($in, 'description'), true);
    if (mb_strlen($desc, 'UTF-8') > 1000) $e['description'] = 'توضیحات نباید بیش از ۱۰۰۰ نویسه باشد.';
    $v['description'] = $desc;
    return [$v, $e];
}

/** شماره واحد: ارقام فارسی به لاتین تبدیل می‌شوند تا «۱۲» و «12» یک واحد حساب شوند. */
function normalize_digits_keep_text(string $s): string {
    return strtr($s, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);
}

function unit_images_list(array $u): array { return all_project_images($u); }

function unit_admin_row(array $u): array {
    $images = [];
    foreach (unit_images_list($u) as $img) if (is_file(UPLOAD_DIR . $img)) $images[] = ['name' => $img, 'url' => UPLOAD_URL . $img];
    return [
        'id' => (int)$u['id'], 'project_id' => (int)$u['project_id'], 'unit_number' => (string)$u['unit_number'],
        'floor' => $u['floor'] === null ? '' : (string)$u['floor'], 'area' => building_num_text($u['area']),
        'bedrooms' => $u['bedrooms'] === null ? '' : (string)$u['bedrooms'], 'bathrooms' => $u['bathrooms'] === null ? '' : (string)$u['bathrooms'],
        'parking' => $u['parking'] === null ? '' : (string)$u['parking'], 'storage' => (bool)$u['storage'], 'direction' => (string)$u['direction'],
        'price' => $u['price'] === null ? '' : (string)$u['price'], 'price_visible' => (bool)$u['price_visible'],
        'status' => (string)$u['status'], 'description' => (string)$u['description'], 'sort_order' => (int)$u['sort_order'],
        'images' => $images, 'updated_at' => (string)$u['updated_at'],
    ];
}

/** نمایش عمومی واحدها؛ قیمت فقط وقتی مدیر اجازه داده نمایش داده می‌شود. */
function units_public(int $projectId): array {
    $q = db()->prepare('SELECT * FROM units WHERE project_id=? ORDER BY COALESCE(`floor`,0), sort_order, unit_number');
    $q->execute([$projectId]);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $images = [];
        foreach (unit_images_list($u) as $img) if (is_file(UPLOAD_DIR . $img)) $images[] = UPLOAD_URL . $img;
        $out[] = [
            'id' => (int)$u['id'], 'unit_number' => (string)$u['unit_number'],
            'floor' => $u['floor'], 'area' => $u['area'] === null ? null : (float)$u['area'],
            'bedrooms' => $u['bedrooms'], 'bathrooms' => $u['bathrooms'], 'parking' => $u['parking'],
            'storage' => (bool)$u['storage'], 'direction' => (string)$u['direction'],
            'price' => ((int)$u['price_visible'] === 1 && $u['price'] !== null) ? (int)$u['price'] : null,
            'status' => (string)$u['status'], 'status_label' => UNIT_STATUSES[$u['status']] ?? '',
            'description' => (string)$u['description'], 'images' => $images,
        ];
    }
    return $out;
}

function units_summary(array $units): array {
    $s = ['total' => count($units), 'available' => 0, 'reserved' => 0, 'sold' => 0];
    foreach ($units as $u) if (isset($s[$u['status']])) $s[$u['status']]++;
    return $s;
}
