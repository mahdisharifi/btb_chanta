# راهنمای بخش SEO در پنل ادمین

**فایل:** `resources/views/livewire/admin/products/create.blade.php`  
**تاریخ بروزرسانی:** 2026-09-17  
**وضعیت:** بهبود یافته ✅

---

## 📋 فهرست مطالب

1. [خلاصه بهبودها](#خلاصه-بهبودها)
2. [فیلدهای SEO](#فیلدهای-seo)
3. [پیش‌نمایش گوگل](#پیش‌نمایش-گوگل)
4. [Canonical URL](#canonical-url)
5. [راهنمای استفاده](#راهنمای-استفاده)
6. [نکات مهم](#نکات-مهم)

---

## ✨ خلاصه بهبودها

### قبل از بهبود ❌
```blade
{{-- پیش‌نمایش ساده --}}
<div class="mt-4 p-4 rounded-lg border bg-gray-50">
    <p class="text-xs">پیش‌نمایش گوگل</p>
    <p class="text-blue-600">{{ $seo_title }}</p>
    <p class="text-green-700">{{ $seo_canonical_url }}</p>
    <p class="text-gray-600">{{ $seo_description }}</p>
</div>
```

### بعد از بهبود ✅
- 🎨 طراحی شبیه‌سازی دقیق نتایج Google
- 💡 Tooltip توضیحی برای Canonical URL
- ✅ نمایش Canonical پیش‌فرض
- 📊 راهنمای طول عنوان و توضیحات
- 🎯 پیشنهادات بهینه‌سازی

---

## 📝 فیلدهای SEO

### 1. عنوان SEO (SEO Title)
```blade
<input type="text" wire:model="seo_title" maxlength="60">
<p class="text-xs">{{ mb_strlen($seo_title ?? '') }}/60</p>
```

**محدوده ایده‌آل:** 50-60 کاراکتر

**مثال خوب:**
```
✅ گوشی موبایل سامسونگ A54 | خرید عمده با قیمت ویژه
```

**مثال بد:**
```
❌ گوشی (خیلی کوتاه - 4 کاراکتر)
❌ گوشی موبایل سامسونگ A54 با حافظه 128 گیگابایت و رم 8 گیگ (خیلی طولانی - 75 کاراکتر)
```

---

### 2. توضیحات SEO (SEO Description)
```blade
<textarea wire:model="seo_description" rows="3" maxlength="160"></textarea>
<p class="text-xs">{{ mb_strlen($seo_description ?? '') }}/160</p>
```

**محدوده ایده‌آل:** 120-160 کاراکتر

**مثال خوب:**
```
✅ گوشی سامسونگ A54 با صفحه نمایش 6.4 اینچ و دوربین 50 مگاپیکسل. 
خرید عمده با قیمت ویژه و ارسال رایگان. تحویل فوری به سراسر کشور.
(158 کاراکتر)
```

**مثال بد:**
```
❌ گوشی سامسونگ (خیلی کوتاه - 13 کاراکتر)
❌ این یک گوشی عالی با امکانات فوق‌العاده و... (مبهم و بی‌محتوا)
```

---

### 3. کلمات کلیدی (Keywords)
```blade
<input type="text" wire:model="seo_keywords" maxlength="255">
```

**نحوه استفاده:**
```
گوشی موبایل, سامسونگ, Samsung A54, خرید عمده, قیمت ویژه
```

**توصیه:**
- 5-10 کلمه کلیدی
- مرتبط با محصول
- از کاما (،) برای جداسازی استفاده کنید

---

### 4. آدرس Canonical (Canonical URL)

#### 💡 Canonical URL چیست؟
Canonical URL یک URL یکتا و اصلی است که به Google می‌گوید این صفحه همان محتوای اصلی است و نباید duplicate content در نظر گرفته شود.

#### مثال عملی:

**بدون Canonical:**
```
https://example.com/products/sample-product
https://example.com/products/sample-product?ref=homepage
https://example.com/products/sample-product?utm_source=google
```
❌ Google این‌ها را 3 صفحه مختلف می‌بیند (duplicate content)

**با Canonical:**
```html
<link rel="canonical" href="https://example.com/products/sample-product">
```
✅ Google می‌داند همه این URLها یک صفحه هستند

#### نحوه استفاده در پنل:

**حالت 1: خالی گذاشتن (توصیه می‌شود)**
```
[فیلد خالی]
```
سیستم به صورت خودکار از URL محصول استفاده می‌کند:
```
https://yoursite.com/products/product-slug
```

**حالت 2: وارد کردن URL سفارشی**
```
https://example.com/special-url
```
فقط در موارد خاص (مثلاً محصول قبلاً در URL دیگری منتشر شده بود)

---

## 🔍 پیش‌نمایش گوگل

### طراحی جدید

```blade
{{-- پیش‌نمایش بهبود یافته --}}
<div class="rounded-xl border-2 border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-5">
    <div class="flex items-center gap-2 mb-3">
        <div class="w-10 h-10 rounded-lg bg-white flex items-center justify-center">
            <!-- Google Icon -->
        </div>
        <div>
            <p class="text-sm font-bold">پیش‌نمایش در نتایج جستجوی گوگل</p>
            <p class="text-xs text-gray-500">این نمایش تقریبی است</p>
        </div>
    </div>
    
    <div class="bg-white rounded-lg p-4 border">
        <!-- عنوان آبی -->
        <h3 class="text-blue-600 text-lg font-medium">
            {{ $seo_title }}
        </h3>
        
        <!-- URL سبز -->
        <div class="text-green-700 text-sm">
            {{ $seo_canonical_url }}
        </div>
        
        <!-- توضیحات خاکستری -->
        <p class="text-gray-600 text-sm">
            {{ $seo_description }}
        </p>
    </div>
    
    <!-- راهنمای بهینه‌سازی -->
    <div class="mt-3 grid grid-cols-2 gap-2">
        <!-- وضعیت عنوان -->
        <div class="text-xs {{ $status }}">
            ✓ عنوان در محدوده ایده‌آل است
        </div>
        
        <!-- وضعیت توضیحات -->
        <div class="text-xs {{ $status }}">
            ✓ توضیحات در محدوده ایده‌آل است
        </div>
    </div>
</div>
```

### قابلیت‌های پیش‌نمایش:

1. **نمایش Real-time**
   - تغییرات فوری نمایش داده می‌شوند
   - بدون نیاز به ذخیره

2. **راهنمای طول محتوا**
   - ✅ سبز: محدوده ایده‌آل
   - ⚠️ زرد: خیلی کوتاه یا طولانی

3. **پیشنهادات بهینه‌سازی**
   ```
   ✅ عنوان در محدوده ایده‌آل است (50-60 کاراکتر)
   ⚠️ عنوان کوتاه است (بهتر است 50-60 کاراکتر باشد)
   ⚠️ عنوان خیلی طولانی است (Google کوتاه می‌کند)
   ```

---

## 🎯 راهنمای استفاده

### سناریو 1: ایجاد محصول جدید

```
1. وارد کردن اطلاعات پایه محصول
   - عنوان محصول
   - توضیحات کوتاه
   - slug (به صورت خودکار)

2. رفتن به بخش SEO
   - کلیک روی "تولید خودکار" (اختیاری)
   - یا وارد کردن دستی اطلاعات SEO

3. بررسی پیش‌نمایش گوگل
   - آیا عنوان جذاب است؟
   - آیا توضیحات واضح است؟
   - آیا طول محتوا مناسب است؟

4. Canonical URL
   - خالی بگذارید (توصیه می‌شود)
   - سیستم خودکار URL صحیح را تنظیم می‌کند

5. ذخیره محصول
```

### سناریو 2: ویرایش محصول موجود

```
1. بررسی SEO فعلی
   - آیا Canonical URL صحیح است؟
   - آیا عنوان بهینه است؟

2. بهبود محتوا
   - افزودن کلمات کلیدی مهم
   - بهبود توضیحات

3. بررسی پیش‌نمایش
   - مقایسه قبل و بعد

4. ذخیره تغییرات
```

---

## ⚠️ نکات مهم

### ✅ انجام دهید:

1. **عنوان SEO را منحصر به فرد کنید**
   ```
   ✅ گوشی سامسونگ A54 128GB | خرید عمده با تخفیف 15%
   ❌ گوشی سامسونگ (تکراری و عمومی)
   ```

2. **از کلمات کلیدی در عنوان استفاده کنید**
   ```
   ✅ کفش ورزشی نایک مدل Air Max | خرید اینترنتی با ارسال رایگان
   (شامل: کفش ورزشی، نایک، Air Max، خرید اینترنتی)
   ```

3. **Canonical URL را خالی بگذارید**
   ```
   ✅ [خالی] → سیستم خودکار تنظیم می‌کند
   ❌ https://... → فقط در موارد خاص
   ```

4. **از Action Verbs استفاده کنید**
   ```
   ✅ خرید، سفارش، دریافت، دانلود
   مثال: "خرید کفش ورزشی با ارسال رایگان"
   ```

### ❌ انجام ندهید:

1. **از عنوان‌های کلی استفاده نکنید**
   ```
   ❌ محصول شماره 1
   ❌ کالای جدید
   ```

2. **Keyword Stuffing نکنید**
   ```
   ❌ گوشی گوشی موبایل سامسونگ سامسونگ A54 خرید گوشی
   ```

3. **توضیحات را کپی نکنید**
   ```
   ❌ کپی کردن از سایت‌های دیگر
   ✅ نوشتن توضیحات منحصر به فرد
   ```

4. **Canonical URL اشتباه وارد نکنید**
   ```
   ❌ http://example.com (بدون https)
   ❌ example.com/products (بدون protocol)
   ✅ https://example.com/products/sample
   ```

---

## 📊 معیارهای موفقیت SEO

| معیار | خوب ✅ | قابل قبول ⚠️ | نیاز به بهبود ❌ |
|-------|---------|--------------|-----------------|
| طول عنوان | 50-60 کاراکتر | 40-49 یا 61-70 | < 40 یا > 70 |
| طول توضیحات | 120-160 کاراکتر | 100-119 یا 161-180 | < 100 یا > 180 |
| تعداد کلمات کلیدی | 5-10 | 3-4 یا 11-15 | < 3 یا > 15 |
| Canonical URL | خالی یا صحیح | - | نادرست یا مبهم |

---

## 🔗 منابع مرتبط

- [مستندات کامل Canonical URL](.kiro/docs/seo-product-display-improvements.md)
- [پیش‌نمایش HTML](.kiro/docs/canonical-url-preview.html)
- [مقایسه قبل/بعد](.kiro/docs/zqs-8315-before-after.md)
- [راهنمای Google Search Central](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)

---

## 📞 پشتیبانی

در صورت نیاز به راهنمایی بیشتر:
1. مراجعه به مستندات بالا
2. تست با Google Search Console
3. استفاده از ابزارهای SEO (Yoast, Rank Math)

---

**نگارش:** Kiro AI Assistant  
**آخرین بروزرسانی:** 2026-09-17  
**وضعیت:** فعال و قابل استفاده
