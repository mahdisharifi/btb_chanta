# اصلاح فرمول‌های خودکار در Fast Pricing Export

## تاریخ: 2026-09-14
## وضعیت: ✅ اصلاح شده

---

## مشکل

در صفحه Fast Pricing Import (`/supplier/fast-pricing/import`)، خروجی اکسل فاقد موارد زیر بود:
- ❌ فرمول‌های خودکار محاسبه قیمت بر اساس درصدهای تیراژ
- ❌ فرمول‌های خودکار محاسبه قیمت چک بر اساس دوره‌های چک
- ❌ رنگ‌های پس‌زمینه برای سلول‌های خودکار (indigo-100)
- ❌ کامنت‌های توضیحی برای فرمول‌ها

## علت مشکل

بررسی دیتابیس نشان داد:
- ✅ Supplier 34 تنظیمات `tier-discounts` (4 تیراژ با درصدهای +6%, 0%, -5%, -10%) دارد
- ✅ Supplier 34 تنظیمات `check-periods` (3 دوره با درصدهای +4%, +7%, +1%) دارد
- ❌ اما **هیچ محصولی** `auto_calculate_tiers = true` ندارد (0 از 195 محصول)

### منطق قبلی (اشتباه):
```php
$autoCalculate = $ps->auto_calculate_tiers ?? false;
```

این یعنی فرمول‌ها **فقط** زمانی اعمال می‌شدند که محصول صریحاً `auto_calculate_tiers = true` داشت.

## راه‌حل

### 1. تغییر منطق `auto_calculate`:

**منطق جدید:**
```php
// محاسبه درصدهای مؤثر (با اولویت‌بندی)
$tierPercentages = $this->getEffectiveTierPercentages(...);

// بررسی اینکه آیا supplier تنظیمات غیر-صفر دارد
$hasSupplierTierSettings = !empty($tierPercentages) && array_filter($tierPercentages, function($p) { 
    return $p != 0; 
});

// auto_calculate فعال است اگر:
// 1. محصول صریحاً auto_calculate_tiers = true دارد
// 2. یا supplier تنظیمات tier-discount دارد (پیش‌فرض)
$autoCalculate = ($ps->auto_calculate_tiers ?? false) || $hasSupplierTierSettings;
```

**مزایا:**
- ✅ اگر supplier تنظیمات کلی دارد، به صورت **پیش‌فرض** فرمول‌ها اعمال می‌شوند
- ✅ محصولات می‌توانند با `auto_calculate_tiers = false` این رفتار را غیرفعال کنند
- ✅ backward compatible: رفتار قبلی حفظ می‌شود

### 2. تغییر منطق `check periods`:

**منطق قبلی:**
```php
$enabledCheckPeriods = [];
foreach ($ps->prices as $price) {
    if ($price->check_period_id !== null && $price->price > 0) {
        $key = $price->tier_index . '_' . $price->check_period_id;
        $enabledCheckPeriods[$key] = true;
    }
}
```

**مشکل:** اگر محصول هیچ قیمت چکی نداشته باشد، `$enabledCheckPeriods` خالی است و تمام دوره‌ها غیرفعال نشان داده می‌شوند.

**منطق جدید:**
```php
$enabledCheckPeriods = [];
$hasAnyCheckPrice = false;

foreach ($ps->prices as $price) {
    if ($price->check_period_id !== null) {
        $hasAnyCheckPrice = true;
        if ($price->price > 0) {
            $key = $price->tier_index . '_' . $price->check_period_id;
            $enabledCheckPeriods[$key] = true;
        }
    }
}

// اگر محصول هیچ قیمت چکی نداشته باشد، تمام دوره‌های supplier را فعال فرض می‌کنیم
$shouldEnableAllCheckPeriods = !$hasAnyCheckPrice && $checkPeriods->isNotEmpty();

// در بررسی هر سلول:
$isCheckPeriodEnabled = $shouldEnableAllCheckPeriods || isset($enabledCheckPeriods[$checkKey]);
```

**مزایا:**
- ✅ محصولات جدید (بدون قیمت چک) به صورت پیش‌فرض تمام دوره‌های supplier را دارند
- ✅ کاربر فقط قیمت نقدی وارد می‌کند، قیمت‌های چک خودکار محاسبه می‌شوند
- ✅ محصولات قدیمی (با قیمت چک) رفتار قبلی را حفظ می‌کنند

## نتیجه اصلاحات

### فرمول‌های تیراژ (نقدی):
برای محصولاتی که supplier تنظیمات tier-discount دارد:

**تیراژ 0 (خرده‌فروشی):**
```excel
=ROUND(G3*(1+6/100), 0)
```
رنگ پس‌زمینه: `#E0E7FF` (indigo-100)
کامنت: "🔒 قیمت خودکار (قفل شده) - قیمت مرجع (کارتن) + 6%"

**تیراژ 1 (کارتن):** 
قابل ویرایش (مرجع) - بدون فرمول

**تیراژ 2 (بسته):**
```excel
=ROUND(G3*(1+-5/100), 0)
```
رنگ پس‌زمینه: `#E0E7FF`
کامنت: "قیمت مرجع (کارتن) - 5%"

**تیراژ 3 (پالت):**
```excel
=ROUND(G3*(1+-10/100), 0)
```
رنگ پس‌زمینه: `#E0E7FF`
کامنت: "قیمت مرجع (کارتن) - 10%"

### فرمول‌های چکی:
برای هر تیراژ و هر دوره چک:

**مثال: کارتن - چک 30 روزه (درصد: 4%):**
```excel
=ROUND(G3*(1+4/100), 0)
```
رنگ پس‌زمینه: `#E0E7FF`
کامنت: "🔒 قیمت خودکار - قیمت نقدی این تیراژ + 4%"

## اولویت‌بندی درصدها

همان اولویت قبلی حفظ شده:

1. **درصد اختصاصی محصول** (edit-price) - بالاترین اولویت
2. **درصد اختصاصی دسته‌بندی** (`category_tier_discount`)
3. **درصد پیش‌فرض supplier** (`supplier_tier_discount`)

## تست

### سناریو 1: محصول بدون تنظیمات اختصاصی
- ✅ فرمول‌ها از تنظیمات کلی supplier استفاده می‌کنند
- ✅ رنگ پس‌زمینه indigo-100 اعمال می‌شود
- ✅ سلول‌ها قفل می‌شوند (protection)

### سناریو 2: محصول با تنظیمات اختصاصی
- ✅ فرمول‌ها از درصدهای اختصاصی محصول استفاده می‌کنند
- ✅ اولویت‌بندی درست کار می‌کند

### سناریو 3: محصول بدون قیمت چک
- ✅ تمام دوره‌های supplier به صورت پیش‌فرض فعال هستند
- ✅ فرمول‌های چک اعمال می‌شوند
- ✅ کاربر فقط قیمت نقدی وارد می‌کند

### سناریو 4: supplier بدون تنظیمات
- ✅ `$hasSupplierTierSettings = false`
- ✅ `$autoCalculate = false`
- ✅ فرمول‌ها اعمال نمی‌شوند (رفتار قبلی)

## فایل‌های تغییر یافته

- `app/Livewire/Supplier/FastPricing/Import.php`
  - متد `downloadTemplate()` - خطوط ~818-840
  - متد `downloadTemplate()` - خطوط ~870-885

## مستندات مرتبط

- `.kiro/docs/fast-pricing-checklist.md` - چک‌لیست کامل Fast Pricing
- `.kiro/docs/fast-pricing-final-report.md` - گزارش نهایی پیاده‌سازی
- `app/Livewire/Supplier/Settings/ManageTierDiscounts.php` - مدیریت درصدهای تیراژ
- `app/Livewire/Supplier/Settings/ManageCheckPeriods.php` - مدیریت دوره‌های چک

## نکات مهم

1. **فرمول‌ها فقط در export اعمال می‌شوند**، نه در import
2. **رنگ پس‌زمینه** (`#E0E7FF`) نشان‌دهنده سلول‌های قفل شده است
3. **کامنت‌ها** اطلاعات کاملی درباره فرمول و منبع آن ارائه می‌دهند
4. **قفل سلول‌ها** با `setLocked(PROTECTION_PROTECTED)` انجام می‌شود
5. **تیراژ مرجع** از جدول `supplier_tier_percentages` خوانده می‌شود (معمولاً تیراژ 1)

---

**تاریخ اصلاح:** 2026-09-14  
**نسخه:** 1.0  
**وضعیت:** ✅ تکمیل شده
