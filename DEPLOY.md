# 🚀 راهنمای Deploy و بهینه‌سازی سرور Production

## 📦 دستورات Deploy (بعد از git pull)

### 1️⃣ روش سریع (یک خط)
```bash
composer install --optimize-autoloader --no-dev && php artisan optimize && php artisan migrate --force
```

### 2️⃣ روش گام به گام

```bash
# 1. نصب dependencies
composer install --optimize-autoloader --no-dev

# 2. پاکسازی cache های قدیمی
php artisan optimize:clear

# 3. اجرای مایگریشن‌ها (اختیاری)
php artisan migrate --force

# 4. بهینه‌سازی کامل
php artisan optimize

# 5. بررسی وضعیت
php artisan about
```

---

## 🔧 مشکلات رایج و راه‌حل

### ❌ مشکل: تغییرات کد اعمال نمی‌شود

**علت:** Cache کلاس‌ها و view ها

**راه‌حل:**
```bash
php artisan optimize:clear
php artisan view:clear
```

### ❌ مشکل: صفحه terminal دستورات را نشان نمی‌دهد

**علت:** فایل PHP کش شده یا view کش شده

**راه‌حل:**
```bash
# پاکسازی کامل
php artisan optimize:clear

# یا فقط view
php artisan view:clear

# سپس
php artisan optimize
```

### ❌ مشکل: خطای 500 بعد از deploy

**علت:** مشکل در cache یا autoload

**راه‌حل:**
```bash
composer dump-autoload --optimize
php artisan optimize:clear
php artisan optimize
```

### ❌ مشکل: Route ها کار نمی‌کنند

**علت:** route cache قدیمی است

**راه‌حل:**
```bash
php artisan route:clear
php artisan route:cache
```

---

## 🎯 چک‌لیست Deploy

قبل از هر deploy این موارد را چک کنید:

- [ ] `git pull origin main` انجام شد
- [ ] فایل `.env` با تنظیمات production (APP_ENV=production, APP_DEBUG=false)
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force` (اگر مایگریشن جدید دارید)
- [ ] `php artisan optimize:clear`
- [ ] `php artisan optimize`
- [ ] بررسی سایت در مرورگر
- [ ] بررسی لاگ‌ها: `tail -f storage/logs/laravel.log`

---

## 🚨 دستورات اضطراری (در صورت مشکل)

اگر سایت به مشکل خورد، این دستورات را به ترتیب اجرا کنید:

```bash
# 1. پاکسازی کامل
php artisan optimize:clear

# 2. بازسازی autoload
composer dump-autoload --optimize

# 3. بهینه‌سازی دوباره
php artisan optimize

# 4. اگر هنوز مشکل دارد
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 5. سپس cache کردن دوباره
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📊 بررسی سلامت سرور

### بررسی سرعت سایت
```bash
# Windows
curl -o NUL -s -w "TTFB: %{time_starttransfer}s | Total: %{time_total}s\n" https://chanta.ir

# Linux
curl -o /dev/null -s -w "TTFB: %{time_starttransfer}s | Total: %{time_total}s\n" https://chanta.ir
```

**مقادیر خوب:**
- TTFB < 500ms ✅
- Total < 2s ✅

### بررسی لاگ‌ها
```bash
# نمایش 50 خط آخر
tail -n 50 storage/logs/laravel.log

# نمایش real-time
tail -f storage/logs/laravel.log
```

### بررسی disk space
```bash
df -h
```

### بررسی PHP version
```bash
php -v
```

### بررسی OPcache
```bash
php -i | grep opcache
```

---

## 🔐 تنظیمات امنیتی Production

مطمئن شوید این موارد در `.env` تنظیم شده‌اند:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://chanta.ir

SESSION_DRIVER=file
CACHE_STORE=file

# اگر Redis دارید (بهتر از file)
# SESSION_DRIVER=redis
# CACHE_STORE=redis
```

---

## 📝 نکات مهم

1. **هیچ‌وقت در production این کارها را نکنید:**
   - `APP_DEBUG=true` نگذارید
   - بدون test دستورات خطرناک اجرا نکنید
   - migrations را بدون backup اجرا نکنید

2. **همیشه قبل از deploy:**
   - Backup از database بگیرید
   - تغییرات را در local تست کنید
   - با تیم هماهنگ کنید

3. **بعد از deploy:**
   - سایت را در مرورگر چک کنید
   - لاگ‌ها را بررسی کنید
   - سرعت را با curl تست کنید

---

## 🆘 در صورت اضطرار

اگر سایت کاملاً از کار افتاد:

```bash
# 1. بررسی لاگ
tail -n 100 storage/logs/laravel.log

# 2. Permission ها
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 3. پاکسازی کامل و rebuild
php artisan optimize:clear
composer dump-autoload --optimize
php artisan optimize

# 4. اگر باز هم مشکل دارد، به commit قبلی برگردید
git log --oneline -n 5
git checkout <commit-hash>
php artisan optimize:clear
php artisan optimize
```

---

**تاریخ:** 2026-08-27  
**نسخه:** 1.0
