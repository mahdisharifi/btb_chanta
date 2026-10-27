# راهنمای کامل LSCache برای Laravel

## 📚 منبع
https://docs.litespeedtech.com/lscache/lsclaravel/installation/

---

## ✅ روش صحیح استفاده از LSCache در Laravel

### مشکل قبلی:
- ❌ LSCache همه صفحات را کش می‌کرد (حتی صفحات داینامیک)
- ❌ باعث نمایش منوی ادمین به مهمانان می‌شد
- ❌ باعث خطای 419 در لاگین می‌شد

### راه‌حل صحیح:
- ✅ استفاده از پکیج رسمی `litespeed/lscache-laravel`
- ✅ کنترل دقیق کش با Middleware
- ✅ فقط صفحات مجاز کش شوند

---

## 🚀 نصب و تنظیم

### مرحله 1: نصب پکیج

```bash
composer require litespeed/lscache-laravel
```

### مرحله 2: انتشار تنظیمات (Laravel 5.5+)

```bash
php artisan vendor:publish --provider="Litespeed\\LSCache\\LSCacheServiceProvider"
```

### مرحله 3: تنظیم `.htaccess`

```apache
<IfModule LiteSpeed>
    CacheLookup on
</IfModule>
```

**نکته مهم:** فقط `CacheLookup on` کافی است، نه `CacheEnable`!

---

## 📋 نحوه استفاده

### 1. کنترل کش در Routes

```php
// routes/web.php

// صفحاتی که باید کش شوند (مثل صفحه اصلی)
Route::get('/', function () {
    // ...
})->middleware('lscache:3600'); // کش به مدت 1 ساعت

// صفحات محصول (کش با TTL متغیر)
Route::get('/products/{slug}', function ($slug) {
    // ...
})->middleware('lscache:1800'); // کش به مدت 30 دقیقه

// صفحات استاتیک
Route::get('/about', function () {
    // ...
})->middleware('lscache:86400'); // کش به مدت 24 ساعت
```

### 2. صفحاتی که نباید کش شوند

```php
// صفحات احراز هویت (بدون middleware lscache)
Route::get('/login', [LoginController::class, 'show']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout']);

// پنل کاربری (بدون کش)
Route::prefix('admin')->middleware('auth')->group(function () {
    // ...
});

// سبد خرید
Route::get('/cart', [CartController::class, 'index']);
```

### 3. پاکسازی کش هنگام تغییر داده

```php
use LSCache;

// بعد از ذخیره محصول
public function update(Request $request, Product $product)
{
    $product->update($request->all());
    
    // پاکسازی کش این محصول
    LSCache::purge(url('/products/' . $product->slug));
    
    return redirect()->back();
}

// یا پاکسازی همه کش
LSCache::purgeAll();
```

---

## 🎯 تنظیمات پیشنهادی برای پروژه شما

### فایل `.htaccess` نهایی:

```apache
# ============================================
# LITESPEED CACHE FOR LARAVEL
# ============================================

<IfModule LiteSpeed>
    # فقط CacheLookup فعال کنید
    CacheLookup on
    
    # CacheEnable را فعال نکنید (کنترل توسط Laravel)
</IfModule>

# سایر تنظیمات...
```

---

## 📊 مقایسه روش‌ها

| روش | مزایا | معایب |
|-----|-------|-------|
| **بدون LSCache** | ساده، بدون مشکل | کندتر |
| **LSCache با .htaccess** | سریع | مشکل با صفحات داینامیک |
| **lscache-laravel (پیشنهادی)** | کنترل دقیق، سازگار با Laravel | نیاز به تنظیم |

---

## ✅ چک‌لیست پیاده‌سازی

1. [ ] نصب پکیج: `composer require litespeed/lscache-laravel`
2. [ ] انتشار تنظیمات: `php artisan vendor:publish ...`
3. [ ] تنظیم `.htaccess`: فقط `CacheLookup on`
4. [ ] اضافه کردن middleware به routes:
   - صفحات استاتیک: `lscache:3600`
   - صفحات محصول: `lscache:1800`
   - صفحات داینامیک: بدون middleware
5. [ ] تست ورود/خروج
6. [ ] تست صفحات کش شده

---

## 🚨 نکات مهم

### 1. فقط یک Full-Page Cache
اگر از LSCache استفاده می‌کنید، دیگر کش‌های صفحه کامل (مثل صفحه کش Laravel) را غیرفعال کنید.

### 2. TTL پیش‌فرض = 0
پکیج به صورت پیش‌فرض هیچ صفحه‌ای را کش نمی‌کند (TTL=0). باید برای هر route مشخص کنید.

### 3. صفحات احراز هویت
**هرگز** از middleware `lscache` برای صفحات احراز هویت استفاده نکنید!

---

## 📞 پشتیبانی

- مستندات: https://docs.litespeedtech.com/lscache/lsclaravel/
- GitHub: https://github.com/litespeedtech/lscache-laravel

---

**تاریخ ایجاد:** 1405/07/01
