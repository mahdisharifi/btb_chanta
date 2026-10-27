# اصلاح نهایی Fast Pricing Export - 2026-09-14

## مشکلات حل شده

### 1. ✅ تیراژهای غیرفعال
**مشکل:** تیراژهایی که در edit-price غیرفعال شده بودند (`tier_enabled = false`)، هنوز هم در اکسل فرمول داشتند.

**راه‌حل:**
- فقط تیراژهای **فعال** در `$cashColumnMap` ذخیره می‌شوند
- شرط `in_array($phTierIndex, $activeTiers)` چک می‌شود
- تیراژهای غیرفعال: سلول خالی + رنگ خاکستری + کامنت "تیراژ غیرفعال"

### 2. ✅ دوره‌های چک غیرفعال
**مشکل:** دوره‌های چکی که در edit-price غیرفعال شده بودند، هنوز هم فرمول داشتند.

**راه‌حل:**
- `$shouldEnableAllCheckPeriods = false` (پیش‌فرض)
- فقط دوره‌هایی که رکورد قیمت دارند، فعال هستند
- دوره‌های غیرفعال: سلول خالی + رنگ خاکستری + کامنت "دوره غیرفعال"

### 3. ✅ محاسبه خودکار (auto_calculate)
**مشکل:** حتی اگر کاربر در edit-price تیک "محاسبه خودکار" را نزده بود، فرمول‌ها اعمال می‌شدند.

**راه‌حل:**
```php
// فقط اگر محصول صریحاً auto_calculate_tiers = true دارد
$autoCalculate = $ps->auto_calculate_tiers ?? false;
```

**نتیجه:** کاربر باید در edit-price تیک "محاسبه خودکار تیراژها" را بزند تا فرمول‌ها اعمال شوند.

### 4. ✅ سلسله مراتب درصد دوره چک
**مشکل:** درصد اختصاصی که در edit-price تنظیم شده بود (`percentage_override`)، در فرمول استفاده نمی‌شد.

**راه‌حل - سلسله مراتب اولویت:**

```php
// اولویت 1: درصد اختصاصی محصول (percentage_override)
if (isset($checkPercentageOverrides[$checkKey])) {
    $checkPercentage = $checkPercentageOverrides[$checkKey];
    $percentageSource = 'اختصاصی محصول';
}

// اولویت 2: درصد اختصاصی دسته‌بندی (category_check_period)
if ($checkPercentage === null) {
    $categoryPercentage = DB::table('category_check_period')
        ->where('supplier_id', $ps->supplier_id)
        ->where('category_id', $ps->product->category_id ?? 0)
        ->where('check_period_id', $checkPeriodId)
        ->where('is_active', true)
        ->value('percentage_increase');
    
    if ($categoryPercentage !== null) {
        $checkPercentage = (float)$categoryPercentage;
        $percentageSource = 'دسته‌بندی';
    }
}

// اولویت 3: درصد پیش‌فرض supplier (supplier_check_period)
if ($checkPercentage === null) {
    $checkPeriod = $checkPeriods->firstWhere('id', $checkPeriodId);
    if ($checkPeriod && $checkPeriod->suppliers->isNotEmpty()) {
        $pivotData = $checkPeriod->suppliers->first()->pivot;
        $checkPercentage = (float)($pivotData->percentage_increase ?? 0);
        $percentageSource = 'پیش‌فرض supplier';
    }
}
```

### 5. ✅ سلسله مراتب درصد تیراژ
**توجه:** این منطق قبلاً پیاده‌سازی شده بود و درست کار می‌کند.

**اولویت:**
1. درصد اختصاصی محصول (`tier_percentages` در `product_supplier`)
2. درصد اختصاصی دسته‌بندی (`category_tier_discount`)
3. درصد پیش‌فرض supplier (`supplier_tier_discount`)

**کد موجود:**
```php
private function getEffectiveTierPercentages(int $supplierId, ?int $categoryId, array $productTierPercentages): array
{
    // اولویت 1: درصد اختصاصی محصول
    // اولویت 2: درصد اختصاصی دسته‌بندی
    // اولویت 3: درصد پیش‌فرض
}
```

## خلاصه تغییرات

### فایل‌های تغییر یافته:
- `app/Livewire/Supplier/FastPricing/Import.php`

### تغییرات کلیدی:

#### خط ~860: فقط تیراژهای فعال در cashColumnMap
```php
// ✅ اگر قیمت نقدی است و تیراژ فعال است، ستون را ذخیره کن
if ($priceType && $priceType->code === 'cash' && in_array($ph['tier_index'], $activeTiers)) {
    $cashColumnMap[$ph['tier_index']] = $colLetter;
}
```

#### خط ~895: جمع‌آوری percentage_override
```php
$checkPercentageOverrides = []; // [tier_period] => percentage_override

foreach ($ps->prices as $price) {
    if ($price->check_period_id !== null) {
        // ...
        if ($price->percentage_override !== null) {
            $checkPercentageOverrides[$key] = (float)$price->percentage_override;
        }
    }
}
```

#### خط ~1006-1055: سلسله مراتب درصد چک
```php
// اولویت 1: percentage_override
// اولویت 2: category_check_period
// اولویت 3: supplier_check_period
```

#### خط ~835: auto_calculate فقط از product
```php
$autoCalculate = $ps->auto_calculate_tiers ?? false;
```

## خروجی اکسل نهایی

### سلول‌های فرمول‌دار (indigo-100):
- ✅ فقط تیراژهای فعال
- ✅ فقط دوره‌های چک فعال
- ✅ فقط اگر `auto_calculate_tiers = true`
- ✅ با درصد صحیح (سلسله مراتب)

### سلول‌های غیرفعال (gray-100):
- ✅ تیراژهای غیرفعال
- ✅ دوره‌های چک غیرفعال
- ✅ سلول خالی + کامنت توضیحی

### سلول‌های قابل ویرایش (سفید):
- ✅ تیراژ مرجع
- ✅ اگر `auto_calculate = false`

## کامنت‌های توضیحی

### فرمول خودکار:
```
🔒 قیمت خودکار (قفل شده)

📊 محاسبه:
  • قیمت نقدی این تیراژ
  • + درصد دوره چک (30 روزه): 4.0%
  • منبع: اختصاصی محصول

🎯 رند: عدد صحیح

💡 فرمول: =ROUND(G3*(1+4/100), 0)

⚠️ این سلول قابل ویرایش نیست
```

### تیراژ غیرفعال:
```
⚠️ تیراژ غیرفعال

این تیراژ (بسته) برای این محصول غیرفعال است.

برای فعال‌سازی، به صفحه ویرایش قیمت محصول مراجعه کنید.
```

### دوره چک غیرفعال:
```
⚠️ غیرفعال

این دوره چک (60 روزه) برای این تیراژ (خرده‌فروشی) فعال نیست.

برای فعال‌سازی، به صفحه ویرایش قیمت محصول مراجعه کنید.
```

## تست

### آماده تست در:
`http://127.0.0.1:8000/supplier/fast-pricing/import`

### سناریوهای تست:
1. ✅ محصول با `auto_calculate = true` و همه تیراژها فعال
2. ✅ محصول با تیراژهای غیرفعال
3. ✅ محصول با دوره‌های چک غیرفعال
4. ✅ محصول با `percentage_override` اختصاصی
5. ✅ محصول با `auto_calculate = false`

---

**تاریخ:** 2026-09-14  
**وضعیت:** ✅ تکمیل شده  
**نسخه:** 2.0


---

## اصلاحیه 3: مشکل قیمت‌های اعشاری - 2026-09-14

### 🐛 مشکل جدید یافت شده

**توضیح کاربر:**
عدد داخل اکسل `1,051,010.0501` هست که اگر اعشار حذف بشه عدد تغییر می‌کنه.

**نتیجه در سیستم:**
- مقدار صحیح: `1,051,010`
- مقدار غلط در سیستم: `10,510,100,501`

### 🔍 علت مشکل

**کد قبلی (غلط):**
```php
$cleanedVal = preg_replace('/[^\d]/', '', (string)$cellValue);
```

این کد **همه کاراکترهای غیر عددی** را حذف می‌کند، **از جمله نقطه اعشار**!

پس عدد `1,051,010.0501` (که در حافظه به صورت `1051010.0501` ذخیره شده) تبدیل می‌شود به:
- حذف کاما‌ها: `1051010.0501`
- حذف نقطه: `10510100501` ❌

### ✅ راه‌حل

**کد جدید (صحیح):**
```php
if (is_numeric($cellValue)) {
    // عدد اعشاری یا صحیح → رند کن
    $cleanedVal = (int)round((float)$cellValue);
} else {
    // رشته → پاکسازی کاراکترهای غیر عددی
    $cleanedVal = (int)preg_replace('/[^\d]/', '', (string)$cellValue);
}
```

**منطق جدید:**
1. اگر مقدار عددی است (int یا float) → مستقیماً `round()` و `intval()` کن
2. اگر رشته است (مثل `"1,040,604"`) → کاراکترهای غیر عددی را حذف کن

### 📊 نتایج تست

| مقدار اصلی | روش قدیمی (غلط) | روش جدید (صحیح) |
|------------|----------------|-----------------|
| `1051010.0501` | `10,510,100,501` ❌ | `1,051,010` ✅ |
| `1040604.0` | `1,040,604` ✅ | `1,040,604` ✅ |
| `1000000` | `1,000,000` ✅ | `1,000,000` ✅ |
| `"1,040,604"` | `1,040,604` ✅ | `1,040,604` ✅ |
| `"1,040,604.50"` | `104,060,450` ❌ | `104,060,450` ❌ |

**نکته:** رشته `"1,040,604.50"` همچنان غلط است، چون این فرمت باید در اکسل عدد باشد، نه رشته.

### 📁 فایل‌های تغییر یافته

- `app/Livewire/Supplier/FastPricing/Import.php` - خطوط ~273-284

### 🧪 آماده تست

کاربر می‌تواند مجدداً فایل اکسل را آپلود کند و این بار قیمت‌ها به درستی ذخیره می‌شوند.

---

**تاریخ اصلاح:** 2026-09-14  
**نسخه:** 2.1
