# 🐛 Debug مشکل ذخیره

## مشکل گزارش شده:
- ✅ حالت پیشرفته (custom): ذخیره می‌شود
- ❌ حالت درصد (percentage): ذخیره نمی‌شود
- ❌ حالت مبلغ ثابت (fixed): ذخیره نمی‌شود

## تست سناریو 1: درصد ثابت

### مراحل:
1. با گارانتی: ✅ فعال
2. نوع گارانتی: مدت‌دار
3. مدت: 365 روز
4. **هزینه آپشن: درصد ثابت = 5**
5. قیمت نقدی: 100000
6. کلیک ذخیره

### خطاهای احتمالی:
- [ ] warrantyValue validation
- [ ] hasWarranty validation
- [ ] warrantyProvider validation
- [ ] warrantyCoverageType validation
- [ ] warrantyTypeCoverage validation
- [ ] warrantyDurationDays validation

---

## بررسی Validation:

### Validation Rules (بعد از اصلاح):
```php
'warrantyType' => ['required', 'string', 'in:none,percentage,fixed,custom']
'warrantyValue' => [
    'nullable',
    'numeric',
    'min:0',
    function ($attribute, $value, $fail) {
        // فقط اگر نوع percentage یا fixed است، value الزامی است
        if (in_array($this->warrantyType, ['percentage', 'fixed']) && empty($value)) {
            $fail('مقدار هزینه گارانتی الزامی است.');
        }
    }
]
```

### بررسی:
- `warrantyType = 'percentage'` ✅
- `warrantyValue = 5` ✅ (not empty)
- `hasWarranty = true` ✅
- `warrantyProvider = 'manufacturer'` ✅
- `warrantyCoverageType = 'full'` ✅
- `warrantyTypeCoverage = 'limited'` ✅
- `warrantyDurationDays = 365` ✅

---

## نکات مهم:

1. **empty() در PHP:**
   - `empty(0)` = **true** ❌
   - `empty('0')` = **true** ❌
   - `empty(5)` = **false** ✅

2. **مشکل احتمالی:**
   - اگر کاربر عدد `0` وارد کند، validation خطا می‌دهد!
   - باید از `is_null()` یا `=== null` استفاده کنیم

---

## راه حل پیشنهادی:

```php
'warrantyValue' => [
    'nullable',
    'numeric',
    'min:0',
    function ($attribute, $value, $fail) {
        // فقط اگر نوع percentage یا fixed است، value الزامی است
        if (in_array($this->warrantyType, ['percentage', 'fixed']) && $value === null) {
            $fail('مقدار هزینه گارانتی الزامی است.');
        }
        
        // بررسی محدوده درصد
        if ($this->warrantyType === 'percentage' && $value !== null && $value > 100) {
            $fail('درصد گارانتی نمی‌تواند بیشتر از 100 باشد.');
        }
        
        // بررسی محدوده مبلغ
        if ($this->warrantyType === 'fixed' && $value !== null && $value > 999999999) {
            $fail('مبلغ گارانتی بیش از حد مجاز است.');
        }
    },
]
```

---

## دستور تست سریع:

```bash
# در مرورگر Developer Tools > Console
console.log('warrantyType:', @this.warrantyType);
console.log('warrantyValue:', @this.warrantyValue);
console.log('hasWarranty:', @this.hasWarranty);
```

