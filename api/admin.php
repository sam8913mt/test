<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

const SETTING_KEYS = ['about','phone','email','address','tagline','founded_year','registration_number','company_manager','credentials'];

function admin_data(): array {
    $projects = [];
    foreach (db()->query('SELECT * FROM projects ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $images = [];
        foreach (all_project_images($p) as $img) {
            if (is_file(UPLOAD_DIR . $img)) $images[] = ['name' => $img, 'url' => UPLOAD_URL . $img];
        }
        $projects[] = [
            'id' => (int)$p['id'], 'title' => (string)$p['title'], 'slug' => (string)$p['slug'],
            'location' => (string)$p['location'], 'status' => (string)$p['status'], 'description' => (string)$p['description'],
            'floors' => (string)$p['floors'], 'area' => (string)$p['area'], 'amenities' => (string)$p['amenities'],
            'featured' => (bool)$p['featured'], 'images' => $images, 'url' => project_url($p),
        ];
    }
    $services = db()->query('SELECT id,title,description,icon,sort_order FROM services ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    $pages = [];
    foreach (db()->query('SELECT * FROM pages') as $pg) {
        $pages[$pg['slug']] = ['title' => (string)$pg['title'], 'content' => (string)$pg['content'], 'meta_title' => (string)$pg['meta_title'], 'meta_description' => (string)$pg['meta_description']];
    }
    $contacts = db()->query('SELECT id,name,phone,email,topic,project_title,message,status,created_at FROM contact_requests ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    $unread = (int)db()->query("SELECT COUNT(*) FROM contact_requests WHERE status='new'")->fetchColumn();
    $settings = [];
    foreach (SETTING_KEYS as $k) {
        $fallback = match ($k) { 'tagline' => SITE_TAGLINE, 'phone' => SITE_PHONE, 'address' => SITE_ADDRESS, default => '' };
        $settings[$k] = setting($k, $fallback);
    }
    return ['projects' => $projects, 'services' => $services, 'pages' => $pages, 'contacts' => $contacts, 'unread' => $unread, 'settings' => $settings];
}

function post_str(string $key): string { return trim((string)($_POST[$key] ?? '')); }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['ok' => true, 'csrf' => csrf_token(), 'data' => admin_data()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    json_response(['ok' => false, 'message' => 'روش درخواست مجاز نیست.'], 405);
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
        $title = post_str('title');
        if ($title === '') throw new RuntimeException('نام ساختمان الزامی است.');
        $existing = [];
        if ($id) {
            $q = db()->prepare('SELECT * FROM projects WHERE id=?'); $q->execute([$id]);
            $existing = $q->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new RuntimeException('ساختمان پیدا نشد.');
        }
        $slug = slugify(post_str('slug') ?: $title);
        $check = db()->prepare('SELECT id FROM projects WHERE slug=? AND id<>?'); $check->execute([$slug, $id]);
        if ($check->fetch()) $slug .= '-' . bin2hex(random_bytes(3));
        $newImages = [];
        try {
            $newImages = upload_images();
            $oldImages = all_project_images($existing ?: []);
            $remove = $_POST['remove_images'] ?? [];
            if (!is_array($remove)) $remove = [];
            $remove = array_values(array_unique(array_filter(array_map('strval', $remove), static fn($v) => in_array($v, $oldImages, true))));
            $images = array_values(array_unique(array_merge(array_values(array_diff($oldImages, $remove)), $newImages)));
            if (count($images) > 12) throw new RuntimeException('هر ساختمان حداکثر می‌تواند ۱۲ تصویر داشته باشد. ابتدا چند تصویر قدیمی را حذف کنید.');
            $primary = $images[0] ?? '';
            $data = [$title, $slug, post_str('location'), post_str('status') ?: 'در حال ساخت', post_str('description'), $primary, json_encode($images, JSON_UNESCAPED_UNICODE), post_str('floors'), post_str('area'), post_str('amenities'), isset($_POST['featured']) ? 1 : 0];
            $pdo = db(); $pdo->beginTransaction();
            try {
                if ($id) {
                    $data[] = $id;
                    $pdo->prepare('UPDATE projects SET title=?,slug=?,location=?,status=?,description=?,image=?,images=?,floors=?,area=?,amenities=?,featured=? WHERE id=?')->execute($data);
                    $notice = 'اطلاعات ساختمان ویرایش شد.';
                } else {
                    $pdo->prepare('INSERT INTO projects(title,slug,location,status,description,image,images,floors,area,amenities,featured) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute($data);
                    $notice = 'ساختمان جدید اضافه شد.';
                }
                $pdo->commit();
            } catch (Throwable $dbError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $dbError;
            }
            foreach (array_diff($oldImages, $images) as $old) if (is_file(UPLOAD_DIR . $old)) @unlink(UPLOAD_DIR . $old);
        } catch (Throwable $saveError) {
            foreach ($newImages as $new) if (is_file(UPLOAD_DIR . $new)) @unlink(UPLOAD_DIR . $new);
            throw $saveError;
        }
    } elseif ($action === 'project_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $q = db()->prepare('SELECT * FROM projects WHERE id=?'); $q->execute([$id]);
        $p = $q->fetch(PDO::FETCH_ASSOC);
        db()->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
        if ($p) foreach (all_project_images($p) as $img) if (is_file(UPLOAD_DIR . $img)) @unlink(UPLOAD_DIR . $img);
        $notice = $p ? 'ساختمان حذف شد.' : 'ساختمان پیدا نشد.';
    } elseif ($action === 'page_save') {
        $slug = post_str('slug');
        if (!in_array($slug, ['about', 'services'], true)) throw new RuntimeException('صفحه نامعتبر است.');
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
    } else {
        throw new RuntimeException('عملیات نامعتبر است.');
    }
    json_response(['ok' => true, 'message' => $notice, 'csrf' => csrf_token(), 'data' => admin_data()]);
} catch (RuntimeException $ex) {
    json_response(['ok' => false, 'message' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    json_response(['ok' => false, 'message' => 'خطای داخلی سرور؛ عملیات انجام نشد.'], 500);
}
