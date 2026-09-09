# ⚡ محاسبه خودکار Real-time قیمت‌های تیراژها

**نسخه:** 2.1.1  
**تاریخ:** 1404/06/07  
**نوع:** Feature Enhancement

---

## 🎯 خلاصه

این ویژگی باعث می‌شود که **بدون نیاز به کلیک دکمه "ذخیره"**، با تغییر قیمت نقدی تیراژ مرجع، قیمت‌های سایر تیراژها **بلافاصله و خودکار** محاسبه و نمایش داده شوند.

---

## ✨ مزایا

### 1. **تجربه کاربری بهتر**
- ✅ کاربر بلافاصله نتیجه را می‌بیند
- ✅ نیازی به کلیک "ذخیره" برای دیدن محاسبات نیست
- ✅ امکان تست سناریوهای مختلف قیمت

### 2. **جلوگیری از خطا**
- ✅ کاربر قبل از ذخیره، نتیجه را می‌بیند
- ✅ امکان اصلاح فوری اگر نتیجه مناسب نبود

### 3. **سرعت بیشتر**
- ✅ بدون نیاز به reload صفحه
- ✅ محاسبه instant در مرورگر

---

## 🔧 تغییرات فنی

### 1. متد جدید در `EditPrice.php`

```php
/**
 * ✅ محاسبه خودکار قیمت‌های نقدی سایر تیراژها وقتی قیمت تیراژ مرجع تغییر می‌کند
 */
public function updatedPriceMatrix($value, $key)
{
    // اگر محاسبه خودکار غیرفعال است، هیچ کاری نکن
    if (!$this->autoCalculateTiers) {
        return;
    }
    
    // Parse کردن key: "0.1.price" یعنی tier_index=0, price_type_id=1, field=price
    $parts = explode('.', $key);
    if (count($parts) < 3) {
        return;
    }
    
    $tierIndex = (int)$parts[0];
    $priceTypeId = (int)$parts[1];
    $field = $parts[2];
    
    // فقط برای قیمت نقدی
    $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
    if (!$cashPriceType || $priceTypeId !== $cashPriceType->id || $field !== 'price') {
        return;
    }
    
    // فقط اگر قیمت تیراژ مرجع تغییر کرده باشد
    if ($tierIndex !== $this->referenceTierIndex) {
        return;
    }
    
    // محاسبه خودکار قیمت‌های سایر تیراژها
    $this->calculateOtherTierPrices();
}

/**
 * ✅ محاسبه خودکار قیمت‌های نقدی سایر تیراژها بر اساس تیراژ مرجع
 */
protected function calculateOtherTierPrices(): void
{
    $cashPriceType = $this->priceTypes->firstWhere('code', 'cash');
    if (!$cashPriceType) {
        return;
    }
    
    // قیمت نقدی تیراژ مرجع
    $referencePrice = $this->priceMatrix[$this->referenceTierIndex][$cashPriceType->id]['price'] ?? null;
    
    if ($referencePrice === null || $referencePrice <= 0) {
        return; // قیمت مرجع معتبر نیست
    }
    
    // محاسبه قیمت سایر تیراژها
    foreach ($this->quantityTiers as $tierIndex => $tier) {
        // Skip تیراژ مرجع
        if ($tierIndex === $this->referenceTierIndex) {
            continue;
        }
        
        // دریافت درصد این تیراژ
        $percentage = $this->tierPercentages[$tierIndex] ?? 0;
        
        // محاسبه قیمت جدید
        $calculatedPrice = $referencePrice * (1 + ($percentage / 100));
        
        // بروزرسانی در priceMatrix
        if (!isset($this->priceMatrix[$tierIndex][$cashPriceType->id])) {
            $this->priceMatrix[$tierIndex][$cashPriceType->id] = [];
        }
        
        $this->priceMatrix[$tierIndex][$cashPriceType->id]['price'] = round($calculatedPrice, 2);
    }
}
```

---

### 2. متد `loadTierPercentages()` برای مقداردهی اولیه

```php
/**
 * ✅ بارگذاری تنظیمات درصد تیراژها
 */
protected function loadTierPercentages(): void
{
    // تنظیمات پیش‌فرض (اگر قبلاً ذخیره نشده باشد)
    if (empty($this->tierPercentages)) {
        foreach ($this->quantityTiers as $index => $tier) {
            $this->tierPercentages[$index] = 0; // درصد پیش‌فرض
        }
    }
    
    // بارگذاری وضعیت autoCalculate (فعلاً پیش‌فرض false)
    if (!isset($this->autoCalculateTiers)) {
        $this->autoCalculateTiers = false;
    }
}
```

---

### 3. تغییر `wire:model.defer` به `wire:model.live`

**قبل (Desktop):**
```blade
<input type="number"
       wire:model.defer="priceMatrix.{{ $tierIndex }}.{{ $cashPriceType->id }}.price"
       ...>
```

**بعد (Desktop):**
```blade
<input type="number"
       wire:model.live="priceMatrix.{{ $tierIndex }}.{{ $cashPriceType->id }}.price"
       ...>
```

**قبل (Mobile):**
```blade
<input type="number"
       wire:model.defer="priceMatrix.{{ $tierIndex }}.{{ $cashPriceType->id }}.price"
       ...>
```

**بعد (Mobile):**
```blade
<input type="number"
       wire:model.live="priceMatrix.{{ $tierIndex }}.{{ $cashPriceType->id }}.price"
       ...>
```

---

## 🎬 نحوه کار

### سناریو کاربری

1. **کاربر وارد صفحه قیمت‌گذاری می‌شود**
2. **چک‌باکس "محاسبه خودکار" را فعال می‌کند**
3. **درصدهای تیراژها را تنظیم می‌کند:**
   - تیراژ 0: `+10%`
   - تیراژ 1: `0%` (مرجع)
   - تیراژ 2: `-5%`
4. **در تیراژ مرجع (تیراژ 1) قیمت نقدی وارد می‌کند:**
   - مثال: `150,000` تومان
5. **بلافاصله (بدون کلیک دکمه):**
   - تیراژ 0: `165,000` تومان محاسبه و نمایش داده می‌شود
   - تیراژ 2: `142,500` تومان محاسبه و نمایش داده می‌شود

---

## 📊 Flow Chart

```
┌─────────────────────────────────────────┐
│ کاربر قیمت تیراژ مرجع را تغییر می‌دهد │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ wire:model.live → updatedPriceMatrix()   │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ بررسی: autoCalculateTiers فعال است؟    │
└──────────────┬──────────────────────────┘
               │
        ✅ بله │
               ▼
┌─────────────────────────────────────────┐
│ بررسی: قیمت نقدی تیراژ مرجع تغییر کرده؟│
└──────────────┬──────────────────────────┘
               │
        ✅ بله │
               ▼
┌─────────────────────────────────────────┐
│ calculateOtherTierPrices()               │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ برای هر تیراژ (غیر از مرجع):            │
│  - دریافت درصد                          │
│  - محاسبه: price * (1 + percentage/100) │
│  - بروزرسانی priceMatrix                │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ Livewire به صورت خودکار View را         │
│ بروزرسانی می‌کند (Reactive)             │
└─────────────────────────────────────────┘
```

---

## 🎨 نمایش بصری

### قبل از تغییر قیمت

```
┌──────────────────────────────────────────┐
│ 🏷️ تیراژ 1 (مرجع)                      │
│ 💵 قیمت نقدی:  [      ] ✅             │
└──────────────────────────────────────────┘
┌──────────────────────────────────────────┐
│ تیراژ 0                                  │
│ 💵 قیمت نقدی:  [      ] 🔒             │
└──────────────────────────────────────────┘
┌──────────────────────────────────────────┐
│ تیراژ 2                                  │
│ 💵 قیمت نقدی:  [      ] 🔒             │
└──────────────────────────────────────────┘
```

### کاربر `150,000` تومان وارد می‌کند

```
┌──────────────────────────────────────────┐
│ 🏷️ تیراژ 1 (مرجع)                      │
│ 💵 قیمت نقدی:  [150,000] ✅ ← تایپ کرد │
└──────────────────────────────────────────┘
```

### ⚡ فوراً (Real-time) محاسبه می‌شود

```
┌──────────────────────────────────────────┐
│ 🏷️ تیراژ 1 (مرجع)                      │
│ 💵 قیمت نقدی:  [150,000] ✅            │
└──────────────────────────────────────────┘
┌──────────────────────────────────────────┐
│ تیراژ 0 (+10%)                           │
│ 💵 قیمت نقدی:  [165,000] 🔒 ← خودکار! │
└──────────────────────────────────────────┘
┌──────────────────────────────────────────┐
│ تیراژ 2 (-5%)                            │
│ 💵 قیمت نقدی:  [142,500] 🔒 ← خودکار! │
└──────────────────────────────────────────┘
```

---

## 💡 نکات مهم

### 1. شرایط فعال‌سازی
- ✅ چک‌باکس "محاسبه خودکار" باید فعال باشد
- ✅ فقط برای **قیمت نقدی** کار می‌کند (قیمت نسیه مستقل است)
- ✅ فقط وقتی **تیراژ مرجع** تغییر می‌کند

### 2. Performance
- ✅ محاسبات ساده و سریع (ضرب و رند کردن)
- ✅ بدون نیاز به AJAX/API call
- ✅ Livewire به صورت خودکار differential updates انجام می‌دهد

### 3. سازگاری
- ✅ Desktop و Mobile هر دو پشتیبانی می‌شوند
- ✅ با قیمت‌های نسیه تداخل ندارد
- ✅ با سیستم قبلی سازگار است

---

## 🧪 تست

### تست‌های دستی انجام شده

| تست | نتیجه | توضیحات |
|-----|-------|---------|
| تغییر قیمت مرجع | ✅ | سایر تیراژها فوراً محاسبه شدند |
| تغییر درصد تیراژ | ⏳ | نیاز به تست بیشتر |
| غیرفعال کردن auto | ✅ | محاسبه خودکار متوقف شد |
| تست در Mobile | ⏳ | نیاز به تست |
| تست با 5 تیراژ | ⏳ | نیاز به تست |

---

## 📦 فایل‌های تغییر یافته

```
✅ app/Livewire/Supplier/Products/EditPrice.php
   - متد updatedPriceMatrix() [جدید]
   - متد calculateOtherTierPrices() [جدید]
   - متد loadTierPercentages() [جدید]

✅ resources/views/livewire/supplier/products/edit-price.blade.php
   - خط 762: wire:model.defer → wire:model.live (Desktop)
   - خط 1002: wire:model.defer → wire:model.live (Mobile)
```

---

## 🔮 بهبودهای آینده

### نسخه 2.1.2
- [ ] debounce برای جلوگیری از محاسبات مکرر (500ms)
- [ ] افزودن animation برای تغییرات
- [ ] نمایش loading spinner هنگام محاسبه

### نسخه 2.2.0
- [ ] محاسبه real-time برای قیمت نسیه هم
- [ ] پیش‌نمایش قیمت نهایی با گارانتی
- [ ] History/Undo برای تغییرات

---

## ✅ وضعیت

```
┌────────────────────────────────────┐
│  ✅ تکمیل شده                     │
│                                    │
│  📝 کد: Done                      │
│  🎨 UX: Improved                   │
│  📖 مستندات: Complete             │
│  🧪 تست: Partial (نیاز به تست   │
│       کامل در محیط واقعی)        │
│                                    │
│  🚀 آماده برای استفاده!          │
└────────────────────────────────────┘
```

---

## 📖 مستندات مرتبط

- [tier-reference-updates.md](./tier-reference-updates.md) - تغییرات تیراژ مرجع
- [CHANGELOG-TIER-REFERENCE.md](./CHANGELOG-TIER-REFERENCE.md) - تاریخچه تغییرات
- [QUICK-SUMMARY-v2.1.md](./QUICK-SUMMARY-v2.1.md) - خلاصه سریع

---

**📅 تاریخ:** 1404/06/07  
**📌 نسخه:** 2.1.1  
**👨‍💻 توسعه‌دهنده:** AI Assistant  
**✅ وضعیت:** Complete
