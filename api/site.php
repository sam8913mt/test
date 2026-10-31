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
    if (!in_array($status, BUILDING_STATUSES, true)) $status = '';

    $sql = 'SELECT * FROM projects WHERE 1=1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (title LIKE ? OR location LIKE ? OR description LIKE ? OR city LIKE ? OR district LIKE ? OR address LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle, $needle, $needle, $needle);
    }
    if ($status !== '') { $sql .= ' AND status = ?'; $params[] = $status; }
    $sql .= ' ORDER BY featured DESC, id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $projects = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $images = array_values(array_filter(all_project_images($p), static fn($img) => is_file(UPLOAD_DIR . $img)));
        $facts = [];
        if ($p['project_type'] !== null && $p['project_type'] !== '') $facts[] = (string)$p['project_type'];
        if ($p['floors'] !== null && $p['floors'] !== '') $facts[] = $p['floors'] . ' طبقه';
        if ($p['units_count'] !== null && $p['units_count'] !== '') $facts[] = $p['units_count'] . ' واحد';
        $projects[] = [
            'id' => (int)$p['id'], 'title' => (string)$p['title'], 'slug' => (string)($p['slug'] ?: $p['id']),
            'location' => (string)$p['location'], 'status' => (string)$p['status'],
            'description' => mb_strimwidth(strip_tags((string)$p['description']), 0, 180, '…', 'UTF-8'),
            'image' => $images ? UPLOAD_URL . $images[0] : '', 'facts' => $facts,
            'featured' => (bool)$p['featured'], 'url' => project_url($p)
        ];
    }
    $services = db()->query('SELECT title,description,icon FROM services ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    $allProjects = db()->query('SELECT id,title FROM projects ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);
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
        'ok'=>true, 'csrf'=>csrf_token(), 'site'=>['name'=>SITE_NAME, 'url'=>site_url(), 'logo'=>'assets/logo.webp'],
        'settings'=>$settings, 'projects'=>$projects, 'services'=>$services, 'allProjects'=>$allProjects, 'faq'=>$faq,
        'selectedProject'=>trim((string)($_GET['project'] ?? ''))
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'دریافت اطلاعات سایت ناموفق بود. لطفاً بعداً دوباره تلاش کنید.'], JSON_UNESCAPED_UNICODE);
}
