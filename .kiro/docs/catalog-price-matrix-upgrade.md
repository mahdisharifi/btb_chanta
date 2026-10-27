# ✅ به‌روزرسانی کاتالوگ برای استفاده از ماتریس قیمت جدید

**تاریخ:** 2026-08-27  
**وضعیت:** ✅ تکمیل شده  
**نسخه:** 2.0.0

---

## 📋 خلاصه تغییرات

### ❌ مشکل قبلی:
کد کاتالوگ از فیلد منسوخ `price_matrix` (JSON) در جدول `product_supplier` استفاده می‌کرد که:
- **منسوخ شده** و دیگر بروز نمی‌شود
- با ساختار جدید `product_prices` **ناسازگار** است
- قیمت‌های چک با دوره‌های مختلف را **پشتیبانی نمی‌کند**

### ✅ راه‌حل:
کاتالوگ حالا مستقیماً از جدول `product_prices` استفاده می‌کند با کلید:
```
"{tier_index}_{price_type_id}_{check_period_id}"
```

---

## 🔄 تغییرات اعمال شده

### 1. Backend: `app/Livewire/Supplier/Catalog/Preview.php`

#### قبل:
```php
// خواندن از price_matrix (JSON منسوخ)
$priceMatrix = is_string($ps->price_matrix) 
    ? json_decode($ps->price_matrix, true) 
    : $ps->price_matrix;

foreach ($selectedTiers as $tierIndex) {
    $tierData = $priceMatrix[$tierIndex];
    foreach ($priceTypesForThisTier as $priceTypeId) {
        $priceData = $tierData[$priceTypeId];
        $priceValue = is_array($priceData) ? $priceData['price'] : $priceData;
    }
}
```

#### بعد:
```php
// ✅ خواندن از relation prices (جدول product_prices)
$productPrices = $ps->prices; // Collection از ProductPrice

// گروه‌بندی بر اساس کلید یکتا
$pricesGrouped = $productPrices->groupBy(function($price) {
    return $price->tier_index . '_' . $price->price_type_id . '_' . ($price->check_period_id ?? 'null');
});

foreach ($selectedTiers as $tierIndex) {
    foreach ($priceTypesForThisTier as $priceTypeId) {
        // قیمت نقدی/گارانتی
        $key = "{$tierIndex}_{$priceTypeId}_null";
        $priceRecord = $pricesGrouped->get($key)?->first();
        
        // قیمت‌های چک (اگر نوع = check)
        if ($priceType->code === 'check') {
            foreach ($checkPeriods as $period) {
                $checkKey = "{$tierIndex}_{$priceTypeId}_{$period->id}";
                $checkPriceRecord = $pricesGrouped->get($checkKey)?->first();
            }
        }
    }
}
```

---

### 2. Frontend: `resources/views/pdf/supplier-catalog.blade.php`

#### تولید ستون‌ها:

**قبل:**
```php
// فقط یک ستون برای "چک" (بدون دوره‌های مختلف)
$priceColumns[] = [
    'key' => "{$tierIndex}_{$priceTypeId}",
    'label' => "{$tierName} - {$priceTypeLabel}",
];
```

**بعد:**
```php
// ✅ اگر نوع = چک، یک ستون برای هر دوره چک
if ($priceTypeId === 2) { // چک
    // یافتن همه دوره‌های چک موجود
    $checkPeriodsUsed = [];
    foreach ($products as $product) {
        foreach ($product['prices'] as $key => $priceData) {
            if (isset($priceData['check_period_id'])) {
                $checkPeriodsUsed[$priceData['check_period_id']] = [
                    'id' => $priceData['check_period_id'],
                    'days' => $priceData['check_period_days'],
                    'label' => $priceData['check_period_label'],
                ];
            }
        }
    }
    
    // ایجاد یک ستون برای هر دوره
    foreach ($checkPeriodsUsed as $period) {
        $priceColumns[] = [
            'key' => "{$tierIndex}_{$priceTypeId}_{$period['id']}",
            'label' => "{$tierName} - {$period['label']}",
        ];
    }
}
```

#### نمایش قیمت:

**قبل:**
```php
$priceKey = $column['key']; // tier_index + price_type_id
$priceData = $product['prices'][$priceKey] ?? null;
```

**بعد:**
```php
// ✅ کلید حالا شامل check_period_id هم می‌شود
$priceKey = $column['key']; // tier_index + price_type_id [+ check_period_id]
$priceData = $product['prices'][$priceKey] ?? null;

// نمایش اطلاعات گارانتی
if ($warrantyType === 'test') { /* تست */ }
elseif ($warrantyType === 'limited') { /* X ماه */ }
elseif ($warrantyType === 'lifetime') { /* مادام‌العمر */ }
```

---

## 🎯 ویژگی‌های جدید

### ✅ 1. پشتیبانی کامل از قیمت‌های چک
- **قبل:** فقط یک ستون "چک" (بدون تفکیک دوره)
- **بعد:** یک ستون برای هر دوره چک (30، 60، 90، 120 روز)

**مثال:**
```
| محصول | کارتن نقدی | کارتن چک 30 | کارتن چک 60 | کارتن چک 90 |
|-------|------------|-------------|-------------|-------------|
| A     | 10,000     | 12,000      | 13,500      | 14,800      |
```

### ✅ 2. نمایش اطلاعات گارانتی
- نوع گارانتی: تست / مدت‌دار / مادام‌العمر
- مدت گارانتی: X ماه (فقط برای مدت‌دار)

### ✅ 3. Eager Loading برای Performance
```php
// بارگذاری قیمت‌ها با relation
->with(['prices' => function ($priceQuery) {
    $priceQuery->where('is_active', true);
}])
```

---

## 📊 ساختار داده

### کلیدهای قیمت در آرایه `$prices`:

#### قیمت نقدی/گارانتی:
```php
$prices["{tier_index}_{price_type_id}"] = [
    'price' => 10000,
    'tier_index' => 0,
    'price_type_id' => 1,
    'warranty_type' => 'limited',
    'warranty_duration' => 12,
];
```

#### قیمت چک:
```php
$prices["{tier_index}_{price_type_id}_{check_period_id}"] = [
    'price' => 12000,
    'tier_index' => 0,
    'price_type_id' => 2,
    'check_period_id' => 5,
    'check_period_days' => 30,
    'check_period_label' => 'چک 30 روزه',
];
```

---

## ⚠️ Backward Compatibility

### ساختار قدیم (Index.php):
```php
// ساختار قدیم: [1, 2, 3] - فقط price_type_ids
$selectedPrices = [1, 2, 3];

// ✅ تبدیل خودکار به ساختار جدید
$this->selectedPrices = [
    1 => [1, 2, 3], // فقط تیراژ کارتن
];
$this->selectedTiers = [1];
```

این تضمین می‌کند که کاتالوگ‌های قدیمی بدون خطا کار کنند.

---

## 🧪 تست

### Test 1: کاتالوگ با قیمت نقدی
```
تیراژ: کارتن
قیمت: نقدی
انتظار: یک ستون "کارتن - نقدی"
```

### Test 2: کاتالوگ با قیمت چک
```
تیراژ: کارتن
قیمت: چک
دوره‌ها: 30، 60، 90 روز
انتظار: سه ستون "کارتن - چک 30"، "کارتن - چک 60"، "کارتن - چک 90"
```

### Test 3: کاتالوگ با چند تیراژ
```
تیراژ: زیرکارتن، کارتن
قیمت: نقدی، چک (30، 60)
انتظار: 6 ستون
  - زیرکارتن - نقدی
  - زیرکارتن - چک 30
  - زیرکارتن - چک 60
  - کارتن - نقدی
  - کارتن - چک 30
  - کارتن - چک 60
```

### Test 4: کاتالوگ قدیمی
```
ساختار: [1, 2, 3] (قدیم)
انتظار: تبدیل به [1 => [1,2,3]] + کار کند بدون خطا
```

---

## 📈 بهبود Performance

### قبل:
- N+1 queries برای دریافت قیمت‌ها
- JSON decode برای هر محصول

### بعد:
- ✅ Eager loading: `->with('prices')`
- ✅ Collection groupBy (بدون query اضافی)
- ✅ Cache: 2 ساعت

---

## 🐛 مشکلات برطرف شده

### ❌ مشکل 1: قیمت‌های چک نمایش داده نمی‌شدند
**علت:** کد از `price_matrix` استفاده می‌کرد که دوره‌های چک را نداشت  
**راه‌حل:** خواندن مستقیم از `product_prices`

### ❌ مشکل 2: قیمت‌ها صفر بودند
**علت:** `price_matrix` بروز نمی‌شد  
**راه‌حل:** استفاده از relation `prices` که همیشه بروز است

### ❌ مشکل 3: گارانتی نمایش داده نمی‌شد
**علت:** فیلد `warranty_months` در `price_matrix` نبود  
**راه‌حل:** استفاده از `warranty_type` و `warranty_duration` از جدول

---

## 🚀 Migration Path

### مرحله 1: ✅ انجام شده
- به‌روزرسانی کد کاتالوگ برای استفاده از `product_prices`
- اضافه کردن پشتیبانی از قیمت‌های چک
- نمایش اطلاعات گارانتی

### مرحله 2: آینده (اختیاری)
- حذف کامل فیلد `price_matrix` از جدول `product_supplier`
- Migration برای پاک‌سازی داده‌های قدیمی

---

## 📚 فایل‌های تغییر یافته

1. **Backend:**
   - `app/Livewire/Supplier/Catalog/Preview.php` (خطوط 287-349)

2. **Frontend:**
   - `resources/views/pdf/supplier-catalog.blade.php` (خطوط 158-268)

---

## 📞 Support

**مشکلی پیش آمد؟**
- مستندات: `.kiro/docs/catalog-price-matrix-upgrade.md`
- مستندات ماتریس: `.kiro/docs/check-price-system-v2.md`
- Git: `git log --grep="catalog price matrix"`

---

**آخرین بروزرسانی:** 2026-08-27  
**نسخه:** 2.0.0  
**وضعیت:** Production Ready ✅
