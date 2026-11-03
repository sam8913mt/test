<?php
declare(strict_types=1);

/* آمار بازدید (فقط داده‌ی واقعی ثبت‌شده) و آمار داشبورد. */

function is_bot_request(): bool {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') return true;
    return (bool)preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|lighthouse|curl|wget|python-requests|monitor|uptime/i', $ua);
}

/**
 * یک بازدید را ثبت می‌کند. بازدید ربات‌ها و مدیر واردشده شمرده نمی‌شود و
 * بازدید تکراری همان نشست از همان صفحه تا ۳۰ دقیقه یک بار حساب می‌شود.
 */
function record_view(string $kind, int $id): void {
    if (!in_array($kind, ['home', 'building', 'article'], true) || $id < 0) return;
    if (is_bot_request() || is_admin()) return;
    $key = $kind . ':' . $id; $now = time();
    $seen = is_array($_SESSION['seen_views'] ?? null) ? $_SESSION['seen_views'] : [];
    if (isset($seen[$key]) && $now - (int)$seen[$key] < 1800) return;
    $seen[$key] = $now;
    if (count($seen) > 200) { asort($seen); $seen = array_slice($seen, -100, null, true); }
    $_SESSION['seen_views'] = $seen;
    try {
        db()->prepare('INSERT INTO content_views(kind,item_id,day,views) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE views = views + 1')
            ->execute([$kind, $id, date('Y-m-d')]);
    } catch (Throwable $e) {
        error_log('[stats] ' . $e->getMessage());
    }
}

function stats_count_by(string $sql): array {
    $out = [];
    foreach (db()->query($sql)->fetchAll(PDO::FETCH_NUM) as [$k, $c]) $out[(string)$k] = (int)$c;
    return $out;
}

/** آمار داشبورد؛ همه‌ی اعداد از دیتابیس خوانده می‌شوند. روزهای قبل از شروع ثبت بازدید «بدون داده» هستند، نه صفر. */
function admin_stats(): array {
    $pdo = db();
    $u = stats_count_by('SELECT status, COUNT(*) FROM units GROUP BY status');
    $r = stats_count_by('SELECT status, COUNT(*) FROM reviews GROUP BY status');
    $a = stats_count_by('SELECT status, COUNT(*) FROM articles GROUP BY status');
    $avg = $pdo->query("SELECT AVG(rating) FROM reviews WHERE status='approved'")->fetchColumn();
    $units = ['total' => array_sum($u), 'available' => $u['available'] ?? 0, 'reserved' => $u['reserved'] ?? 0, 'sold' => $u['sold'] ?? 0];
    $reviews = ['total' => array_sum($r), 'pending' => $r['pending'] ?? 0, 'approved' => $r['approved'] ?? 0, 'hidden' => $r['hidden'] ?? 0,
                'average' => ($avg === null || $avg === false) ? null : round((float)$avg, 1)];
    $articles = ['total' => array_sum($a), 'published' => $a['published'] ?? 0, 'draft' => $a['draft'] ?? 0];

    $today = date('Y-m-d'); $d7 = date('Y-m-d', strtotime('-6 days')); $d30 = date('Y-m-d', strtotime('-29 days')); $m0 = date('Y-m-01');
    $since = $pdo->query('SELECT MIN(day) FROM content_views')->fetchColumn();
    $since = ($since === false || $since === null) ? null : (string)$since;
    $sum = static function (string $from) use ($pdo): int { $q = $pdo->prepare('SELECT COALESCE(SUM(views),0) FROM content_views WHERE day>=?'); $q->execute([$from]); return (int)$q->fetchColumn(); };
    $views = [
        'tracking_since' => $since, 'today' => $sum($today), 'last7' => $sum($d7), 'last30' => $sum($d30), 'this_month' => $sum($m0),
        'total' => (int)$pdo->query('SELECT COALESCE(SUM(views),0) FROM content_views')->fetchColumn(),
        'by_kind_30' => [], 'daily' => [], 'monthly' => [], 'top_buildings' => [], 'top_articles' => [],
    ];
    if ($since !== null) {
        $q = $pdo->prepare('SELECT kind, SUM(views) AS v FROM content_views WHERE day>=? GROUP BY kind'); $q->execute([$d30]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $views['by_kind_30'][(string)$row['kind']] = (int)$row['v'];
        $q = $pdo->prepare('SELECT day, SUM(views) AS v FROM content_views WHERE day>=? GROUP BY day ORDER BY day'); $q->execute([$d30]);
        $byDay = []; foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $byDay[(string)$row['day']] = (int)$row['v'];
        // فقط از روز اولین ثبت تا امروز؛ روزهای بدون بازدید بعد از شروع ثبت، صفر واقعی‌اند.
        for ($t = strtotime(max($since, $d30)); $t <= strtotime($today); $t += 86400) { $d = date('Y-m-d', $t); $views['daily'][] = ['day' => $d, 'views' => $byDay[$d] ?? 0]; }
        foreach ($pdo->query('SELECT substr(day,1,7) AS m, SUM(views) AS v FROM content_views GROUP BY m ORDER BY m DESC LIMIT 12')->fetchAll(PDO::FETCH_ASSOC) as $row) $views['monthly'][] = ['month' => (string)$row['m'], 'views' => (int)$row['v']];
        $views['monthly'] = array_reverse($views['monthly']);
        $q = $pdo->prepare("SELECT p.id, p.title, SUM(v.views) AS v FROM content_views v JOIN projects p ON p.id=v.item_id WHERE v.kind='building' AND v.day>=? GROUP BY p.id ORDER BY v DESC LIMIT 5"); $q->execute([$d30]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $views['top_buildings'][] = ['id' => (int)$row['id'], 'title' => (string)$row['title'], 'views' => (int)$row['v']];
        $q = $pdo->prepare("SELECT a.id, a.title, SUM(v.views) AS v FROM content_views v JOIN articles a ON a.id=v.item_id WHERE v.kind='article' AND v.day>=? GROUP BY a.id ORDER BY v DESC LIMIT 5"); $q->execute([$d30]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $views['top_articles'][] = ['id' => (int)$row['id'], 'title' => (string)$row['title'], 'views' => (int)$row['v']];
    }

    // وضعیت واحدها و بازدید هر ساختمان
    $vq = $pdo->prepare("SELECT item_id, SUM(views) AS total, SUM(CASE WHEN day>=? THEN views ELSE 0 END) AS d30, SUM(CASE WHEN day>=? THEN views ELSE 0 END) AS d7 FROM content_views WHERE kind='building' GROUP BY item_id");
    $vq->execute([$d30, $d7]);
    $vmap = []; foreach ($vq->fetchAll(PDO::FETCH_ASSOC) as $row) $vmap[(int)$row['item_id']] = $row;
    $buildings = [];
    $sql = "SELECT p.id, p.title, COUNT(u.id) AS total,
            COALESCE(SUM(CASE WHEN u.status='available' THEN 1 ELSE 0 END),0) AS available,
            COALESCE(SUM(CASE WHEN u.status='reserved' THEN 1 ELSE 0 END),0) AS reserved,
            COALESCE(SUM(CASE WHEN u.status='sold' THEN 1 ELSE 0 END),0) AS sold
            FROM projects p LEFT JOIN units u ON u.project_id=p.id GROUP BY p.id ORDER BY p.title";
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['id']; $vm = $vmap[$id] ?? null;
        $buildings[] = ['id' => $id, 'title' => (string)$row['title'], 'units' => (int)$row['total'], 'available' => (int)$row['available'], 'reserved' => (int)$row['reserved'], 'sold' => (int)$row['sold'],
                        'views_total' => $vm ? (int)$vm['total'] : 0, 'views_30' => $vm ? (int)$vm['d30'] : 0, 'views_7' => $vm ? (int)$vm['d7'] : 0];
    }
    return [
        'projects' => (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
        'contacts_new' => (int)$pdo->query("SELECT COUNT(*) FROM contact_requests WHERE status='new'")->fetchColumn(),
        'units' => $units, 'reviews' => $reviews, 'articles' => $articles, 'views' => $views, 'buildings' => $buildings,
    ];
}
