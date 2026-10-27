# تنظیمات تیراژ و دوره چک در Fast Pricing Export

## منبع تنظیمات

### 1. تیراژهای فعال/غیرفعال (`tier_enabled`)
**منبع**: جدول `product_supplier`, فیلد `tier_enabled` (JSON)

**مثال**:
```json
{
  "0": true,  // خرده‌فروشی: فعال
  "1": true,  // کارتن: فعال
  "2": false, // بسته: غیرفعال
  "3": true   // پالت: فعال
}
```

**منطق در export**:
- اگر `tier_enabled` خالی یا null باشد → همه تیراژها فعال
- اگر `tier_enabled[tier_index] = false` → تیراژ غیرفعال
- تیراژهای غیرفعال در فایل اکسل:
  - سلول خالی
  - رنگ پس‌زمینه: `#F3F4F6` (gray-100)
  - فونت: خاکستری، italic
  - کامنت توضیحی: "⚠️ تیراژ غیرفعال"

### 2. دوره‌های چک فعال/غیرفعال
**منبع**: جدول `product_supplier_price`, بر اساس **وجود رکورد**

**منطق**:
```php
// برای هر محصول، بررسی می‌کنیم که آیا رکورد قیمت چک دارد یا نه
$hasCheckPrice = $ps->prices->contains(function($price) use ($tierIndex, $periodId) {
    return $price->tier_index === $tierIndex 
        && $price->check_period_id === $periodId
        && $price->price > 0;
});

// اگر رکورد دارد → فعال
// اگر رکورد ندارد → بستگی دارد...
```

**حالت 1: محصول قیمت چک دارد**
- فقط دوره‌هایی که رکورد دارند، فعال هستند
- بقیه دوره‌ها غیرفعال (خاکستری)

**حالت 2: محصول هیچ قیمت چکی ندارد**
- **تمام دوره‌های supplier به صورت پیش‌فرض فعال هستند**
- فرمول خودکار اعمال می‌شود
- کاربر فقط قیمت نقدی وارد می‌کند، بقیه خودکار محاسبه می‌شوند

```php
$shouldEnableAllCheckPeriods = !$hasAnyCheckPrice && $checkPeriods->isNotEmpty();
$isCheckPeriodEnabled = $shouldEnableAllCheckPeriods || isset($enabledCheckPeriods[$checkKey]);
```

## صفحه مدیریت (edit-price)

**URL**: `/supplier/products/{id}/edit-price`

### تنظیمات تیراژ:
- ✅ فعال/غیرفعال هر تیراژ
- ✅ درصد تخفیف/افزایش نسبت به تیراژ مرجع
- ✅ تیراژ مرجع (معمولاً تیراژ 1 = کارتن)

### تنظیمات دوره چک:
- ✅ فعال/غیرفعال هر دوره برای هر تیراژ
- ✅ mode: `default` (درصد کلی) | `percentage` (درصد اختصاصی) | `manual` (قیمت دستی)
- ✅ درصد اختصاصی برای هر دوره

## جریان کار (Workflow)

### Scenario 1: محصول جدید (بدون قیمت)
1. کاربر از `/fast-pricing/import` فایل دانلود می‌کند
2. تمام تیراژها فعال هستند (پیش‌فرض)
3. تمام دوره‌های چک فعال هستند (پیش‌فرض)
4. فرمول‌های خودکار برای تیراژها و چک‌ها اعمال شده
5. کاربر فقط قیمت نقدی تیراژ مرجع را وارد می‌کند
6. بقیه قیمت‌ها خودکار محاسبه می‌شوند

### Scenario 2: محصول با تنظیمات اختصاصی
1. کاربر در `/products/{id}/edit-price` تیراژ 2 را غیرفعال می‌کند
2. دوره چک 30 روزه را برای تیراژ 0 غیرفعال می‌کند
3. فایل export:
   - تیراژ 2 → همه سلول‌ها خاکستری و خالی
   - تیراژ 0 - چک 30 روزه → سلول خاکستری و خالی
   - بقیه سلول‌ها با فرمول خودکار

### Scenario 3: محصول با قیمت‌های manual
1. کاربر قبلاً قیمت‌های دستی وارد کرده
2. در edit-price، برخی دوره‌ها mode = `manual` هستند
3. فایل export:
   - قیمت‌های manual → مقدار عددی (بدون فرمول)
   - قیمت‌های default → فرمول خودکار (اگر auto_calculate فعال باشد)

## کد مهم

### بررسی تیراژ فعال:
```php
$tierEnabled = $ps->tier_enabled ?? [];
$activeTiers = [];

if (empty($tierEnabled)) {
    // پیش‌فرض: همه فعال
    $activeTiers = array_keys($quantityTiers);
} else {
    foreach ($tierEnabled as $tier => $enabled) {
        if ($enabled) {
            $activeTiers[] = $tier;
        }
    }
}

$isTierActive = in_array($phTierIndex, $activeTiers);
```

### بررسی دوره چک فعال:
```php
// جمع‌آوری دوره‌های فعال از دیتابیس
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

// پیش‌فرض: اگر محصول هیچ قیمت چکی نداشته باشد، همه فعال
$shouldEnableAllCheckPeriods = !$hasAnyCheckPrice && $checkPeriods->isNotEmpty();

// بررسی هر سلول
$checkKey = $phTierIndex . '_' . $checkPeriodId;
$isCheckPeriodEnabled = $shouldEnableAllCheckPeriods || isset($enabledCheckPeriods[$checkKey]);
```

## نکات مهم

1. **پیش‌فرض: همه فعال**
   - برای محصولات جدید، تمام تیراژها و دوره‌های چک فعال هستند

2. **تنظیمات محصول > تنظیمات supplier**
   - اگر محصول تنظیمات اختصاصی دارد، از آن استفاده می‌شود

3. **وجود رکورد = فعال**
   - اگر قیمت چک در دیتابیس باشد، آن دوره فعال است

4. **رنگ‌ها و استایل‌ها**
   - فعال + خودکار: `#E0E7FF` (indigo-100)
   - غیرفعال: `#F3F4F6` (gray-100)
   - قابل ویرایش (مرجع): سفید

---

**تاریخ:** 2026-09-14  
**وضعیت:** ✅ پیاده‌سازی شده
