# رفع خطای MySQL: Specified key was too long; max key length is 1000 bytes

## 🔴 خطا
```
#1071 - Specified key was too long; max key length is 1000 bytes
```

## 📊 علت مشکل

### چرا این خطا رخ می‌دهد؟

در MySQL با `utf8mb4` charset:
- هر کاراکتر = **4 بایت**
- محدودیت MySQL برای index = **1000 بایت** (در برخی نسخه‌ها 767 بایت)

**مثال:**
```php
$table->string('title'); // VARCHAR(255) - پیش‌فرض Laravel
// محاسبه: 255 × 4 = 1020 بایت > 1000 بایت ❌
```

### کدام indexها مشکل دارند؟

✅ **مشکل اصلی در پروژه شما:**
```php
$table->fullText(['title', 'short_description']); 
// title = VARCHAR(255) = 1020 بایت
// short_description = TEXT = بیشتر!
// مجموع >> 1000 بایت ❌
```

---

## ✅ راه حل‌ها

### گزینه 1: استفاده از Migration جدید (توصیه می‌شود)

Migration اصلاح شده است و FULLTEXT index به migration جداگانه منتقل شد.

#### مرحله 1: در لوکال

```bash
# Refresh migrations
php artisan migrate:fresh --seed
```

#### مرحله 2: در سرور

```bash
# اجرای migration جدید
php artisan migrate --force
```

این migration جدید ایجاد شده:
- `2026_09_23_120000_create_products_fulltext_index.php`

---

### گزینه 2: اصلاح مستقیم SQL در سرور

اگر دیتابیس از قبل import شده و فقط این index مشکل دارد:

```sql
-- 1. حذف FULLTEXT index اشتباه (اگر وجود دارد)
ALTER TABLE products 
DROP INDEX IF EXISTS products_title_short_description_fulltext;

-- 2. ایجاد FULLTEXT index صحیح
ALTER TABLE products 
ADD FULLTEXT INDEX products_title_short_description_fulltext (title, short_description);
```

**یا استفاده از اسکریپت آماده:**
```bash
mysql -u username -p database_name < .kiro/scripts/fix-index-length-issues.sql
```

---

### گزینه 3: تغییر طول ستون (اگر نیاز باشد)

اگر ستون‌های دیگر هم مشکل دارند:

```php
// به جای:
$table->string('column_name'); // 255 کاراکتر

// استفاده کنید:
$table->string('column_name', 191); // 191 × 4 = 764 بایت < 1000 ✓
```

**نکته:** برای `title` و `short_description` نیازی به تغییر طول نیست، چون FULLTEXT این محدودیت را ندارد.

---

## 🔍 تشخیص سایر مشکلات

### بررسی indexهای بلند در دیتابیس

```sql
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns,
    SUM(CHARACTER_MAXIMUM_LENGTH * 4) AS estimated_bytes
FROM information_schema.statistics s
JOIN information_schema.columns c 
    ON s.table_schema = c.table_schema 
    AND s.table_name = c.table_name 
    AND s.column_name = c.column_name
WHERE s.table_schema = 'your_database_name'
    AND s.index_type != 'FULLTEXT'
    AND c.DATA_TYPE IN ('varchar', 'char')
GROUP BY TABLE_NAME, INDEX_NAME
HAVING estimated_bytes > 1000
ORDER BY estimated_bytes DESC;
```

---

## 📋 تفاوت‌های نسخه‌های MySQL

| نسخه MySQL | محدودیت Index با utf8mb4 | محدودیت FULLTEXT |
|-----------|-------------------------|------------------|
| MySQL 5.6 | 767 بایت | بدون محدودیت |
| MySQL 5.7 | 767 بایت (با innodb_large_prefix: 3072) | بدون محدودیت |
| MySQL 8.0+ | 3072 بایت | بدون محدودیت |
| MariaDB 10.2+ | 3072 بایت | بدون محدودیت |

**نکته:** در shared hosting معمولاً MySQL 5.7 با تنظیمات پیش‌فرض است (767 یا 1000 بایت).

---

## 🎯 راه حل نهایی برای پروژه B2B

### تغییرات اعمال شده:

#### 1. Migration اصلی (`create_products_table`)
```php
// ❌ قبلی (مشکل‌دار):
$table->fullText(['title', 'short_description']);

// ✅ فعلی (اصلاح شده):
// FULLTEXT index moved to separate migration
// See: 2026_09_23_120000_create_products_fulltext_index.php
```

#### 2. Migration جدید (`create_products_fulltext_index`)
```php
// ایجاد FULLTEXT با raw SQL که محدودیت ندارد
DB::statement('ALTER TABLE products ADD FULLTEXT products_title_short_description_fulltext (title, short_description)');
```

---

## 🚀 دستورات Import به سرور

### روش 1: Migration (توصیه می‌شود)

```bash
# 1. آپلود کدها به سرور
git pull origin main

# 2. اجرای migrationها
php artisan migrate --force

# 3. بررسی
php artisan migrate:status
```

### روش 2: SQL Dump

```bash
# 1. Export از لوکال با migration جدید
php artisan migrate:fresh --seed
mysqldump -u root -p --single-transaction database_name > database_export.sql

# 2. Import در سرور
mysql -u username -p database_name < database_export.sql

# 3. اگر خطا آمد، اسکریپت fix را اجرا کنید
mysql -u username -p database_name < .kiro/scripts/fix-index-length-issues.sql
```

---

## ⚠️ مشکلات رایج و راه حل

### 1. خطا در حین CREATE TABLE
```
Error: Specified key was too long during CREATE TABLE
```

**راه حل:**
```sql
-- قبل از import، اجرای:
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### 2. خطا با UNIQUE index
```
Error on: ALTER TABLE ADD UNIQUE KEY
```

**راه حل:**
```php
// به جای:
$table->string('email')->unique();

// استفاده کنید:
$table->string('email', 191)->unique();
```

### 3. خطا با Composite Index
```
Error on multi-column index
```

**راه حل:**
```php
// بررسی مجموع طول ستون‌ها
$table->index(['column1', 'column2']); 
// مطمئن شوید: column1_length + column2_length < 250 (در utf8mb4)
```

---

## 📝 چک‌لیست Import به سرور

- [ ] Backup از دیتابیس سرور گرفته شده
- [ ] Migration جدید `create_products_fulltext_index` commit شده
- [ ] در لوکال بدون خطا اجرا می‌شود
- [ ] نسخه MySQL سرور بررسی شده
- [ ] تنظیمات `innodb_large_prefix` بررسی شده
- [ ] اسکریپت fix آماده است (در صورت نیاز)

---

## 🔗 منابع مفید

- [MySQL Documentation: Index Length Limits](https://dev.mysql.com/doc/refman/8.0/en/innodb-limits.html)
- [Laravel Migrations: String Length](https://laravel.com/docs/migrations#indexes)
- [UTF8MB4 and Index Length](https://stackoverflow.com/questions/1814532/)

---

**تاریخ ایجاد:** 2026-09-23  
**آخرین بروزرسانی:** 2026-09-23  
**مرتبط با:** Database Migration, MySQL Optimization, Server Deployment
