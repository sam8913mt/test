<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_admin();

const SETTING_KEYS = ['about','phone','email','address','tagline','founded_year','registration_number','company_manager','credentials','about_mission','about_vision','about_history'];

function admin_data(): array {
    $projects = [];
    foreach (db()->query('SELECT * FROM projects ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $images = [];
        foreach (all_project_images($p) as $img) {
            if (is_file(UPLOAD_DIR . $img)) $images[] = ['name' => $img, 'url' => UPLOAD_URL . $img];
        }
        $projects[] = building_admin_row($p) + ['images' => $images, 'url' => project_url($p), 'updated_at' => (string)($p['updated_at'] ?? '')];
    }
    $services = db()->query('SELECT id,title,description,icon,sort_order FROM services ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    $pages = [];
    foreach (db()->query('SELECT * FROM pages') as $pg) {
        $pages[$pg['slug']] = ['title' => (string)$pg['title'], 'content' => (string)$pg['content'], 'meta_title' => (string)$pg['meta_title'], 'meta_description' => (string)$pg['meta_description']];
    }
    $faqs = [];
    foreach (db()->query('SELECT id,question,answer,sort_order,is_active FROM faqs ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $faqs[] = ['id' => (int)$f['id'], 'question' => (string)$f['question'], 'answer' => (string)$f['answer'], 'sort_order' => (int)$f['sort_order'], 'is_active' => (bool)$f['is_active']];
    }
    $units = array_map('unit_admin_row', db()->query('SELECT * FROM units ORDER BY project_id, COALESCE(`floor`,0), sort_order, unit_number')->fetchAll(PDO::FETCH_ASSOC));
    $reviews = [];
    foreach (db()->query('SELECT r.id, r.project_id, r.name, r.rating, r.comment, r.status, r.created_at, r.moderated_at, p.title AS project_title FROM reviews r JOIN projects p ON p.id=r.project_id ORDER BY r.id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $reviews[] = ['id' => (int)$r['id'], 'project_id' => (int)$r['project_id'], 'project_title' => (string)$r['project_title'], 'name' => (string)$r['name'], 'rating' => (int)$r['rating'], 'comment' => (string)$r['comment'], 'status' => (string)$r['status'], 'created_at' => (string)$r['created_at'], 'moderated_at' => (string)$r['moderated_at']];
    }
    $articles = array_map('article_admin_row', db()->query('SELECT * FROM articles ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC));
    $aboutItems = array_map('about_item_admin_row', db()->query('SELECT * FROM about_items ORDER BY kind, sort_order, id')->fetchAll(PDO::FETCH_ASSOC));
    $aboutImg = setting('about_image', '');
    $contacts = db()->query('SELECT id,name,phone,email,topic,project_title,message,status,created_at FROM contact_requests ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    $unread = (int)db()->query("SELECT COUNT(*) FROM contact_requests WHERE status='new'")->fetchColumn();
    $settings = [];
    foreach (SETTING_KEYS as $k) {
        $fallback = match ($k) { 'tagline' => SITE_TAGLINE, 'phone' => SITE_PHONE, 'address' => SITE_ADDRESS, default => '' };
        $settings[$k] = setting($k, $fallback);
    }
    return ['options' => building_options() + unit_options() + ['review_statuses' => REVIEW_STATUSES, 'article_statuses' => ARTICLE_STATUSES, 'about_kinds' => ABOUT_KINDS],
            'units' => $units, 'reviews' => $reviews, 'articles' => $articles, 'about_items' => $aboutItems, 'about_image' => ($aboutImg !== '' && is_file(UPLOAD_DIR . $aboutImg)) ? UPLOAD_URL . $aboutImg : '', 'stats' => admin_stats(),
             'projects' => $projects, 'services' => $services, 'faqs' => $faqs, 'pages' => $pages, 'contacts' => $contacts, 'unread' => $unread, 'settings' => $settings];
}


/** اعتبارسنجی ساده‌ی رشته؛ خطا را در $errors[$key] ثبت می‌کند. */
function admin_text(string $key, string $label, int $min, int $max, array &$errors): string {
    $raw = $_POST[$key] ?? '';
    $v = building_clean_text(is_array($raw) ? '' : (string)$raw, $max > 300);
    $len = mb_strlen($v, 'UTF-8');
    if ($len > 0 && $len < $min) $errors[$key] = $label . ' باید حداقل ' . $min . ' نویسه باشد.';
    elseif ($len > $max) $errors[$key] = $label . ' نباید بیش از ' . $max . ' نویسه باشد.';
    return $v;
}
function admin_fail(array $errors): never {
    json_response(['ok' => false, 'message' => 'لطفاً خطاهای مشخص‌شده در فرم را برطرف کنید.', 'errors' => $errors], 422);
}

/** ادغام تصاویر قدیمی، حذف‌شده‌ها و آپلودهای جدید؛ تصویر اصلی (cover_image) اول می‌آید. خروجی: [همه تصاویر، تصاویر جدید] */
function admin_merge_images(array $old, int $max, string $field): array {
    $remove = $_POST['remove_images'] ?? [];
    if (!is_array($remove)) $remove = [];
    $remove = array_values(array_unique(array_filter(array_map('strval', $remove), static fn($v) => in_array($v, $old, true))));
    $kept = array_values(array_diff($old, $remove));
    $new = upload_images($field, max(0, $max - count($kept)));
    $images = array_values(array_unique(array_merge($kept, $new)));
    $cover = post_str('cover_image');
    if ($cover !== '' && in_array($cover, $images, true)) $images = array_values(array_unique(array_merge([$cover], $images)));
    return [$images, $new];
}
function admin_unlink_files(array $names): void { foreach ($names as $n) if ($n !== '' && is_file(UPLOAD_DIR . $n)) @unlink(UPLOAD_DIR . $n); }
function admin_set_setting(string $key, string $value): void {
    db()->prepare('INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)')->execute([$key, $value]);
}

function post_str(string $key): string { return trim((string)($_POST[$key] ?? '')); }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['ok' => true, 'csrf' => csrf_token(), 'data' => admin_data()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    json_response(['ok' => false, 'message' => 'روش درخواست مجاز نیست.'], 405);
}
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    json_response(['ok' => false, 'message' => 'حجم کل فایل‌های ارسالی از حد مجاز سرور (post_max_size) بیشتر است. تعداد یا حجم تصاویر را کمتر کنید.'], 413);
}
verify_csrf();
$action = post_str('action');
$notice = '';

try {
    if ($action === 'settings') {
        // فقط کلیدهای ارسال‌شده ذخیره می‌شوند؛ بقیه‌ی محتوای موجود دست‌نخورده می‌ماند.
        [$vals, $err] = settings_parse($_POST);
        if ($err) admin_fail($err);
        if (!$vals) throw new RuntimeException('هیچ فیلدی برای ذخیره ارسال نشد.');
        $pdo = db(); $pdo->beginTransaction();
        try { foreach ($vals as $k => $val) admin_set_setting($k, $val); $pdo->commit(); }
        catch (Throwable $t) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $t; }
        $notice = 'تغییرات ذخیره شد.';
    } elseif ($action === 'project_save') {
        $id = (int)($_POST['id'] ?? 0);
        $existing = [];
        if ($id) {
            $q = db()->prepare('SELECT * FROM projects WHERE id=?'); $q->execute([$id]);
            $existing = $q->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new RuntimeException('ساختمان پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        }
        // ۱) اعتبارسنجی فیلدها پیش از هر نوشتن روی دیسک یا دیتابیس
        [$values, $errors] = building_parse($_POST, $existing ?: []);
        if ($errors) json_response(['ok' => false, 'message' => 'لطفاً خطاهای مشخص‌شده در فرم را برطرف کنید.', 'errors' => $errors], 422);

        $slug = slugify((string)($values['slug'] !== '' ? $values['slug'] : $values['title']));
        $check = db()->prepare('SELECT id FROM projects WHERE slug=? AND id<>?'); $check->execute([$slug, $id]);
        if ($check->fetch()) $slug = mb_substr($slug, 0, 150, 'UTF-8') . '-' . bin2hex(random_bytes(3));
        $values['slug'] = $slug;

        // ۲) تصاویر: حفظ، حذف، انتخاب تصویر اصلی و بارگذاری تصاویر جدید
        $oldImages = all_project_images($existing ?: []);
        $remove = $_POST['remove_images'] ?? [];
        if (!is_array($remove)) $remove = [];
        $remove = array_values(array_unique(array_filter(array_map('strval', $remove), static fn($v) => in_array($v, $oldImages, true))));
        $kept = array_values(array_diff($oldImages, $remove));
        $room = 12 - count($kept);
        if ($room < 0) $room = 0;
        $newImages = [];
        try {
            $newImages = upload_images('images', $room);
            $images = array_values(array_unique(array_merge($kept, $newImages)));
            if (count($images) > 12) throw new RuntimeException('هر ساختمان حداکثر می‌تواند ۱۲ تصویر داشته باشد. ابتدا چند تصویر قدیمی را حذف کنید.');
            $cover = post_str('cover_image');
            if ($cover !== '' && in_array($cover, $images, true)) $images = array_values(array_unique(array_merge([$cover], $images)));
            $row = $values;
            $row['image'] = $images[0] ?? '';
            $row['images'] = json_encode($images, JSON_UNESCAPED_UNICODE);

            $pdo = db(); $pdo->beginTransaction();
            try {
                if ($id) {
                    $set = implode(',', array_map(static fn($c) => $c . '=?', array_keys($row)));
                    $pdo->prepare("UPDATE projects SET $set, updated_at=NOW() WHERE id=?")->execute(array_merge(array_values($row), [$id]));
                    $notice = 'اطلاعات ساختمان ویرایش شد.';
                } else {
                    $cols = implode(',', array_keys($row)); $marks = implode(',', array_fill(0, count($row), '?'));
                    $pdo->prepare("INSERT INTO projects($cols, updated_at) VALUES($marks, NOW())")->execute(array_values($row));
                    $id = (int)$pdo->lastInsertId();
                    $notice = 'ساختمان جدید اضافه شد.';
                }
                $pdo->commit();
            } catch (Throwable $dbError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $dbError;
            }
            // فایل‌های حذف‌شده فقط بعد از موفقیت کامل ذخیره از دیسک پاک می‌شوند.
            foreach (array_diff($oldImages, $images) as $old) if (is_file(UPLOAD_DIR . $old)) @unlink(UPLOAD_DIR . $old);
        } catch (Throwable $saveError) {
            foreach ($newImages as $new) if (is_file(UPLOAD_DIR . $new)) @unlink(UPLOAD_DIR . $new);
            throw $saveError;
        }
    } elseif ($action === 'project_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $q = db()->prepare('SELECT * FROM projects WHERE id=?'); $q->execute([$id]);
        $p = $q->fetch(PDO::FETCH_ASSOC);
        $unitFiles = [];
        $uq = db()->prepare('SELECT image, images FROM units WHERE project_id=?'); $uq->execute([$id]);
        foreach ($uq->fetchAll(PDO::FETCH_ASSOC) as $ur) $unitFiles = array_merge($unitFiles, all_project_images($ur));
        db()->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);   // واحدها و نظرات با ON DELETE CASCADE حذف می‌شوند
        if ($p) foreach (all_project_images($p) as $img) if (is_file(UPLOAD_DIR . $img)) @unlink(UPLOAD_DIR . $img);
        admin_unlink_files($unitFiles);
        $notice = $p ? 'ساختمان حذف شد.' : 'ساختمان پیدا نشد.';
    } elseif ($action === 'page_save') {
        $slug = post_str('slug');
        if (!in_array($slug, ['about', 'services'], true)) throw new RuntimeException('صفحه نامعتبر است.');
        $err = [];
        $title = admin_text('title', 'عنوان صفحه', 2, 150, $err);
        if ($title === '' && !isset($err['title'])) $err['title'] = 'عنوان صفحه الزامی است.';
        $content = admin_text('content', 'محتوای صفحه', 0, 20000, $err);
        $metaTitle = admin_text('meta_title', 'عنوان سئو', 0, 120, $err);
        $metaDesc = admin_text('meta_description', 'توضیحات سئو', 0, 300, $err);
        if ($err) admin_fail($err);
        $st = db()->prepare('INSERT INTO pages(slug,title,content,meta_title,meta_description) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),content=VALUES(content),meta_title=VALUES(meta_title),meta_description=VALUES(meta_description)');
        $st->execute([$slug, $title, $content, $metaTitle, $metaDesc]);
        $notice = 'صفحه ذخیره شد.';
    } elseif ($action === 'service_save') {
        $id = (int)($_POST['id'] ?? 0);
        $err = [];
        $title = admin_text('title', 'عنوان خدمت', 2, 120, $err);
        if ($title === '' && !isset($err['title'])) $err['title'] = 'عنوان خدمت الزامی است.';
        $desc = admin_text('description', 'توضیحات', 0, 500, $err);
        $icon = admin_text('icon', 'نماد', 0, 8, $err); if ($icon === '') $icon = '◆';
        $ord = normalize_digits(trim((string)($_POST['sort_order'] ?? '0'))); if ($ord === '') $ord = '0';
        if (!preg_match('/^-?\d{1,4}$/', $ord)) $err['sort_order'] = 'ترتیب نمایش باید عدد صحیح باشد.';
        if ($err) admin_fail($err);
        $vals = [$title, $desc, $icon, (int)$ord];
        if ($id) { $vals[] = $id; db()->prepare('UPDATE services SET title=?,description=?,icon=?,sort_order=? WHERE id=?')->execute($vals); }
        else { db()->prepare('INSERT INTO services(title,description,icon,sort_order) VALUES(?,?,?,?)')->execute($vals); }
        $notice = 'خدمت ذخیره شد.';
    } elseif ($action === 'service_delete') {
        db()->prepare('DELETE FROM services WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        $notice = 'خدمت حذف شد.';
    } elseif ($action === 'faq_save') {
        $id = (int)($_POST['id'] ?? 0);
        [$v, $errors] = faq_parse($_POST);
        if (!$errors) {
            $dup = db()->prepare('SELECT id FROM faqs WHERE question=? AND id<>?'); $dup->execute([$v['question'], $id]);
            if ($dup->fetch()) $errors['question'] = 'این پرسش قبلاً ثبت شده است.';
            if (!$id && (int)db()->query('SELECT COUNT(*) FROM faqs')->fetchColumn() >= FAQ_MAX_ITEMS) throw new RuntimeException('حداکثر ' . FAQ_MAX_ITEMS . ' پرسش قابل ثبت است.');
        }
        if ($errors) json_response(['ok' => false, 'message' => 'لطفاً خطاهای مشخص‌شده در فرم را برطرف کنید.', 'errors' => $errors], 422);
        if ($id) {
            $ex = db()->prepare('SELECT id FROM faqs WHERE id=?'); $ex->execute([$id]);
            if (!$ex->fetch()) throw new RuntimeException('پرسش پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
            db()->prepare('UPDATE faqs SET question=?, answer=?, sort_order=?, is_active=? WHERE id=?')->execute([$v['question'], $v['answer'], $v['sort_order'], $v['is_active'], $id]);
            $notice = 'پرسش ویرایش شد.';
        } else {
            db()->prepare('INSERT INTO faqs(question, answer, sort_order, is_active) VALUES(?,?,?,?)')->execute([$v['question'], $v['answer'], $v['sort_order'], $v['is_active']]);
            $notice = 'پرسش جدید اضافه شد.';
        }
    } elseif ($action === 'faq_import_samples') {
        $added = faq_import_samples(db());
        $notice = $added > 0 ? ($added . ' پرسش آماده اضافه شد. می‌توانید هر کدام را ویرایش، مخفی یا حذف کنید.') : 'همه‌ی پرسش‌های آماده از قبل وجود دارند؛ چیزی اضافه نشد.';
    } elseif ($action === 'faq_toggle') {
        db()->prepare('UPDATE faqs SET is_active = CASE is_active WHEN 1 THEN 0 ELSE 1 END WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        $notice = 'وضعیت نمایش پرسش تغییر کرد.';
    } elseif ($action === 'faq_delete') {
        db()->prepare('DELETE FROM faqs WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        $notice = 'پرسش حذف شد.';
    } elseif ($action === 'unit_save') {
        $id = (int)($_POST['id'] ?? 0);
        [$v, $errors] = unit_parse($_POST);
        $existing = null;
        if ($id) {
            $q = db()->prepare('SELECT * FROM units WHERE id=?'); $q->execute([$id]); $existing = $q->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new RuntimeException('واحد پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        }
        if (!isset($errors['project_id'])) {
            $pq = db()->prepare('SELECT id FROM projects WHERE id=?'); $pq->execute([$v['project_id']]);
            if (!$pq->fetch()) $errors['project_id'] = 'ساختمان انتخاب‌شده پیدا نشد.';
        }
        if (!isset($errors['project_id']) && !isset($errors['unit_number'])) {
            $dq = db()->prepare('SELECT id FROM units WHERE project_id=? AND unit_number=? AND id<>?'); $dq->execute([$v['project_id'], $v['unit_number'], $id]);
            if ($dq->fetch()) $errors['unit_number'] = 'واحدی با این شماره در همین ساختمان وجود دارد.';
        }
        if ($errors) admin_fail($errors);
        $new = [];
        try {
            [$images, $new] = admin_merge_images($existing ? unit_images_list($existing) : [], UNIT_MAX_IMAGES, 'images');
            $row = $v; $row['image'] = $images[0] ?? ''; $row['images'] = json_encode($images, JSON_UNESCAPED_UNICODE);
            $changed = !$existing || (string)$existing['status'] !== $v['status'];
            $row['status_changed_at'] = $changed ? gmdate('Y-m-d H:i:s') : (string)$existing['status_changed_at'];
            $pdo = db(); $pdo->beginTransaction();
            try {
                if ($id) {
                    $set = implode(',', array_map(static fn($c) => $c . '=?', array_keys($row)));
                    $pdo->prepare("UPDATE units SET $set, updated_at=NOW() WHERE id=?")->execute(array_merge(array_values($row), [$id]));
                    $notice = 'اطلاعات واحد ذخیره شد.';
                } else {
                    $cols = implode(',', array_keys($row)); $marks = implode(',', array_fill(0, count($row), '?'));
                    $pdo->prepare("INSERT INTO units($cols, updated_at) VALUES($marks, NOW())")->execute(array_values($row));
                    $notice = 'واحد جدید اضافه شد.';
                }
                $pdo->commit();
            } catch (Throwable $dbErr) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $dbErr; }
            if ($existing) admin_unlink_files(array_diff(unit_images_list($existing), $images));
        } catch (PDOException $dbEx) {
            admin_unlink_files($new);
            if (str_contains($dbEx->getMessage(), 'UNIQUE')) admin_fail(['unit_number' => 'واحدی با این شماره در همین ساختمان وجود دارد.']);
            throw $dbEx;
        } catch (Throwable $saveErr) { admin_unlink_files($new); throw $saveErr; }
    } elseif ($action === 'unit_status') {
        $id = (int)($_POST['id'] ?? 0); $status = post_str('status');
        if (!isset(UNIT_STATUSES[$status])) throw new RuntimeException('وضعیت فروش معتبر نیست.');
        $q = db()->prepare('SELECT status FROM units WHERE id=?'); $q->execute([$id]); $cur = $q->fetchColumn();
        if ($cur === false) throw new RuntimeException('واحد پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        if ((string)$cur !== $status) db()->prepare("UPDATE units SET status=?, status_changed_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$status, $id]);
        $notice = 'وضعیت فروش واحد به «' . UNIT_STATUSES[$status] . '» تغییر کرد.';
    } elseif ($action === 'unit_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $q = db()->prepare('SELECT image, images FROM units WHERE id=?'); $q->execute([$id]); $u = $q->fetch(PDO::FETCH_ASSOC);
        if (!$u) throw new RuntimeException('واحد پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        db()->prepare('DELETE FROM units WHERE id=?')->execute([$id]);
        admin_unlink_files(all_project_images($u));
        $notice = 'واحد حذف شد.';
    } elseif (in_array($action, ['review_approve', 'review_hide', 'review_restore'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $q = db()->prepare('SELECT status FROM reviews WHERE id=?'); $q->execute([$id]); $cur = $q->fetchColumn();
        if ($cur === false) throw new RuntimeException('نظر پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        $next = review_transition($action, (string)$cur);
        if ($next === null) throw new RuntimeException('این عملیات برای وضعیت فعلی نظر مجاز نیست.');
        db()->prepare("UPDATE reviews SET status=?, moderated_at=NOW() WHERE id=?")->execute([$next, $id]);
        $notice = ['review_approve' => 'نظر تأیید شد و در سایت نمایش داده می‌شود.', 'review_hide' => 'نظر مخفی شد و در سایت نمایش داده نمی‌شود.', 'review_restore' => 'نظر بازگردانی شد و دوباره در سایت نمایش داده می‌شود.'][$action];
    } elseif ($action === 'review_delete') {
        $q = db()->prepare('DELETE FROM reviews WHERE id=?'); $q->execute([(int)($_POST['id'] ?? 0)]);
        if ($q->rowCount() < 1) throw new RuntimeException('نظر پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        $notice = 'نظر حذف شد.';
    } elseif ($action === 'article_save') {
        $id = (int)($_POST['id'] ?? 0);
        [$v, $errors] = article_parse($_POST);
        $existing = null;
        if ($id) {
            $q = db()->prepare('SELECT * FROM articles WHERE id=?'); $q->execute([$id]); $existing = $q->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new RuntimeException('مقاله پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        }
        if ($errors) admin_fail($errors);
        $slug = slugify($v['slug'] !== '' ? $v['slug'] : $v['title']);
        $dq = db()->prepare('SELECT id FROM articles WHERE slug=? AND id<>?'); $dq->execute([$slug, $id]);
        if ($dq->fetch()) $slug = mb_substr($slug, 0, 150, 'UTF-8') . '-' . bin2hex(random_bytes(3));
        $newCover = [];
        try {
            $newCover = upload_images('cover', 1);
            $cover = $existing ? (string)$existing['cover'] : '';
            if (!empty($_POST['remove_cover'])) $cover = '';
            if ($newCover) $cover = $newCover[0];
            $publishedAt = $existing ? (string)$existing['published_at'] : '';
            if ($v['status'] === 'published' && $publishedAt === '') $publishedAt = gmdate('Y-m-d H:i:s');
            $row = $v; $row['slug'] = $slug; $row['cover'] = $cover; $row['published_at'] = $publishedAt;
            $pdo = db(); $pdo->beginTransaction();
            try {
                if ($id) {
                    $set = implode(',', array_map(static fn($c) => $c . '=?', array_keys($row)));
                    $pdo->prepare("UPDATE articles SET $set, updated_at=NOW() WHERE id=?")->execute(array_merge(array_values($row), [$id]));
                    $notice = 'مقاله ذخیره شد.';
                } else {
                    $cols = implode(',', array_keys($row)); $marks = implode(',', array_fill(0, count($row), '?'));
                    $pdo->prepare("INSERT INTO articles($cols, updated_at) VALUES($marks, NOW())")->execute(array_values($row));
                    $notice = 'مقاله جدید اضافه شد.';
                }
                $pdo->commit();
            } catch (Throwable $dbErr) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $dbErr; }
            if ($existing && (string)$existing['cover'] !== '' && (string)$existing['cover'] !== $cover) admin_unlink_files([(string)$existing['cover']]);
        } catch (Throwable $saveErr) { admin_unlink_files($newCover); throw $saveErr; }
    } elseif ($action === 'article_publish') {
        $id = (int)($_POST['id'] ?? 0); $status = post_str('status');
        if (!isset(ARTICLE_STATUSES[$status])) throw new RuntimeException('وضعیت انتشار معتبر نیست.');
        $q = db()->prepare('SELECT published_at FROM articles WHERE id=?'); $q->execute([$id]); $pub = $q->fetchColumn();
        if ($pub === false) throw new RuntimeException('مقاله پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        $at = ($status === 'published' && (string)$pub === '') ? gmdate('Y-m-d H:i:s') : (string)$pub;
        db()->prepare("UPDATE articles SET status=?, published_at=?, updated_at=NOW() WHERE id=?")->execute([$status, $at, $id]);
        $notice = $status === 'published' ? 'مقاله منتشر شد.' : 'مقاله از حالت انتشار خارج شد و پیش‌نویس است.';
    } elseif ($action === 'article_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $q = db()->prepare('SELECT cover FROM articles WHERE id=?'); $q->execute([$id]); $cv = $q->fetchColumn();
        if ($cv === false) throw new RuntimeException('مقاله پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        db()->prepare('DELETE FROM articles WHERE id=?')->execute([$id]);
        admin_unlink_files([(string)$cv]);
        db()->prepare("DELETE FROM content_views WHERE kind='article' AND item_id=?")->execute([$id]);
        $notice = 'مقاله حذف شد.';
    } elseif ($action === 'about_image_save') {
        $newImg = [];
        try {
            $newImg = upload_images('about_image', 1);
            $old = setting('about_image', ''); $cur = $old;
            if (!empty($_POST['remove_about_image'])) $cur = '';
            if ($newImg) $cur = $newImg[0];
            if ($cur === $old && !$newImg) throw new RuntimeException('تصویری برای ذخیره انتخاب نشده است.');
            admin_set_setting('about_image', $cur);
            if ($old !== '' && $old !== $cur) admin_unlink_files([$old]);
        } catch (Throwable $t) { admin_unlink_files($newImg); throw $t; }
        $notice = 'تصویر «درباره ما» ذخیره شد.';
    } elseif ($action === 'about_item_save') {
        $id = (int)($_POST['id'] ?? 0);
        [$v, $errors] = about_item_parse($_POST);
        $existing = null;
        if ($id) { $q = db()->prepare('SELECT * FROM about_items WHERE id=?'); $q->execute([$id]); $existing = $q->fetch(PDO::FETCH_ASSOC); if (!$existing) throw new RuntimeException('مورد پیدا نشد؛ ممکن است قبلاً حذف شده باشد.'); }
        if ($errors) admin_fail($errors);
        $newImg = [];
        try {
            $newImg = ($v['kind'] === 'team') ? upload_images('image', 1) : [];
            $img = $existing ? (string)$existing['image'] : '';
            if (!empty($_POST['remove_image']) || $v['kind'] !== 'team') $img = '';
            if ($newImg) $img = $newImg[0];
            $row = $v; $row['image'] = $img;
            if ($id) {
                $set = implode(',', array_map(static fn($c) => $c . '=?', array_keys($row)));
                db()->prepare("UPDATE about_items SET $set, updated_at=NOW() WHERE id=?")->execute(array_merge(array_values($row), [$id]));
            } else {
                $cols = implode(',', array_keys($row)); $marks = implode(',', array_fill(0, count($row), '?'));
                db()->prepare("INSERT INTO about_items($cols, updated_at) VALUES($marks, NOW())")->execute(array_values($row));
            }
            if ($existing && (string)$existing['image'] !== '' && (string)$existing['image'] !== $img) admin_unlink_files([(string)$existing['image']]);
        } catch (Throwable $t) { admin_unlink_files($newImg); throw $t; }
        $notice = 'مورد ذخیره شد.';
    } elseif ($action === 'about_item_toggle') {
        db()->prepare("UPDATE about_items SET is_active = CASE is_active WHEN 1 THEN 0 ELSE 1 END, updated_at=NOW() WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $notice = 'وضعیت نمایش تغییر کرد.';
    } elseif ($action === 'about_item_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $q = db()->prepare('SELECT image FROM about_items WHERE id=?'); $q->execute([$id]); $im = $q->fetchColumn();
        if ($im === false) throw new RuntimeException('مورد پیدا نشد؛ ممکن است قبلاً حذف شده باشد.');
        db()->prepare('DELETE FROM about_items WHERE id=?')->execute([$id]);
        admin_unlink_files([(string)$im]);
        $notice = 'مورد حذف شد.';
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
    json_response(['ok' => true, 'message' => $notice, 'csrf' => csrf_token(), 'saved_id' => $action === 'project_save' ? $id : null, 'data' => admin_data()]);
} catch (PDOException $ex) {
    // PDOException زیرکلاس RuntimeException است؛ متن خطای SQL نباید به مرورگر برگردد.
    error_log('[admin.php] DB error: ' . $ex->getMessage());
    json_response(['ok' => false, 'message' => 'خطای پایگاه داده؛ عملیات انجام نشد. جزئیات در لاگ سرور ثبت شد.'], 500);
} catch (RuntimeException $ex) {
    json_response(['ok' => false, 'message' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    error_log('[admin.php] ' . get_class($ex) . ': ' . $ex->getMessage());
    json_response(['ok' => false, 'message' => 'خطای داخلی سرور؛ عملیات انجام نشد.'], 500);
}
