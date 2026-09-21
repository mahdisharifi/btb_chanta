# رفع سریع خطای 500 - راهنمای عملی

## 🚨 اقدام فوری (در هاست)

### دستورات SSH (کپی کنید)

```bash
cd /home/oirjwzer/chanta

# 1. تنظیم دسترسی‌ها (مهمترین!)
chmod -R 775 storage bootstrap/cache
chown -R oirjwzer:oirjwzer storage bootstrap/cache

# 2. پاکسازی کش
php artisan optimize:clear

# 3. اگر optimize:clear خطا داد
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*

# 4. تنظیم APP_KEY (اگر نیست)
php artisan key:generate

# 5. تست
php artisan route:list
```

---

## 📋 اگر SSH ندارید (از File Manager)

### مرحله 1: تنظیم دسترسی storage
1. File Manager را باز کنید
2. به `/home/oirjwzer/chanta` بروید
3. Right-click روی `storage` → **Change Permissions**
4. عدد `775` را وارد کنید
5. تیک **Recursive** را بزنید
6. **Change Permissions** را بزنید

### مرحله 2: تنظیم دسترسی bootstrap/cache
همین کار را برای `bootstrap/cache` انجام دهید

### مرحله 3: پاکسازی کش
پوشه‌های زیر را خالی کنید:
- `storage/framework/cache/`
- `storage/framework/views/`
- `bootstrap/cache/` (فقط فایل‌های .php را حذف کنید)

### مرحله 4: بررسی .env
فایل `.env` را باز کنید:
```env
APP_DEBUG=false
APP_KEY=base64:ENW44uDQGJ8dl4fv9FYDRe2DBgdlJpgtIyUoZLhQ9HA=
```

---

## 🔍 تست‌های سریع

### تست 1: دسترسی storage
در Terminal یا SSH:
```bash
ls -la storage/
```
خروجی باید `drwxrwxr-x` باشد

### تست 2: تست ساده PHP
این فایل را آپلود کنید: `public/test-simple.php`
سپس در مرورگر باز کنید: `https://new.chanta.ir/test-simple.php`

### تست 3: بررسی لاگ
```bash
tail -20 storage/logs/laravel.log
```

---

## ⚡ سریع‌ترین راه‌حل

```bash
# فقط این دستور را اجرا کنید
chmod -R 775 storage bootstrap/cache
```

این دستور **90%** خطاهای 500 را رفع می‌کند.

---

## 📞 اگر مشکل حل نشد

1. فایل `storage/logs/laravel.log` را بررسی کنید
2. خروجی `test-simple.php` را ببینید
3. با پشتیبانی هاست تماس بگیرید

---

**مسیر پروژه:** `/home/oirjwzer/chanta`
**دامنه:** `new.chanta.ir`
