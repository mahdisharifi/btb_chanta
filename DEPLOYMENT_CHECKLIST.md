# چک‌لیست استقرار Laravel در هاست اشتراکی

**تاریخ ایجاد:** 1405/07/01  
**پروژه:** B2B Marketplace  
**نسخه Laravel:** 12.51.0

---

## 📋 فهرست مطالب
1. [قبل از انتقال (لوکال)](#1-قبل-از-انتقال-لوکال)
2. [آماده‌سازی فایل‌ها](#2-آماده‌سازی-فایل‌ها)
3. [انتقال به هاست](#3-انتقال-به-هاست)
4. [تنظیمات هاست](#4-تنظیمات-هاست)
5. [پس از انتقال](#5-پس-از-انتقال)
6. [تست و بررسی نهایی](#6-تست-و-بررسی-نهایی)

---

## 1. قبل از انتقال (لوکال)

### ✅ بررسی سلامت کد

```bash
# بررسی syntax خطاها
php artisan route:list
php artisan config:clear
php artisan cache:clear

# تست migrations (بدون اجرای واقعی)
php artisan migrate --pretend

# بررسی تعداد روت‌ها
php artisan route:list --json | python -c "import sys, json; print(len(json.load(sys.stdin)))"
```

### ✅ تنظیم محیط Production

در فایل `.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# تنظیمات دیتابیس هاست (بعداً پر شود)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### ✅ بهینه‌سازی کد

```bash
# پاکسازی کش‌های قدیمی
php artisan optimize:clear

# بهینه‌سازی autoload
composer install --no-dev --optimize-autoloader

# کش کردن تنظیمات، روت‌ها و ویوها
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# یا یکجا با optimize
php artisan optimize
```

### ✅ بررسی فایل‌های حساس

**فایل‌هایی که نباید آپلود شوند:**
- `.git` folder
- `.env` (فقط نمونه)
- `node_modules` folder
- `tests` folder (اختیاری)
- `.phpunit.result.cache`
- `*.log` files
- `storage/logs/*`
- `storage/framework/cache/*`

---

## 2. آماده‌سازی فایل‌ها

### ساختار پوشه برای هاست اشتراکی

```
public_html/              ← ریشه دامنه
├── app/
├── bootstrap/
├── config/
├── database/
├── lang/
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env                  ← تنظیمات محیطی
├── .htaccess             ← در public/
├── index.php             ← در public/
├── ... (سایر فایل‌های public/)
└── artisan
```

### ✅ ایجاد فایل .htaccess برای public/

```apache
<IfModule mod_rewrite.c>
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

# Disable index viewing
Options -Indexes

# Protect sensitive files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### ✅ تنظیمات PHP برای هاست

ایجاد فایل `.user.ini` در پوشه `public/`:
```ini
; افزایش محدودیت‌ها برای هاست اشتراکی
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; تنظیمات امنیتی
display_errors = Off
log_errors = On
error_log = /home/username/logs/php_errors.log

; timezone
date.timezone = Asia/Tehran

; session
session.save_path = /home/username/tmp
session.gc_maxlifetime = 7200
```

---

## 3. انتقال به هاست

### ✅ روش 1: آپلود دستی (FTP/File Manager)

1. **فشرده‌سازی فایل‌ها:**
```bash
# ایجاد آرشیو (بدون پوشه‌های غیرضروری)
tar -czf project.tar.gz \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='.phpunit.result.cache' \
  --exclude='*.log' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  app bootstrap config database lang public resources routes storage vendor artisan composer.json composer.lock
```

2. **آپلود به هاست:**
   - استفاده از File Manager یا FTP
   - extract در `public_html`

3. **انتقال محتویات public:**
```bash
# محتویات public/ باید در ریشه public_html باشد
mv public_html/public/* public_html/
rm -rf public_html/public
```

### ✅ روش 2: Git Deploy (پیشنهادی)

```bash
# در سرور هاست
cd public_html
git clone https://github.com/your-repo/b2b-marketplace.git .

# نصب dependencies
composer install --no-dev --optimize-autoloader

# تنظیم .env
cp .env.example .env
nano .env

# Generate key
php artisan key:generate
```

---

## 4. تنظیمات هاست

### ✅ تنظیم مسیر DocumentRoot

**در cPanel → Subdomains or Additional Domains:**
- Document Root باید به `public_html` اشاره کند (نه `public_html/public`)

### ✅ تنظیم دسترسی‌های پوشه storage

```bash
# از طریق SSH یا Terminal در cPanel
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chmod -R 775 public/uploads

# اگر SSH ندارید، از File Manager استفاده کنید
# Right-click → Change Permissions → 775
```

### ✅ ایجاد دیتابیس

1. **در cPanel → MySQL Databases:**
   - Create New Database
   - Create MySQL User
   - Add User to Database (ALL PRIVILEGES)

2. **Import دیتابیس:**
```bash
# از طریق phpMyAdmin
# یا از طریق SSH:
mysql -u db_user -p db_name < backup.sql
```

### ✅ تنظیم SSL Certificate

1. **در cPanel → SSL/TLS:**
   - Install Let's Encrypt (رایگان)
   - یا SSL پولی

2. **اجبار به HTTPS:**
   - در `.htaccess` اضافه کنید:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 5. پس از انتقال

### ✅ دستورات اولیه در هاست

```bash
# پاکسازی کش‌های قدیمی
php artisan optimize:clear

# کش کردن تنظیمات
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# یا یکجا
php artisan optimize

# اجرای migrations
php artisan migrate --force

# لینک کردن storage (اگر نیاز است)
php artisan storage:link

# ایجاد symbolic link برای storage (در هاست اشتراکی ممکن است نیاز به دستی باشد)
```

### ✅ تنظیم Cron Job

**در cPanel → Cron Jobs:**
```
* * * * * cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1
```

### ✅ بررسی Queues

اگر از Queue استفاده می‌کنید:
```bash
# در cPanel → Terminal یا SSH
php artisan queue:work --daemon

# یا تنظیم Cron Job برای queue:work
*/5 * * * * cd /home/username/public_html && php artisan queue:work --stop-when-empty
```

---

## 6. تست و بررسی نهایی

### ✅ چک‌لیست تست

- [ ] **صفحه اصلی:** باز می‌شود؟
- [ ] **Login/Logout:** کار می‌کند؟
- [ ] **ثبت‌نام:** کار می‌کند؟
- [ ] **آپلود فایل:** کار می‌کند؟
- [ ] **دیتابیس:** اتصال برقرار است؟
- [ ] **SSL:** HTTPS فعال است؟
- [ ] **Cache:** کار می‌کند؟
- [ ] **Email:** ارسال می‌شود؟
- [ ] **API endpoints:** کار می‌کنند؟
- [ ] **Admin panel:** دسترسی وجود دارد؟
- [ ] **Error pages:** 404/500 نمایش داده می‌شود؟
- [ ] **Logs:** ذخیره می‌شوند؟

### ✅ بررسی لاگ‌ها

```bash
# مشاهده لاگ‌های Laravel
tail -f storage/logs/laravel.log

# مشاهده لاگ‌های PHP
tail -f /home/username/logs/php_errors.log
```

### ✅ تست Performance

```bash
# بررسی زمان پاسخ‌گویی
curl -w "@curl-format.txt" -o /dev/null -s https://yourdomain.com

# یا استفاده از ابزارهای آنلاین:
# - Google PageSpeed Insights
# - GTmetrix
# - Pingdom
```

---

## 🚨 مشکلات رایج و راه‌حل‌ها

### مشکل 1: خطای 500 Internal Server Error

**راه‌حل:**
1. بررسی `.env` و تنظیمات دیتابیس
2. بررسی دسترسی‌های storage و cache
3. بررسی لاگ‌های خطا
4. اجرای `php artisan optimize:clear`

### مشکل 2: صفحه سفید (White Screen)

**راه‌حل:**
1. فعال‌سازی `display_errors` در `.user.ini`
2. بررسی `memory_limit`
3. بررسی `max_execution_time`
4. بررسی لاگ‌های PHP

### مشکل 3: خطای "Class not found"

**راه‌حل:**
```bash
composer dump-autoload
php artisan optimize:clear
php artisan config:cache
```

### مشکل 4: آپلود فایل کار نمی‌کند

**راه‌حل:**
1. بررسی `upload_max_filesize` و `post_max_size` در `.user.ini`
2. بررسی دسترسی‌های پوشه `storage/app/public`
3. بررسی `FILESYSTEM_DISK` در `.env`

### مشکل 5: Queue کار نمی‌کند

**راه‌حل:**
1. بررسی `QUEUE_CONNECTION` در `.env`
2. تنظیم Cron Job برای `queue:work`
3. استفاده از Supervisor (در VPS)

---

## 📝 نکات مهم امنیتی

### ✅ تنظیمات امنیتی

1. **APP_DEBUG=false** در production
2. **تغییر APP_KEY** قبل از production
3. **استفاده از HTTPS** اجباری
4. **محدودیت آپلود فایل** (نوع و حجم)
5. **بکاپ‌گیری منظم** از دیتابیس
6. **بررسی لاگ‌ها** به صورت دوره‌ای
7. **آپدیت منظم** Laravel و packages

### ✅ محدودیت دسترسی

در `.htaccess`:
```apache
# جلوگیری از دسترسی به فایل‌های حساس
<FilesMatch "^\.env|composer\.(json|lock)|package\.json|webpack\.mix\.js">
    Order allow,deny
    Deny from all
</FilesMatch>

# جلوگیری از دسترسی به پوشه‌های حساس
RewriteRule ^(app|bootstrap|config|database|resources|routes|storage|vendor)/ - [F,L]
```

---

## 🔄 فرآیند بکاپ‌گیری

### بکاپ دیتابیس
```bash
# Cron Job روزانه
0 2 * * * mysqldump -u db_user -p'password' db_name > /home/username/backups/db_$(date +\%Y\%m\%d).sql
```

### بکاپ فایل‌ها
```bash
# Cron Job هفتگی
0 3 * * 0 tar -czf /home/username/backups/files_$(date +\%Y\%m\%d).tar.gz /home/username/public_html
```

---

## 📞 پشتیبانی

در صورت بروز مشکل:
1. بررسی لاگ‌ها: `storage/logs/laravel.log`
2. بررسی لاگ‌های PHP در cPanel
3. تماس با پشتیبانی هاست
4. مراجعه به مستندات Laravel

---

**آخرین بروزرسانی:** 1405/07/01
