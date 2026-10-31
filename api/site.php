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
    $toCard = static function (array $p): array {
        $images = array_values(array_filter(all_project_images($p), static fn($img) => is_file(UPLOAD_DIR . $img)));
        $facts = [];
        if ($p['project_type'] !== null && $p['project_type'] !== '') $facts[] = (string)$p['project_type'];
        if ($p['floors'] !== null && $p['floors'] !== '') $facts[] = $p['floors'] . ' طبقه';
        if ($p['units_count'] !== null && $p['units_count'] !== '') $facts[] = $p['units_count'] . ' واحد';
        return [
            'id' => (int)$p['id'], 'title' => (string)$p['title'], 'slug' => (string)($p['slug'] ?: $p['id']),
            'location' => (string)$p['location'], 'status' => (string)$p['status'],
            'description' => mb_strimwidth(strip_tags((string)$p['description']), 0, 180, '…', 'UTF-8'),
            'image' => $images ? UPLOAD_URL . $images[0] : '', 'facts' => $facts,
            'featured' => (bool)$p['featured'], 'url' => project_url($p)
        ];
    };
    $projects = array_map($toCard, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // پروژه‌های شاخص و آمار از کل ساختمان‌ها محاسبه می‌شوند (مستقل از جست‌وجو و فیلتر).
    $featured = array_map($toCard, db()->query('SELECT * FROM projects WHERE featured=1 ORDER BY id DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC));
    $agg = db()->query("SELECT COUNT(*) AS total,
        SUM(CASE WHEN status='در حال ساخت' THEN 1 ELSE 0 END) AS building,
        SUM(CASE WHEN status='تکمیل‌شده' THEN 1 ELSE 0 END) AS done,
        SUM(CASE WHEN status='پیش‌فروش' THEN 1 ELSE 0 END) AS presale,
        COALESCE(SUM(units_count),0) AS units FROM projects")->fetch(PDO::FETCH_ASSOC);
    $stats = [];
    foreach ([['total','ساختمان معرفی‌شده',''], ['building','پروژه در حال ساخت',''], ['done','پروژه تکمیل‌شده',''], ['presale','پروژه در مرحله پیش‌فروش',''], ['units','واحد در مجموع پروژه‌ها','']] as [$k, $label]) {
        $n = (int)($agg[$k] ?? 0);
        if ($n > 0) $stats[] = ['key' => $k, 'value' => $n, 'label' => $label];
    }
    $services = db()->query('SELECT title,description,icon FROM services ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    $allProjects = db()->query('SELECT id,title FROM projects ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);
    $faq = [];
    foreach (db()->query('SELECT question, answer FROM faqs WHERE is_active=1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC) as $f) $faq[] = ['q' => (string)$f['question'], 'a' => (string)$f['answer']];
    $keys = ['tagline','about','phone','email','address','founded_year','registration_number','company_manager','credentials'];
    $settings = [];
    foreach ($keys as $key) {
        $fallback = match ($key) { 'tagline'=>SITE_TAGLINE, 'phone'=>SITE_PHONE, 'address'=>SITE_ADDRESS, default=>'' };
        $settings[$key] = setting($key, $fallback);
    }
    echo json_encode([
        'ok'=>true, 'csrf'=>csrf_token(), 'site'=>['name'=>SITE_NAME, 'url'=>site_url(), 'logo'=>'assets/logo.webp'],
        'settings'=>$settings, 'projects'=>$projects, 'services'=>$services, 'allProjects'=>$allProjects, 'faq'=>$faq, 'featured'=>$featured, 'stats'=>$stats,
        'selectedProject'=>trim((string)($_GET['project'] ?? ''))
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'دریافت اطلاعات سایت ناموفق بود. لطفاً بعداً دوباره تلاش کنید.'], JSON_UNESCAPED_UNICODE);
}
