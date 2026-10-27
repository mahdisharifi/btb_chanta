# 🚀 راهنمای جامع بهینه‌سازی Performance صفحه محصول

## 📊 وضعیت فعلی

### ✅ بهینه‌سازی‌های انجام شده:

1. **تصاویر:**
   - ✅ WebP format
   - ✅ Responsive images (xs, sm, md, lg, xl)
   - ✅ Lazy loading
   - ✅ `fetchpriority="high"` برای LCP image
   - ✅ Width/Height attributes
   - ✅ `decoding="async"`

2. **SEO:**
   - ✅ Schema.org JSON-LD
   - ✅ Open Graph tags
   - ✅ Canonical URL
   - ✅ Breadcrumb schema

3. **Cache:**
   - ✅ Cache-Control headers (30 minutes)
   - ✅ Expires headers

---

## 🔧 بهینه‌سازی‌های پیشنهادی

### 1️⃣ **CSS بهینه‌سازی**

#### الف. Inline Critical CSS

فایل: `resources/views/layouts/app.blade.php`

```blade
<head>
    {{-- Critical CSS Inline --}}
    <style>
        /* فقط CSS‌های ضروری برای above-the-fold */
        body { font-family: 'Vazir', sans-serif; }
        .header { /* ... */ }
        .product-gallery { /* ... */ }
        /* ... */
    </style>
    
    {{-- Non-critical CSS با preload --}}
    <link rel="preload" href="{{ Vite::asset('resources/css/app.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ Vite::asset('resources/css/app.css') }}"></noscript>
</head>
```

#### ب. Purge Unused CSS

فایل: `tailwind.config.js`

```javascript
module.exports = {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

---

### 2️⃣ **JavaScript بهینه‌سازی**

#### الف. Defer Non-Critical Scripts

```blade
{{-- Alpine.js با defer --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

{{-- Livewire Scripts --}}
@livewireScripts
```

#### ب. Code Splitting

فایل: `vite.config.js`

```javascript
export default defineConfig({
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor': ['alpinejs'],
          'livewire': ['@livewire/alpine'],
        }
      }
    }
  }
})
```

---

### 3️⃣ **Fonts بهینه‌سازی**

#### الف. Self-host فونت‌ها

به جای CDN، فونت‌ها را local host کنید:

```blade
<head>
    {{-- Preload فونت اصلی --}}
    <link rel="preload" href="/fonts/Vazir-Regular.woff2" as="font" type="font/woff2" crossorigin>
    
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('/fonts/Vazir-Regular.woff2') format('woff2');
            font-display: swap;
            font-weight: normal;
            font-style: normal;
        }
    </style>
</head>
```

#### ب. Font Display Swap

```css
@font-face {
  font-family: 'Vazir';
  font-display: swap; /* ✅ نمایش فوری text */
}
```

---

### 4️⃣ **Preconnect & DNS Prefetch**

```blade
<head>
    {{-- Preconnect به CDN‌ها --}}
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    
    {{-- اگر از Google Fonts استفاده می‌کنید --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
```

---

### 5️⃣ **Lazy Load مح
صولات مرتبط**

فایل: `resources/views/pages/products/show.blade.php`

```blade
{{-- محصولات مرتبط را بعد از محتوای اصلی لود کن --}}
<div x-data="{ loaded: false }" 
     x-intersect="loaded = true">
    <template x-if="loaded">
        <div>
            @foreach($relatedProducts as $related)
                <x-product.card :product="$related" />
            @endforeach
        </div>
    </template>
    
    {{-- Skeleton Loader تا زمان لود --}}
    <template x-if="!loaded">
        <div class="animate-pulse">
            {{-- Skeleton cards --}}
        </div>
    </template>
</div>
```

---

### 6️⃣ **Database Query بهینه‌سازی**

#### الف. Eager Loading (✅ انجام شده)

```php
// ProductController.php
$product->load([
    'category',
    'supplier',
    'galleries',
    'productSuppliers.prices',
    'retailPrices',
]);
```

#### ب. Database Indexing

```sql
-- اضافه کردن index برای کوئری‌های پرتکرار
CREATE INDEX idx_products_slug ON products(slug);
CREATE INDEX idx_products_active_status ON products(is_active, status);
CREATE INDEX idx_product_prices_active ON product_prices(product_supplier_id, is_active);
```

---

### 7️⃣ **Cache Strategy**

#### الف. Redis برای Session و Cache

فایل: `.env`

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

#### ب. Cache کوئری‌های سنگین

```php
// ProductController.php
$relatedProducts = Cache::remember(
    "product:{$product->id}:related",
    now()->addHours(1),
    fn() => $this->getRelatedProducts($product, 8)
);
```

#### ج. OPcache برای PHP

فایل: `php.ini`

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

---

### 8️⃣ **CDN استفاده**

#### الف. تصاویر در CDN

```php
// config/filesystems.php
'cdn' => [
    'driver' => 's3',
    'url' => env('CDN_URL', 'https://cdn.chanta.ir'),
    // ...
],
```

#### ب. Static Assets در CDN

```blade
<link rel="stylesheet" href="{{ cdn('css/app.css') }}">
<script src="{{ cdn('js/app.js') }}"></script>
```

---

### 9️⃣ **Compression**

#### الف. Gzip/Brotli در Nginx

فایل: `nginx.conf`

```nginx
# Gzip
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json application/javascript;

# Brotli (بهتر از Gzip)
brotli on;
brotli_comp_level 6;
brotli_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json application/javascript;
```

#### ب. Laravel Asset Compression

```bash
npm run build -- --minify
```

---

### 🔟 **Server-Side Rendering (SSR)**

برای بخش‌های دینامیک، می‌توانید از Livewire SSR استفاده کنید:

```bash
php artisan livewire:publish --config
```

فایل: `config/livewire.php`

```php
'render_on_redirect' => true,
```

---

## 📊 Core Web Vitals اهداف

| Metric | Target | توضیح |
|--------|--------|-------|
| **LCP** | < 2.5s | Largest Contentful Paint |
| **FID** | < 100ms | First Input Delay |
| **CLS** | < 0.1 | Cumulative Layout Shift |
| **FCP** | < 1.8s | First Contentful Paint |
| **TTFB** | < 600ms | Time to First Byte |

---

## 🧪 ابزارهای تست

1. **Google PageSpeed Insights**
   - https://pagespeed.web.dev/

2. **GTmetrix**
   - https://gtmetrix.com/

3. **WebPageTest**
   - https://www.webpagetest.org/

4. **Chrome DevTools**
   - Lighthouse
   - Performance tab
   - Network tab

5. **Laravel Debugbar**
   ```bash
   composer require barryvdh/laravel-debugbar --dev
   ```

---

## 📝 چک‌لیست اجرایی

### فوری (High Priority):
- [ ] Inline critical CSS
- [ ] Defer non-critical JavaScript
- [ ] Self-host fonts با `font-display: swap`
- [ ] Enable Gzip/Brotli compression
- [ ] Add preconnect hints
- [ ] Enable OPcache

### میان‌مدت (Medium Priority):
- [ ] Implement Redis cache
- [ ] Add database indexes
- [ ] Code splitting برای JavaScript
- [ ] Lazy load محصولات مرتبط
- [ ] CDN برای تصاویر

### بلندمدت (Long Term):
- [ ] CDN برای تمام assets
- [ ] Service Worker برای offline
- [ ] HTTP/2 Push
- [ ] Progressive Web App (PWA)

---

## 🎯 نتیجه مورد انتظار

با اعمال این بهینه‌سازی‌ها:

- 📈 PageSpeed Score: **90+** (Mobile & Desktop)
- ⚡ LCP: **< 2s**
- 🎨 CLS: **< 0.05**
- 🖱️ FID: **< 50ms**
- 📦 Page Size: **< 1MB**
- ⏱️ Load Time: **< 3s**

---

## 📚 منابع مفید

1. [Web.dev - Performance](https://web.dev/performance/)
2. [Laravel Performance Best Practices](https://laravel.com/docs/performance)
3. [Tailwind CSS Optimization](https://tailwindcss.com/docs/optimizing-for-production)
4. [Chrome Performance Guide](https://developer.chrome.com/docs/devtools/performance/)

---

**تاریخ ایجاد:** 2026-09-22  
**آخرین بروزرسانی:** 2026-09-22  
**نویسنده:** Kiro AI
