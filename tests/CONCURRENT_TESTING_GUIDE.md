# راهنمای تست کاربر همزمان

این راهنما برای تست قابلیت‌های همزمان سیستم کاتالوگ PDF طراحی شده است.

## روش‌های تست

### 1. تست دستی (ساده‌ترین روش)

**ابزار مورد نیاز:**
- چند مرورگر مختلف (Chrome, Firefox, Edge)
- حالت Incognito/Private
- موبایل یا تبلت

**مراحل:**
1. مرورگر Chrome را باز کنید و با کاربر A وارد شوید
2. یک پنجره Incognito باز کنید و با کاربر B وارد شوید
3. هر دو کاربر به `/supplier/catalog/index` بروند
4. هر دو همزمان روی دکمه "PDF" کلیک کنند
5. بررسی کنید:
   - ✅ هر کاربر Progress Bar خودش را می‌بیند
   - ✅ درصد هر کاربر مستقل است
   - ✅ PDF ها با هم تداخل ندارند

---

### 2. تست با PHPUnit (تست خودکار)

**فایل تست:**
```
tests/Feature/ConcurrentUserTest.php
```

**اجرا:**
```bash
# اجرای تست
php artisan test --filter ConcurrentUserTest

# یا
php artisan test tests/Feature/ConcurrentUserTest.php
```

**تست‌های موجود:**
1. `test_concurrent_catalog_generation` - تست تولید کاتالوگ همزمان
2. `test_progress_bar_cache_isolation` - تست عدم تداخل Progress
3. `test_progress_api_endpoint` - تست API Progress

---

### 3. تست با Apache Bench (ab)

**نصب:**
```bash
# Linux/Ubuntu
sudo apt-get install apache2-utils

# macOS
brew install httpd

# Windows
# دانلود از: https://www.apachelounge.com/download/
```

**اجرا:**
```bash
# ویرایش فایل concurrent-test-ab.sh
nano tests/concurrent-test-ab.sh

# تنظیم URL و Session
BASE_URL="https://your-domain.com"
# Cookie را از مرورگر کپی کنید

# اجرا
chmod +x tests/concurrent-test-ab.sh
./tests/concurrent-test-ab.sh
```

**پارامترها:**
- `-n`: تعداد کل درخواست‌ها
- `-c`: تعداد کاربران همزمان
- `-H`: هدرها (Cookie برای احراز هویت)

**خروجی نمونه:**
```
Requests per second:    2.45 [#/sec] (mean)
Time per request:       408.163 [ms] (mean)
Failed requests:        0
```

---

### 4. تست با cURL (همزمان)

**اجرا:**
```bash
# ویرایش فایل concurrent-test.sh
nano tests/concurrent-test.sh

# تنظیم URL و Session
BASE_URL="https://your-domain.com"

# اجرا
chmod +x tests/concurrent-test.sh
./tests/concurrent-test.sh
```

---

### 5. تست با k6 (پیشرفته)

**نصب k6:**
```bash
# Windows (Chocolatey)
choco install k6

# macOS
brew install k6

# Linux
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C4914BAC7D3A
sudo add-apt-repository 'deb https://dl.k6.io/deb stable main'
sudo apt update
sudo apt install k6
```

**فایل تست k6:**
```javascript
// tests/concurrent-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '10s', target: 5 },  // 5 کاربر همزمان
        { duration: '30s', target: 10 }, // افزایش به 10 کاربر
        { duration: '10s', target: 0 },  // کاهش تدریجی
    ],
};

export default function () {
    let response = http.get('https://your-domain.com/supplier/catalog/preview/1', {
        headers: {
            'Cookie': 'laravel_session=YOUR_SESSION_HERE',
        },
    });

    check(response, {
        'status is 200': (r) => r.status == 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });

    sleep(1);
}
```

**اجرا:**
```bash
k6 run tests/concurrent-test.js
```

---

### 6. تست با Artillery (Node.js)

**نصب:**
```bash
npm install -g artillery
```

**فایل تست:**
```yaml
# tests/concurrent-test.yml
config:
  target: 'https://your-domain.com'
  phases:
    - duration: 60
      arrivalRate: 5
  defaults:
    headers:
      Cookie: 'laravel_session=YOUR_SESSION_HERE'

scenarios:
  - flow:
      - get:
          url: '/supplier/catalog/preview/1'
```

**اجرا:**
```bash
artillery run tests/concurrent-test.yml
```

---

## سناریوهای تست

### سناریو 1: تولید کاتالوگ همزمان
**هدف:** اطمینان از اینکه چند کاربر می‌توانند همزمان PDF تولید کنند

**مراحل:**
1. 5 کاربر همزمان وارد سیستم شوند
2. هر کاربر یک کاتالوگ مختلف انتخاب کند
3. همگی همزمان PDF تولید کنند
4. بررسی:
   - ✅ همه PDF ها با موفقیت تولید شوند
   - ✅ Progress هر کاربر جدا باشد
   - ✅ خطایی رخ ندهد

---

### سناریو 2: Progress Bar Isolation
**هدف:** اطمینان از عدم تداخل Progress بین کاربران

**مراحل:**
1. کاربر A شروع به تولید PDF کند
2. وقتی به 30% رسید، کاربر B شروع کند
3. بررسی:
   - ✅ کاربر A: 30%
   - ✅ کاربر B: 0%
   - ✅ هر progressKey منحصر به فرد باشد

---

### سناریو 3: Cache Isolation
**هدف:** اطمینان از عملکرد صحیح Cache

**تست:**
```php
// در tinker
$user1 = User::find(1);
$user2 = User::find(2);

$key1 = 'catalog_progress_' . $user1->id . '_test';
$key2 = 'catalog_progress_' . $user2->id . '_test';

Cache::put($key1, ['percent' => 30], 300);
Cache::put($key2, ['percent' => 60], 300);

// بررسی
Cache::get($key1); // باید 30% باشد
Cache::get($key2); // باید 60% باشد
```

---

## مانیتورینگ

### 1. بررسی Cache
```bash
# ت户数 Cache keys
php artisan tinker
>>> Cache::getPrefix()
>>> Cache::get('catalog_progress_.*')
```

### 2. بررسی لاگ‌ها
```bash
# مشاهده لاگ‌ها به صورت real-time
tail -f storage/logs/laravel.log

# جستجوی خطاها
grep "ERROR" storage/logs/laravel.log | tail -20
```

### 3. بررسی دیتابیس
```sql
-- مشاهده جدول Cache (اگر database driver باشد)
SELECT * FROM cache WHERE `key` LIKE 'catalog_progress_%';

-- پاکسازی Cache های قدیمی
DELETE FROM cache WHERE `key` LIKE 'catalog_progress_%' AND expiration < UNIX_TIMESTAMP();
```

---

## عیب‌یابی

### مشکل 1: تداخل Progress
**علت:** progressKey یکسان برای همه کاربران

**راه حل:**
```php
// اطمینان از تولید کلید یونیک
$this->progressKey = 'catalog_progress_' . session()->getId() . '_' . time();
```

---

### مشکل 2: Cache کار نمی‌کند
**علت:** Driver اشتباه یا تنظیمات نادرست

**راه حل:**
```bash
# بررسی .env
CACHE_STORE=database

# پاکسازی Cache
php artisan cache:clear

# تست Cache
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
```

---

### مشکل 3: خطای Memory
**علت:** تعداد محصولات زیاد

**راه حل:**
```php
// افزایش memory limit
ini_set('memory_limit', '512M');

// یا در php.ini
memory_limit = 512M
```

---

## گزارش تست

بعد از تست، این موارد را ثبت کنید:

```
### تست همزمان - [تاریخ]

**تعداد کاربران:** 5
**روش تست:** Apache Bench
**مدت زمان:** 60 ثانیه
**درخواست‌های موفق:** 48/50
**میانگین زمان پاسخ:** 408ms
**خطاها:** 0

**نتیجه:** ✅ همه تست‌ها موفق بودند
```

---

## منابع

- [k6 Documentation](https://k6.io/docs/)
- [Apache Bench Tutorial](https://httpd.apache.org/docs/2.4/programs/ab.html)
- [Artillery Documentation](https://www.artillery.io/docs)
- [Laravel Testing](https://laravel.com/docs/11.x/testing)
