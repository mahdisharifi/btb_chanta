# سلسله مراتب درصد افزایش قیمت چک

## 🎯 هدف
تعیین درصد افزایش قیمت چک نسبت به قیمت نقدی با یک سیستم سلسله مراتبی که انعطاف‌پذیری لازم را برای تامین‌کنندگان فراهم می‌کند.

## 📊 سلسله مراتب اولویت

### 1️⃣ اولویت اول: درصد اختصاصی محصول (Product-Level Override)
**مکان ذخیره:** `product_prices.percentage_override`

- **استفاده:** زمانی که یک محصول خاص نیاز به درصد متفاوتی دارد
- **مثال:** محصول A با دوره 30 روزه → 8% (به جای 5% پیش‌فرض)
- **وضعیت:** ✅ پیاده‌سازی شده

```php
// نمونه:
$productPrice = ProductPrice::create([
    'product_id' => 123,
    'check_period_id' => 1, // 30 روزه
    'percentage_override' => 8.0, // این درصد اولویت دارد
    // ...
]);
```

### 2️⃣ اولویت دوم: درصد دسته‌بندی (Category-Level)
**مکان ذخیره:** `category_check_period` (جدول pivot آینده)

- **استفاده:** زمانی که یک دسته‌بندی کامل نیاز به درصد خاصی دارد
- **مثال:** تمام محصولات دسته "چاپ دیجیتال" → 6% برای 30 روزه
- **وضعیت:** 🔜 آماده برای پیاده‌سازی آینده (TODO)

```php
// نمونه (آینده):
Category::find(5)->checkPeriods()->attach(1, [
    'percentage_increase' => 6.0
]);
```

### 3️⃣ اولویت سوم: درصد پیش‌فرض دوره چک (Check Period Default)
**مکان ذخیره:** `supplier_check_period.percentage_increase`

- **استفاده:** درصد پیش‌فرض برای همه محصولات
- **مثال:** دوره 30 روزه → 5% برای همه محصولات
- **وضعیت:** ✅ پیاده‌سازی شده

```php
// تنظیم در صفحه: /supplier/settings/check-periods
// ذخیره در: supplier_check_period.percentage_increase
```

## 🔄 نحوه محاسبه

### فلوچارت تصمیم‌گیری:
```
┌─────────────────────────────────────┐
│  محاسبه قیمت چکی                    │
└─────────────┬───────────────────────┘
              │
              ▼
    ┌─────────────────────┐
    │ percentage_override  │
    │  در ProductPrice    │
    │    موجود است?       │
    └─────┬───────────────┘
          │
    ┌─────▼─────┐
    │    بله    │────► از percentage_override استفاده کن
    └───────────┘
          │ خیر
          ▼
    ┌─────────────────────┐
    │ درصد دسته‌بندی      │
    │   موجود است?        │
    │  (آینده - TODO)    │
    └─────┬───────────────┘
          │
    ┌─────▼─────┐
    │    بله    │────► از درصد دسته‌بندی استفاده کن
    └───────────┘
          │ خیر
          ▼
    ┌─────────────────────┐
    │ درصد پیش‌فرض        │
    │ دوره چک موجود است? │
    └─────┬───────────────┘
          │
    ┌─────▼─────┐
    │    بله    │────► از درصد check_period استفاده کن
    └───────────┘
          │ خیر
          ▼
    ┌─────────────────────┐
    │  قیمت چک = null    │
    │ (قیمت‌گذاری نشده)   │
    └─────────────────────┘
```

### کد PHP:
```php
use App\Models\CheckPeriod;
use App\Models\ProductPrice;

// محاسبه با سلسله مراتب
$cashPrice = 100000; // تومان
$checkPeriodId = 1; // 30 روزه
$supplierId = 5;
$productId = 123;
$percentageOverride = null; // یا مقدار اختصاصی

$checkPrice = ProductPrice::calculateCheckPrice(
    $cashPrice,
    $checkPeriodId,
    $supplierId,
    $productId,
    $percentageOverride
);

// مثال با percentage_override:
$checkPrice = ProductPrice::calculateCheckPrice(
    cashPrice: 100000,
    checkPeriodId: 1,
    supplierId: 5,
    productId: 123,
    percentageOverride: 8.0 // اولویت اول - اینجا استفاده می‌شود
);
// نتیجه: 108,000 تومان
```

## 📝 نمونه‌های کاربردی

### سناریو 1: بدون override (استفاده از پیش‌فرض)
```
قیمت نقدی: 100,000 تومان
دوره: 30 روزه
درصد پیش‌فرض دوره: 5%
percentage_override: null

قیمت چک = 100,000 × (1 + 5/100) = 105,000 تومان
```

### سناریو 2: با override محصول
```
قیمت نقدی: 100,000 تومان
دوره: 30 روزه
درصد پیش‌فرض دوره: 5%
percentage_override: 8%  ← اولویت دارد

قیمت چک = 100,000 × (1 + 8/100) = 108,000 تومان
```

### سناریو 3: آینده - با درصد دسته‌بندی
```
قیمت نقدی: 100,000 تومان
دوره: 30 روزه
درصد پیش‌فرض دوره: 5%
درصد دسته‌بندی: 6%  ← اولویت دارد
percentage_override: null

قیمت چک = 100,000 × (1 + 6/100) = 106,000 تومان
```

## 🗄️ ساختار Database

### جدول `product_prices`
```sql
CREATE TABLE product_prices (
    id BIGINT PRIMARY KEY,
    product_id BIGINT,
    check_period_id BIGINT,
    percentage_override DECIMAL(5,2) NULL COMMENT 'درصد اختصاصی این قیمت',
    price INT,
    -- ... سایر فیلدها
);
```

### جدول `supplier_check_period` (موجود)
```sql
CREATE TABLE supplier_check_period (
    id BIGINT PRIMARY KEY,
    supplier_id BIGINT,
    check_period_id BIGINT,
    percentage_increase DECIMAL(5,2) DEFAULT 0,
    label VARCHAR(100) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    -- ...
);
```

### جدول `category_check_period` (آینده - TODO)
```sql
-- پیاده‌سازی آینده:
CREATE TABLE category_check_period (
    id BIGINT PRIMARY KEY,
    category_id BIGINT,
    check_period_id BIGINT,
    supplier_id BIGINT,
    percentage_increase DECIMAL(5,2) DEFAULT 0,
    -- ...
);
```

## 🔧 متدهای کمکی

### CheckPeriod::getEffectivePercentage()
```php
/**
 * دریافت درصد مؤثر با توجه به سلسله مراتب
 */
public static function getEffectivePercentage(
    int $supplierId, 
    ?int $productId = null, 
    ?float $priceOverride = null, 
    int $checkPeriodId
): ?float
```

### ProductPrice::calculateCheckPrice()
```php
/**
 * محاسبه قیمت چک با سلسله مراتب
 */
public static function calculateCheckPrice(
    int $cashPrice, 
    int $checkPeriodId, 
    int $supplierId, 
    ?int $productId = null, 
    ?float $percentageOverride = null
): ?int
```

### ProductPrice::getEffectivePercentageAttribute
```php
/**
 * Accessor برای دریافت درصد مؤثر این قیمت
 */
$productPrice->effective_percentage; // float|null
```

## ⚠️ نکات مهم

1. **Null vs Zero:**
   - `null` = از سطح بعدی استفاده شود
   - `0` = قیمت چک = قیمت نقدی (هشدار)

2. **Performance:**
   - متد `getEffectivePercentage` برای هر محاسبه query می‌زند
   - پیشنهاد: cache کردن در صورت استفاده گسترده

3. **Validation:**
   - همه درصدها باید بین 0 تا 100 باشند
   - در UI باید به کاربر نشان داده شود که کدام درصد استفاده می‌شود

4. **Migration Path:**
   - فعلاً: Product override + Check period default
   - آینده: اضافه کردن Category level

## 📅 تاریخچه تغییرات

- **2026-09-01:** پیاده‌سازی اولیه با Product override + Check period default
- **آینده:** اضافه کردن Category-level percentage

## 🔗 فایل‌های مرتبط

- `app/Models/CheckPeriod.php` - متد `getEffectivePercentage()`
- `app/Models/ProductPrice.php` - متد `calculateCheckPrice()`
- `database/migrations/2026_09_01_130540_add_percentage_override_to_product_prices_table.php`
- `app/Livewire/Supplier/Settings/ManageCheckPeriods.php` - مدیریت درصد پیش‌فرض

---

**نویسنده:** Kiro AI  
**تاریخ:** 2026-09-01  
**وضعیت:** فعال و در حال استفاده
