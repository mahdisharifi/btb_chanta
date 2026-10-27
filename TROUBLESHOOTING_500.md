# عیب‌یابی خطای 500 در هاست اشتراکی

## 🚨 اقدامات فوری

### 1. فعال‌سازی نمایش خطاها

در فایل `.env`:
```env
APP_DEBUG=true
```

یا در فایل `public/index.php` (بعد از <?php):
```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

### 2. بررسی دسترسی‌های Storage (مهمترین دلیل)

**در SSH یا Terminal:**
```bash
cd /home/username/public_html
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R username:username storage
chown -R username:username bootstrap/cache
```

**از File Manager:**
1. Right-click روی پوشه `storage`
2. Change Permissions → 775
3. Recursive (اعمال به زیرپوشه‌ها)

### 3. بررسی فایل .env

**خطای رایج:** فایل .env وجود ندارد یا تنظیم نیست

**راه‌حل:**
```bash
# کپی از نمونه
cp .env.example .env

# تولید APP_KEY
php artisan key:generate

# تنظیم دیتابیس
nano .env
```

### 4. پاکسازی کش‌ها

```bash
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### 5. بررسی vendor/

**اگر پوشه vendor وجود ندارد:**
```bash
composer install --no-dev --optimize-autoloader
```

---

## 🔍 استفاده از فایل diagnostic.php

1. فایل `public/diagnostic.php` را در مرورگر باز کنید:
   ```
   https://yourdomain.com/diagnostic.php
   ```

2. همه موارد را بررسی کنید

3. **بعد از رفع مشکل، فایل diagnostic.php را حذف کنید!**

---

## 🛠️ مشکلات رایج و راه‌حل‌ها

### مشکل 1: دسترسی Storage (70% موارد)

**علت:** پوشه‌های storage یا bootstrap/cache قابل نوشتن نیستند

**راه‌حل:**
```bash
chmod -R 775 storage bootstrap/cache
```

### مشکل 2: فایل .env

**علت:** فایل .env وجود ندارد یا APP_KEY تنظیم نیست

**راه‌حل:**
```bash
php artisan key:generate
```

### مشکل 3: کش‌های قدیمی

**علت:** کش‌های لوکال با هاست هماهنگ نیست

**راه‌حل:**
```bash
php artisan optimize:clear
```

### مشکل 4: دیتابیس

**علت:** تنظیمات دیتابیس اشتباه

**راه‌حل:**
```bash
# بررسی اتصال
php artisan tinker
>>> DB::connection()->getPdo();
```

### مشکل 5: PHP Version

**علت:** نسخه PHP کمتر از 8.2

**راه‌حل:**
- در cPanel → Select PHP Version
- انتخاب PHP 8.2 یا بالاتر

### مشکل 6: Extensions

**علت:** اکستنشن‌های PHP فعال نیستند

**راه‌حل:**
- در cPanel → Select PHP Version
- فعال کردن:
  - pdo_mysql
  - mbstring
  - xml
  - json
  - openssl
  - tokenizer
  - ctype
  - fileinfo
  - bcmath
  - curl
  - dom

### مشکل 7: .htaccess

**علت:** تنظیمات .htaccess با هاست هماهنگ نیست

**راه‌حل:**
فایل `public/.htaccess` را بررسی کنید

### مشکل 8: Memory Limit

**علت:** memory_limit کم است

**راه‌حل:**
در `.user.ini`:
```ini
memory_limit = 256M
```

---

## 📋 چک‌لیست سریع

- [ ] فایل `.env` وجود دارد
- [ ] `APP_KEY` تنظیم شده
- [ ] دسترسی `storage/` برابر 775
- [ ] دسترسی `bootstrap/cache/` برابر 775
- [ ] دیتابیس وصل است
- [ ] `php artisan optimize:clear` اجرا شده
- [ ] PHP >= 8.2
- [ ] Extensions فعال هستند

---

## 🚀 دستورات یکجا (کپی-پیست)

```bash
cd /home/username/public_html

# 1. تنظیم دسترسی‌ها
chmod -R 775 storage bootstrap/cache

# 2. پاکسازی کش
php artisan optimize:clear

# 3. تنظیم APP_KEY (اگر نیست)
php artisan key:generate

# 4. کش کردن تنظیمات
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. بررسی
php artisan route:list
```

---

## 📞 اگر مشکل حل نشد

1. لاگ خطا را بررسی کنید:
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. خطای دقیق را در `diagnostic.php` ببینید

3. با پشتیبانی هاست تماس بگیرید

---

**نکته مهم:** بعد از رفع مشکل، حتماً:
- `APP_DEBUG=false` کنید
- فایل `diagnostic.php` را حذف کنید
