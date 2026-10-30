# Single source of truth for building fields (generates PHP spec + admin HTML form)
STATUSES=['در حال ساخت','تکمیل‌شده','در مرحله طراحی','پیش‌فروش']
TYPES=['مسکونی','تجاری','اداری','مسکونی و تجاری','سایر']
STRUCT=['اسکلت فلزی','اسکلت بتنی','سازه مختلط','بنایی','سایر']
PARKING=['زیرزمینی','طبقه‌ای','روباز','ندارد']
HEATING=['موتورخانه مرکزی','پکیج هر واحد','گرمایش از کف','بخاری گازی','سایر']
COOLING=['چیلر و فن‌کویل','کولر گازی (اسپلیت)','کولر آبی','سایر']
ELEC=['تک‌فاز','سه‌فاز']
FACILITIES=['لابی و پذیرش','نگهبانی ۲۴ ساعته','دوربین مداربسته','سالن ورزش','استخر','روف‌گاردن','سالن اجتماعات','فضای سبز و حیاط','پارکینگ مهمان','فضای بازی کودکان','ژنراتور اضطراری','آسانسور باربر','سیستم اطفای حریق','ایفون تصویری']
UTILITIES=['برق','آب شهری','گاز شهری','فاضلاب شهری','تلفن','اینترنت پرسرعت / فیبر نوری','منبع آب ذخیره','انشعاب جداگانه برای هر واحد']
CONSTS={'BUILDING_STATUSES':STATUSES,'BUILDING_TYPES':TYPES,'BUILDING_STRUCTURES':STRUCT,'BUILDING_PARKING_TYPES':PARKING,'BUILDING_HEATING':HEATING,'BUILDING_COOLING':COOLING,'BUILDING_ELECTRICITY':ELEC,'BUILDING_FACILITIES':FACILITIES,'BUILDING_UTILITIES':UTILITIES}
OPT={'status':'BUILDING_STATUSES','project_type':'BUILDING_TYPES','structure_type':'BUILDING_STRUCTURES','parking_type':'BUILDING_PARKING_TYPES','heating_system':'BUILDING_HEATING','cooling_system':'BUILDING_COOLING','electricity_type':'BUILDING_ELECTRICITY','facilities':'BUILDING_FACILITIES','utilities':'BUILDING_UTILITIES'}
def F(key,label,type,section,**kw):
    d=dict(key=key,label=label,type=type,section=section); d.update(kw); return d
S=['general','address','technical','facilities','utilities','extra']
SECTION_TITLES={'general':('۱','اطلاعات کلی'),'address':('۲','آدرس و موقعیت'),'technical':('۳','مشخصات فنی'),'facilities':('۴','پارکینگ، آسانسور و امکانات'),'utilities':('۵','تأسیسات و انشعابات'),'gallery':('۶','تصاویر و گالری'),'extra':('۷','توضیحات تکمیلی و انتشار')}
FIELDS=[
 F('title','نام ساختمان','text','general',required=True,max=150,ph='مثلاً برج مسکونی الماس'),
 F('slug','شناسه آدرس (Slug)','slug','general',max=160,ph='اختیاری؛ خالی باشد خودکار ساخته می‌شود'),
 F('project_type','نوع کاربری','enum','general',required=True),
 F('status','وضعیت پروژه','enum','general',required=True,noblank=True),
 F('builder','سازنده / مجری','text','general',max=150),
 F('architect','طراح / معمار','text','general',max=150),
 F('start_year','سال شروع ساخت (شمسی)','int','general',lo=1300,hi=1500,ph='مثلاً ۱۴۰۳'),
 F('completion_year','سال تکمیل یا تحویل (شمسی)','int','general',lo=1300,hi=1500,ph='مثلاً ۱۴۰۶'),
 F('delivery_date','تاریخ تحویل (توضیح متنی)','text','general',max=60,ph='مثلاً پاییز ۱۴۰۶'),
 F('progress_percent','درصد پیشرفت فیزیکی','int','general',lo=0,hi=100,ph='۰ تا ۱۰۰'),
 F('permit_number','شماره پروانه ساخت (اختیاری)','text','general',max=100,hint='فقط در صورت صحت وارد شود؛ در صفحه عمومی نمایش داده می‌شود.'),
 F('short_description','خلاصه معرفی (برای کارت‌ها)','textarea','general',max=300,rows=2,full=True,hint='حداکثر ۳۰۰ نویسه؛ در فهرست ساختمان‌ها نمایش داده می‌شود.'),
 F('description','توضیحات کامل','textarea','general',max=5000,rows=5,full=True),
 F('province','استان','text','address',max=80,ph='مثلاً تهران'),
 F('city','شهر','text','address',required=True,max=80,ph='مثلاً پردیس'),
 F('location','محدوده / فاز / محله','text','address',max=150,ph='پردیس، فاز ...'),
 F('postal_code','کد پستی (۱۰ رقم)','digits','address',digits=10,ph='۱۰ رقم'),
 F('address','نشانی کامل','text','address',max=300,full=True),
 F('latitude','عرض جغرافیایی','coord','address',lo=-90,hi=90,ph='مثلاً 35.7448',hint='اختیاری؛ همراه با طول جغرافیایی وارد شود.'),
 F('longitude','طول جغرافیایی','coord','address',lo=-180,hi=180,ph='مثلاً 51.7551'),
 F('floors','تعداد طبقات (روی زمین)','int','technical',lo=1,hi=200,legacy=True,ph='مثلاً ۱۲'),
 F('underground_floors','تعداد طبقات زیرزمین','int','technical',lo=0,hi=20,ph='مثلاً ۲'),
 F('units_count','تعداد کل واحدها','int','technical',lo=1,hi=5000,ph='مثلاً ۴۸'),
 F('units_per_floor','تعداد واحد در هر طبقه','int','technical',lo=1,hi=50),
 F('area','زیربنای کل (مترمربع)','decimal','technical',lo=1,hi=10000000,legacy=True,ph='مثلاً ۱۸۰۰'),
 F('land_area','مساحت زمین (مترمربع)','decimal','technical',lo=1,hi=10000000),
 F('unit_area_min','کمترین متراژ واحد (مترمربع)','decimal','technical',lo=1,hi=100000),
 F('unit_area_max','بیشترین متراژ واحد (مترمربع)','decimal','technical',lo=1,hi=100000),
 F('structure_type','نوع سازه','enum','technical'),
 F('facade','نمای ساختمان','text','technical',max=150,ph='مثلاً سنگ و شیشه'),
 F('parking_spaces','تعداد پارکینگ','int','facilities',lo=0,hi=5000),
 F('parking_type','نوع پارکینگ','enum','facilities'),
 F('elevators','تعداد آسانسور','int','facilities',lo=0,hi=50),
 F('storage_rooms','تعداد انباری','int','facilities',lo=0,hi=5000),
 F('facilities','امکانات رفاهی و ایمنی','list','facilities',legend='امکانات رفاهی و ایمنی'),
 F('amenities','سایر امکانات (توضیح آزاد)','textarea','facilities',max=2000,rows=3,full=True,ph='پارکینگ، آسانسور، انباری...'),
 F('utilities','انشعابات و خدمات','list','utilities',legend='انشعابات و خدمات موجود'),
 F('heating_system','سیستم گرمایش','enum','utilities'),
 F('cooling_system','سیستم سرمایش','enum','utilities'),
 F('electricity_type','نوع برق','enum','utilities'),
 F('utilities_notes','توضیحات تأسیسات','textarea','utilities',max=1000,rows=3,full=True),
 F('notes','سایر توضیحات و نکات','textarea','extra',max=3000,rows=3,full=True),
 F('featured','نمایش به‌عنوان پروژه شاخص','bool','extra',default=False),
 F('is_published','منتشر شود (در سایت عمومی دیده شود)','bool','extra',default=True),
]
BASE_COLS={'title','slug','location','status','description','floors','area','amenities','featured'}
