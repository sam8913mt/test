import re,sys,html,json,os
import os
root=os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)),'..'))+'/'
sys.path.insert(0,os.path.dirname(os.path.abspath(__file__))); import spec
ok=True
def check(name,cond,extra=''):
    global ok; print(('PASS ' if cond else 'FAIL ')+name+(' '+str(extra) if extra and not cond else '')); ok&=bool(cond)

# 1) PHP bracket/quote balance (tokenizer is approximate, NOT a real linter)
def php_balance(path):
    s=open(path,encoding='utf-8').read(); i=0; st=[]; pairs={')':'(',']':'[','}':'{'}
    n=len(s); line=1
    while i<n:
        c=s[i]
        if c=='\n': line+=1
        if s.startswith('//',i) or c=='#' and not s.startswith('#[',i):
            j=s.find('\n',i); i=n if j<0 else j; continue
        if s.startswith('/*',i):
            j=s.find('*/',i); line+=s[i:j].count('\n'); i=j+2; continue
        if c in "'\"":
            q=c; i+=1
            while i<n and s[i]!=q:
                if s[i]=='\\': i+=1
                if i<n and s[i]=='\n': line+=1
                i+=1
            i+=1; continue
        if c in '([{': st.append((c,line))
        elif c in ')]}':
            if not st or st[-1][0]!=pairs[c]: return f'mismatch {c} at line {line}'
            st.pop()
        i+=1
    return 'ok' if not st else f'unclosed {st[-3:]}'
for f in ['includes/functions.php','api/admin.php','api/building.php','api/site.php','api/contact.php','sitemap.php','config.php']:
    r=php_balance(root+f); check(f'PHP bracket balance {f}',r=='ok',r)

# 2) every spec field has a form control and vice versa
adm=open(root+'admin.html',encoding='utf-8').read()
php=open(root+'includes/functions.php',encoding='utf-8').read()
php_keys=re.findall(r"^  '([a-z_]+)'=>\['type'",php,re.M)
form=adm[adm.index('id="project-form"'):adm.index('id="settings-form"')]
names=set(re.findall(r'name="([a-z_]+)(?:\[\])?"',form))-{'id','image_order','remove_images'}
names={n for n in names if not n.startswith('replace_images')}
check('spec keys == php building_spec keys',[f['key'] for f in spec.FIELDS]==php_keys,(len(php_keys)))
check('every spec key has an input in admin.html',set(php_keys)<=names,set(php_keys)-names)
check('no unknown inputs in building form',names-{'images'}<=set(php_keys),names-set(php_keys)-{'images'})
# 3) constants identical in PHP, HTML options, site.php
for const,vals in spec.CONSTS.items():
    m=re.search(r"const "+const+r" = \[(.*?)\];",php); got=re.findall(r"'([^']*)'",m.group(1))
    check(f'PHP const {const} matches spec',got==vals)
for v in spec.STATUSES: check('status option in HTML: '+v,f'<option value="{v}">' in adm)
site=open(root+'api/site.php',encoding='utf-8').read()
m=re.search(r"\$validStatuses = \[(.*?)\];",site); check('site.php status filter list == spec',re.findall(r"'([^']*)'",m.group(1))==spec.STATUSES)
check('ZWNJ preserved in "تکمیل‌شده"','\u200c' in php and 'تکمیل\u200cشده' in php)
# 4) php literals contain no stray syntax issues
check('clean_text keeps ZWNJ (uses \\p{Cc}, not \\p{C})',r"\P{Cc}" in php and r"\P{C}\n" not in php)
# 5) migration columns correspond to fields
cols=re.findall(r"^  '([a-z_]+)'=>\"(.*?)\",$",php[php.index('function building_new_columns'):],re.M)
check('building_new_columns count',len(cols)==36,len(cols))
for js in ['assets/admin.js','assets/detail.js','assets/main.js']:
    r=os.system(f'node --check {root}{js}'); check('node --check '+js,r==0)
print('ALL OK' if ok else 'SOME FAILED'); sys.exit(0 if ok else 1)
