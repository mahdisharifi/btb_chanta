# دستورات سریع استقرار Laravel

## 🔥 دستورات ضروری قبل از آپلود (لوکال)

```bash
# 1. پاکسازی کش‌های قدیمی
php artisan optimize:clear

# 2. نصب dependencies بدون dev
composer install --no-dev --optimize-autoloader

# 3. بهینه‌سازی کامل
php artisan optimize

# 4. بررسی روت‌ها
php artisan route:list

# 5. بررسی تنظیمات
php artisan config:show
```

---

## 🚀 دستورات ضروری بعد از آپلود (هاست)

### از طریق SSH یا Terminal در cPanel:

```bash
# 1. رفتن به پوشه پروژه
cd /home/username/public_html

# 2. پاکسازی کش‌های قدیمی
php artisan optimize:clear

# 3. کش کردن تنظیمات
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 4. اجرای migrations
php artisan migrate --force

# 5. لینک storage (اگر نیاز است)
php artisan storage:link

# 6. بهینه‌سازی نهایی
php artisan optimize
```

---

## 🔧 دستورات تعمیر و نگهداری

```bash
# پاکسازی همه کش‌ها
php artisan optimize:clear

# یا جداگانه:
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# بازسازی کش
php artisan optimize

# بررسی وضعیت
php artisan about

# تست دیتابیس
php artisan tinker
>>> DB::connection()->getPdo();

# بررسی لاگ‌ها
tail -f storage/logs/laravel.log
```

---

## 📦 بکاپ و بازیابی

### بکاپ دیتابیس:
```bash
# Export
mysqldump -u db_user -p db_name > backup.sql

# Import
mysql -u db_user -p db_name < backup.sql
```

### بکاپ فایل‌ها:
```bash
# فشرده‌سازی
tar -czf backup.tar.gz /home/username/public_html

# استخراج
tar -xzf backup.tar.gz -C /home/username/public_html
```

---

## 🚨 رفع مشکلات رایج

### خطای 500:
```bash
php artisan optimize:clear
chmod -R 775 storage bootstrap/cache
```

### خطای Class not found:
```bash
composer dump-autoload
php artisan optimize:clear
```

### خطای Permission denied:
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chmod -R 775 public/uploads
```

### خطای symlink:
```bash
# در هاست اشتراکی ممکن است دستی symlink بسازید
# یا محتویات storage/app/public را در public/storage کپی کنید
```

---

## ⏰ تنظیم Cron Job

```bash
# در cPanel → Cron Jobs
* * * * * cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔍 بررسی سلامت

```bash
# بررسی روت‌ها
php artisan route:list --name=home

# بررسی تنظیمات
php artisan config:show app

# بررسی environment
php artisan env

# تست application
php artisan about
```

---

## 📱 دسترسی سریع

| کار | دستور |
|-----|-------|
| پاکسازی همه کش‌ها | `php artisan optimize:clear` |
| بهینه‌سازی کامل | `php artisan optimize` |
| اجرای migration | `php artisan migrate --force` |
| بررسی روت‌ها | `php artisan route:list` |
| مشاهده لاگ‌ها | `tail -f storage/logs/laravel.log` |

---

**نکته:** همه دستورات را می‌توانید از **Artisan Runner** در پنل ادمین اجرا کنید!
