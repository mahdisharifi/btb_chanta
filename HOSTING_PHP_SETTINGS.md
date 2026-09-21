# تنظیمات PHP پیشنهادی برای هاست

## ✅ تنظیمات فعلی شما - عالی است!

تنظیمات فعلی کاملاً مناسب هستند. فقط چند پیشنهاد جزئی:

---

## 📝 پیشنهادات بهبود (اختیاری)

### 1. افزایش max_execution_time (اگر نیاز است)

برای عملیات‌های سنگین مثل import/export:

```ini
max_execution_time = 300
max_input_time = 300
```

### 2. Timezone (اختیاری)

برای اطمینان از timezone صحیح:

```ini
date.timezone = "Asia/Tehran"
```

### 3. Session Settings (اختیاری)

برای sessions طولانی‌تر:

```ini
session.gc_maxlifetime = 7200
session.cookie_lifetime = 7200
```

### 4. OPcache در Production

بعد از اتمام توسعه، برای performance بهتر:

```ini
opcache.validate_timestamps = 0
opcache.revalidate_freq = 60
```

**نکته:** با `validate_timestamps = 0`، بعد از هر تغییر کد باید:
```bash
php artisan optimize:clear
```

---

## 🎯 تنظیمات نهایی پیشنهادی

```ini
; cPanel-generated php ini directives, do not edit
; Manual editing of this file may result in unexpected behavior.
; To make changes to this file, use the cPanel MultiPHP INI Editor

allow_url_fopen = On
display_errors = Off
enable_dl = Off
error_log = "error_log"
file_uploads = On
log_errors = On

; Execution & Memory
max_execution_time = 300
max_input_time = 300
max_input_vars = 3000
memory_limit = 1000M

; Upload
post_max_size = 1024M
upload_max_filesize = 1024M

; Session
session.gc_maxlifetime = 7200
session.save_path = "/var/cpanel/php/sessions/ea-php82"

; Timezone
date.timezone = "Asia/Tehran"

; Compression
zlib.output_compression = Off

; --- OPcache Tuning ---
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.revalidate_freq = 60
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1
```

---

## ⚠️ نکات مهم

### در محیط توسعه:
```ini
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
```

### در محیط Production (نهایی):
```ini
opcache.validate_timestamps = 0
opcache.revalidate_freq = 60
```

---

## 📊 مقایسه Performance

| تنظیم | فعلی | پیشنهادی | توضیح |
|-------|------|----------|-------|
| memory_limit | 1000M | 1000M | ✓ عالی |
| max_execution_time | 60 | 300 | بهتر برای عملیات سنگین |
| opcache.memory | 256M | 256M | ✓ عالی |
| validate_timestamps | 1 | 0 | در production بگذارید 0 |

---

## ✅ نتیجه

تنظیمات فعلی شما **کاملاً مناسب** هستند. پیشنهادات بالا فقط برای بهبود بیشتر هستند و ضروری نیستند.

**اگر مشکلی ندارید، نیازی به تغییر نیست!**
