@php
    $allowedFormats = implode('، ', array_map('strtoupper', config('product_completion.media.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp'])));
    $maxMegabytes = rtrim(rtrim(number_format(config('product_completion.media.max_file_size_kb', 2048) / 1024, 2, '.', ''), '0'), '.');
@endphp
<aside id="image-upload-guide" class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm leading-7 text-blue-900" aria-labelledby="image-upload-guide-title">
    <h3 id="image-upload-guide-title" class="mb-2 font-semibold">راهنمای تصویر مناسب محصول</h3>
    <ul class="list-disc space-y-1 pr-5">
        <li>فرمت‌های مجاز: <span dir="ltr">{{ $allowedFormats }}</span>؛ حداکثر حجم هر تصویر: {{ $maxMegabytes }} مگابایت.</li>
        <li>حداقل عرض {{ number_format(config('product_completion.media.min_width', 800)) }} و حداقل ارتفاع {{ number_format(config('product_completion.media.min_height', 800)) }} پیکسل.</li>
        <li>حداکثر عرض {{ number_format(config('product_completion.media.max_width', 8000)) }} و حداکثر ارتفاع {{ number_format(config('product_completion.media.max_height', 8000)) }} پیکسل؛ مجموع پیکسل‌های تصویر نباید از {{ number_format(config('product_completion.media.max_pixels', 40000000)) }} بیشتر باشد.</li>
        <li>مجموع تصاویر موجود و جدیدِ باقی‌مانده در گالری حداکثر {{ config('product_completion.media.max_images', 10) }} تصویر باشد.</li>
    </ul>
    <p class="mt-3 font-semibold">توصیه‌های کیفیت تصویر</p>
    <ul class="list-disc space-y-1 pr-5">
        <li>عکس مربوط به همین محصول و مدل باشد؛ محصول واضح، با نور کافی و بدون تاری دیده شود.</li>
        <li>برای تصویر اصلی، محصول کامل و در مرکز کادر با پس‌زمینهٔ ساده و ترجیحاً سفید قرار بگیرد.</li>
        <li>از تصویر دارای قیمت، شماره تماس، متن تبلیغاتی یا واترمارک استفاده نکنید.</li>
        <li>تصاویر تکمیلی، زاویه‌های دیگر، جزئیات و لوازم همراه را نشان دهند؛ عکس تکراری اضافه نکنید.</li>
    </ul>
    <p class="mt-3">پس از بارگذاری، پیش‌نمایش را بررسی و تصویر اصلی را با دکمهٔ «اصلی» مشخص کنید. تصاویر جدید همراه تسک برای بازبین ارسال می‌شوند.</p>
</aside>
