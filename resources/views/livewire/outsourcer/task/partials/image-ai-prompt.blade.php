@php
    $imageAiPrompt = <<<'PROMPT'
یک عکس حرفه‌ای و واقع‌گرایانه از محصول فروشگاهی بسازید.

- نسبت تصویر: مربع ۱:۱
- اندازه خروجی: دقیقاً ۱۲۰۰×۱۲۰۰ پیکسل
- فرمت خروجی: WEBP با کیفیت حدود ۸۰ درصد؛ حجم نهایی کمتر از ۲۰۰ کیلوبایت و حداکثر ۲ مگابایت
- پس‌زمینه: سفید خالص یا خاکستری بسیار روشن، بدون سایه سنگین
- محصول: کامل، واضح و در مرکز کادر؛ حدود ۸۰ تا ۹۰ درصد کادر را بگیرد و هیچ بخشی بریده نشود
- نورپردازی: یکنواخت و نرم، بدون انعکاس شدید و بدون تاری
- ممنوع: متن، لوگوی نامرتبط، واترمارک، قیمت، شماره تماس، قاب و حاشیه تزئینی
- نمایش محصول باید با نوع و مدل واقعی کالا مطابقت داشته باشد؛ جزئیات را اضافه یا تغییر ندهید

موضوع محصول: [نام و مدل محصول را اینجا بنویسید]
زاویه تصویر: [روبه‌رو / سه‌رخ / جزئیات]
PROMPT;
@endphp
<aside class="rounded-xl border border-violet-200 bg-violet-50 p-4 text-sm text-violet-950" x-data="{ copied: false }">
    <h3 class="font-semibold">پرامپت استاندارد تولید تصویر با هوش مصنوعی</h3>
    <p class="mt-1 leading-7">اگر از هوش مصنوعی برای ساخت تصویر استفاده می‌کنید، این متن را کپی کنید و نام و مدل محصول را جایگزین کنید.</p>
    <textarea x-ref="prompt" readonly rows="12" class="mt-3 w-full rounded-lg border border-violet-200 bg-white p-3 text-sm leading-7" dir="rtl" aria-label="پرامپت استاندارد تصویر">{{ $imageAiPrompt }}</textarea>
    <div class="mt-2 flex flex-wrap items-center gap-3">
        <button type="button" class="min-h-11 rounded-lg border border-violet-300 bg-white px-4 py-2 font-medium"
            @click="navigator.clipboard.writeText($refs.prompt.value).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">کپی پرامپت</button>
        <span x-show="copied" x-cloak class="text-emerald-800" role="status">پرامپت کپی شد.</span>
    </div>
    <p class="mt-3 leading-7 text-violet-900">برخی ابزارها مستقیماً WEBP نمی‌سازند؛ در این حالت تصویر را با ابزار تبدیل فرمت به WEBP و اندازه ۱۲۰۰×۱۲۰۰ تبدیل کنید.</p>
</aside>
