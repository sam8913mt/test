<?php
declare(strict_types=1);

/*
 * مشخصات ساختمان: منبع واحد برای فیلدها، گزینه‌ها، اعتبارسنجی و نمایش عمومی.
 * هم پنل مدیریت (api/admin.php) و هم صفحه عمومی (api/building.php) از همین فایل استفاده می‌کنند.
 */

const BUILDING_STATUSES = ['در حال ساخت', 'تکمیل‌شده', 'در مرحله طراحی', 'پیش‌فروش'];

function building_options(): array {
    return [
        'statuses'       => BUILDING_STATUSES,
        'project_types'  => ['مسکونی', 'تجاری', 'اداری', 'تجاری و مسکونی', 'اداری و تجاری'],
        'structure_types'=> ['اسکلت فلزی', 'اسکلت بتنی', 'بتن‌آرمه با دیوار برشی', 'سازه ترکیبی', 'سایر'],
        'parking_types'  => ['پارکینگ مسقف', 'پارکینگ زیرزمینی', 'پارکینگ روباز', 'ترکیبی', 'ندارد'],
        'heating'        => ['موتورخانه مرکزی', 'پکیج هر واحد', 'گرمایش از کف', 'شوفاژ', 'سایر'],
        'cooling'        => ['چیلر مرکزی', 'داکت اسپلیت', 'کولر گازی اسپلیت', 'فن‌کوئل', 'کولر آبی', 'سایر'],
        'water'          => ['آب شهری', 'آب شهری با منبع ذخیره', 'چاه', 'سایر'],
        'power'          => ['برق سه‌فاز', 'برق تک‌فاز', 'برق سه‌فاز با ژنراتور', 'سایر'],
        'gas'            => ['گاز شهری', 'بدون انشعاب گاز'],
        'facilities'     => [
            'lobby' => 'لابی', 'security' => 'نگهبانی ۲۴ ساعته', 'cctv' => 'دوربین مداربسته',
            'fire_system' => 'سیستم اطفای حریق', 'generator' => 'ژنراتور اضطراری', 'remote_door' => 'درب ریموتی پارکینگ',
            'intercom' => 'آیفون تصویری', 'central_antenna' => 'آنتن مرکزی', 'gym' => 'سالن ورزشی',
            'pool' => 'استخر', 'sauna' => 'سونا و جکوزی', 'roof_garden' => 'روف‌گاردن',
            'meeting_hall' => 'سالن اجتماعات', 'playground' => 'فضای بازی کودکان', 'green_space' => 'فضای سبز',
            'solar' => 'پنل خورشیدی',
        ],
    ];
}

/**
 * تعریف فیلدها. type: text | textarea | enum | int | decimal | year | postal | lat | lng
 * section: برای نمایش در صفحه عمومی؛ null یعنی در بخش‌ها نمایش داده نمی‌شود.
 */
function building_field_specs(): array {
    $o = building_options();
    return [
        'title'             => ['label' => 'نام ساختمان', 'type' => 'text', 'required' => true, 'min' => 2, 'max' => 150],
        'slug'              => ['label' => 'شناسه آدرس', 'type' => 'text', 'max' => 160],
        'status'            => ['label' => 'وضعیت پروژه', 'type' => 'enum', 'options' => $o['statuses'], 'required' => true],
        'featured'          => ['label' => 'پروژه شاخص', 'type' => 'bool'],
        'location'          => ['label' => 'موقعیت (عنوان کوتاه)', 'type' => 'text', 'max' => 200],

        'project_type'      => ['label' => 'نوع کاربری', 'type' => 'enum', 'options' => $o['project_types'], 'section' => 'general'],
        'developer'         => ['label' => 'سازنده / شرکت مجری', 'type' => 'text', 'max' => 150, 'section' => 'general'],
        'architect'         => ['label' => 'طراح / معمار', 'type' => 'text', 'max' => 150, 'section' => 'general'],
        'structure_type'    => ['label' => 'نوع سازه', 'type' => 'enum', 'options' => $o['structure_types'], 'section' => 'general'],
        'facade'            => ['label' => 'نمای ساختمان', 'type' => 'text', 'max' => 150, 'section' => 'general'],
        'start_year'        => ['label' => 'سال شروع ساخت', 'type' => 'year', 'section' => 'general'],
        'delivery_year'     => ['label' => 'سال تحویل', 'type' => 'year', 'section' => 'general'],
        'progress'          => ['label' => 'پیشرفت فیزیکی', 'type' => 'int', 'min' => 0, 'max' => 100, 'unit' => 'درصد', 'section' => null],

        'province'          => ['label' => 'استان', 'type' => 'text', 'max' => 100, 'section' => 'address'],
        'city'              => ['label' => 'شهر', 'type' => 'text', 'max' => 100, 'section' => 'address'],
        'district'          => ['label' => 'منطقه / محله', 'type' => 'text', 'max' => 100, 'section' => 'address'],
        'address'           => ['label' => 'نشانی کامل', 'type' => 'textarea', 'max' => 400, 'section' => 'address'],
        'postal_code'       => ['label' => 'کد پستی', 'type' => 'postal', 'section' => 'address'],
        'latitude'          => ['label' => 'عرض جغرافیایی', 'type' => 'lat'],
        'longitude'         => ['label' => 'طول جغرافیایی', 'type' => 'lng'],

        'floors'            => ['label' => 'تعداد طبقات', 'type' => 'int', 'min' => 1, 'max' => 200, 'legacy' => true, 'store' => 'text', 'section' => 'technical'],
        'underground_floors'=> ['label' => 'تعداد طبقات زیرزمین', 'type' => 'int', 'min' => 0, 'max' => 20, 'section' => 'technical'],
        'units_count'       => ['label' => 'تعداد کل واحدها', 'type' => 'int', 'min' => 1, 'max' => 5000, 'section' => 'technical'],
        'units_per_floor'   => ['label' => 'تعداد واحد در هر طبقه', 'type' => 'int', 'min' => 1, 'max' => 100, 'section' => 'technical'],

        'land_area'         => ['label' => 'مساحت زمین', 'type' => 'decimal', 'min' => 1, 'max' => 10000000, 'unit' => 'مترمربع', 'section' => 'areas'],
        'built_area'        => ['label' => 'زیربنای کل', 'type' => 'decimal', 'min' => 1, 'max' => 10000000, 'unit' => 'مترمربع', 'section' => 'areas'],
        'area'              => ['label' => 'متراژ (توضیح متنی)', 'type' => 'text', 'max' => 100, 'legacy' => true, 'section' => 'areas'],
        'unit_area_min'     => ['label' => 'کمترین متراژ واحد', 'type' => 'decimal', 'min' => 1, 'max' => 100000, 'unit' => 'مترمربع', 'section' => 'areas'],
        'unit_area_max'     => ['label' => 'بیشترین متراژ واحد', 'type' => 'decimal', 'min' => 1, 'max' => 100000, 'unit' => 'مترمربع', 'section' => 'areas'],

        'parking_spaces'    => ['label' => 'تعداد پارکینگ', 'type' => 'int', 'min' => 0, 'max' => 5000, 'section' => 'parking'],
        'parking_type'      => ['label' => 'نوع پارکینگ', 'type' => 'enum', 'options' => $o['parking_types'], 'section' => 'parking'],
        'elevators'         => ['label' => 'تعداد آسانسور', 'type' => 'int', 'min' => 0, 'max' => 50, 'section' => 'parking'],
        'storage_units'     => ['label' => 'تعداد انباری', 'type' => 'int', 'min' => 0, 'max' => 5000, 'section' => 'parking'],

        'heating_system'    => ['label' => 'سیستم گرمایش', 'type' => 'enum', 'options' => $o['heating'], 'section' => 'utilities'],
        'cooling_system'    => ['label' => 'سیستم سرمایش', 'type' => 'enum', 'options' => $o['cooling'], 'section' => 'utilities'],
        'water_supply'      => ['label' => 'تأمین آب', 'type' => 'enum', 'options' => $o['water'], 'section' => 'utilities'],
        'power_supply'      => ['label' => 'تأمین برق', 'type' => 'enum', 'options' => $o['power'], 'section' => 'utilities'],
        'gas_supply'        => ['label' => 'گاز', 'type' => 'enum', 'options' => $o['gas'], 'section' => 'utilities'],

        'amenities'         => ['label' => 'سایر امکانات', 'type' => 'textarea', 'max' => 1500],
        'description'       => ['label' => 'توضیحات', 'type' => 'textarea', 'max' => 5000],
        'technical_notes'   => ['label' => 'توضیحات فنی', 'type' => 'textarea', 'max' => 3000],
    ];
}

/** ستون‌های جدید جدول projects (مهاجرت ایمن و تکرارپذیر؛ فقط ستون‌های ناموجود اضافه می‌شوند). */
function building_migration_columns(): array {
    return [
        'project_type' => "TEXT DEFAULT ''", 'developer' => "TEXT DEFAULT ''", 'architect' => "TEXT DEFAULT ''",
        'structure_type' => "TEXT DEFAULT ''", 'facade' => "TEXT DEFAULT ''",
        'start_year' => 'INTEGER DEFAULT NULL', 'delivery_year' => 'INTEGER DEFAULT NULL', 'progress' => 'INTEGER DEFAULT NULL',
        'province' => "TEXT DEFAULT ''", 'city' => "TEXT DEFAULT ''", 'district' => "TEXT DEFAULT ''",
        'address' => "TEXT DEFAULT ''", 'postal_code' => "TEXT DEFAULT ''",
        'latitude' => 'REAL DEFAULT NULL', 'longitude' => 'REAL DEFAULT NULL',
        'underground_floors' => 'INTEGER DEFAULT NULL', 'units_count' => 'INTEGER DEFAULT NULL', 'units_per_floor' => 'INTEGER DEFAULT NULL',
        'land_area' => 'REAL DEFAULT NULL', 'built_area' => 'REAL DEFAULT NULL',
        'unit_area_min' => 'REAL DEFAULT NULL', 'unit_area_max' => 'REAL DEFAULT NULL',
        'parking_spaces' => 'INTEGER DEFAULT NULL', 'parking_type' => "TEXT DEFAULT ''",
        'elevators' => 'INTEGER DEFAULT NULL', 'storage_units' => 'INTEGER DEFAULT NULL',
        'heating_system' => "TEXT DEFAULT ''", 'cooling_system' => "TEXT DEFAULT ''", 'water_supply' => "TEXT DEFAULT ''",
        'power_supply' => "TEXT DEFAULT ''", 'gas_supply' => "TEXT DEFAULT ''",
        'facilities' => "TEXT DEFAULT '[]'", 'technical_notes' => "TEXT DEFAULT ''", 'updated_at' => "TEXT DEFAULT ''",
    ];
}

/** تبدیل ارقام فارسی/عربی و جداکننده‌ها به ارقام لاتین. */
function normalize_digits(string $v): string {
    return strtr($v, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        '٫'=>'.','٬'=>'', ','=>'', '،'=>'', '/'=>'.',
    ]);
}

function building_clean_text(string $v, bool $multiline): string {
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    if (!$multiline) $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
    return trim($v);
}

/**
 * ورودی (معمولاً $_POST) را اعتبارسنجی و پاک‌سازی می‌کند.
 * خروجی: [مقادیر آماده ذخیره، آرایه خطاها به‌صورت field => message]
 */
function building_parse(array $in, array $existing = []): array {
    $specs = building_field_specs();
    $options = building_options();
    $values = []; $errors = [];

    foreach ($specs as $key => $sp) {
        $label = $sp['label'];
        if (($sp['type'] ?? '') === 'bool') { $values[$key] = isset($in[$key]) && (string)$in[$key] !== '0' ? 1 : 0; continue; }
        $raw = $in[$key] ?? '';
        if (is_array($raw)) $raw = '';
        $raw = building_clean_text((string)$raw, ($sp['type'] ?? '') === 'textarea');
        $empty = $raw === '';
        $values[$key] = null;

        // داده‌های قدیمی که قالب جدید را ندارند، تا زمانی که دست نخورده‌اند حفظ می‌شوند.
        if (!empty($sp['legacy']) && !$empty && $raw === trim((string)($existing[$key] ?? ''))) { $values[$key] = $raw; continue; }

        if ($empty) {
            if (!empty($sp['required'])) { $errors[$key] = $label . ' الزامی است.'; }
            $values[$key] = in_array($sp['type'], ['text','textarea','enum','postal'], true) || ($sp['store'] ?? '') === 'text' ? '' : null;
            continue;
        }
        switch ($sp['type']) {
            case 'text': case 'textarea':
                $len = mb_strlen($raw, 'UTF-8');
                if (isset($sp['min']) && $len < $sp['min']) $errors[$key] = $label . ' باید حداقل ' . $sp['min'] . ' نویسه باشد.';
                elseif ($len > $sp['max']) $errors[$key] = $label . ' نباید بیش از ' . $sp['max'] . ' نویسه باشد.';
                $values[$key] = $raw; break;
            case 'enum':
                if (!in_array($raw, $sp['options'], true)) $errors[$key] = 'مقدار انتخاب‌شده برای «' . $label . '» معتبر نیست.';
                $values[$key] = $raw; break;
            case 'int': case 'year':
                $n = normalize_digits($raw);
                if (!preg_match('/^\d{1,9}$/', $n)) { $errors[$key] = $label . ' باید یک عدد صحیح باشد.'; break; }
                $num = (int)$n;
                $min = $sp['type'] === 'year' ? 1300 : $sp['min']; $max = $sp['type'] === 'year' ? 1500 : $sp['max'];
                if ($num < $min || $num > $max) {
                    $errors[$key] = $sp['type'] === 'year' ? $label . ' را به‌صورت سال شمسی چهار رقمی (بین ۱۳۰۰ تا ۱۵۰۰) وارد کنید.' : $label . ' باید بین ' . $min . ' تا ' . $max . ' باشد.';
                    break;
                }
                $values[$key] = ($sp['store'] ?? '') === 'text' ? (string)$num : $num; break;
            case 'decimal':
                $n = normalize_digits($raw);
                if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $n)) { $errors[$key] = $label . ' باید عدد مثبت (حداکثر دو رقم اعشار) باشد.'; break; }
                $num = (float)$n;
                if ($num < $sp['min'] || $num > $sp['max']) { $errors[$key] = $label . ' باید بین ' . $sp['min'] . ' تا ' . $sp['max'] . ' باشد.'; break; }
                $values[$key] = $num; break;
            case 'postal':
                $n = normalize_digits($raw);
                if (!preg_match('/^\d{10}$/', $n)) { $errors[$key] = 'کد پستی باید دقیقاً ۱۰ رقم باشد.'; break; }
                $values[$key] = $n; break;
            case 'lat': case 'lng':
                $n = normalize_digits($raw);
                if (!preg_match('/^-?\d{1,3}(\.\d{1,8})?$/', $n)) { $errors[$key] = $label . ' باید عددی اعشاری مانند 35.7219 باشد.'; break; }
                $num = (float)$n; $lim = $sp['type'] === 'lat' ? 90 : 180;
                if ($num < -$lim || $num > $lim) { $errors[$key] = $label . ' باید بین ' . (-$lim) . ' تا ' . $lim . ' باشد.'; break; }
                $values[$key] = $num; break;
        }
    }

    // قواعد ترکیبی
    $has = static fn(string $k): bool => isset($values[$k]) && $values[$k] !== null && $values[$k] !== '';
    if (!isset($errors['start_year']) && !isset($errors['delivery_year']) && $has('start_year') && $has('delivery_year') && $values['delivery_year'] < $values['start_year']) {
        $errors['delivery_year'] = 'سال تحویل نمی‌تواند قبل از سال شروع ساخت باشد.';
    }
    if (!isset($errors['unit_area_min']) && !isset($errors['unit_area_max']) && $has('unit_area_min') && $has('unit_area_max') && $values['unit_area_min'] > $values['unit_area_max']) {
        $errors['unit_area_max'] = 'بیشترین متراژ واحد نباید کمتر از کمترین متراژ باشد.';
    }
    if (!isset($errors['units_count']) && !isset($errors['units_per_floor']) && $has('units_count') && $has('units_per_floor') && $values['units_per_floor'] > $values['units_count']) {
        $errors['units_per_floor'] = 'تعداد واحد در هر طبقه نمی‌تواند از تعداد کل واحدها بیشتر باشد.';
    }
    if ($has('latitude') xor $has('longitude')) {
        $errors[$has('latitude') ? 'longitude' : 'latitude'] = 'عرض و طول جغرافیایی باید با هم وارد شوند.';
    }
    if (!isset($errors['progress']) && $has('progress') && ($values['status'] ?? '') === 'تکمیل‌شده' && (int)$values['progress'] !== 100) {
        $errors['progress'] = 'برای پروژه تکمیل‌شده، پیشرفت فیزیکی باید ۱۰۰ درصد باشد (یا خالی بماند).';
    }
    if (!isset($errors['progress']) && $has('progress') && ($values['status'] ?? '') === 'در مرحله طراحی' && (int)$values['progress'] > 20) {
        $errors['progress'] = 'برای پروژه در مرحله طراحی، پیشرفت فیزیکی بیش از ۲۰ درصد منطقی نیست.';
    }

    // امکانات (چک‌باکس‌ها)
    $selected = $in['facilities'] ?? [];
    if (!is_array($selected)) $selected = [];
    $allowed = array_keys($options['facilities']);
    $clean = [];
    foreach ($selected as $k) {
        if (!is_string($k) || !in_array($k, $allowed, true)) { $errors['facilities'] = 'یکی از امکانات انتخاب‌شده معتبر نیست.'; continue; }
        $clean[$k] = true;
    }
    $values['facilities'] = json_encode(array_values(array_intersect($allowed, array_keys($clean))), JSON_UNESCAPED_UNICODE);

    return [$values, $errors];
}

function building_facility_keys(array $p): array {
    $arr = json_decode((string)($p['facilities'] ?? '[]'), true);
    if (!is_array($arr)) return [];
    $allowed = array_keys(building_options()['facilities']);
    return array_values(array_filter($arr, static fn($k) => is_string($k) && in_array($k, $allowed, true)));
}

/** مقدار عددی را بدون صفرهای اضافی اعشار به رشته تبدیل می‌کند. */
function building_num_text($v): string {
    if ($v === null || $v === '') return '';
    if (is_numeric($v)) { $f = (float)$v; return $f == floor($f) ? (string)(int)$f : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.'); }
    return (string)$v;
}

/** تمام فیلدهای ساختمان برای پنل مدیریت (به‌صورت رشته‌ای تا فرم راحت پر شود). */
function building_admin_row(array $p): array {
    $out = [];
    foreach (building_field_specs() as $key => $sp) {
        if (($sp['type'] ?? '') === 'bool') { $out[$key] = !empty($p[$key]); continue; }
        $out[$key] = building_num_text($p[$key] ?? '');
    }
    $out['id'] = (int)$p['id'];
    $out['facilities'] = building_facility_keys($p);
    return $out;
}

/** بخش‌های نمایش عمومی؛ فقط فیلدهای دارای مقدار برگردانده می‌شوند. */
function building_public_sections(array $p): array {
    $titles = [
        'general' => 'اطلاعات عمومی', 'address' => 'نشانی', 'technical' => 'مشخصات فنی', 'areas' => 'متراژها',
        'parking' => 'پارکینگ و آسانسور', 'utilities' => 'تأسیسات',
    ];
    $sections = [];
    foreach (building_field_specs() as $key => $sp) {
        $sec = $sp['section'] ?? null;
        if (!$sec || !isset($titles[$sec])) continue;
        $val = building_num_text($p[$key] ?? '');
        if ($val === '') continue;
        // عدد صفر برای تعداد پارکینگ/آسانسور/انباری هم اطلاعات مفید است و نمایش داده می‌شود.
        $sections[$sec]['title'] = $titles[$sec];
        $sections[$sec]['type'] = 'list';
        $sections[$sec]['items'][] = ['label' => $sp['label'], 'value' => $val, 'unit' => $sp['unit'] ?? '', 'numeric' => in_array($sp['type'], ['int','year','decimal','postal'], true)];
    }
    $labels = building_options()['facilities'];
    $chips = [];
    foreach (building_facility_keys($p) as $k) $chips[] = $labels[$k];
    $other = trim((string)($p['amenities'] ?? ''));
    if ($chips || $other !== '') {
        $sections['facilities'] = ['title' => 'امکانات رفاهی', 'type' => 'chips', 'chips' => $chips, 'text' => $other];
    }
    $notes = trim((string)($p['technical_notes'] ?? ''));
    if ($notes !== '') $sections['notes'] = ['title' => 'توضیحات فنی', 'type' => 'text', 'text' => $notes];
    $order = ['general', 'address', 'technical', 'areas', 'parking', 'utilities', 'facilities', 'notes'];
    $result = [];
    foreach ($order as $k) if (isset($sections[$k])) $result[] = ['key' => $k] + $sections[$k];
    return $result;
}
