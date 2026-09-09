# ⚡ راهنمای سریع UI تیراژها - Quick Reference

## 🎯 برای Developers

### 📍 کجا چه چیزی اضافه شد؟

#### 1. صفحه محصول (خریداران)
**فایل:** `resources/views/pages/products/show.blade.php`

```php
// قبل از ماتریس قیمت (خط ~380)
{{-- 📘 Info Box: راهنمای تیراژها برای کاربران --}}

// بعد از toggle گارانتی (خط ~500)  
{{-- 📊 Quick Summary: خلاصه سریع تیراژها --}}

// در header های جدول (خط ~540)
<th x-data="{ showTooltip: false }">
    <!-- Tooltip -->
</th>

// بعد از جدول (خط ~780)
{{-- Scroll hint - بهبود یافته --}}

// بعد از راهنما (خط ~810)
{{-- 📞 CTA: تماس با تأمین‌کننده --}}

// در کارت تأمین‌کننده (خط ~850)
data-supplier-card
id="supplier-card-{{ $ps->supplier->id }}"
```

---

#### 2. صفحه قیمت‌گذاری (تأمین‌کنندگان)
**فایل:** `resources/views/livewire/supplier/products/edit-price.blade.php`

```php
// بعد از توضیحات Section 2 (خط ~370)
{{-- 📘 Info Box: توضیح تیراژها --}}

// شامل:
// - توضیح اصلی
// - مثال عملی محاسباتی (جدید!)
// - نمایش واحد بسته‌بندی
```

---

#### 3. Component تیراژ
**فایل:** `resources/views/components/price-tier-table.blade.php`

```php
// در header (خط ~15)
<div class="flex-1">
    <div class="flex items-center gap-3">
        <!-- Title + Tooltip -->
    </div>
    <div class="mt-2 flex items-center gap-2">
        <!-- Badge برای range -->
    </div>
</div>
```

---

## 🎨 استایل‌های استفاده شده

### Info Boxes:
```html
<!-- آبی - اطلاعات عمومی -->
<div class="bg-gradient-to-l from-indigo-50 to-blue-50 border-r-4 border-indigo-500">

<!-- سبز - مثال‌های عملی -->
<div class="bg-gradient-to-l from-green-50 to-emerald-50 border border-green-200">

<!-- نارنجی - CTA / هشدار -->
<div class="bg-gradient-to-l from-amber-50 to-orange-50 border-2 border-amber-400">
```

### Tooltips:
```html
<div x-data="{ showTooltip: false }">
    <button @mouseenter="showTooltip = true" 
            @mouseleave="showTooltip = false">
        <svg><!-- info icon --></svg>
    </button>
    <div x-show="showTooltip" 
         x-cloak
         class="absolute ... bg-gray-900 text-white">
        <!-- محتوا -->
        <div class="absolute -top-1 ... rotate-45"></div> <!-- Arrow -->
    </div>
</div>
```

### Badges:
```html
<span class="inline-flex items-center px-3 py-1.5 bg-indigo-50 border border-indigo-200 rounded-lg">
    <span class="text-xs font-bold text-indigo-900">تیراژ 1:</span>
    <span class="text-xs text-indigo-700">1 تا 19 عدد</span>
</span>
```

---

## 🔧 Alpine.js Patterns

### Tooltip:
```javascript
x-data="{ showTooltip: false }"
@mouseenter="showTooltip = true"
@mouseleave="showTooltip = false"
x-show="showTooltip"
x-cloak
```

### Scroll با Highlight:
```javascript
onclick="
    event.preventDefault(); 
    const el = document.getElementById('supplier-card-{{ $id }}');
    el.scrollIntoView({behavior: 'smooth', block: 'start'});
    el.classList.add('ring-4', 'ring-amber-400');
    setTimeout(() => el.classList.remove('ring-4', 'ring-amber-400'), 2000);
"
```

---

## 📱 Responsive Classes

```html
<!-- Text sizes -->
text-xs sm:text-sm md:text-base lg:text-lg

<!-- Padding -->
p-2 sm:p-3 md:p-4 lg:p-5

<!-- Grid -->
grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4

<!-- Hidden on mobile -->
hidden md:block

<!-- Only mobile -->
md:hidden
```

---

## 🧪 تست کردن

### چک‌لیست تست:
- [ ] Info box ها در دسکتاپ درست نمایش داده می‌شوند
- [ ] Tooltip ها با hover کار می‌کنند
- [ ] CTA به کارت تأمین‌کننده scroll می‌کند
- [ ] Highlight effect ظاهر و محو می‌شود
- [ ] در موبایل scroll hint نمایش داده می‌شود
- [ ] Number formatting صحیح است (1,000 نه 1000)
- [ ] مثال‌های عملی با داده‌های واقعی کار می‌کنند
- [ ] Dark mode درست کار می‌کند

### URLs تست:
```
# صفحه محصول
http://127.0.0.1:8000/products/{slug}

# قیمت‌گذاری تأمین‌کننده
http://127.0.0.1:8000/supplier/products/{id}/edit-price

# ادمین - ایجاد محصول
http://127.0.0.1:8000/admin/products/create
```

---

## 🐛 مشکلات احتمالی و راه‌حل

### Tooltip نمایش داده نمی‌شود:
```bash
# بررسی Alpine.js
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

# بررسی x-cloak
<style>
    [x-cloak] { display: none !important; }
</style>
```

### Number format کار نمی‌کند:
```php
// استفاده از number_format
{{ number_format($value) }}  // ✅
{{ $value }}                 // ❌
```

### Scroll به تأمین‌کننده کار نمی‌کند:
```php
// اطمینان از وجود ID
id="supplier-card-{{ $ps->supplier->id }}"

// اطمینان از وجود data attribute
data-supplier-card
```

---

## 📦 Dependencies

```json
{
  "alpine": "^3.x",
  "tailwindcss": "^3.x",
  "heroicons": "^2.x"
}
```

---

## 🔄 بروزرسانی در آینده

اگر می‌خواهید این بخش‌ها را تغییر دهید:

### اضافه کردن تیراژ جدید:
1. در ادمین: `products/create` یا `products/edit`
2. تیراژ جدید خودکار در همه جا نمایش داده می‌شود

### تغییر متن Info Box:
```php
// edit-price.blade.php - Line ~375
<h4>💡 تیراژ چیست و چرا اهمیت دارد؟</h4>
<p>متن شما...</p>
```

### تغییر رنگ‌ها:
```css
/* از این رنگ‌ها استفاده کنید */
--blue: from-blue-50 to-indigo-50
--green: from-green-50 to-emerald-50  
--amber: from-amber-50 to-orange-50
--purple: from-purple-50 to-blue-50
```

---

## 💡 Tips & Best Practices

1. **همیشه `number_format()` استفاده کنید** برای اعداد
2. **Tooltip ها کوتاه نگه دارید** - max 3 خط
3. **Info box ها در بالای بخش قرار بگیرند** - نه پایین
4. **CTA ها باید واضح و action-oriented باشند**
5. **از emoji استفاده کنید** برای جلب توجه (اما نه زیاد!)

---

## 📞 پشتیبانی

مشکلی پیش آمد؟
- مستندات: `.kiro/docs/tier-ui-improvements-final.md`
- Code: فایل‌های ذکر شده در بالا
- Git: `git log --grep="tier UI"`

---

**آخرین بروزرسانی:** 2026-08-27  
**نسخه:** 1.0
