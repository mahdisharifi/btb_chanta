# بازبینی مستقل Cache و کارایی صفحهٔ دسته‌بندی‌ها

تاریخ: ۲۰۲۶-۱۰-۲۵. محدوده: `/categories`، مصرف‌کنندهٔ `CategoryController@index`، مدل‌های Category/Product، مسیرهای نوشتن دسته و محصول و assetهای مشترک صفحه. دو پرسش Cache و کارایی به‌صورت جداگانه بررسی شدند. بررسی فقط‌خواندنی بود؛ داده، کش و سورس برنامه تغییر نکردند.

## پرسش اول: آیا کش پس از تغییر داده معتبر می‌ماند؟

### C1 — P1 / زیاد: درخت کش‌شده تا یک ساعت دستهٔ غیرفعال، حذف‌شده یا URL قدیمی را نشان می‌دهد

- محل اصلی: `app/Http/Controllers/Web/CategoryController.php:19`؛ کلید ثابت `categories.index.tree` با TTL برابر ۳۶۰۰ ثانیه، بدون invalidation در هیچ مسیر نوشتن مرتبط.
- مسیر فعال ایجاد/ویرایش: `routes/admin.php:60` و `:63` هر دو به `App\Livewire\Admin\Categories\Create` می‌روند. نوشتن مدل در `app/Livewire/Admin/Categories/Create.php:366` و `:369`، فیلدهای مؤثر شامل name/slug/parent_id/icon/sort_order/is_active در `:341` به بعد هستند. هیچ forget/نسخه‌گذاری پس از commit وجود ندارد.
- مسیر فعال حذف: `app/Livewire/Admin/Categories/Index.php:58` دسته را soft-delete می‌کند؛ کش عمومی دست‌نخورده می‌ماند.
- مسیرهای دیگر بررسی‌شده: `app/Livewire/Admin/Categories/Edit.php:141`؛ کنترلر MVC `app/Http/Controllers/Admin/CategoryController.php:114`، `:201`، `:237` و `:271`. این‌ها نیز invalidation ندارند؛ کنترلر و Edit قدیمی را با مسیر فعال Livewire یکی نگرفته‌ایم.
- ثبت observer: `app/Providers/AppServiceProvider.php:91` فقط SupplierObserver را ثبت می‌کند. Category/Product observer، event مدل، یا مصرف دیگری از `CACHE_KEY_TREE` در کد برنامه پیدا نشد. جست‌وجوی سراسری ارجاع مستقیم این کلید فقط اعلان و remember همین کنترلر را یافت.
- بازتولید پیشنهادی در محیط تست: یک بار GET `/categories` برای گرم‌شدن کش؛ سپس از ویرایش دستهٔ فعال، slug را تغییر دهید یا is_active را false کنید؛ دوباره صفحهٔ عمومی را بارگذاری کنید. درخت سریال‌شده هنوز نام، slug و عضویت قبلی را دارد. لینک قدیمی پس از تغییر slug با route binding مدل زنده ۴۰۴ می‌شود؛ پس از غیرفعال‌شدن، `CategoryController.php:72` صریحاً ۴۰۴ می‌دهد؛ حذف نیز دیگر bind نمی‌شود. ایجاد دسته/جابه‌جایی والد/تغییر ترتیب نیز تا انقضای کش منعکس نمی‌شود. این سناریو در DB واقعی اجرا نشد تا دادهٔ کاربر تغییر نکند؛ نتیجه از مسیر قطعی خواندن و نوشتن است.
- اثر: نمایش نتیجه‌ای که دیگر قابل پیمایش نیست، محتوای منقضی در JSON-LD و جست‌وجوی داخل صفحه، ناهماهنگی پنل مدیر و صفحهٔ عمومی.
- پیشنهاد: سرویس واحد برای invalidation درخت، و فراخوانی پس از commit برای saved/deleted/restored/forceDeleted دسته؛ مسیرهای bulk/query-builder باید صریحاً همان سرویس را فراخوانی کنند چون event مدل اجرا نمی‌کنند. پس از اصلاح N+1 و واقعی‌شدن snapshot شمارش، تغییرات مؤثر Product نیز باید همان کش را نامعتبر کنند. با رقابت rebuild هم‌زمان و commit، کش تازه نباید دوباره با نسل قبلی نوشته شود؛ نسخهٔ نسل یا هماهنگی rebuild/invalidation را در پیاده‌سازی لحاظ کنید. افزودن Redis برای رفع این مسئله لازم نیست.

### مرزبندی مهم دربارهٔ تغییر محصول

نبود invalidation در Product به‌تنهایی به معنی «شمارش محصول امروز قطعاً یک ساعت قدیمی می‌ماند» نیست. `Category.php:179` accessor مقدار ذخیره‌شدهٔ `total_products_count` را نمی‌خواند؛ با نبود `active_products_count` دوباره از DB می‌شمارد (`:185`). بنابراین درختِ عضویت ثابت، ایجاد/حذف/ویرایش وضعیت/جابه‌جایی محصول در حال حاضر غالباً در شمارش زنده دیده می‌شود؛ هزینهٔ آن N+1 است. پس از اصلاح این accessor/DTO، نگه‌داشتن snapshot بدون invalidation محصول به باگ شمارش قدیمی تبدیل خواهد شد.

ردیابی تولیدکننده‌های بررسی‌شده:

| عملیات | مسیر نمونهٔ واقعی | نتیجهٔ بررسی کش |
| --- | --- | --- |
| ایجاد/ویرایش محصول مدیر؛ category/status/is_active | `app/Livewire/Admin/Products/Create.php:764`، `:780`، `:782` | کلید درخت نامعتبر نمی‌شود؛ counts امروز accessor زنده دارند |
| فعال/غیرفعال و حذف محصول مدیر | `app/Livewire/Admin/Products/Index.php:108`، `:117` | همان وضعیت |
| تغییر وضعیت محصول | `app/Livewire/Admin/Product/Index.php:84` | همان وضعیت |
| ایجاد/ویرایش محصول تأمین‌کننده | `app/Livewire/Supplier/Products/Create.php:179`؛ `Edit.php:223` | همان وضعیت؛ ایجاد اولیه draft و غیرفعال است |
| مسیرهای MVC محصول مدیر | `app/Http/Controllers/Admin/ProductController.php:99`، `:220`، `:308`، `:319`، `:328`، `:349` | invalidation مرتبط ندارد |
| مسیرهای MVC محصول تأمین‌کننده | `app/Http/Controllers/Supplier/ProductController.php:112`، `:169`، `:180`، `:193`، `:196`، `:208` | invalidation مرتبط ندارد |
| restore دسته/محصول | SoftDeletes در هر دو مدل؛ جست‌وجوی restore در `app` | handler فعلی restore پیدا نشد؛ برنامه‌نویسی/عملیات آتی باید پوشش داده شود، گردش UI فعلی ادعا نمی‌شود |
| slug محصول | ورودی درخت شامل نام/slug دسته و شمارش است، نه slug محصول | به‌خودی‌خود این کش را تغییر نمی‌دهد؛ slug دسته حتماً مؤثر است |

کلید بدون وابستگی به کاربر است و query پایهٔ این صفحه نیز دادهٔ عمومی مشترک می‌خواند؛ اشکال مشخصی از نشت پاسخ شخصی میان نقش‌ها در این کلید دیده نشد. TTL یک‌ساعته، جایگزین invalidation پس از تغییر داده نیست. سناریوی rollback، هم‌زمانی و restore اجرایی آزمایش نشد.

## پرسش دوم: هزینهٔ query و assetها چقدر است؟

### P1 — زیاد، یافتهٔ مشترک با بازبین query: کش مانع N+1 شمارش نمی‌شود

- محل: `CategoryController.php:58`، `Category.php:179` تا `:203`؛ مصرف دوباره در `resources/views/pages/categories/index.blade.php:39`، `:43`، `:191` و `:222`.
- کنترلر `products_count` می‌گیرد و `total_products_count` را دستی مقداردهی می‌کند، اما accessor دنبال `active_products_count` است و مقدار دستی را هم نادیده می‌گیرد؛ به‌علاوه برای برگ‌های بدون children لودشده، descendants را دوباره query می‌کند.
- شاهد اجرایی به‌نقل از بازبین مستقل query تیم: فقط `$cached->sum('total_products_count')` برای ۳۳ دسته، ۶۶ SELECT اجرا کرده است. این عدد اندازه‌گیری این عامل نیست و دوباره اندازه‌گیری نشد؛ تعداد کل query صفحه نیز از این عدد نتیجه‌گیری نمی‌شود. رندر Blade چند بار accessor را صدا می‌زند و هزینهٔ اضافه دارد.
- بازتولید: کش از قبل گرم، query log فقط برای اجرای sum بالا فعال شود؛ تعداد SELECT مستقل از cache hit همچنان با دسته‌ها رشد می‌کند.
- اصلاح پیشنهادی: یک قرارداد واحد برای شمارش، مانند DTO با total ازپیش‌محاسبه‌شده، یا accessor سازگار با alias درست و استفاده از relations ازقبل‌لودشده؛ در رندر به accessor دارای query تکیه نشود. آنگاه invalidation دسته و محصول طبق C1 پیش‌نیاز صحت داده است. پس از اصلاح، آزمون با کش گرم و دادهٔ تازه و query count ثابت لازم است.

### assetها: اندازه‌گیری فایل محلی، بدون ادعای Core Web Vitals

فایل‌های واقعی build و manifest بررسی شدند:

| فایل | اندازهٔ خام روی دیسک |
| --- | ---: |
| `public/build/assets/app-CYLMqVnJ.js` | ۵۱٬۵۷۹ بایت |
| `public/build/assets/app-DI5ywwF4.css` | ۱۹۸٬۱۰۹ بایت |
| چهار فونت WOFF2 Regular/Medium/Bold/Black | مجموع ۱۷۰٬۰۸۴ بایت |

- دسته‌بندی‌ها تصویر raster رندر نمی‌کند؛ آیکون‌ها SVG داخلی Blade هستند. بنابراین برای همین صفحه، ادعای تصویر بزرگ/بدون lazy-load وارد نیست.
- فونت‌ها در `resources/css/app.css:2` تا `:35` دارای WOFF2 و `font-display: swap` هستند. Regular در `layouts/app.blade.php:29` preload می‌شود و فایل موجود با manifest هماهنگ است.
- Mermaid در `resources/js/app.js:42` با import پویا لود می‌شود؛ صرفِ بزرگ‌بودن مجموعهٔ chunkهای Mermaid در پوشهٔ build به معنی دانلود همهٔ آن‌ها در `/categories` نیست.
- صفحه Livewire component ندارد اما layout از `@livewireScripts` در `resources/views/layouts/app.blade.php:382` استفاده می‌کند؛ این اسکریپت Alpine لازم برای `x-data` صفحه را هم فراهم می‌کند، پس حذف سادهٔ آن پیشنهاد درستی نیست. جداسازی bundle فقط با حفظ Alpine و شواهد هزینهٔ اجرایی ارزش بررسی دارد.
- CSS مشترک حدود ۱۹۸KB خام است؛ بدون اندازهٔ انتقال فشرده، coverage و زمان style/layout آن را باگ بحرانی اعلام نمی‌کنیم.

محدودیت: این عامل Chrome را کنترل نکرد؛ Network، transferSize، compression/cache headers، LCP/CLS/INP، CPU throttle، Lighthouse و لود سرد/گرم مرورگر را اندازه‌گیری نکرد. بررسی بصری/موبایل به عوامل مستقل مربوط واگذار شده است. هیچ source edit، DB mutation یا cache clear برای این گزارش انجام نشد.
