<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

const SETTING_KEYS = ['about','phone','email','address','tagline','founded_year','registration_number','company_manager','credentials'];

function admin_data(int $contactsLimit = 100): array {
    $contactsLimit = max(20, min(1000, $contactsLimit));
    $projects = [];
    foreach (db()->query('SELECT * FROM projects ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $alts = json_decode((string)($p['images_alt'] ?? '{}'), true);
        if (!is_array($alts)) $alts = [];
        $images = [];
        foreach (all_project_images($p) as $img) {
            if (is_file(UPLOAD_DIR . $img)) $images[] = ['name' => $img, 'url' => image_url($img, true), 'alt' => (string)($alts[$img] ?? '')];
        }
        $projects[] = building_export($p) + ['images' => $images, 'url' => project_url($p), 'updated_at_iso' => utc_to_iso((string)($p['updated_at'] ?? ''))];
    }
    $services = db()->query('SELECT id,title,description,icon,sort_order FROM services ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    $pages = [];
    foreach (db()->query('SELECT * FROM pages') as $pg) {
        $pages[$pg['slug']] = ['title' => (string)$pg['title'], 'content' => (string)$pg['content'], 'meta_title' => (string)$pg['meta_title'], 'meta_description' => (string)$pg['meta_description']];
    }
    $contacts = db()->query('SELECT id,name,phone,email,topic,project_title,message,status,created_at FROM contact_requests ORDER BY id DESC LIMIT ' . $contactsLimit)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($contacts as &$c) $c['created_at_iso'] = utc_to_iso((string)$c['created_at']);
    unset($c);
    $contactsTotal = (int)db()->query('SELECT COUNT(*) FROM contact_requests')->fetchColumn();
    $unread = (int)db()->query("SELECT COUNT(*) FROM contact_requests WHERE status='new'")->fetchColumn();
    $settings = [];
    foreach (SETTING_KEYS as $k) {
        $fallback = match ($k) { 'tagline' => SITE_TAGLINE, 'phone' => SITE_PHONE, 'address' => SITE_ADDRESS, default => '' };
        $settings[$k] = setting($k, $fallback);
    }
    $audit = db()->query('SELECT at,action,detail FROM audit_log ORDER BY id DESC LIMIT 40')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($audit as &$a) $a['at_iso'] = utc_to_iso((string)$a['at']);
    unset($a);
    $pending = 0;
    foreach ($projects as $p) foreach ($p['images'] as $img) if (!is_file(UPLOAD_DIR . thumb_name($img['name']))) $pending++;
    return ['projects' => $projects, 'services' => $services, 'pages' => $pages, 'contacts' => $contacts, 'contacts_total' => $contactsTotal, 'unread' => $unread, 'settings' => $settings,
            'warnings' => security_warnings(), 'audit' => $audit, 'images_pending' => gd_available() ? $pending : 0, 'notifications' => notifications_configured(), 'username' => ADMIN_USERNAME];
}

function post_str(string $key): string { return trim((string)($_POST[$key] ?? '')); }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['ok' => true, 'csrf' => csrf_token(), 'data' => admin_data((int)($_GET['contacts_limit'] ?? 100))]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    json_response(['ok' => false, 'message' => 'روش درخواست مجاز نیست.'], 405);
}
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    json_response(['ok' => false, 'message' => 'حجم کل داده‌های ارسالی بیشتر از حد مجاز سرور (post_max_size) است. تعداد یا حجم تصاویر را کمتر کنید یا تنظیمات PHP را طبق راهنما افزایش دهید.'], 413);
}
verify_csrf();
$action = post_str('action');
$notice = '';

try {
    if ($action === 'settings') {
        $s = db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
        foreach (SETTING_KEYS as $k) $s->execute([$k, post_str($k)]);
        $notice = 'اطلاعات شرکت ذخیره شد.';
    } elseif ($action === 'project_save') {
        $id = (int)($_POST['id'] ?? 0);
        $existing = [];
        if ($id) {
            $q = db()->prepare('SELECT * FROM projects WHERE id=?'); $q->execute([$id]);
            $existing = $q->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new RuntimeException('ساختمان پیدا نشد.');
        }
        [$vals, $errors] = validate_building($_POST, $existing);
        if ($errors) throw new ValidationException($errors);
        $slugInput = (string)$vals['slug']; unset($vals['slug']);
        $slug = mb_substr(slugify($slugInput !== '' ? $slugInput : (string)$vals['title']), 0, 150, 'UTF-8');
        $slug = trim($slug, '-') ?: ('building-' . bin2hex(random_bytes(3)));
        $check = db()->prepare('SELECT id FROM projects WHERE slug=? AND id<>?'); $check->execute([$slug, $id]);
        if ($check->fetch()) $slug .= '-' . bin2hex(random_bytes(3));
        $plan = ['final' => [], 'created' => [], 'obsolete' => []];
        try {
            $plan = plan_building_images($id ? all_project_images($existing) : []);
            $images = $plan['final'];
            $row = $vals;
            $row['slug'] = $slug;
            $row['image'] = $images[0] ?? '';
            $row['images'] = json_encode($images, JSON_UNESCAPED_UNICODE);
            $altIn = $_POST['image_alt'] ?? [];
            if (!is_array($altIn)) $altIn = [];
            $oldAlt = json_decode((string)($existing['images_alt'] ?? '{}'), true);
            if (!is_array($oldAlt)) $oldAlt = [];
            $alts = [];
            foreach ($images as $n) {
                $src = $n;
                foreach ($plan['replaced'] as $o => $nn) if ($nn === $n) $src = $o;   // a replaced image keeps its description
                $hex = explode('.', $src)[0];
                $v = (array_key_exists($hex, $altIn) && is_scalar($altIn[$hex])) ? clean_text((string)$altIn[$hex]) : (string)($oldAlt[$src] ?? '');
                $v = mb_substr(preg_replace('/\s+/u', ' ', $v) ?? $v, 0, 150, 'UTF-8');
                if ($v !== '') $alts[$n] = $v;
            }
            $row['images_alt'] = json_encode($alts, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
            $row['updated_at'] = utc_now();
            $pdo = db(); $pdo->beginTransaction();
            try {
                if ($id) {
                    $set = implode(',', array_map(static fn($c) => $c . '=?', array_keys($row)));
                    $pdo->prepare('UPDATE projects SET ' . $set . ' WHERE id=?')->execute(array_merge(array_values($row), [$id]));
                    $notice = 'اطلاعات ساختمان ویرایش شد.';
                } else {
                    $cols = implode(',', array_keys($row));
                    $marks = implode(',', array_fill(0, count($row), '?'));
                    $pdo->prepare('INSERT INTO projects(' . $cols . ') VALUES(' . $marks . ')')->execute(array_values($row));
                    $notice = 'ساختمان جدید اضافه شد.';
                }
                $pdo->commit();
            } catch (Throwable $dbError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $dbError;
            }
            foreach ($plan['obsolete'] as $old) image_files_delete($old);
        } catch (Throwable $saveError) {
            foreach ($plan['created'] as $new) image_files_delete($new);
            throw $saveError;
        }
    } elseif ($action === 'project_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $q = db()->prepare('SELECT * FROM projects WHERE id=?'); $q->execute([$id]);
        $p = $q->fetch(PDO::FETCH_ASSOC);
        db()->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
        if ($p) foreach (all_project_images($p) as $img) image_files_delete($img);
        $notice = $p ? 'ساختمان حذف شد.' : 'ساختمان پیدا نشد.';
    } elseif ($action === 'page_save') {
        $slug = post_str('slug');
        if (!in_array($slug, ['about', 'services', 'privacy'], true)) throw new RuntimeException('صفحه نامعتبر است.');
        $s = db()->prepare('INSERT INTO pages(slug,title,content,meta_title,meta_description) VALUES(?,?,?,?,?) ON CONFLICT(slug) DO UPDATE SET title=excluded.title,content=excluded.content,meta_title=excluded.meta_title,meta_description=excluded.meta_description');
        $s->execute([$slug, post_str('title'), post_str('content'), post_str('meta_title'), post_str('meta_description')]);
        if ($slug === 'about') {
            db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute(['about', post_str('content')]);
        }
        $notice = 'صفحه ذخیره شد.';
    } elseif ($action === 'service_save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = post_str('title');
        if ($title === '') throw new RuntimeException('عنوان خدمت الزامی است.');
        $vals = [$title, post_str('description'), post_str('icon') ?: '◆', (int)($_POST['sort_order'] ?? 0)];
        if ($id) { $vals[] = $id; db()->prepare('UPDATE services SET title=?,description=?,icon=?,sort_order=? WHERE id=?')->execute($vals); }
        else { db()->prepare('INSERT INTO services(title,description,icon,sort_order) VALUES(?,?,?,?)')->execute($vals); }
        $notice = 'خدمت ذخیره شد.';
    } elseif ($action === 'service_delete') {
        db()->prepare('DELETE FROM services WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        $notice = 'خدمت حذف شد.';
    } elseif ($action === 'contact_status') {
        $status = post_str('status') ?: 'read';
        if (!in_array($status, ['new', 'read'], true)) throw new RuntimeException('وضعیت درخواست نامعتبر است.');
        db()->prepare('UPDATE contact_requests SET status=? WHERE id=?')->execute([$status, (int)($_POST['id'] ?? 0)]);
        $notice = 'وضعیت درخواست به‌روزرسانی شد.';
    } elseif ($action === 'contact_delete') {
        db()->prepare('DELETE FROM contact_requests WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        $notice = 'درخواست حذف شد.';
    } elseif ($action === 'change_password') {
        $cur = (string)($_POST['current_password'] ?? ''); $new = (string)($_POST['new_password'] ?? ''); $rep = (string)($_POST['repeat_password'] ?? '');
        if (admin_login_is_limited()) throw new RuntimeException('تلاش‌های ناموفق بیش از حد مجاز است؛ ۱۵ دقیقه بعد دوباره تلاش کنید.');
        if (!password_verify($cur, admin_password_hash())) { admin_login_record_failure(); throw new RuntimeException('رمز عبور فعلی درست نیست.'); }
        if (mb_strlen($new, 'UTF-8') < 10) throw new RuntimeException('رمز جدید باید حداقل ۱۰ نویسه باشد.');
        if ($new !== $rep) throw new RuntimeException('تکرار رمز جدید با رمز جدید یکسان نیست.');
        if ($new === $cur || strcasecmp($new, ADMIN_USERNAME) === 0) throw new RuntimeException('رمز جدید باید با رمز فعلی و نام کاربری متفاوت باشد.');
        if (count(array_unique(mb_str_split($new, 1, 'UTF-8'))) < 5) throw new RuntimeException('رمز جدید خیلی ساده است؛ از نویسه‌های متنوع‌تر استفاده کنید.');
        db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute(['admin_password_hash', password_hash($new, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $notice = 'رمز عبور تغییر کرد.';
    } elseif ($action === 'images_optimize') {
        $done = 0; $failed = 0; $left = 0; $limit = 12;
        if (!gd_available()) throw new RuntimeException('افزونه GD در PHP فعال نیست؛ بهینه‌سازی ممکن نیست.');
        foreach (db()->query('SELECT image,images FROM projects')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            foreach (all_project_images($p) as $name) {
                if (!is_file(UPLOAD_DIR . $name) || is_file(UPLOAD_DIR . thumb_name($name))) continue;
                if ($done + $failed >= $limit) { $left++; continue; }
                $info = @getimagesize(UPLOAD_DIR . $name); $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
                if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && optimize_image($name, $mime)) $done++; else $failed++;
            }
        }
        $notice = "{$done} تصویر بهینه شد" . ($failed ? "، {$failed} تصویر قابل پردازش نبود" : '') . ($left ? "؛ {$left} تصویر باقی مانده است (دوباره اجرا کنید)." : '.');
    } else {
        throw new RuntimeException('عملیات نامعتبر است.');
    }
    if ($action !== 'contact_status') audit($action, trim('id=' . (string)($_POST['id'] ?? '') . ' ' . post_str('title') . ' ' . post_str('slug')));
    json_response(['ok' => true, 'message' => $notice, 'csrf' => csrf_token(), 'data' => admin_data()]);
} catch (ValidationException $ex) {
    json_response(['ok' => false, 'message' => $ex->getMessage(), 'errors' => $ex->errors], 422);
} catch (RuntimeException $ex) {
    json_response(['ok' => false, 'message' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    log_exception($ex, 'admin.php action=' . $action);
    json_response(['ok' => false, 'message' => 'خطای داخلی سرور؛ عملیات انجام نشد. جزئیات در فایل app.log ثبت شد.'], 500);
}
