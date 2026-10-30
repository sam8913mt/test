<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

try {
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q, 'UTF-8') > 100) $q = mb_substr($q, 0, 100, 'UTF-8');
    $status = trim((string)($_GET['status'] ?? ''));
    $validStatuses = ['در حال ساخت', 'تکمیل‌شده', 'در مرحله طراحی', 'پیش‌فروش'];
    if (!in_array($status, $validStatuses, true)) $status = '';

    $sql = 'SELECT * FROM projects WHERE is_published=1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (title LIKE ? OR location LIKE ? OR description LIKE ? OR short_description LIKE ? OR city LIKE ? OR address LIKE ? OR project_type LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle, $needle, $needle, $needle, $needle);
    }
    if ($status !== '') { $sql .= ' AND status = ?'; $params[] = $status; }
    $perPage = 12;
    $page = max(1, min(500, (int)($_GET['page'] ?? 1)));
    $countStmt = db()->prepare(str_replace('SELECT *', 'SELECT COUNT(*)', $sql));
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $sql .= ' ORDER BY featured DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $projects = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $images = all_project_images($p);
        $projects[] = [
            'id' => (int)$p['id'], 'title' => (string)$p['title'], 'slug' => (string)($p['slug'] ?: $p['id']),
            'location' => (string)$p['location'], 'status' => (string)$p['status'],
            'description' => mb_strimwidth(strip_tags((string)(($p['short_description'] ?? '') !== '' ? $p['short_description'] : $p['description'])), 0, 180, '…', 'UTF-8'),
            'project_type' => (string)($p['project_type'] ?? ''), 'city' => (string)($p['city'] ?? ''),
            'image' => ($images && is_file(UPLOAD_DIR . $images[0])) ? image_url($images[0], true) : '',
            'featured' => (bool)$p['featured'], 'url' => project_url($p)
        ];
    }
    $services = db()->query('SELECT title,description,icon FROM services ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    $allProjects = db()->query('SELECT id,title FROM projects WHERE is_published=1 ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);
    $faq = [
        ['q'=>'چطور درباره یک ساختمان اطلاعات بیشتری بگیرم؟','a'=>'از فهرست ساختمان‌ها، صفحه پروژه موردنظر را باز کنید و از طریق فرم درخواست مشاوره یا شماره تماس شرکت، درخواست خود را ثبت کنید.'],
        ['q'=>'چطور درخواست مشاوره ثبت کنم؟','a'=>'فرم همین صفحه را تکمیل کنید، موضوع درخواست را انتخاب کنید و در صورت ارتباط، ساختمان موردنظر را مشخص کنید. اطلاعات فرم برای پیگیری درخواست در پنل مدیریت ثبت می‌شود.'],
        ['q'=>'آیا می‌توانم تصاویر و مشخصات پروژه‌ها را ببینم؟','a'=>'بله؛ در صورت ثبت و انتشار اطلاعات هر ساختمان، صفحه جزئیات آن شامل تصاویر موجود و مشخصات ثبت‌شده نمایش داده می‌شود.'],
        ['q'=>'چه اطلاعاتی برای ارسال درخواست لازم است؟','a'=>'نام، شماره تماس و توضیح کوتاه درباره موضوع درخواست ضروری است. ایمیل و انتخاب پروژه اختیاری هستند.']
    ];
    $keys = ['tagline','about','phone','email','address','founded_year','registration_number','company_manager','credentials'];
    $settings = [];
    foreach ($keys as $key) {
        $fallback = match ($key) { 'tagline'=>SITE_TAGLINE, 'phone'=>SITE_PHONE, 'address'=>SITE_ADDRESS, default=>'' };
        $settings[$key] = setting($key, $fallback);
    }
    echo json_encode([
        'ok'=>true, 'csrf'=>public_csrf_token(), 'site'=>['name'=>SITE_NAME, 'url'=>site_url(), 'logo'=>'assets/logo.webp'],
        'settings'=>$settings, 'projects'=>$projects, 'total'=>$total, 'page'=>$page, 'perPage'=>$perPage, 'services'=>$services, 'allProjects'=>$allProjects, 'faq'=>$faq,
        'selectedProject'=>trim((string)($_GET['project'] ?? ''))
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
} catch (Throwable $e) {
    log_exception($e, 'site.php');
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'دریافت اطلاعات سایت ناموفق بود. لطفاً بعداً دوباره تلاش کنید.'], JSON_UNESCAPED_UNICODE);
}
