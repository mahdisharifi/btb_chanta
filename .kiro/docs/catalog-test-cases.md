# 🧪 Test Cases برای کاتالوگ با ماتریس قیمت جدید

**تاریخ:** 2026-08-27  
**نسخه:** 2.0.0

---

## 🎯 هدف تست

اطمینان از عملکرد صحیح کاتالوگ پس از به‌روزرسانی به ماتریس قیمت جدید:
- خواندن صحیح از جدول `product_prices`
- نمایش قیمت‌های چک با دوره‌های مختلف
- Backward compatibility با کاتالوگ‌های قدیمی
- Performance و cache

---

## ✅ Test Case 1: کاتالوگ با قیمت نقدی ساده

### پیش‌نیاز:
```sql
-- محصول با قیمت نقدی برای تیراژ کارتن
INSERT INTO product_prices (
    product_supplier_id, price_type_id, tier_index, 
    check_period_id, price, is_active
) VALUES (
    1, 1, 1,  -- تیراژ کارتن (1), قیمت نقدی (1)
    NULL, 10000, true
);
```

### مراحل تست:
1. وارد صفحه کاتالوگ شوید: `/supplier/catalog/index`
2. یک کاتالوگ جدید بسازید
3. در بخش قیمت‌ها:
   - تیراژ: کارتن ✅
   - نوع قیمت: نقدی ✅
4. دکمه "پیش‌نمایش" را بزنید

### نتیجه مورد انتظار:
```
✅ PDF با یک ستون "کارتن - نقدی"
✅ قیمت نمایش داده می‌شود: 10,000
✅ هیچ خطایی رخ نمی‌دهد
```

---

## ✅ Test Case 2: کاتالوگ با قیمت چک (چند دوره)

### پیش‌نیاز:
```sql
-- قیمت نقدی
INSERT INTO product_prices (
    product_supplier_id, price_type_id, tier_index, 
    check_period_id, price, is_active
) VALUES (1, 1, 1, NULL, 10000, true);

-- قیمت چک 30 روزه
INSERT INTO product_prices (
    product_supplier_id, price_type_id, tier_index, 
    check_period_id, price, is_active
) VALUES (1, 2, 1, 5, 12000, true);

-- قیمت چک 60 روزه
INSERT INTO product_prices (
    product_supplier_id, price_type_id, tier_index, 
    check_period_id, price, is_active
) VALUES (1, 2, 1, 6, 13500, true);

-- قیمت چک 90 روزه
INSERT INTO product_prices (
    product_supplier_id, price_type_id, tier_index, 
    check_period_id, price, is_active
) VALUES (1, 2, 1, 7, 14800, true);
```

### مراحل تست:
1. وارد صفحه کاتالوگ شوید
2. یک کاتالوگ جدید بسازید
3. در بخش قیمت‌ها:
   - تیراژ: کارتن ✅
   - نوع قیمت: نقدی ✅ + چک ✅
4. دکمه "پیش‌نمایش" را بزنید

### نتیجه مورد انتظار:
```
✅ PDF با 4 ستون:
   1. کارتن - نقدی: 10,000
   2. کارتن - چک 30 روزه: 12,000
   3. کارتن - چک 60 روزه: 13,500
   4. کارتن - چک 90 روزه: 14,800
✅ ستون‌ها به ترتیب روزها مرتب شده‌اند
✅ هیچ خطایی رخ نمی‌دهد
```

---

## ✅ Test Case 3: کاتالوگ با چند تیراژ

### پیش‌نیاز:
```sql
-- تیراژ زیرکارتن (0) - نقدی
INSERT INTO product_prices VALUES (1, 1, 0, NULL, 11000, true);

-- تیراژ کارتن (1) - نقدی
INSERT INTO product_prices VALUES (1, 1, 1, NULL, 10000, true);

-- تیراژ کارتن (1) - چک 30
INSERT INTO product_prices VALUES (1, 2, 1, 5, 12000, true);

-- تیراژ عمده (2) - نقدی
INSERT INTO product_prices VALUES (1, 1, 2, NULL, 9500, true);
```

### مراحل تست:
1. وارد صفحه کاتالوگ شوید
2. یک کاتالوگ جدید بسازید
3. در بخش قیمت‌ها:
   - تیراژ زیرکارتن: نقدی ✅
   - تیراژ کارتن: نقدی ✅ + چک ✅
   - تیراژ عمده: نقدی ✅

### نتیجه مورد انتظار:
```
✅ PDF با 4 ستون:
   1. زیرکارتن - نقدی: 11,000
   2. کارتن - نقدی: 10,000
   3. کارتن - چک 30 روزه: 12,000
   4. عمده - نقدی: 9,500
✅ ستون‌ها به ترتیب تیراژها مرتب شده‌اند
✅ هشدار "انتخاب 2+ تیراژ" نمایش داده می‌شود
```

---

## ✅ Test Case 4: کاتالوگ قدیمی (Backward Compatibility)

### پیش‌نیاز:
```sql
-- یک کاتالوگ قدیمی با ساختار قدیم
UPDATE catalog_templates 
SET config = JSON_OBJECT(
    'prices', JSON_ARRAY(1, 2, 3),  -- ساختار قدیم: [1,2,3]
    'categories', 'all'
)
WHERE id = 1;
```

### مراحل تست:
1. وارد صفحه `/supplier/catalog/index` شوید
2. کاتالوگ قدیمی را ویرایش کنید
3. دکمه "پیش‌نمایش" را بزنید

### نتیجه مورد انتظار:
```
✅ کد تشخیص می‌دهد که ساختار قدیم است
✅ تبدیل می‌کند به: ['1' => [1,2,3]]
✅ PDF تولید می‌شود بدون خطا
✅ فقط تیراژ کارتن نمایش داده می‌شود
```

---

## ✅ Test Case 5: کاتالوگ با گارانتی

### پیش‌نیاز:
```sql
-- قیمت با گارانتی مدت‌دار 12 ماه
INSERT INTO product_prices (
    product_supplier_id, price_type_id, tier_index, 
    check_period_id, price, warranty_type, 
    warranty_duration, is_active
) VALUES (1, 3, 1, NULL, 15000, 'limited', 12, true);

-- قیمت با گارانتی مادام‌العمر
INSERT INTO product_prices (
    product_supplier_id, price_type_id, tier_index, 
    check_period_id, price, warranty_type, 
    warranty_duration, is_active
) VALUES (1, 3, 1, NULL, 20000, 'lifetime', NULL, true);
```

### مراحل تست:
1. وارد صفحه کاتالوگ شوید
2. یک کاتالوگ جدید بسازید
3. در بخش قیمت‌ها:
   - تیراژ: کارتن ✅
   - نوع قیمت: گارانتی ✅

### نتیجه مورد انتظار:
```
✅ PDF با 2 ستون:
   1. کارتن - گارانتی: 15,000
      زیرنویس: (12 ماه)
   2. کارتن - گارانتی: 20,000
      زیرنویس: (مادام‌العمر)
```

---

## ✅ Test Case 6: Performance (Cache)

### مراحل تست:
1. وارد صفحه کاتالوگ شوید
2. یک کاتالوگ بزرگ بسازید (100+ محصول)
3. دکمه "پیش‌نمایش" را بزنید → اولین بار (کش نمی‌شود)
4. صفحه را رفرش کنید و دوباره پیش‌نمایش کنید → دومین بار (از کش)

### نتیجه مورد انتظار:
```
✅ اولین بار: 5-10 ثانیه
✅ دومین بار: <1 ثانیه (از کش)
✅ در console: "داده‌ها از کش بازیابی شدند"
✅ هیچ query اضافی به database نمی‌زند
```

---

## ✅ Test Case 7: محصولات بدون قیمت

### پیش‌نیاز:
```sql
-- محصولی که هیچ قیمتی ندارد
-- (فقط رکورد در product_supplier ولی نه در product_prices)
```

### مراحل تست:
1. وارد صفحه کاتالوگ شوید
2. یک کاتالوگ بسازید که این محصول را شامل شود

### نتیجه مورد انتظار:
```
✅ محصول در PDF نمایش داده نمی‌شود
✅ هیچ خطایی رخ نمی‌دهد
✅ در console: تعداد محصولات کاهش می‌یابد
```

---

## ✅ Test Case 8: Sort بر اساس قیمت

### مراحل تست:
1. وارد صفحه کاتالوگ شوید
2. در تنظیمات Sort:
   - سطح 1: "قیمت کارتن نقدی" - صعودی
3. دکمه "پیش‌نمایش" را بزنید

### نتیجه مورد انتظار:
```
✅ محصولات بر اساس قیمت کارتن نقدی مرتب شده‌اند
✅ ارزان‌ترین در بالا، گران‌ترین در پایین
✅ محصولات بدون قیمت در انتها
```

---

## ⚠️ Edge Cases

### Edge Case 1: قیمت صفر
```sql
INSERT INTO product_prices VALUES (1, 1, 1, NULL, 0, true);
```
**انتظار:** محصول نمایش داده نمی‌شود

### Edge Case 2: قیمت غیرفعال
```sql
INSERT INTO product_prices VALUES (1, 1, 1, NULL, 10000, false);
```
**انتظار:** محصول نمایش داده نمی‌شود

### Edge Case 3: قیمت منقضی شده
```sql
INSERT INTO product_prices VALUES (
    1, 1, 1, NULL, 10000, true,
    '2026-01-01', '2026-07-01'  -- گذشته
);
```
**انتظار:** محصول نمایش داده نمی‌شود

### Edge Case 4: چند تامین‌کننده برای یک محصول
```sql
-- تامین‌کننده 1: 10,000
INSERT INTO product_prices VALUES (1, 1, 1, NULL, 10000, true);
-- تامین‌کننده 2: 9,500
INSERT INTO product_prices VALUES (2, 1, 1, NULL, 9500, true);
```
**انتظار:** کمترین قیمت (9,500) انتخاب می‌شود

---

## 🐛 باگ‌های شناخته شده

### ❌ باگ 1: N+1 Query در CheckPeriod
**وضعیت:** رفع شد  
**راه‌حل:** استفاده از `->with('prices')`

### ❌ باگ 2: Cache invalidation
**وضعیت:** رفع شد  
**راه‌حل:** استفاده از `max('updated_at')` در کلید کش

---

## 📊 Checklist قبل از Production

- [ ] همه test caseها pass شدند
- [ ] Performance تست شد (100+ محصول)
- [ ] Backward compatibility تایید شد
- [ ] Cache به درستی کار می‌کند
- [ ] هیچ N+1 query نداریم
- [ ] Error handling برای edge cases
- [ ] Documentation بروز است
- [ ] Git commit با پیام مناسب

---

## 🔍 Debug Commands

### بررسی قیمت‌ها:
```bash
php artisan tinker
$ps = \App\Models\ProductSupplier::find(1);
$ps->prices()->where('is_active', true)->get();
```

### پاک کردن کش:
```bash
php artisan cache:clear
```

### بررسی log:
```bash
tail -f storage/logs/laravel.log
```

### بررسی query count:
```php
\DB::enableQueryLog();
// ... کد کاتالوگ ...
dd(\DB::getQueryLog());
```

---

**آخرین بروزرسانی:** 2026-08-27  
**نسخه:** 2.0.0  
**وضعیت:** Ready for Testing ✅
