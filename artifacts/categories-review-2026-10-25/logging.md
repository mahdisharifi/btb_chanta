# بازبینی مستقل لاگینگ و مانیتورینگ

تاریخ: ۲۰۲۶-۱۰-۲۵. محدوده: `/categories`، مسیر show مرتبط، middleware عمومی و exception/logging config. **یافتهٔ تازهٔ قابل‌اقدام با شدت قطعی در این محدوده تأیید نشد.** خطای آیکون و N+1 در گزارش‌های تخصصی دیگر بررسی شده‌اند و اینجا دوباره شمرده نمی‌شوند.

## بررسی تشخیص و ثبت خطا

- `app/Http/Controllers/Web/CategoryController.php:17` و `:70` exception را catch و پنهان نمی‌کنند. خطای DB/Blade از این مسیر به handler عمومی Laravel می‌رسد؛ callback مخصوصی که گزارش خطای این صفحه را غیرفعال کند در provider/bootstrap مربوط پیدا نشد.
- `bootstrap/app.php:47` تا `:75` خروجی HTML خطا را بازنویسی می‌کند، نه اینکه مسیر report را خاموش کند. در `:57` debug محلی صفحهٔ خطای اصلی را حفظ می‌کند؛ برای HTML عمومی با debug=false در `:62` قالب امن `errors.page` استفاده می‌شود و `:64` مقدار `no-store, private` می‌گذارد. API/JSON/Livewire از بازنویسی HTML خارج شده‌اند (`:50` و `:51`).
- کد نصب‌شدهٔ framework هم بررسی شد: `vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:357` و `:376` پس از قواعد shouldntReport، exceptionهای قابل‌گزارش را با message و exception context به logger می‌فرستند. این شاهد بررسی کد است؛ برای تأیید اجرایی، خطای عمدی تولید نشد.
- `resources/views/errors/page.blade.php` پیام کاربر را از statusهای مشخص می‌سازد و متن exception یا stack trace را در HTML برند قرار نمی‌دهد. status/protocol headers در callback حفظ می‌شوند. برای این بازبینی config تولید یا APP_DEBUG تغییر نکرد.

## تنظیمات واقعی مشاهده‌شده

Bootstrap فقط‌خواندنی برنامه نشان داد:

| موضوع | مقدار فعلی محلی |
| --- | --- |
| environment | local |
| debug | true |
| logging.default | stack |
| اعضای stack | single |
| سطح single | debug |
| ignore_exceptions در stack | false |
| writable بودن storage/logs و laravel.log | true؛ بدون نوشتن فایل آزمایشی |

تعریف این تنظیمات در `config/logging.php:21`، `:55` تا `:67` است. وجود trace در خطاهای debug همین محیط، افشای محیط تولید را ثابت نمی‌کند؛ APP_DEBUG و channel هاست تولید خوانده نشده‌اند. استفادهٔ فعلی از single به‌جای daily دیده شد، اما رشد نامحدود مؤثر یا پرشدن دیسک در این بررسی ثابت نشد و به‌عنوان باگ صفحه اعلام نمی‌شود.

## شواهد لاگ موجود و حریم خصوصی

- فایل `storage/logs/laravel.log` در زمان بررسی حدود **۴۲۶KB** و ۲۴۲۵ خط داشت. فقط شمارش referenceهای `CategoryController`، view دسته‌ها و پیام کلاس component ناموجود انجام شد؛ هر سه شمارش در snapshot فعلی صفر بودند. این نتیجه به معنی «صفحه هرگز خطا نداده» یا «handler ثبت نمی‌کند» نیست؛ فقط لاگ فعلی شاهد آمادهٔ آن رخداد را ندارد.
- محتوای خام لاگ، stack trace، URLهای webhook، اطلاعات کاربر، token، cookie یا secret در گزارش استخراج یا منتشر نشد.
- در کنترلر دسته، Blade index و `app/Http/Middleware/EnsureMobileIsVerified.php` فراخوانی logger/report یا dump صریح request/input برای این مسیر پیدا نشد. بنابراین نشت مشخص دادهٔ حساس از logging اختصاصی این صفحه تأیید نشد. این بررسی محدود، گواه پاک‌بودن همهٔ لاگ‌های پروژه نیست.
- عبارت جست‌وجوی دسته‌ها در state محلی Alpine است (`resources/views/pages/categories/index.blade.php:35` به بعد) و در همان پیاده‌سازی درخواست جست‌وجوی مجزا یا لاگ سمت سرور ندارد.

## محدودیت‌ها

اجرای واقعی: خواندن کد و configهای مرتبط، خواندن config مؤثر بدون secrets، بررسی metadata/writable مسیر لاگ و اسکن شمارشی محدود فایل موجود. انجام نشد: crash عمدی، تغییر آیکون/DB، نوشتن log آزمایشی، پاک‌سازی یا rotation لاگ، تغییر channel/debug، بررسی dashboard یا alert خارجی، اندازه‌گیری هشدار slow-query و تأیید پیکربندی production. نبود correlation ID یا APM را بدون نیاز محصول یا failure قابل‌اثبات، یافتهٔ اجباری جدید اعلام نکردیم.
