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
cd /home/oirjwzer/chanta

# 2. پاکسازی کش‌های قدیمی
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan optimize:clear

# 3. کش کردن تنظیمات
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan config:cache
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan route:cache
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan view:cache
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan event:cache

# 4. اجرای migrations
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan migrate --force

# 5. لینک storage (اگر نیاز است)
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan storage:link

# 6. بهینه‌سازی نهایی
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan optimize
```

---

## 🔧 دستورات تعمیر و نگهداری

```bash
# پاکسازی همه کش‌ها
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan optimize:clear

# یا جداگانه:
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan cache:clear
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan config:clear
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan route:clear
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan view:clear

# بازسازی کش
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan optimize

# بررسی وضعیت
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan about

# تست دیتابیس
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan tinker
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
tar -czf backup.tar.gz /home/oirjwzer/chanta

# استخراج
tar -xzf backup.tar.gz -C /home/oirjwzer/chanta
```

---

## 🚨 رفع مشکلات رایج

### خطای 500:
```bash
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan optimize:clear
chmod -R 775 storage bootstrap/cache
```

### خطای Class not found:
```bash
# Composer هاست را با /usr/local/bin/ea-php82 و مسیر تأییدشدهٔ فایل Composer اجرا کنید؛ سپس dump-autoload را اجرا کنید.
# مسیر Composer روی این هاست هنوز بررسی نشده است.
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan optimize:clear
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
* * * * * /usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan schedule:run >> /home/oirjwzer/chanta/storage/logs/scheduler-cron.log 2>&1
```

در cPanel پنج فیلد زمان را روی `*` قرار دهید و فقط بخش بعد از پنج ستاره را در Command وارد کنید. همین زمان‌بند worker صف را اجرا می‌کند؛ Cron جداگانه برای `queue:work` اضافه نکنید. جزئیات استقرار و بررسی اجرا در [HOSTING_CRON.md](HOSTING_CRON.md) آمده است.

---

## 🔍 بررسی سلامت

```bash
# بررسی روت‌ها
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan route:list --name=home

# بررسی تنظیمات
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan config:show app

# بررسی environment
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan env

# تست application
/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan about
```

---

## 📱 دسترسی سریع

| کار | دستور |
|-----|-------|
| پاکسازی همه کش‌ها | `/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan optimize:clear` |
| بهینه‌سازی کامل | `/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan optimize` |
| اجرای migration | `/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan migrate --force` |
| بررسی روت‌ها | `/usr/local/bin/ea-php82 /home/oirjwzer/chanta/artisan route:list` |
| مشاهده لاگ‌ها | `tail -f storage/logs/laravel.log` |

---

**نکته:** همه دستورات را می‌توانید از **Artisan Runner** در پنل ادمین اجرا کنید!
