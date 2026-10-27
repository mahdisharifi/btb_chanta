# استاندارد کد ارزی ایران در Schema.org

## تاریخچه
- **ایجاد:** 2026-09-01
- **آخرین بروزرسانی:** 2026-09-01
- **وضعیت:** ✅ فعال

---

## خلاصه

این مستند استاندارد صحیح کد ارزی ایران را در Schema.org توضیح می‌دهد و دلیل تغییر از `IRT` به `IRR` را شرح می‌دهد.

---

## سؤال و پاسخ

### ۱. آیا گوگل IRT (تومان) را می‌شناسد؟

**خیر - IRT کد رسمی ISO 4217 نیست.**

- گوگل فقط کدهای **ISO 4217** را می‌پذیرد
- IRT در استاندارد ISO 4217 وجود ندارد
- استفاده از IRT باعث خطا در Google Search Console می‌شود

### ۲. کد رسمی ایران چیست؟

- **IRR** = Iranian Rial (ریال ایرانی)
- این کد در ISO 4217 ثبت شده و گوگل آن را می‌پذیرد

### ۳. چرا IRT استفاده می‌شد؟

IRT یک کد غیررسمی است که در برخی سیستم‌های داخلی ایران استفاده می‌شود، اما:
- ❌ در ISO 4217 نیست
- ❌ گوگل آن را نمی‌شناسد
- ❌ باعث خطای "Invalid value for property priceCurrency" می‌شود

---

## راه‌حل پیاده‌سازی شده

### قبل از اصلاح:
```php
$schema['offers'] = [
    '@type'         => 'AggregateOffer',
    'priceCurrency' => 'IRT', // ❌ غیراستاندارد
    'lowPrice'      => $lowestPrice, // تومان
    'highPrice'     => $highestPrice, // تومان
];
```

### بعد از اصلاح:
```php
// ✅ تبدیل تومان به ریال برای ISO 4217 (IRR)
$lowestPriceRial = $lowestPrice * 10;
$highestPriceRial = $highestPrice * 10;

$schema['offers'] = [
    '@type'         => 'AggregateOffer',
    'priceCurrency' => 'IRR', // ✅ کد رسمی ISO 4217
    'lowPrice'      => $lowestPriceRial, // ریال
    'highPrice'     => $highestPriceRial, // ریال
];
```

---

## فرمول تبدیل

```
ریال = تومان × 10
```

### مثال:
- قیمت در سیستم: **100,000 تومان**
- قیمت در Schema.org: **1,000,000 ریال**

---

## فایل‌های اصلاح شده

### ۱. ProductController.php
**مسیر:** `app/Http/Controllers/Web/ProductController.php`
**متد:** `buildProductSchema()`

**تغییرات:**
- تبدیل `IRT` به `IRR`
- ضرب قیمت‌ها در 10 (تبدیل تومان به ریال)
- اعمال در سه بخش:
  1. قیمت عمده (AggregateOffer)
  2. قیمت خرده‌فروشی (Offer)
  3. قیمت placeholder (AggregateOffer با قیمت 0)

---

## منابع معتبر

### ISO 4217 Currency Codes
- [Official ISO 4217 List](https://www.iso.org/iso-4217-currency-codes.html)
- **IRR** = Iranian Rial (کد رسمی)

### Schema.org Documentation
- [Schema.org Offer - priceCurrency](https://schema.org/priceCurrency)
- باید از کدهای ISO 4217 استفاده شود

### Google Search Central
- [Google Structured Data - Product](https://developers.google.com/search/docs/advanced/structured-data/product)
- گوگل فقط کدهای ISO 4217 را می‌پذیرد

---

## تأثیر بر SEO

### قبل از اصلاح:
- ❌ خطا در Google Search Console
- ❌ عدم نمایش Rich Snippets برای قیمت
- ❌ عدم نمایش قیمت در نتایج جستجو

### بعد از اصلاح:
- ✅ بدون خطا در Search Console
- ✅ نمایش صحیح Rich Snippets
- ✅ نمایش قیمت در نتایج جستجو گوگل
- ✅ بهبود CTR (Click-Through Rate)

---

## نکات مهم

### ۱. نمایش به کاربر
این تغییر فقط روی Schema.org است. در UI همچنان قیمت‌ها به **تومان** نمایش داده می‌شوند.

### ۲. پایگاه داده
قیمت‌ها در دیتابیس به **تومان** ذخیره می‌شوند. تبدیل فقط هنگام تولید Schema انجام می‌شود.

### ۳. تست
برای تست صحت Schema:
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema Markup Validator](https://validator.schema.org/)

---

## مثال Schema صحیح

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "محصول نمونه",
  "offers": {
    "@type": "AggregateOffer",
    "priceCurrency": "IRR",
    "lowPrice": 1000000,
    "highPrice": 5000000,
    "offerCount": 5
  }
}
```

---

## چک‌لیست

قبل از deploy:
- [x] تغییر IRT به IRR
- [x] ضرب قیمت‌ها در 10
- [x] تست با Google Rich Results Test
- [ ] بررسی Search Console پس از index (نیاز به زمان)

---

## پیگیری

### پس از Deploy:
1. منتظر index شدن صفحات بمانید (1-2 هفته)
2. در Search Console بررسی کنید:
   - Enhancements > Products
   - باید بدون خطا باشد
3. در نتایج جستجو چک کنید:
   - قیمت‌ها باید نمایش داده شوند

---

**نویسنده:** SEO & Ranking Expert Agent
**تاریخ:** 2026-09-01
