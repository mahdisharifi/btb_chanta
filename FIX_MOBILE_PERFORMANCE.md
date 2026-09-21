# رفع کندی موبایل - راهنمای فوری

## 🔥 مشکل اصلی: فایل‌های JavaScript سنگین

### فایل‌های مشکل‌دار:

| فایل | حجم | وضعیت |
|------|-----|-------|
| app.js | 667 KB | 🔴 بسیار سنگین |
| wardley.js | 598 KB | 🔴 بسیار سنگین |
| cytoscape.js | 431 KB | 🔴 سنگین |
| katex.js | 255 KB | 🟡 متوسط |
| app.css | 186 KB | 🟡 متوسط |

**مجموع JavaScript:** ~2 MB

---

## 🚀 راه‌حل‌های فوری

### 1. Lazy Load کردن فایل‌های غیرضروری

فایل‌های `wardley.js`، `cytoscape.js` و `katex.js` احتمالاً برای **نمودارها و دیاگرام‌ها** هستند.

**اگر در همه صفحات استفاده نمی‌شوند، فقط در صفحات مورد نیاز لود کنید:**

```blade
{{-- در صفحه اصلی و محصول - فقط app.js لود شود --}}
<script src="{{ asset('build/assets/app-BXZlWWpH.js') }}" defer></script>

{{-- فقط در صفحاتی که دیاگرام دارند --}}
@if(Route::is('pages.diagrams'))
<script src="{{ asset('build/assets/wardley-L42UT6IY-Ct7yOSLg.js') }}" defer></script>
<script src="{{ asset('build/assets/cytoscape.esm-CkSuTymj.js') }}" defer></script>
@endif
```

---

### 2. Code Splitting

فایل `app.js` را تقسیم کنید:

```javascript
// vite.config.js یا webpack.mix.js
export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor': ['alpinejs', 'livewire'],
                    'diagrams': ['mermaid', 'cytoscape'],
                    'charts': ['chart.js'],
                }
            }
        }
    }
});
```

---

### 3. Defer همه Scripts

```blade
{{-- همه فایل‌ها باید defer باشند --}}
<script src="{{ asset('build/assets/app-BXZlWWpH.js') }}" defer></script>
```

---

### 4. Preload Critical Assets

```blade
<head>
    {{-- فونت --}}
    <link rel="preload" href="{{ asset('fonts/Vazirmatn.woff2') }}" as="font" type="font/woff2" crossorigin>
    
    {{-- CSS اصلی --}}
    <link rel="preload" href="{{ asset('build/assets/app-BvImUGO6.css') }}" as="style">
    
    {{-- LCP Image --}}
    <link rel="preload" as="image" href="{{ $product->main_image ?? '' }}" fetchpriority="high">
</head>
```

---

### 5. حذف کدهای استفاده نشده

```bash
# بررسی کدهای استفاده نشده
npx depcheck

# حذف پکیج‌های غیرضروری
npm uninstall package-name
```

---

## 📊 هدف

| متریک | حال حاضر | هدف |
|-------|----------|-----|
| حجم JS | ~2 MB | <500 KB |
| حجم CSS | 186 KB | <100 KB |
| تعداد درخواست | بالا | <30 |
| LCP موبایل | 4s | <2.5s |

---

## ⚡ سریع‌ترین راه‌حل (5 دقیقه)

### مرحله 1: بررسی اینکه آیا wardley/cytoscape استفاده می‌شود

```bash
grep -r "wardley\|cytoscape\|katex" resources/views/
```

### مرحله 2: اگر استفاده نمی‌شوند، حذف کنید

```bash
npm uninstall wardley cytoscape katex
npm run build
```

### مرحله 3: اگر استفاده می‌شوند، فقط در صفحات خاص لود کنید

---

## 🔍 بررسی بیشتر

اجازه بدید ببینیم این فایل‌ها کجا استفاده می‌شوند:


## 🎯 راه‌حل نهایی

مشخص شد که فایل‌های سنگین **Mermaid و Diagrams** فقط در صفحات **فلوچارت ادمین** استفاده می‌شوند.

### مرحله 1: بررسی فایل app.js

این فایل‌ها از طریق Vite bundling شده‌اند. باید بررسی کنیم که آیا می‌توانیم آن‌ها را lazy load کنیم.

### مرحله 2: استفاده از Dynamic Import

در فایل JavaScript اصلی:

```javascript
// resources/js/app.js

// Instead of:
// import mermaid from 'mermaid';

// Use:
const loadMermaid = async () => {
    if (document.querySelector('.mermaid-diagram')) {
        const mermaid = await import('mermaid');
        mermaid.initialize({ startOnLoad: true });
    }
};

// فقط در صفحات ادمین
if (window.location.pathname.includes('/admin/flowcharts')) {
    loadMermaid();
}
```

### مرحله 3: Lazy Load در Blade

```blade
{{-- در resources/views/livewire/admin/flowcharts/preview.blade.php --}}

@once
@push('scripts')
<script>
    // Dynamic import - فقط در این صفحه لود می‌شود
    import('{{ asset('build/assets/wardley-L42UT6IY-Ct7yOSLg.js') }}');
    import('{{ asset('build/assets/cytoscape.esm-CkSuTymj.js') }}');
</script>
@endpush
@endonce
```

---

## 🚀 سریع‌ترین راه‌حل (اگر Vite دارید)

### فایل `vite.config.js` را ویرایش کنید:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [laravel(['resources/css/app.css', 'resources/js/app.js'])],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    // جدا کردن کتابخانه‌های سنگین
                    'mermaid': ['mermaid'],
                    'diagrams': ['cytoscape', 'd3'],
                    'vendor': ['alpinejs', 'axios', 'livewire'],
                }
            }
        }
    }
});
```

### سپس:

```bash
npm run build
```

---

## 📊 نتیجه مورد انتظار

| متریک | قبل | بعد |
|-------|-----|-----|
| حجم app.js | 667 KB | ~200 KB |
| حجم در صفحه محصول | 2 MB | ~500 KB |
| LCP موبایل | 4s | ~2s |

---

## ⚠️ اگر نمی‌دانید چگونه انجام دهید

ساده‌ترین راه: از یک توسعه‌دهنده فرانت‌اند بخواهید این کار را انجام دهد.

یا منتظر بمانید تا من یک راه‌حل آماده ارائه دهم.
