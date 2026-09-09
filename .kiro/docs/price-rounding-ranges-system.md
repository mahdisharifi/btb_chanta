# 📊 سیستم رُند کردن قیمت بر اساس رنج‌های قیمتی

**تاریخ ایجاد:** 2026-09-05  
**وضعیت:** ✅ فعال و آماده استفاده  
**نسخه:** 2.0

---

## 🎯 هدف

تأمین‌کنندگان می‌توانند برای **رنج‌های قیمتی مختلف**، استراتژی متفاوتی برای رُند کردن قیمت‌ها تعیین کنند.

### مثال کاربردی:
- قیمت‌های زیر 100,000 تومان → رُند هر 500 به بالا
- قیمت‌های 100,000 تا 500,000 → رُند هر 1,000 به بالا
- قیمت‌های بالای 500,000 → رُند هر 5,000 به بالا

---

## 🏗️ معماری سیستم

### جدول Database: `supplier_price_rounding_ranges`

```sql
CREATE TABLE supplier_price_rounding_ranges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NOT NULL,
    
    -- رنج قیمتی
    min_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    max_price DECIMAL(15,2) NULL, -- NULL = بدون محدودیت
    
    -- تنظیمات رُند
    rounding_method ENUM('ceil', 'floor', 'nearest') NOT NULL DEFAULT 'ceil',
    rounding_step INT UNSIGNED NOT NULL DEFAULT 100,
    
    -- اولویت
    priority INT NOT NULL DEFAULT 0,
    
    -- وضعیت
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    INDEX idx_supplier_range_active (supplier_id, min_price, max_price, is_active)
);
```

---

## 📝 انواع Rounding

### 1. **ceil** (رُند به بالا)
همیشه به سمت بالا گرد می‌کند

```php
// قیمت: 49,600 | مبلغ مرجع: 500
ceil(49600 / 500) * 500 = 50,000 ✅
```

### 2. **floor** (رُند به پایین)
همیشه به سمت پایین گرد می‌کند

```php
// قیمت: 49,600 | مبلغ مرجع: 500
floor(49600 / 500) * 500 = 49,500 ✅
```

### 3. **nearest** (نزدیک‌ترین)
به نزدیک‌ترین مقدار گرد می‌کند

```php
// قیمت: 49,600 | مبلغ مرجع: 500
round(49600 / 500) * 500 = 50,000 ✅

// قیمت: 49,200 | مبلغ مرجع: 500
round(49200 / 500) * 500 = 49,000 ✅
```

---

## 🎛️ مبالغ مرجع مجاز

```php
[100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000]
```

تأمین‌کننده می‌تواند برای هر رنج یکی از این مقادیر را انتخاب کند.

---

## 🔄 Flow سیستم

### محاسبه قیمت چک:

```
1. EditPrice یا FastPricing → قیمت نقدی + درصد
2. PriceRoundingHelper::calculateCheckPrice()
3. PriceRoundingHelper::round()
4. PriceRoundingHelper::findRangeForPrice()
   ├─ رنج پیدا شد؟
   │  ├─ بله → range->roundPrice()
   │  └─ خیر → roundByType() (سیستم قدیمی)
5. قیمت نهایی رُند شده
```

### انتخاب رنج مناسب:

```sql
SELECT * FROM supplier_price_rounding_ranges
WHERE supplier_id = ?
  AND is_active = true
  AND min_price <= ?
  AND (max_price IS NULL OR max_price > ?)
ORDER BY priority ASC
LIMIT 1
```

---

## 📂 فایل‌های کلیدی

### Backend:
- **Migration:** `database/migrations/2026_09_05_120257_create_supplier_price_rounding_ranges_table.php`
- **Model:** `app/Models/SupplierPriceRoundingRange.php`
- **Helper:** `app/Helpers/PriceRoundingHelper.php`
- **Component:** `app/Livewire/Supplier/Settings/ManagePriceRoundingRanges.php`

### Frontend:
- **View:** `resources/views/livewire/supplier/settings/manage-price-rounding-ranges.blade.php`
- **Route:** `routes/supplier.php` → `/supplier/settings/price-rounding`

---

## 🚀 نحوه استفاده

### برای تأمین‌کننده:

1. **رفتن به صفحه تنظیمات:**
   ```
   http://127.0.0.1:8000/supplier/settings/price-rounding
   ```

2. **افزودن رنج جدید:**
   - کلیک روی "افزودن رنج جدید"
   - تعیین حداقل و حداکثر قیمت
   - انتخاب نوع رُند (ceil/floor/nearest)
   - انتخاب مبلغ مرجع (100-50000)
   - تعیین اولویت
   - ذخیره

3. **بروزرسانی قیمت‌های موجود:**
   - کلیک روی "بروزرسانی همه قیمت‌ها"
   - تأیید عملیات

### برای توسعه‌دهنده:

```php
use App\Helpers\PriceRoundingHelper;

// محاسبه قیمت چک با رُند خودکار
$roundedPrice = PriceRoundingHelper::calculateCheckPrice(
    cashPrice: 100000,
    percentage: 5.5,
    supplier: $supplierId
);

// یافتن رنج مناسب
$range = PriceRoundingHelper::findRangeForPrice(
    price: 150000,
    supplierId: $supplierId
);

// رُند کردن مستقیم
$rounded = PriceRoundingHelper::round(
    price: 123456.78,
    supplier: $supplier
);
```

---

## 🧪 مثال‌های کاربردی

### مثال 1: تنظیمات پیشنهادی برای فروشگاه کالای الکترونیک

```
رنج 1: 0 - 100,000 | ceil | 500
  → 49,600 → 50,000
  → 78,300 → 78,500

رنج 2: 100,000 - 500,000 | ceil | 1,000
  → 150,200 → 151,000
  → 349,800 → 350,000

رنج 3: 500,000 - 2,000,000 | ceil | 5,000
  → 756,200 → 760,000
  → 1,234,567 → 1,235,000

رنج 4: بالای 2,000,000 | nearest | 10,000
  → 2,567,000 → 2,570,000
  → 5,123,456 → 5,120,000
```

### مثال 2: تنظیمات پیشنهادی برای فروشگاه پوشاک

```
رنج 1: 0 - 50,000 | ceil | 100
  → 12,345 → 12,400
  → 34,567 → 34,600

رنج 2: 50,000 - 200,000 | ceil | 500
  → 78,900 → 79,000
  → 156,200 → 156,500

رنج 3: بالای 200,000 | ceil | 1,000
  → 234,567 → 235,000
  → 567,890 → 568,000
```

---

## ⚙️ API Reference

### Model: `SupplierPriceRoundingRange`

#### Constants:
```php
const ROUNDING_METHODS = [
    'ceil' => 'رُند به بالا',
    'floor' => 'رُند به پایین',
    'nearest' => 'نزدیک‌ترین',
];

const ALLOWED_STEPS = [
    100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000
];
```

#### Methods:
```php
// Scopes
->active()                          // فقط رنج‌های فعال
->orderedByPriority()              // مرتب‌سازی بر اساس اولویت
->forSupplier($supplierId)         // رنج‌های یک تأمین‌کننده
->containsPrice($price)            // رنج‌هایی که قیمت را شامل می‌شوند

// Methods
->containsPrice($price): bool      // آیا قیمت در این رنج است؟
->roundPrice($price): float        // رُند کردن قیمت
->generateExamples(): array        // تولید 3 مثال
->hasOverlapWith(): bool           // آیا با رنج دیگری همپوشانی دارد؟

// Attributes
->range_label                      // "0 تا 100,000 تومان"
->method_label                     // "رُند به بالا"
->step_label                       // "500"
```

### Helper: `PriceRoundingHelper`

```php
// محاسبه قیمت چک
PriceRoundingHelper::calculateCheckPrice(
    float $cashPrice,
    float $percentage,
    int|Supplier $supplier
): float

// رُند کردن قیمت
PriceRoundingHelper::round(
    float $price,
    int|Supplier $supplier
): float

// یافتن رنج
PriceRoundingHelper::findRangeForPrice(
    float $price,
    int $supplierId
): ?SupplierPriceRoundingRange

// بروزرسانی دسته‌جمعی
PriceRoundingHelper::updateSupplierPrices(
    int $supplierId,
    ?string $newRoundingType = null
): array // ['updated' => count, 'errors' => count]

// تعداد قیمت‌های تحت تأثیر
PriceRoundingHelper::getAffectedPricesCount(
    int $supplierId
): int
```

---

## 🔒 امنیت

### Validations:
- ✅ تأیید مالکیت supplier در تمام عملیات
- ✅ Validation مقادیر (min_price ≥ 0, max_price > min_price)
- ✅ بررسی همپوشانی رنج‌ها
- ✅ مقادیر مجاز برای method و step
- ✅ Transaction safety در save/update

### Authorization:
```php
// فقط تأمین‌کننده مالک می‌تواند رنج‌ها را مدیریت کند
$supplierId = Auth::user()->supplier->id;
$range = SupplierPriceRoundingRange::forSupplier($supplierId)->find($id);
```

---

## 🐛 Troubleshooting

### مشکل: رنج‌ها اعمال نمی‌شوند
```php
// بررسی:
1. آیا رنج فعال است؟ (is_active = true)
2. آیا supplier_id درست است؟
3. آیا قیمت در محدوده رنج است؟

// Debug:
$range = PriceRoundingHelper::findRangeForPrice($price, $supplierId);
dd($range); // باید رنج مناسب را برگرداند
```

### مشکل: قیمت‌ها بروزرسانی نشدند
```php
// اجرای دستی Command:
php artisan prices:update-check --supplier=1 --dry-run
php artisan prices:update-check --supplier=1 --force

// یا از UI:
// کلیک روی "بروزرسانی همه قیمت‌ها"
```

### مشکل: همپوشانی رنج‌ها
```
خطا: "این رنج با رنج‌های دیگر همپوشانی دارد"

راه‌حل:
- رنج‌ها نباید همپوشانی داشته باشند
- مثال اشتباه: 
  رنج 1: 0-100k
  رنج 2: 50k-200k ❌ (همپوشانی)
  
- مثال صحیح:
  رنج 1: 0-100k
  رنج 2: 100k-200k ✅
```

---

## 🔄 Backward Compatibility

سیستم به صورت خودکار با سیستم قدیمی (`price_rounding_type`) سازگار است:

```php
// اگر رنج تعریف شده باشد → از رنج استفاده می‌کند
// اگر رنج تعریف نشده باشد → از price_rounding_type استفاده می‌کند

// مثال:
$supplier = Supplier::find(1);

// حالت 1: دارای رنج
if ($supplier->activePriceRoundingRanges()->exists()) {
    // از سیستم رنج‌ها استفاده می‌شود
}

// حالت 2: بدون رنج (fallback)
else {
    // از price_rounding_type استفاده می‌شود
}
```

---

## 📊 Performance

### بهینه‌سازی‌های انجام شده:
- ✅ Index روی `(supplier_id, min_price, max_price, is_active)`
- ✅ Query با LIMIT 1 برای یافتن رنج
- ✅ Cache-friendly: رنج‌ها کم تغییر می‌کنند
- ✅ Eager loading در loadRanges()

### پیشنهادات:
```php
// اگر تعداد رنج‌ها زیاد شد، می‌توان cache کرد:
Cache::remember("supplier_{$supplierId}_ranges", 3600, function() use ($supplierId) {
    return SupplierPriceRoundingRange::forSupplier($supplierId)
        ->active()
        ->orderedByPriority()
        ->get();
});
```

---

## 📚 مستندات مرتبط

- [سیستم قیمت‌گذاری چک v2](check-price-system-v2.md)
- [پیشنهاد طراحی رنج‌ها](price-rounding-ranges-proposal.md)

---

## ✅ Checklist استقرار

قبل از استقرار در production:

- [ ] Migration اجرا شده است
- [ ] Route در `routes/supplier.php` اضافه شده
- [ ] Cache پاک شده (`php artisan cache:clear`)
- [ ] تست با supplier واقعی
- [ ] بررسی Performance با حجم بالای داده
- [ ] آموزش تأمین‌کنندگان برای استفاده از UI جدید
- [ ] پشتیبان Database گرفته شده

---

**تاریخ بروزرسانی:** 2026-09-05  
**نگارنده:** Kiro AI  
**وضعیت:** آماده استفاده در Production ✅
