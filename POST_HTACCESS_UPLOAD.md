# کارهای لازم بعد از آپلود .htaccess جدید

## ✅ اقدامات ضروری (الان انجام دهید)

### 1. پاکسازی کش‌ها (مهم!)

در SSH یا Terminal:
```bash
cd /home/oirjwzer/chanta
php artisan optimize:clear
```

### 2. تست HTTPS (مهم!)

1. سایت را در مرورگر باز کنید: `https://new.chanta.ir`
2. مطمئن شوید HTTPS فعال است (آیکون قفل در نوار آدرس)
3. اگر HTTP به HTTPS ریدایرکت نمی‌شود، منتظر بمانید (ممکن است 5-10 دقیقه طول بکشد)

### 3. تست سایت

صفحات زیر را بررسی کنید:
- [ ] صفحه اصلی: `https://new.chanta.ir`
- [ ] پنل ادمین: `https://new.chanta.ir/admin`
- [ ] پنل تامین‌کننده: `https://new.chanta.ir/supplier/dashboard`
- [ ] یک صفحه محصول: `https://new.chanta.ir/products/...`

---

## 🔍 بررسی فعال بودن LSCache (اختیاری)

### روش 1: بررسی Response Headers

1. Developer Tools را باز کنید (F12)
2. به تب **Network** بروید
3. صفحه را رفرش کنید
4. روی درخواست اصلی کلیک کنید
5. در **Response Headers** دنبال این بگردید:
   ```
   Server: LiteSpeed
   X-LiteSpeed-Cache: hit
   ```

اگر `X-LiteSpeed-Cache: hit` را دیدید، یعنی کش کار می‌کند!

### روش 2: استفاده از curl

```bash
curl -I https://new.chanta.ir | grep -i "litespeed"
```

---

## ⚠️ اگر مشکلی پیش آمد

### مشکل 1: خطای 500

**راه‌حل:**
```bash
# بررسی لاگ خطا
tail -20 storage/logs/laravel.log

# یا موقتاً فعال کردن display_errors
# در .env:
APP_DEBUG=true
```

### مشکل 2: صفحه سفید

**راه‌حل:**
```bash
php artisan optimize:clear
chmod -R 775 storage bootstrap/cache
```

### مشکل 3: HTTPS کار نمی‌کند

**راه‌حل:**
- منتظر بمانید 5-10 دقیقه
- یا از `.htaccess` خطوط زیر را حذف کنید:
  ```apache
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```

### مشکل 4: LSCache کار نمی‌کند

**راه‌حل:**
- این طبیعی است اگر پلن هاست شما LSCache ندارد
- بهینه‌سازی‌های Gzip و Browser Caching همچنان کار می‌کنند

---

## 📊 چک‌لیست نهایی

بعد از آپلود .htaccess:

- [ ] `php artisan optimize:clear` اجرا شد
- [ ] سایت در HTTPS باز می‌شود
- [ ] صفحه اصلی کار می‌کند
- [ ] پنل ادمین کار می‌کند
- [ ] پنل تامین‌کننده کار می‌کند
- [ ] صفحات محصول کار می‌کنند
- [ ] خطایی در لاگ نیست

---

## 🚀 دستورات یکجا (کپی کنید)

```bash
cd /home/oirjwzer/chanta
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

---

## 📞 اگر همه چیز درست است

فایل‌های زیر را حذف کنید (امنیت):
- [ ] `public/diagnostic.php`
- [ ] `public/test-simple.php`
- [ ] `public/.htaccess.debug`

---

**نکته مهم:** اگر سایت درست کار می‌کند، نیازی به انجام کار دیگری نیست!
