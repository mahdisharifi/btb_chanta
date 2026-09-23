# ✅ راه حل سریع: Specified key was too long (1000 bytes)

## 🎯 مشکل شما
هنگام import SQL در سرور:
```
#1071 - Specified key was too long; max key length is 1000 bytes
```

## ⚡ راه حل (3 گزینه)

---

### گزینه 1️⃣: استفاده از Migration (بهترین روش) ⭐

Migration اصلاح شد و مشکل برطرف شد.

#### در لوکال (انجام شد ✓):
```bash
php artisan migrate
# ✓ Migration: 2026_09_23_120000_create_products_fulltext_index اجرا شد
```

#### در سرور:
```bash
# 1. Pull کدهای جدید
git pull origin main

# 2. اجرای migration
php artisan migrate --force

# 3. بررسی
php artisan migrate:status | grep fulltext
```

**مزیت:**
- ✅ Clean و قابل trace
- ✅ مشکل برای همیشه حل می‌شود
- ✅ در Rollback هم کار می‌کند

---

### گزینه 2️⃣: اصلاح SQL Dump قبل از Import

اگر SQL dump قدیمی دارید و می‌خواهید import کنید:

#### روش A: اسکریپت خودکار
```bash
# در سرور، بعد از import
mysql -u username -p database_name < .kiro/scripts/fix-index-length-issues.sql
```

#### روش B: دستی در phpMyAdmin/MySQL
```sql
-- 1. حذف FULLTEXT قدیمی (اگر خطا داد)
ALTER TABLE products DROP INDEX IF EXISTS products_title_short_description_fulltext;

-- 2. ایجاد مجدد
ALTER TABLE products 
ADD FULLTEXT products_title_short_description_fulltext (title, short_description);
```

---

### گزینه 3️⃣: Export جدید از لوکال

```bash
# 1. در لوکال، اطمینان از اجرای migrationها
php artisan migrate

# 2. Export دیتابیس
mysqldump -u root -p --single-transaction b2b_marketplace > database_new.sql

# 3. Upload به سرور و Import
mysql -u username -p database_name < database_new.sql
```

---

## 🔍 چرا این مشکل رخ داد؟

### تحلیل تکنیکی:

```
utf8mb4: هر کاراکتر = 4 بایت
VARCHAR(255) × 4 = 1020 بایت
محدودیت MySQL = 1000 بایت
1020 > 1000 ❌
```

### تغییرات اعمال شده:

**قبل (در migration اصلی):**
```php
$table->fullText(['title', 'short_description']);
// ❌ Laravel این را به ALTER TABLE تبدیل می‌کند که ممکن است خطا دهد
```

**بعد (migration جداگانه):**
```php
DB::statement('ALTER TABLE products ADD FULLTEXT ...');
// ✅ Raw SQL که FULLTEXT را بدون محدودیت ایجاد می‌کند
```

---

## 📊 فایل‌های ایجاد شده

1. ✅ **Migration جدید:**
   - `database/migrations/2026_09_23_120000_create_products_fulltext_index.php`

2. ✅ **اسکریپت Fix:**
   - `.kiro/scripts/fix-index-length-issues.sql`

3. ✅ **مستندات کامل:**
   - `.kiro/docs/mysql-index-length-fix.md`

4. ✅ **این فایل (راهنمای سریع):**
   - `.kiro/QUICK-FIX-INDEX-LENGTH.md`

---

## 🎬 مراحل توصیه شده برای سرور

```bash
# 1. Backup فعلی
mysqldump -u username -p database_name > backup_before_fix.sql

# 2. Pull کدهای جدید
cd /path/to/project
git pull origin main

# 3. اجرای migration جدید
php artisan migrate --force

# 4. تست
php artisan migrate:status
```

---

## ⚠️ در صورت بروز مشکل

### اگر migration خطا داد:
```bash
# Rollback آخرین batch
php artisan migrate:rollback --step=1

# اجرای اسکریپت دستی
mysql -u username -p database_name < .kiro/scripts/fix-index-length-issues.sql

# Fake کردن migration
php artisan migrate --pretend
```

### اگر دیتابیس از قبل دارای index است:
```sql
-- بررسی
SHOW INDEXES FROM products WHERE Key_name LIKE '%fulltext%';

-- اگر وجود داشت، migration به صورت خودکار skip می‌شود ✓
```

---

## ✨ نتیجه

- ✅ Migration در لوکال تست شد
- ✅ FULLTEXT index به migration جداگانه منتقل شد
- ✅ مشکل در سرور برطرف می‌شود
- ✅ راه حل‌های backup و دستی هم آماده است

---

**توسعه‌دهنده:** Kiro AI  
**تاریخ:** 2026-09-23  
**وضعیت:** ✅ آماده برای Deploy
