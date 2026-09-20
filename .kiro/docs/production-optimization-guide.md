# راهنمای بهینه‌سازی و استقرار پروژه B2B Marketplace در Production

## 📋 فهرست مطالب
1. [دستورات بهینه‌سازی Laravel](#1-دستورات-بهینه‌سازی-laravel)
2. [تنظیمات PHP برای هاست اشتراکی](#2-تنظیمات-php-برای-هاست-اشتراکی)
3. [تنظیمات .htaccess](#3-تنظیمات-htaccess)
4. [تنظیمات محیط Production](#4-تنظیمات-محیط-production)
5. [بررسی سرعت](#5-بررسی-سرعت)
6. [عیب‌یابی مشکلات سرعت](#6-عیب‌یابی-مشکلات-سرعت)

---

## 1. دستورات بهینه‌سازی Laravel

### الف. دستورات اولیه (قبل از انتقال به سرور)

```bash
# 1. نصب dependencies بدون dev
composer install --optimize-autoloader --no-dev

# 2. Cache کردن configuration
php artisan config:cache

# 3. Cache کردن routes
php artisan route:cache

# 4. Cache کردن views
php artisan view:cache

# 5. Cache کردن events
php artisan event:cache

# 6. Optimize کردن کل پروژه (شامل همه موارد بالا)
php artisan optimize
```

### ب. دستورات پاک‌سازی Cache (در صورت تغییرات)

اگر تغییراتی در کد دادید و نیاز به بازسازی cache دارید:

```bash
# پاک کردن همه cache‌ها
php artisan optimize:clear

# یا به صورت جداگانه:
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# سپس دوباره cache کنید
php artisan optimize
```

### ج. Workflow توسعه و استقرار

```bash
# 1. محیط Local (Development)
# کاری نکنید - بدون cache کار کنید

# 2. قبل از Push به Git
php artisan test  # اگر تست دارید

# 3. روی سرور Production
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan optimize
php artisan queue:restart  # اگر queue دارید
```

---

## 2. تنظیمات PHP برای هاست اشتراکی

### الف. فایل `php.ini` یا `.user.ini`

در root پروژه (یا public) فایل `.user.ini` بسازید:

```ini
; Memory Limit
memory_limit = 256M

; Execution Time
max_execution_time = 300
max_input_time = 300

; Upload Limits
upload_max_filesize = 20M
post_max_size = 25M

; OPcache Settings (بسیار مهم برای سرعت)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
opcache.enable_cli = 1
opcache.validate_timestamps = 0  ; در production فقط

; Session
session.gc_maxlifetime = 7200
session.cookie_lifetime = 7200

; Timezone
date.timezone = "Asia/Tehran"

; Error Reporting (Production)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

### ب. بررسی OPcache

بعد از تنظیم، با این دستور بررسی کنید:

```bash
php -i | grep opcache
```

یا یک فایل `opcache-info.php` بسازید:

```php
<?php
phpinfo();
// بعد از بررسی، این فایل را حذف کنید
```

---

## 3. تنظیمات .htaccess

فایل `.htaccess` در پوشه `public`:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# ============================================
# تنظیمات بهینه‌سازی سرعت
# ============================================

# 1. فعال‌سازی فشرده‌سازی GZIP
<IfModule mod_deflate.c>
    # Compress HTML, CSS, JavaScript, Text, XML and fonts
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/vnd.ms-fontobject
    AddOutputFilterByType DEFLATE application/x-font
    AddOutputFilterByType DEFLATE application/x-font-opentype
    AddOutputFilterByType DEFLATE application/x-font-otf
    AddOutputFilterByType DEFLATE application/x-font-truetype
    AddOutputFilterByType DEFLATE application/x-font-ttf
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE font/opentype
    AddOutputFilterByType DEFLATE font/otf
    AddOutputFilterByType DEFLATE font/ttf
    AddOutputFilterByType DEFLATE image/svg+xml
    AddOutputFilterByType DEFLATE image/x-icon
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml

    # Remove browser bugs (only needed for really old browsers)
    BrowserMatch ^Mozilla/4 gzip-only-text/html
    BrowserMatch ^Mozilla/4\.0[678] no-gzip
    BrowserMatch \bMSIE !no-gzip !gzip-only-text/html
    Header append Vary User-Agent
</IfModule>

# 2. Browser Caching
<IfModule mod_expires.c>
    ExpiresActive On

    # Images
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"

    # Video
    ExpiresByType video/mp4 "access plus 1 year"
    ExpiresByType video/mpeg "access plus 1 year"

    # CSS, JavaScript
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"

    # Fonts
    ExpiresByType font/ttf "access plus 1 year"
    ExpiresByType font/otf "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType application/font-woff "access plus 1 year"

    # PDF
    ExpiresByType application/pdf "access plus 1 month"

    # Others
    ExpiresByType application/x-shockwave-flash "access plus 1 month"
</IfModule>

# 3. Cache-Control Headers
<IfModule mod_headers.c>
    # Images
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|ico)$">
        Header set Cache-Control "max-age=31536000, public, immutable"
    </FilesMatch>

    # CSS and JavaScript
    <FilesMatch "\.(css|js)$">
        Header set Cache-Control "max-age=2592000, public"
    </FilesMatch>

    # Fonts
    <FilesMatch "\.(ttf|otf|woff|woff2|eot)$">
        Header set Cache-Control "max-age=31536000, public, immutable"
    </FilesMatch>

    # HTML
    <FilesMatch "\.(html|htm)$">
        Header set Cache-Control "max-age=0, no-cache, no-store, must-revalidate"
    </FilesMatch>

    # Security Headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# 4. ETags (برای بهبود caching)
<IfModule mod_headers.c>
    Header unset ETag
</IfModule>
FileETag None

# 5. جلوگیری از دسترسی به فایل‌های حساس
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
<FilesMatch "(composer\.json|composer\.lock|\.env|\.git)">
    Require all denied
</FilesMatch>
```

---

## 4. تنظیمات محیط Production

### الف. فایل `.env` در سرور

```env
APP_NAME="پلتفرم چن تا؟"
APP_ENV=production  # ✅ مهم
APP_KEY=base64:... # کلید خود را بگذارید
APP_DEBUG=false  # ✅ در production حتماً false
APP_URL=https://chanta.ir  # ✅ آدرس واقعی

# Timezone
APP_TIMEZONE=Asia/Tehran
APP_LOCALE=fa

# Session & Cache (✅ برای بهبود سرعت)
SESSION_DRIVER=file  # یا redis اگر دارید
SESSION_LIFETIME=120

CACHE_STORE=file  # یا redis اگر دارید

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Queue (اگر استفاده می‌کنید)
QUEUE_CONNECTION=database  # یا redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@chanta.ir
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@chanta.ir
MAIL_FROM_NAME="${APP_NAME}"

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=warning  # فقط warning و error

# Payment
ZARINPAL_MERCHANT_ID=your_merchant_id
ZARINPAL_IS_SANDBOX=false  # ✅ در production حتماً false
```

### ب. فایل‌های ضروری

مطمئن شوید این فایل‌ها در سرور وجود دارند:

```
/storage/
  /app/
  /framework/
    /cache/
    /sessions/
    /views/
  /logs/
```

و Permission مناسب داشته باشند:

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache  # یا user هاست شما
```

---

## 5. بررسی سرعت

### الف. دستور curl برای بررسی TTFB

```bash
# Windows PowerShell
curl -o NUL -s -w "DNS: %{time_namelookup}s | Connect: %{time_connect}s | SSL: %{time_appconnect}s | TTFB: %{time_starttransfer}s | Total: %{time_total}s\n" https://chanta.ir

# Linux / macOS
curl -o /dev/null -s -w "DNS: %{time_namelookup}s | Connect: %{time_connect}s | SSL: %{time_appconnect}s | TTFB: %{time_starttransfer}s | Total: %{time_total}s\n" https://chanta.ir
```

### ب. مقادیر خوب برای TTFB

- **عالی:** < 200ms
- **خوب:** 200-500ms
- **متوسط:** 500-1000ms
- **ضعیف:** > 1000ms

### ج. ابزارهای آنلاین

1. **GTmetrix**: https://gtmetrix.com
2. **Google PageSpeed Insights**: https://pagespeed.web.dev
3. **WebPageTest**: https://www.webpagetest.org
4. **Pingdom**: https://tools.pingdom.com

---

## 6. عیب‌یابی مشکلات سرعت

### الف. چک‌لیست اولیه

| مورد | وضعیت | راه‌حل |
|------|-------|---------|
| APP_DEBUG=false | ☑️ | تغییر دهید |
| APP_ENV=production | ☑️ | تغییر دهید |
| config:cache اجرا شده | ☑️ | `php artisan config:cache` |
| route:cache اجرا شده | ☑️ | `php artisan route:cache` |
| view:cache اجرا شده | ☑️ | `php artisan view:cache` |
| OPcache فعال است | ☑️ | بررسی `phpinfo()` |
| GZIP فعال است | ☑️ | بررسی `.htaccess` |
| SESSION_DRIVER=file | ☑️ | بررسی `.env` |
| CACHE_STORE=file | ☑️ | بررسی `.env` |

### ب. بررسی Query های کند

فایل `routes/web.php` را موقتاً اینطور تغییر دهید:

```php
use Illuminate\Support\Facades\DB;

if (app()->environment('local')) {
    DB::listen(function ($query) {
        if ($query->time > 100) { // بیشتر از 100ms
            logger()->warning('Slow Query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time . 'ms'
            ]);
        }
    });
}
```

### ج. بررسی Middleware های سنگین

لیست middleware های فعال:

```bash
php artisan route:list --columns=uri,middleware
```

### د. پروفایلینگ با Laravel Telescope (فقط Development)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

سپس مسیر `/telescope` را در مرورگر باز کنید.

**⚠️ هشدار:** Telescope را در production نصب نکنید!

---

## 7. بهینه‌سازی Database

### الف. Index ها

مطمئن شوید این فیلدها index دارند:

```sql
-- users
CREATE INDEX idx_email ON users(email);
CREATE INDEX idx_mobile ON users(mobile);

-- products
CREATE INDEX idx_slug ON products(slug);
CREATE INDEX idx_status ON products(status);
CREATE INDEX idx_supplier_id ON products(supplier_id);

-- categories
CREATE INDEX idx_slug ON categories(slug);

-- sessions
CREATE INDEX idx_last_activity ON sessions(last_activity);
```

### ب. پاکسازی Session های قدیمی

```bash
# اضافه کردن به cron
0 2 * * * php /path/to/artisan session:gc
```

---

## 8. نکات امنیتی

✅ **قبل از production:**

1. فایل `.env` را از Git حذف کنید (در `.gitignore` باشد)
2. `APP_DEBUG=false` را تنظیم کنید
3. `APP_KEY` را تغییر دهید (از سرور local کپی نکنید)
4. فایل‌های غیرضروری را حذف کنید:
   ```bash
   rm -rf .git tests node_modules
   ```
5. Permission فایل‌ها را بررسی کنید

---

## 9. مانیتورینگ مداوم

### الف. Uptime Monitoring

- **UptimeRobot**: https://uptimerobot.com (رایگان)
- **Pingdom**: https://www.pingdom.com

### ب. Error Monitoring

- **Laravel Log Viewer** (داخلی)
- **Sentry** (اختیاری): https://sentry.io

### ج. بررسی روزانه

هر روز:
1. بررسی `storage/logs/laravel.log`
2. بررسی زمان پاسخگویی سایت
3. بررسی disk space سرور

---

## 10. Checklist نهایی قبل از Deploy

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan optimize`
- [ ] `php artisan migrate --force`
- [ ] فایل `.htaccess` با تنظیمات بهینه
- [ ] فایل `.user.ini` با OPcache
- [ ] `SESSION_DRIVER=file`
- [ ] `CACHE_STORE=file`
- [ ] Permission های درست: `chmod -R 775 storage bootstrap/cache`
- [ ] بررسی TTFB با curl
- [ ] تست سایت از مرورگر
- [ ] بررسی لاگ‌ها: `tail -f storage/logs/laravel.log`

---

**تاریخ ایجاد:** 2026-08-27  
**نسخه:** 1.0  
**آخرین بروزرسانی:** 2026-08-27

