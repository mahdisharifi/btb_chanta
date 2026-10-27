# بازبینی مستقل اسکیما و داده ساخت‌یافته /categories

تاریخ: ۲۰۲۶-۱۰-۲۵. دامنه: JSON-LD صفحه فهرست، انطباق با محتوای نمایان و مقصد مستقیم لینک دسته. بازبینی کد فقط‌خواندنی است؛ هیچ منطق اجرایی تغییر نکرد.

## نتیجه اصلاح‌شده پس از بررسی خروجی واقعی و کامپایل Blade

نتیجه قبلی «یافته‌ای پیدا نشد» پس گرفته می‌شود. بررسی قبلی فقط literal داخل فایل Blade را خوانده بود و اثر directive مربوط به @context روی رشته PHP را ندیده بود. خروجی واقعی اسکیما یک ایراد P2 دارد: کلید @context وجود ندارد و به متن PHP کامپایل‌شده تبدیل شده است. صفحه همچنان باز می‌شود و JSON.parse موفق است؛ موفقیت parse به معنای صحت JSON-LD یا اتصال نوع‌ها به واژگان Schema.org نیست.

### P2 — Blade کلید @context اسکیما را به متن directive کامپایل می‌کند

- محل اصلی: `resources/views/pages/categories/index.blade.php:6`.
- شاهد مستقل: `storage/framework/views/87e276cdf8013b8712b60c075fba2490.php:4` تا `:7` و نسخه compiled دیگر `c08c6dcd3bab6eb7223fbf403fc3a8be.php` به‌جای کلید @context، رشته‌ای با محتوای PHP زیر تولید می‌کنند؛ این متن داخل مقدار کلید JSON قرار می‌گیرد و در مرورگر اجرا نمی‌شود:

```text
<?php $__contextArgs = [];
if (context()->has($__contextArgs[0])) :
if (isset($value)) { $__contextPrevious[] = $value; }
$value = context()->get($__contextArgs[0]); ?>
```

- شاهد اجرایی عامل اصلی: JSON.parse(script.textContent) موفق بود اما property بالا با مقدار https://schema.org وجود داشت و property استاندارد @context غایب بود؛ سایر فیلدها و numberOfItems=2 درست بودند.
- بازتولید مستقل انجام‌شده: با PHP نصب‌شده و vendor/autoload.php، یک BladeCompiler مستقیم ساخته و compileString روی دو رشته حداقلی اجرا شد؛ نیازی به bootstrap، DB یا درخواست وب نبود. قالب حاوی `['@context' => 'https://schema.org']` همان کلید خراب را تولید کرد؛ قالب حاوی `['@@context' => 'https://schema.org']` به PHP صحیح با `['@context' => 'https://schema.org']` تبدیل شد. فرمان exit_code=0 داشت و فایل منبع/داده تغییر نکرد.
- اثر: داده خروجی زمینه استاندارد Schema.org را تعریف نمی‌کند؛ بنابراین تطابق نام/تعداد/URLهای داخل JSON برای تأیید اسکیما کافی نیست. این یافته تضمین عدم پردازش توسط هر سرویس یا افت رتبه خاص نیست.
- اصلاح حداقلی آزموده‌شده در سطح کامپایل: کلید Blade به `'@@context' => 'https://schema.org'` تغییر کند تا خروجی @context صحیح بسازد. گزینه معماری دیگر، ساخت کل آرایه در کنترلر/سرویس و فقط encodeکردن متغیر در Blade است.
- اعتبارسنجی لازم پس از اصلاح: HTML واقعی رندر شود؛ decoded['@context'] دقیقاً https://schema.org و نبود کلید مشتمل بر <?php بررسی شود، سپس Schema Validator/Rich Results Test اجرا شود. @@context فقط در سطح compileString این عامل آزموده شد؛ منبع اصلاح نشده و رندر کامل نسخه اصلاح‌شده انجام نشده است.

## بررسی سازگاری محتوا؛ مستقل از ایراد زمینه JSON-LD

- `resources/views/pages/categories/index.blade.php:7`: نوع موردنظر CollectionPage با ماهیت صفحه فهرست دسته‌ها متناسب است؛ به‌دلیل ایراد @context نباید صحت خروجی آن تأییدشده تلقی شود.
- `:8` و `:9`: نام صفحه با عنوان نمایان و description ارسالی از کنترلر سازگار است.
- `:10`: url از همان route صفحه ساخته می‌شود. `app/Http/Controllers/Web/CategoryController.php:65` نیز canonical را از همان route می‌سازد. عامل اصلی canonical محلی صفحه را تأیید کرده است.
- `:12` تا `:16`: BreadcrumbList مسیر خانه ← دسته‌بندی‌ها را با دو position صحیح و نام و URL هر مرحله بیان می‌کند؛ با مسیر نمایان `:99` تا `:102` منطبق است.
- `:19` تا `:26`: ItemList از همان collection سازنده کارت‌ها تولید می‌شود؛ numberOfItems تعداد دسته‌های ریشه را می‌شمارد، نه تعداد محصولات یا همه زیر‌دسته‌ها. عامل اصلی در DOM دو کارت ریشه و numberOfItems=2 را تأیید کرده است. این تطابق درست است.
- `:23`: positionها از 1 آغاز می‌شوند و به ترتیب collection هستند.
- `:25` و `:196`: URLهای اسکیما و لینک ورود کارت از یک route و slug تولید می‌شوند.
- `:28`: json_encode به همراه JSON_HEX_TAG و JSON_HEX_AMP از بسته‌شدن ناخواسته تگ script با محتوای نام‌ها جلوگیری می‌کند. مقدار نام‌ها با دست‌نویسی JSON درون‌رشته‌ای ساخته نشده است.
- جستجوی صفحه صرفاً فیلتر محلی روی همین فهرست است؛ باقی‌ماندن اسکیما فهرست پایه پس از تایپ جستجوی بدون URL مستقل، به‌خودی‌خود خطای اسکیما ثبت نشد.

## یافته قابل اقدام در مقصد مستقیم — P3

### زنجیره BreadcrumbList در صفحه دسته ادامه پیدا نمی‌کند

- محل: `app/Http/Controllers/Web/CategoryController.php:126` و `resources/views/pages/categories/show.blade.php:9`.
- شاهد ایستا: show به pages.categories.show می‌رود و breadcrumb نمایان دارد؛ در این view و layout عمومی JSON-LD متناظر CollectionPage/BreadcrumbList تولید نمی‌شود. اسکیما در head فقط به index محدود شده است. این فقدان، اسکیما index را نامعتبر نمی‌کند و خطای الزامی استاندارد محسوب نشده است؛ یک فرصت بهبود کم‌اولویت در مقصد مستقیم است.
- بازتولید: از index وارد یک دسته شوید و کد HTML مقصد را از نظر application/ld+json و BreadcrumbList بررسی کنید. این بازتولید HTTP در این عامل اجرا نشده؛ یافته از مسیر view و کد تأیید شد.
- اصلاح پیشنهادی: اگر دامنه کار به صفحه دسته گسترش پیدا کرد، از همان داده breadcrumb نمایان، BreadcrumbList با خانه، دسته‌بندی‌ها، اجداد و دسته جاری بسازید؛ URL دسته جاری با canonical همان صفحه یکسان باشد. داده محصول یا Offer فقط وقتی اضافه شود که اطلاعات واقعی و قابل مشاهده آن در صفحه موجود باشد.

## حدود اعتبارسنجی

- Rich Results Test، Schema Markup Validator، Search Console و crawl تولیدی اجرا نشد.
- compiled Blade مستقلاً دیده و compileString مستقیم برای نمونه خراب و اصلاح پیشنهادی اجرا شد؛ شاهد DOM واقعی از عامل اصلی دریافت شد. این دو بررسی، نتیجه اولیه صرفاً ایستای این گزارش را تغییر دادند.
- URLهای مقصد به‌صورت HTTP و وضعیت پاسخ/redirect در این عامل آزموده نشد؛ فقط route مستقیم و تولید URLها بررسی شد. 301/308، زنجیره یا حلقه redirect و مقصد نهایی canonical تأیید اجرایی نشده است.
- رفتار دامنه واقعی، HTTPS، APP_URL و canonical تولیدی خارج از localhost تأیید نشد.
- غیبت @id، inLanguage یا publisher در این ساختار به‌عنوان خطا گزارش نشد؛ این خصوصیات را نباید بدون نیاز واقعی اجباری دانست.
- تطابق ساختار به معنی تضمین نمایش rich result یا رتبه نیست.

## منابع رسمی بررسی مفهومی

- Schema.org، CollectionPage و breadcrumb: https://schema.org/CollectionPage و https://schema.org/BreadcrumbList
- Google Search Central، دستورالعمل عمومی داده ساخت‌یافته: https://developers.google.com/search/docs/appearance/structured-data/sd-policies
- Google Search Central، Breadcrumb: https://developers.google.com/search/docs/appearance/structured-data/breadcrumb

این منابع برای تفسیر نوع‌ها و محدوده اعتبار اسکیما به کار رفتند؛ یافته‌های فایل‌ها مبتنی بر کد مخزن و شواهد عامل اصلی‌اند.
