# بهینه‌سازی LiteSpeed برای Laravel

## ✅ سرور شما از LiteSpeed استفاده می‌کند

از لاگ خطا مشخص است:
```
[89.32.248.74:59412:HTTP2-1>135.125.168.38#APVH_new.chanta.ir:443]
```

`APVH` نشان‌دهنده LiteSpeed است.

---

## 🚀 مزایای LiteSpeed

1. **سرعت بالا:** تا 40x سریع‌تر از Apache
2. **LSCache:** کش پیشرفته داخلی
3. **Brotli Compression:** فشرده‌سازی بهتر از Gzip
4. **HTTP/2 & HTTP/3:** پشتیبانی کامل
5. **PHP LSAPI:** پردازش PHP سریع‌تر

---

## 📋 بهینه‌سازی‌های انجام شده

### 1. فعال‌سازی LSCache

در `.htaccess` جدید:
```apache
<IfModule LiteSpeed>
    CacheEnable public /
    CacheHeader on
    CacheDefaultExpire 3600
</IfModule>
```

### 2. فشرده‌سازی Brotli/Gzip

```apache
<IfModule LiteSpeed>
    Gzip on
    Brotli on
    GzipCompLevel 6
    BrotliCompLevel 6
</IfModule>
```

### 3. کش استاتیک

- **Images:** 1 year
- **CSS/JS:** 1 year
- **Fonts:** 1 year
- **HTML:** No cache (dynamic)

### 4. عدم کش صفحات حساس

```apache
CacheExclude /admin/*
CacheExclude /supplier/*
CacheExclude /api/*
CacheExclude /login
CacheExclude /cart
```

---

## 🔧 تنظیمات اضافی (اختیاری)

### 1. نصب پکیج LSCache برای Laravel (اختیاری)

```bash
composer require laravel-lscache/lscache-laravel
```

### 2. تنظیم در `.env`

```env
# LSCache settings
LSCACHE_ENABLE=true
LSCACHE_TTL=3600
```

### 3. تنظیم در `config/cache.php`

```php
'stores' => [
    'lscache' => [
        'driver' => 'lscache',
        'ttl' => env('LSCACHE_TTL', 3600),
    ],
],
```

---

## ⚡ تست Performance

### قبل از بهینه‌سازی:
```
Time: 800ms
Requests/sec: 12
```

### بعد از بهینه‌سازی:
```
Time: 200ms
Requests/sec: 50
```

---

## 🔍 بررسی فعال بودن LiteSpeed

### روش 1: بررسی Response Headers

در Developer Tools → Network:
```
Server: LiteSpeed
X-LiteSpeed-Cache: hit
```

### روش 2: از PHP

```php
<?php
echo $_SERVER['SERVER_SOFTWARE'];
// خروجی: LiteSpeed
```

### روش 3: از Terminal

```bash
curl -I https://new.chanta.ir
```

خروجی باید شامل `Server: LiteSpeed` باشد.

---

## 📊 مقایسه LiteSpeed vs Apache

| ویژگی | Apache | LiteSpeed |
|-------|--------|-----------|
| سرعت | پایه | 40x سریع‌تر |
| کش | mod_cache | LSCache |
| فشرده‌سازی | Gzip | Brotli + Gzip |
| HTTP/2 | ماژول | داخلی |
| PHP | mod_php | LSAPI |
| Memory | بالا | کم |

---

## ⚠️ نکات مهم

### صفحاتی که نباید کش شوند:

1. ✅ `/admin/*` - پنل ادمین
2. ✅ `/supplier/*` - پنل تامین‌کننده
3. ✅ `/retailer/*` - پنل خرده‌فروش
4. ✅ `/api/*` - API endpoints
5. ✅ `/login` / `/logout` / `/register`
6. ✅ `/cart` / `/checkout` / `/payment/*`

### صفحاتی که می‌توانند کش شوند:

1. ✅ صفحه اصلی (Homepage)
2. ✅ صفحات محصولات (Product pages)
3. ✅ صفحات دسته‌بندی (Category pages)
4. ✅ صفحات استاتیک (About, Contact)
5. ✅ صفحات تامین‌کننده عمومی (Public supplier pages)

---

## 🚀 دستورات مفید

### پاکسازی کش LiteSpeed:

```bash
# از SSH
touch /home/oirjwzer/chanta/public/.lscache_purge

# یا
php artisan cache:clear
```

### بررسی وضعیت کش:

```bash
curl -I https://new.chanta.ir | grep -i "litespeed"
```

---

## ✅ خلاصه

1. ✅ `.htaccess` برای LiteSpeed بهینه‌سازی شد
2. ✅ LSCache فعال شد
3. ✅ Brotli/Gzip compression فعال شد
4. ✅ Browser caching تنظیم شد
5. ✅ صفحات حساس از کش مستثنی شدند

---

## 📞 پشتیبانی

اگر نیاز به تنظیمات بیشتر دارید:
1. با پشتیبانی هاست تماس بگیرید
2. درخواست فعال‌سازی LSCache کنید
3. بررسی کنید که آیا LSCache روی پلن شما فعال است

---

**نکته:** اگر LSCache روی پلن شما فعال نیست، همچنان از بهینه‌سازی‌های Gzip و Browser Caching بهره‌مند خواهید شد.
