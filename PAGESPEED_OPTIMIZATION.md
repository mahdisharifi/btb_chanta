# بهینه‌سازی PageSpeed Insights و Core Web Vitals

## 📊 وضعیت فعلی

نتایج PageSpeed Insights برای صفحه محصول:
- **Mobile:** بررسی شده
- **Desktop:** بررسی شده

---

## 🎯 اهداف Core Web Vitals

### 1. LCP (Largest Contentful Paint)
- **هدف:** زیر 2.5 ثانیه
- **مشکل رایج:** تصاویر سنگین

### 2. INP (Interaction to Next Paint)
- **هدف:** زیر 200 میلی‌ثانیه
- **مشکل رایج:** JavaScript سنگین

### 3. CLS (Cumulative Layout Shift)
- **هدف:** زیر 0.1
- **مشکل رایج:** تصاویر بدون ابعاد

---

## 🚀 بهینه‌سازی‌های ضروری

### 1. بهینه‌سازی تصاویر

#### أ. استفاده از WebP/AVIF

```blade
{{-- Instead of --}}
<img src="{{ $product->image }}" alt="{{ $product->name }}">

{{-- Use --}}
<picture>
    <source srcset="{{ $product->image_webp }}" type="image/webp">
    <source srcset="{{ $product->image }}" type="image/jpeg">
    <img src="{{ $product->image }}" 
         alt="{{ $product->name }}"
         width="800" 
         height="600"
         loading="lazy"
         fetchpriority="low">
</picture>
```

#### ب. اضافه کردن ابعاد تصویر

```blade
<img src="{{ $product->image }}" 
     alt="{{ $product->name }}"
     width="800"           {{-- الزامی --}}
     height="600"          {{-- الزامی --}}
     loading="lazy"        {{-- برای تصاویر خارج viewport --}}
     fetchpriority="high"> {{-- برای LCP image --}}
```

#### ج. Preload LCP Image

```blade
{{-- در <head> --}}
@php
    $lcpImage = $product->main_image ?? null;
@endphp

@if($lcpImage)
<link rel="preload" as="image" href="{{ $lcpImage }}" fetchpriority="high">
@endif
```

---

### 2. بهینه‌سازی CSS

#### أ. Critical CSS Inline

```blade
{{-- در <head> --}}
<style>
    /* Critical CSS - فقط استایل‌های بالای صفحه */
    body { font-family: 'Vazirmatn', sans-serif; }
    .product-card { /* ... */ }
    .header { /* ... */ }
</style>

{{-- بقیه CSS به صورت async --}}
<link rel="preload" href="{{ asset('css/app.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="{{ asset('css/app.css') }}"></noscript>
```

#### ب. حذف CSS استفاده نشده

```bash
# نصب ابزار
npm install purgecss --save-dev

# اجرا
npx purgecss --css resources/css/app.css --content resources/views/**/*.blade.php --output public/css/
```

---

### 3. بهینه‌سازی JavaScript

#### أ. Defer/Async Scripts

```blade
{{-- Instead of --}}
<script src="{{ asset('js/app.js') }}"></script>

{{-- Use --}}
<script src="{{ asset('js/app.js') }}" defer></script>

{{-- Or for non-critical scripts --}}
<script src="{{ asset('js/analytics.js') }}" async></script>
```

#### ب. Code Splitting

```javascript
// webpack.mix.js
mix.js('resources/js/app.js', 'public/js')
   .extract(['alpinejs', 'livewire'])
   .version();
```

#### ج. Lazy Load Components

```javascript
// Instead of
import HeavyComponent from './HeavyComponent.vue';

// Use
const HeavyComponent = () => import('./HeavyComponent.vue');
```

---

### 4. Font Optimization

#### أ. Font Display Swap

```css
@font-face {
    font-family: 'Vazirmatn';
    src: url('/fonts/Vazirmatn.woff2') format('woff2');
    font-display: swap;  /* مهم! */
}
```

#### ب. Preload Fonts

```blade
{{-- در <head> --}}
<link rel="preload" href="{{ asset('fonts/Vazirmatn.woff2') }}" as="font" type="font/woff2" crossorigin>
```

#### ج. Fallback Font

```css
body {
    font-family: 'Vazirmatn', 'Tahoma', sans-serif;
}
```

---

### 5. کاهش HTTP Requests

#### أ. Combine Files

```javascript
// webpack.mix.js
mix.styles([
    'resources/css/reset.css',
    'resources/css/variables.css',
    'resources/css/app.css'
], 'public/css/all.css');

mix.scripts([
    'resources/js/alpine.js',
    'resources/js/livewire.js',
    'resources/js/app.js'
], 'public/js/all.js');
```

#### ب. Use Sprites

```css
.icon-home {
    background-image: url('/images/sprite.png');
    background-position: 0 -32px;
    width: 32px;
    height: 32px;
}
```

---

### 6. Server-Side Optimizations

#### أ. Enable Gzip/Brotli

(در `.htaccess` قبلی تنظیم شده)

#### ب. Use CDN

```env
# .env
ASSET_URL=https://cdn.chanta.ir
```

#### ج. Database Optimization

```bash
# Index optimization
php artisan migrate

# Query caching
php artisan config:cache
```

---

## 🔧 پیاده‌سازی در Laravel

### ایجاد Component برای تصاویر بهینه

```bash
php artisan make:component OptimizedImage
```<tool_call>str_replace<arg_key>oldStr</arg_key><arg_value># بهینه‌سازی PageSpeed Insights و Core Web Vitals


### فایل Component: `app/View/Components/OptimizedImage.php`

```php
<?php

namespace App\View\Components;

use Illuminate\View\Component;

class OptimizedImage extends Component
{
    public $src;
    public $alt;
    public $width;
    public $height;
    public $loading;
    public $fetchpriority;

    public function __construct(
        $src,
        $alt = '',
        $width = null,
        $height = null,
        $loading = 'lazy',
        $fetchpriority = 'low'
    ) {
        $this->src = $src;
        $this->alt = $alt;
        $this->width = $width;
        $this->height = $height;
        $this->loading = $loading;
        $this->fetchpriority = $fetchpriority;
    }

    public function render()
    {
        return view('components.optimized-image');
    }
}
```

### فایل View: `resources/views/components/optimized-image.blade.php`

```blade
<picture>
    @if(str_ends_with($src, '.jpg') || str_ends_with($src, '.jpeg'))
        <source srcset="{{ str_replace(['.jpg', '.jpeg'], '.webp', $src) }}" type="image/webp">
    @endif
    
    <img src="{{ $src }}"
         alt="{{ $alt }}"
         @if($width) width="{{ $width }}" @endif
         @if($height) height="{{ $height }}" @endif
         loading="{{ $loading }}"
         fetchpriority="{{ $fetchpriority }}">
</picture>
```

### استفاده:

```blade
{{-- LCP Image (تصویر اصلی محصول) --}}
<x-optimized-image 
    :src="$product->main_image"
    :alt="$product->name"
    width="800"
    height="600"
    loading="eager"
    fetchpriority="high" />

{{-- سایر تصاویر (lazy load) --}}
<x-optimized-image 
    :src="$product->thumbnail"
    :alt="$product->name"
    width="400"
    height="300"
    loading="lazy"
    fetchpriority="low" />
```

---

## 📋 چک‌لیست بهینه‌سازی

### قبل از شروع:
- [ ] نتایج PageSpeed Insights را بررسی کنید
- [ ] Core Web Vitals را شناسایی کنید
- [ ] LCP element را پیدا کنید

### تصاویر:
- [ ] همه تصاویر دارای `width` و `height` هستند
- [ ] تصاویر به WebP/AVIF تبدیل شده‌اند
- [ ] LCP image دارای `fetchpriority="high"` است
- [ ] تصاویر خارج viewport دارای `loading="lazy"` هستند

### CSS:
- [ ] Critical CSS inline شده
- [ ] CSS استفاده نشده حذف شده
- [ ] فایل‌های CSS minify شده

### JavaScript:
- [ ] Scripts دارای `defer` یا `async` هستند
- [ ] Code splitting انجام شده
- [ ] Heavy components lazy load شده

### Fonts:
- [ ] `font-display: swap` تنظیم شده
- [ ] Fonts preload شده
- [ ] Fallback fonts مشخص شده

### Server:
- [ ] Gzip/Brotli فعال است
- [ ] Cache headers تنظیم شده
- [ ] CDN استفاده می‌شود (اختیاری)

---

## 🎯 اولویت‌های سریع

### برترین 5 تغییر با بیشترین تأثیر:

1. **تصاویر را با `width` و `height` مشخص کنید** (بهبود CLS)
2. **LCP image را preload کنید** (بهبود LCP)
3. **JavaScript را `defer` کنید** (بهبود INP)
4. **Fonts را preload کنید** (بهبود LCP)
5. **Critical CSS را inline کنید** (بهبود LCP)

---

## 📊 ابزارهای تست

1. **PageSpeed Insights:** https://pagespeed.web.dev/
2. **Lighthouse (Chrome DevTools):** F12 → Lighthouse
3. **WebPageTest:** https://www.webpagetest.org/
4. **GTmetrix:** https://gtmetrix.com/

---

## 🚨 مشکلات رایج

### مشکل 1: LCP بالا
**علت:** تصویر اصلی دیر لود می‌شود
**راه‌حل:** Preload + fetchpriority="high"

### مشکل 2: CLS بالا
**علت:** تصاویر بدون ابعاد هستند
**راه‌حل:** اضافه کردن width و height

### مشکل 3: INP بالا
**علت:** JavaScript سنگین
**راه‌حل:** Defer + Code Splitting

---

## 📞 پشتیبانی

اگر نیاز به کمک دارید:
1. نتایج PageSpeed را بررسی کنید
2. بخش‌های قرمز (Failed) را رفع کنید
3. بخش‌های زرد (Needs Improvement) را بهبود دهید

---

**نکته مهم:** هدف نمره 100 نیست، بلکه **تجربه کاربری خوب** است. نمره 90+ عالی است!
