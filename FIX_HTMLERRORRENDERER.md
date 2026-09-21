# رفع خطای HtmlErrorRenderer.php line 257

## مشکل
این خطا به دلیل **عدم توانایی در نمایش خطاها** رخ می‌دهد - یعنی خطای اصلی پنهان شده و باعث خطای ثانویه می‌شود.

## راه‌حل‌ها (به ترتیب)

### راه‌حل 1: تنظیم APP_DEBUG=false

در فایل `.env`:
```env
APP_DEBUG=false
```

این کار باعث می‌شود Laravel سعی نکند صفحه خطای.pretty نمایش دهد.

---

### راه‌حل 2: تنظیم دسترسی storage (مهمترین!)

```bash
cd /home/oirjwzer/chanta
chmod -R 775 storage bootstrap/cache
chown -R oirjwzer:oirjwzer storage bootstrap/cache
```

**اگر SSH ندارید:**
1. در File Manager، Right-click روی `storage`
2. Change Permissions → 775
3. تیک Recursive بزنید
4. برای `bootstrap/cache` هم همین کار را انجام دهید

---

### راه‌حل 3: پاکسازی کش‌ها

```bash
cd /home/oirjwzer/chanta
php artisan optimize:clear
```

اگر این دستور خطا داد:
```bash
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*
rm -rf bootstrap/cache/*.php
```

---

### راه‌حل 4: بررسی فایل‌های view

```bash
chmod -R 755 resources/views
```

---

### راه‌حل 5: بررسی تنظیمات PHP

در `.user.ini` یا از طریق cPanel → Select PHP Version:

```ini
memory_limit = 256M
max_execution_time = 300
```

---

### راه‌حل 6: بررسی opcache

اگر opcache فعال است، آن را موقتاً غیرفعال کنید:

در `.user.ini`:
```ini
opcache.enable = 0
```

یا در cPanel → Select PHP Version → opcache را غیرفعال کنید

---

## دستورات یکجا (SSH)

```bash
cd /home/oirjwzer/chanta

# تنظیم دسترسی‌ها
chmod -R 775 storage bootstrap/cache resources/views

# پاکسازی کش
php artisan optimize:clear

# اگر optimize:clear کار نکرد
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*
rm -rf bootstrap/cache/*.php

# تنظیم APP_KEY اگر نیست
php artisan key:generate

# تست
php artisan route:list
```

---

## اگر مشکل حل نشد

### بررسی خطای واقعی:

1. فایل `storage/logs/laravel.log` را باز کنید
2. آخرین خطا را پیدا کنید (خطای اصلی)
3. آن خطا را رفع کنید

### یا استفاده از diagnostic.php:

```
https://new.chanta.ir/diagnostic.php
```

---

## نکات مهم

1. **دسترسی storage** معمولاً مشکل اصلی است
2. اگر `APP_DEBUG=true` باعث خطا می‌شود، موقتاً `false` کنید
3. بعد از رفع مشکل، `APP_DEBUG=true` کنید برای تست نهایی
4. در production نهایی، `APP_DEBUG=false` کنید

---

**مسیر پروژه شما:** `/home/oirjwzer/chanta`
