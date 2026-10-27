# بازبینی مستقل استقرار و هاست اشتراکی

تاریخ: ۲۰۲۶-۱۰-۲۵. محدوده: نیازهای runtime و asset صفحهٔ `/categories` و مسیر show مرتبط. نتیجه: مانع قطعی اجرای صفحه در محیط محلی فعلی پیدا نشد؛ یک asset ارجاع‌شده موجود نیست. محیط هاست تولید، تنظیمات Apache/LiteSpeed و مجوزهای کاربر PHP روی آن بررسی اجرایی نشده‌اند.

## D1 — P2 / متوسط: تصویر معرفی صفحه در بستهٔ فعلی وجود ندارد

- محل: `app/Http/Controllers/Web/CategoryController.php:66` آدرس `asset('images/og-default.jpg')` را تولید می‌کند؛ `resources/views/layouts/app.blade.php:18` آن را در og:image قرار می‌دهد.
- شاهد اجرایی: بررسی `is_file(public_path('images/og-default.jpg'))` در bootstrap واقعی برنامه مقدار false برگرداند. فایل مذکور داخل build manifest هم نیست؛ این URL asset عمومی مستقیم است.
- بازتولید: بررسی وجود `public/images/og-default.jpg` در بستهٔ تحویل؛ برای تست HTTP می‌توان همان URL تولیدی را درخواست کرد. در این بازبینی درخواست HTTP آن تصویر انجام نشد.
- اثر: بستهٔ فعلی تصویر معرفی معتبر ندارد؛ پیش‌نمایش اشتراک‌گذاری صفحه تصویر موردانتظار را دریافت نمی‌کند. اجرای HTML اصلی صفحه با این مورد متوقف نمی‌شود.
- پیشنهاد: فایل واقعی تصویر برند در مسیر اعلام‌شده همراه بسته قرار گیرد، یا آدرس به asset واقعاً موجود و مناسب تغییر کند؛ سپس GET تصویر و متاتگ نهایی بررسی شود. این یافته با نقش SEO/برند مشترک است و نباید چند ایراد مستقل شمرده شود.

## سازگاری واقعی runtime

| موضوع | شاهد و نتیجه |
| --- | --- |
| PHP و وابستگی‌ها | `composer.json:9` نیاز PHP ^8.2 دارد. `composer check-platform-reqs --no-dev` واقعاً اجرا شد و همهٔ نیازهای نصب موجود را موفق گزارش کرد؛ PHP محلی 8.2.26 و 64-bit است. این نتیجه تأیید PHP هاست نیست. |
| نسخهٔ Laravel | bootstrap واقعی `app()->version()` برابر 12.51.0؛ constraint در `composer.json:14` برابر ^12.0 است. |
| درخت recursive | بستهٔ adjacency-list نصب‌شده 1.25.2 و CTE نصب‌شده 1.12.4 است؛ `CategoryController.php:83` از descendants استفاده می‌کند. روی اتصال فعلی، `SELECT VERSION()` مقدار `10.4.32-MariaDB` داد و اجرای واقعی descendants برای یک root فعال، ۲۸ id برگرداند. بنابراین incompatibility CTE روی همین سرور مشاهده نشد. |
| PHP پیشنهادی هاست در مخزن | `public/.htaccess:142` از ea-php82 استفاده می‌کند و با نیاز نصب فعلی سازگار است؛ نسخهٔ واقعی web-SAPI هاست مستقل از CLI باید همان محیط بررسی شود. |
| صف و cron | مسیر index/show مرورگر، برای پاسخ اصلی به job یا worker وابسته نیست؛ نبود Redis/worker را برای این صفحه مانع فرض نمی‌کنیم. |

## بستهٔ frontend و فایل‌ها

- `resources/views/layouts/app.blade.php:35` از Vite manifest استفاده می‌کند. manifest فعلی parse شد و وجود تمام entryهای `file` آن روی دیسک بررسی شد: **صفر فایل مفقود**. `public/hot` نیز وجود ندارد؛ این بسته به dev server محلی هدایت نمی‌شود.
- فونت preload در `resources/views/layouts/app.blade.php:29` اکنون با فایل موجود manifest هماهنگ است. فایل‌های WOFF2 چهار وزن نیز وجود دارند. نام hash در layout دستی است؛ در این build خرابی دیده نشد، بنابراین اشکال اجرایی فعلی اعلام نمی‌شود.
- `prepare-deploy.ps1:86` صریحاً اعلام می‌کند که frontend را build نمی‌کند و `public/build` باید در بسته باشد. برای بستهٔ فعلی build موجود و کامل است؛ عدم build خودکار در این اسکریپت به‌تنهایی blocker فعلی نیست. Node در runtime پاسخ PHP این صفحه مصرف نمی‌شود.
- در index دسته‌ها، آیکون‌های Blade/SVG مصرف می‌شوند و تصویر category از storage نمایش داده نمی‌شود؛ نبود symlink به‌خودی‌خود خرابی این صفحه را ثابت نمی‌کند. `public/storage` محلی وجود دارد اما `is_link` آن false است؛ این صرفاً مشاهده است، نه اثبات خرابی URLهای storage یا مجوز هاست.

## کش، writable path و وب‌سرور

- config مؤثر برنامه با bootstrap خوانده شد: cache store برابر database و session driver برابر database است. این مقادیر با template اسکریپت deploy که file پیشنهاد می‌کند یکی نیستند؛ هیچ‌کدام خودبه‌خود غلط نیست و ادعای تغییر تنظیمات داده نمی‌شود.
- `storage/framework/views`، `bootstrap/cache` و `storage/framework/cache/data` در محیط فعلی writable هستند؛ فایل آزمایشی برای سنجش نوشته نشد. مجوزهای Windows محلی دربارهٔ مالکیت و محدودیت هاست لینوکسی نتیجه‌ای نمی‌دهند.
- `public/.htaccess:113` و `:114` HTTPS redirect و rewrite برنامه را تنظیم می‌کنند. `php artisan serve` روی 127.0.0.1 همان رفتار Apache/LiteSpeed را اجرا نمی‌کند؛ حلقهٔ redirect یا عملکرد TLS هاست در این بازبینی اثبات یا رد نشده است. صرف وجود قانون HTTPS، باگ قطعی نامیده نمی‌شود.
- N+1 و عدم invalidation قبلاً در گزارش cache-performance ثبت شده‌اند؛ این گزارش آن‌ها را یافتهٔ تازهٔ استقرار حساب نمی‌کند. زمان پاسخ تحت محدودیت CPU/حافظهٔ هاست اندازه‌گیری نشده است.

## بررسی‌های اجراشده و موارد اجرا‌نشده

واقعاً اجراشده: خواندن composer.json/lock و platform_check، `php -v`، `composer check-platform-reqs --no-dev`، bootstrap فقط‌خواندنی برای نسخه و config و writable/file-exists، `SELECT VERSION()`، query واقعی descendants، بررسی فایل‌های manifest و public/hot، خواندن تنظیمات .htaccess/filesystems و اسکریپت deploy.

اجرا‌نشده: نصب یا update وابستگی‌ها، npm build، cache clear/optimize، migration، تغییر symlink/permissions، deploy، بررسی تولید و cPanel، تست redirect/TLS در Apache، تست concurrency/load و اندازه‌گیری حافظه. هیچ source، schema، cache یا دادهٔ کاربردی تغییر نکرد؛ فقط این گزارش تولید شد.
