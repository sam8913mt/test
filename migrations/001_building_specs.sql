-- مهاجرت دستی «مشخصات ساختمان» (اختیاری)
-- به‌صورت پیش‌فرض این تغییرات هنگام اولین درخواست به هر فایل api/*.php خودکار انجام می‌شود
-- (تابع db() در includes/functions.php، داخل یک تراکنش و فقط برای ستون‌های ناموجود).
-- این فایل فقط برای اجرای دستی با sqlite3 یا ابزارهایی مثل DB Browser for SQLite است.
-- هر دستور را فقط یک بار اجرا کنید؛ اگر ستون از قبل وجود داشته باشد SQLite خطای «duplicate column name» می‌دهد.
-- قبل از اجرا حتماً از data/site.sqlite نسخه پشتیبان بگیرید.
BEGIN;
ALTER TABLE projects ADD COLUMN project_type TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN developer TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN architect TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN structure_type TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN facade TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN start_year INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN delivery_year INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN progress INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN province TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN city TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN district TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN address TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN postal_code TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN latitude REAL DEFAULT NULL;
ALTER TABLE projects ADD COLUMN longitude REAL DEFAULT NULL;
ALTER TABLE projects ADD COLUMN underground_floors INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN units_count INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN units_per_floor INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN land_area REAL DEFAULT NULL;
ALTER TABLE projects ADD COLUMN built_area REAL DEFAULT NULL;
ALTER TABLE projects ADD COLUMN unit_area_min REAL DEFAULT NULL;
ALTER TABLE projects ADD COLUMN unit_area_max REAL DEFAULT NULL;
ALTER TABLE projects ADD COLUMN parking_spaces INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN parking_type TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN elevators INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN storage_units INTEGER DEFAULT NULL;
ALTER TABLE projects ADD COLUMN heating_system TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN cooling_system TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN water_supply TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN power_supply TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN gas_supply TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN facilities TEXT DEFAULT '[]';
ALTER TABLE projects ADD COLUMN technical_notes TEXT DEFAULT '';
ALTER TABLE projects ADD COLUMN updated_at TEXT DEFAULT '';
CREATE INDEX IF NOT EXISTS idx_projects_slug ON projects(slug);
COMMIT;
