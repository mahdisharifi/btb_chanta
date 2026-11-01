# بازبینی طرح امتیازدهی — ۲۸ اکتبر ۲۰۲۶

## نتیجه و محدوده

جدول، مدل، فرم یا تست امتیازدهی در جست‌وجوی مخزن پیدا نشد؛ بررسی درباره پیشنهاد پیوست و نقاط اتصال آن به پروژه است. کد برنامه و داده‌ها تغییر نکردند. تفکیک امتیاز محصول از تأمین‌کننده، امتیاز صحیح ۱ تا ۵ و یک رکورد برای هر کاربر/هدف مناسب است، اما قراردادهای زیر پیش از اجرا باید تکمیل شوند.

سه عامل مستقل `domain_architecture`، `data_review` و `ui_api_review` بازبینی کردند. تخصص‌ها با پرسش و نتیجه جدا به این سه عامل ارجاع شدند؛ برای هر عنوان، عامل جدا ساخته نشد. یافته‌های دامنه با بازبین‌های داده و UI/API هماهنگ و QA پس از دریافت یافته‌های مشترک انجام شد.

## یافته‌های قابل اقدام

| شدت | محل | سناریو | اصلاح پیشنهادی |
| --- | --- | --- | --- |
| زیاد | composer.json، composer.lock، app/Models/User.php:18، app/Http/Controllers/Api/V1/AuthController.php:30 و :57 | مسیر API به Sanctum و createToken تکیه دارد، ولی dependency واقعی و HasApiTokens دیده نشد. | فرض آماده‌بودن Sanctum حذف شود؛ پیش از عرضه API زیرساخت احراز هویت تکمیل و اجرایی آزمایش شود. |
| زیاد | app/Http/Controllers/Web/ProductController.php:454؛ بند Cache پیوست | پاسخ public/max-age=1800 است؛ بعد از تغییر امتیاز، Cache::forget نسخه مرورگر/واسطه را باطل نمی‌کند. | قرارداد تازگی HTTP تعریف شود؛ امتیاز شخصی private/no-store باشد؛ HTML عمومی و JSON-LD هم‌نسخه باشند. invalidation برنامه بعد commit لازم است ولی کافی نیست. |
| زیاد | بندهای Soft Delete، upsert و مدیریت پیوست | مدیر مخفی می‌کند؛ کاربر DELETE سپس PUT می‌زند و restore ممکن است visibility را بازنشانی کند. | moderation مستقل از حذف مالک حفظ شود؛ عملیات مالک hidden_* را تغییر ندهد؛ PUT/hide هم‌زمان نیز تست شود. |
| زیاد | resources/views/pages/products/show.blade.php:33 و :36 | raw JSON با JSON_UNESCAPED_SLASHES در script چاپ می‌شود؛ متن schema دارای پایان تگ می‌تواند از script خارج شود. این یافته پیش‌موجود و تحلیلی است. | encoding امن JSON داخل HTML، مانند flags خانواده JSON_HEX، بررسی و تست شود؛ مسیر سوءاستفاده مرورگری هنوز آزمایش نشده است. |
| متوسط | بند حذف مدیریتی و بازیابی همان رکورد | deleted_at حذف مالک و مدیر را تفکیک نمی‌کند؛ بازیابی مجاز روشن نیست. | عامل/دلیل حذف و جدول انتقال وضعیت تعریف شود؛ حذف مالک، مخفی مدیر و حذف مدیر مستقل باشند. |
| متوسط | bootstrap/app.php:50؛ app/Http/Middleware/ConcealForbiddenResponses.php:15 | 403 به404 تبدیل می‌شود؛ قرارداد API پیشنهادی با رفتار فعلی ناسازگار است. | قرارداد منع دسترسی با سیاست فعلی هماهنگ و تست شود؛ تغییر سراسری بدون بررسی مصرف‌کننده‌ها انجام نشود. |
| متوسط | routes/auth.php:84؛ app/Helpers/AuthHelper.php:13؛ app/Livewire/Pages/Auth/VerifyMobile.php:92 | مهمان پس ورود یا OTP به مقصد نقش هدایت می‌شود؛ بازگشت به هدف امتیاز تضمین نیست. | intended داخلی امن در هر دو جریان ذخیره و مصرف شود؛ امتیاز پس ورود خودکار ارسال نشود. |
| متوسط | app/Livewire/Web/SupplierProducts.php:87 و :129 | پس از mount هدف غیرفعال یا مالکیت تغییر می‌کند؛ snapshot قبلی معتبر نیست. | هر action کاربر و هدف را دوباره معتبر و Policy همان عملیات را اجرا کند؛ شناسه کاربر از auth گرفته شود. |
| متوسط | بند مالکیت و مجوز حذف پیوست | کاربر بعد ثبت، مالک هدف یا عرضه‌کننده آن می‌شود؛ Policy مشترک ممکن است حذف امتیاز قبلی را نیز ببندد. | مجوز حذف از ثبت جدا؛ تکلیف pivot غیرفعال/حذف‌شده و امتیاز موجود پس تغییر مالکیت تعیین شود. |
| متوسط | بند مدیریت؛ app/Services/ProductCompletionAuditService.php:14 | hidden_* فقط آخرین رویداد را نگه می‌دارد؛ hide/restore تاریخچه را از بین می‌برد. | audit افزایشی actor/target/action/قبل/بعد/دلیل/زمان در همان transaction؛ سرویس اختصاصی تکمیل محصول بدون بررسی بازاستفاده نشود. |
| متوسط | بند FK؛ database/migrations/0001_01_01_000000_create_users_table.php:36 و :45؛ app/Models/User.php | schema ستون is_active و deleted_at دارد، ولی مدل SoftDeletes ندارد؛ FK حذف مدیر نیز ممکن است تاریخچه را از بین ببرد. | فعال و حذف‌نشده بودن صریح بررسی شود؛ سیاست حذف هر FK و حفظ audit مشخص، hidden_by nullable/SET NULL بررسی شود. |
| متوسط | resources/views/livewire/web/supplier-products.blade.php:507؛ بند فرم | loading سراسری می‌تواند با انتخاب ستاره کل صفحه را بپوشاند؛ انتخاب موقت/ذخیره‌شده مبهم است. | child component یا wire:target محدود؛ state انتخاب، ذخیره، لغو، خطا و moderation روشن باشد. |

## نقش‌های واقعاً ارجاع‌شده و نتیجه مستقل

| نقش | عامل | پرسش و نتیجه |
| --- | --- | --- |
| مالک محصول/دامنه | domain_architecture | چه کسی مجاز است؟ همه ۱۰ نقش enum واقعی، شامل regular_supplier، پوشش داده شوند؛ شرط تأیید هویت، تأمین‌کننده تأییدنشده و تغییر مالکیت تصمیم محصول می‌خواهند. |
| معماری | domain_architecture | قواعد کجا مشترک‌اند؟ سرویس مشترک Web/API برای eligibility، aggregate و moderation؛ بدون کپی منطق. |
| Laravel | domain_architecture | چرخه درخواست سازگار است؟ محصول عمومی is_active و status ACTIVE؛ تأمین‌کننده فعلی فقط is_active؛ Policy هر عملیات و conceal خطا رعایت شوند. |
| امنیت | domain_architecture | احراز هویت و کنترل‌ها دور زده می‌شوند؟ Sanctum، moderation، JSON-LD، حساب حذف‌شده و مالکیت نیازمند اقدامات جدول بالا؛ whitelist و rate limit در تمام ورودی‌ها. |
| پایگاه داده/migration | data_review | یکتایی و lifecycle چیست؟ دو جدول مستقل با FK واقعی؛ unique(target,user) بدون deleted_at برای بازیابی همان رکورد؛ FK و حذف مدیریتی تکمیل شوند. |
| طراحی query | data_review | چه رکوردی شمرده می‌شود؟ فقط visible و deleted_at IS NULL؛ AVG/COUNT مستقیم بدون join تکثیرکننده عرضه/قیمت و بدون بارگیری همه ratings. |
| MySQL | data_review | SQL و CHECK معتبرند؟ نسخه/sql_mode واقعی بررسی نشده؛ config/database.php:59 strict و InnoDB فقط تنظیم کد است؛ CHECK جای validation نیست. |
| ایندکس | data_review | index شواهد دارد؟ unique(target,user)؛ candidate محاسبه (target,is_visible,deleted_at[,score]) با EXPLAIN سنجیده شود؛ index «امتیازهای من» تا فاز مربوط عقب بیفتد. |
| کارایی | data_review | N+1 و حجم کنترل می‌شوند؟ ابتدا فقط جزئیات و aggregate؛ نیاز ستون خلاصه یا Redis ثابت نشده است. |
| Cache | data_review | تمام تغییرها پوشش دارند؟ مسئولیت invalidation صریح سرویس بعد commit؛ صرف observer کافی نیست چون upsert لزوماً event مدل ندارد؛ HTTP cache جدا بررسی شود. |
| هاست/استقرار | data_review | انتشار چگونه است؟ ابتدا schema و قیود، سپس مصرف‌کننده و فعال‌سازی؛ cache tags/Redis اجباری نشود؛ rollback کد داده امتیاز را حذف نکند. |
| فرانت‌اند | ui_api_review | state و خطا روشن‌اند؟ انتخاب موقت و ذخیره‌شده جدا؛ جلوگیری ارسال تکراری و حفظ انتخاب هنگام خطا. |
| Livewire | ui_api_review | snapshot قدیمی مجاز است؟ authorization و وضعیت هدف در هر action دوباره بررسی شود؛ loading محدود باشد. |
| UI/UX | ui_api_review | موضوع امتیاز چیست؟ محصول و عملکرد تأمین‌کننده جدا؛ بدون امتیاز با متن، نه صفر از۵؛ وضعیت ذخیره/مخفی و تأیید حذف مشخص. |
| موبایل/رسپانسیو | ui_api_review | فرم در عرض محدود جا می‌شود؟ فرم هنوز ساخته نشده؛ آزمون 320/375/768/1024 و zoom200% و متن بلند لازم؛ پنج touch target حداقل44×44. |
| دسترس‌پذیری | ui_api_review | صفحه‌کلید/فناوری کمکی کار می‌کند؟ radio group با legend/checked، برچسب هر مقدار، جهت کلید RTL، focus نمایان، aria-live و اتصال خطا لازم. |
| برند/فارسی | ui_api_review | امتیاز با تأیید اشتباه می‌شود؟ UI فارسی یک اعشار، JSON عددی؛ عنوان «امتیاز کاربران»؛ بدون برچسب خریدار تأییدشده. |
| API/یکپارچگی | ui_api_review | قرارداد و زمان عرضه روشن‌اند؟ زیرساخت auth و خطا تکمیل شود؛ حضور API بین فاز۱ و۲ متناقض است؛ رفتار average/count صفر مستند شود. |
| پرداخت/مالی | domain_architecture | مبلغ یا تسویه اثر می‌گیرد؟ خیر؛ تغییر gateway لازم نیست؛ نقش مالی مانند دیگر نقش‌ها در ماتریس مجوز باشد. |
| لاگینگ/مانیتورینگ | domain_architecture | عملیات قابل ردیابی است؟ audit جدا از log؛ نرخ429، ثبت پیاپی و moderation پایش؛ token و اطلاعات شخصی غیرضروری log نشوند. |
| صف/Job | domain_architecture | صحت به worker نیاز دارد؟ خیر؛ ثبت و نتیجه معتبر هم‌زمان با درخواست؛ worker/Redis جدید ضروری نیست. |
| فایل/ذخیره‌سازی | domain_architecture | فایل جدید داریم؟ خیر؛ تصویر/فایل نظر خارج MVP؛ تغییر storage لازم نیست. |
| SEO | domain_architecture | عدد عمومی قابل مشاهده است؟ خلاصه عمومی SSR و فقط امتیاز شخصی lazy/private؛ تازگی HTML/JSON-LD مشترک. |
| اسکیما | domain_architecture | داده با UI برابر است؟ ProductController.php:1991 از reviews_count/reviewCount استفاده می‌کند؛ ratingCount و حدود۱و۵ و مقدار مشترک UI لازم؛ count صفر یعنی حذف aggregateRating. |
| جستجوی گوگل | domain_architecture | URL یا rich result تضمین دارد؟ URL/redirect جدید لازم نیست؛ canonical حفظ شود؛ eligibility نوع Organization و ثالث/self-serving نیازمند راستی‌آزمایی جاری است؛ ستاره تضمین نشود. |
| AI Overview | domain_architecture | اطلاعات قابل استخراج‌اند؟ عدد معتبر و محتوای قابل مشاهده و entity ثابت؛ افزودن ابزار اختصاصی AI بدون ضرورت توصیه نمی‌شود. |
| جستجو/نمایش AI | domain_architecture | منابع سازگارند؟ API/UI/JSON-LD همسان؛ citation یا رتبه تضمین نشود؛ آزمون سرویس انجام نشده. |
| QA/رگرسیون | ui_api_review | معیارها نقایص را آشکار می‌کنند؟ ماتریس زیر لازم؛ بازبینی عامل معادل تست اجرایی نیست. |

## تصمیم‌ها و آزمون‌های لازم

پیش از اجرا: شرط ایمیل/موبایل تأییدشده؛ صلاحیت تأمین‌کننده تأییدنشده؛ زمان API؛ مرز مالکیت pivot؛ رفتار پس تغییر مالکیت؛ حذف مالک/مدیر و بازیابی؛ قرارداد تازگی HTTP تعیین شوند. برای count صفر، قراردادی صریح مانند average:null/count:0 و متن بدون امتیاز مناسب است.

- همه نقش‌های enum روی هر دو هدف؛ مهمان، حساب غیرفعال/حذف‌شده و تأیید هویت؛ خودمالک مستقیم/pivot و هدف غیرفعال.
- ورودی۰،۶،اعشاری،غیرعددی،null؛ عدم پذیرش user_id/is_visible/hidden_* از مالک.
- PUT دوم بدون رکورد جدید؛ حذف مالک و restore همان id؛ حذف مدیر بدون احیای غیرمجاز.
- MySQL با اتصال‌های مستقل: PUT/PUT، PUT/DELETE، PUT/hide؛ یکتایی، نتیجه قطعی و حفظ moderation.
- hide/restore تکراری با audit کامل و AVG/COUNT صحیح؛ rollback بدون اثر جانبی ناقص.
- cache گرم برنامه و HTTP سپس هر mutation؛ تطابق مهمان/کاربر دیگر/API/JSON-LD و عدم نشت امتیاز شخصی.
- snapshot قدیمی Livewire پس تغییر وضعیت/مالکیت؛ login و OTP با بازگشت امن؛ خطاهای قرارداد نهایی.
- مرورگر واقعی: موبایل/تبلت، zoom، keyboard، RTL، touch، Screen Reader، loading و خطای شبکه.
- EXPLAIN و نسخه/sql_mode واقعی MySQL؛ migration و rollback در محیط مشابه استقرار.

## اعتبارسنجی و محدودیت‌ها

انجام شد: خواندن پیوست و کد، migrationها، مدل‌ها، routeها، auth، view/componentها، تنظیمات و تست‌ها؛ بازبینی سه عامل با نقش‌های جدا؛ تصحیح ادعای اولیه درباره نبود users.is_active با migration. وضعیت دیتابیس واقعی تأیید نشده است.

انجام نشد: تغییر کد برنامه، تست اجرایی، API واقعی، مرورگر/موبایل/Screen Reader، اجرای payload، migration، اتصال DB، EXPLAIN، concurrency، HTTP/CDN واقعی، Rich Results/Search Console و AI. تلاش دسترسی وب متن قابل استناد نداد؛ تأیید آنلاین سیاست‌های جاری گوگل ادعا نمی‌شود.

phpunit.xml از MySQL آزمایشی و CACHE_STORE=array استفاده می‌کند؛ اجرای معمول آن به‌تنهایی cache پایدار، HTTP/CDN یا هم‌زمانی چنداتصالی را ثابت نمی‌کند. بررسی موبایل/دسترس‌پذیری فعلاً بررسی نیازمندی است. همه یافته‌ها باز هستند؛ اصلاح یا پذیرش ریسک انجام نشده. فایل‌های نامرتبط قبلی دست‌نخورده‌اند.
