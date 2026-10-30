import sqlite3,re,json,sys,os
sys.path.insert(0,os.path.dirname(os.path.abspath(__file__))); import spec
import os
root=os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)),'..'))+'/'
php=open(root+'includes/functions.php',encoding='utf-8').read()
cols=dict(re.findall(r"^  '([a-z_]+)'=>\"(.*?)\",$",php[php.index('function building_new_columns'):],re.M))
legacy={'images_alt':"TEXT DEFAULT '{}'",'slug':"TEXT DEFAULT ''",'images':"TEXT DEFAULT '[]'",'floors':"TEXT DEFAULT ''",'area':"TEXT DEFAULT ''",'amenities':"TEXT DEFAULT ''",'featured':"INTEGER DEFAULT 0"}
# Original-schema DB: only the very first CREATE (without later migrations), as an old install would have
old_create="""CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, location TEXT DEFAULT '', status TEXT DEFAULT 'در حال ساخت', description TEXT DEFAULT '', image TEXT DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)"""
db=sqlite3.connect(':memory:'); db.execute(old_create)
db.execute("INSERT INTO projects(title,location,status,description,image) VALUES('برج قدیمی','پردیس فاز ۳','تکمیل‌شده','توضیح قدیمی','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg')")
def migrate(db):
    names=[r[1] for r in db.execute('PRAGMA table_info(projects)')]
    for col,d in {**legacy,**cols}.items():
        if col not in names: db.execute(f'ALTER TABLE projects ADD COLUMN {col} {d}')
migrate(db); migrate(db)   # second run must be a no-op
names=[r[1] for r in db.execute('PRAGMA table_info(projects)')]
res=[]
def chk(n,c): res.append(c); print(('PASS ' if c else 'FAIL ')+n)
chk('all spec columns exist after migration',all(f['key'] in names for f in spec.FIELDS))
chk('no duplicate columns after running twice',len(names)==len(set(names)))
r=db.execute("SELECT title,location,status,description,image,is_published,featured,facilities,utilities,city FROM projects").fetchone()
chk('legacy row data preserved',r[:5]==('برج قدیمی','پردیس فاز ۳','تکمیل‌شده','توضیح قدیمی','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg'))
chk('legacy row is published by default (is_published=1)',r[5]==1)
chk('legacy row defaults: featured=0, lists=[] , city=\'\'',r[6]==0 and r[7]=='[]' and r[8]=='[]' and r[9]=='')
# Simulate the generated UPDATE/INSERT built from $row keys (same construction as api/admin.php)
row={f['key']:('1' if f['type'] not in('bool','list') else (1 if f['type']=='bool' else '[]')) for f in spec.FIELDS if f['key']!='slug'}
row['slug']='x'; row['image']='';row['images']='[]';row['updated_at']='2026-10-29 12:00:00'
setc=','.join(f'{c}=?' for c in row)
db.execute(f'UPDATE projects SET {setc} WHERE id=?',list(row.values())+[1]); chk('generated UPDATE statement executes',True)
db.execute(f"INSERT INTO projects({','.join(row)}) VALUES({','.join('?'*len(row))})",list(row.values())); chk('generated INSERT statement executes',True)
chk('rows persisted (2 rows)',db.execute('select count(*) from projects').fetchone()[0]==2)
sys.exit(0 if all(res) else 1)
