# راهنمای رفع خطای SQL Dump در Import به سرور

## مشکل
هنگام import فایل SQL به سرور، خطای سینتکس در دستورات `ALTER TABLE` برای ایجاد index:

```sql
-- ❌ سینتکس اشتباه (که در dump شما وجود دارد)
ADD KEY `products_sku_idx` `sku`);

-- ✅ سینتکس صحیح
ADD KEY `products_sku_idx` (`sku`);
```

**علت:** پرانتز دور نام ستون کم است.

---

## راه حل 1️⃣: اصلاح خودکار با PowerShell (توصیه می‌شود)

### مرحله 1: اجرای اسکریپت اصلاح

```powershell
# اصلاح فایل SQL dump
powershell -ExecutionPolicy Bypass -File .kiro/scripts/fix-sql-dump-syntax.ps1 -InputFile "path/to/your/database.sql"
```

**نکته:** اسکریپت به صورت خودکار backup از فایل اصلی می‌گیرد.

### مرحله 2: آپلود به سرور

فایل اصلاح شده را به سرور آپلود کنید.

### مرحله 3: Import در سرور

```bash
mysql -u username -p database_name < database.sql
```

---

## راه حل 2️⃣: اصلاح دستی

### با Find & Replace در ویرایشگر متن

1. فایل SQL dump را در VS Code یا Notepad++ باز کنید
2. از Find & Replace با Regex استفاده کنید:

**الگوی 1: ADD KEY**
- Find: `ADD KEY\s+(`[^`]+`)\s+(`[^`]+`)\)`
- Replace: `ADD KEY $1 ($2))`

**الگوی 2: ADD UNIQUE KEY**
- Find: `ADD UNIQUE KEY\s+(`[^`]+`)\s+(`[^`]+`)\)`
- Replace: `ADD UNIQUE KEY $1 ($2))`

**الگوی 3: ADD PRIMARY KEY**
- Find: `ADD PRIMARY KEY\s+(`[^`]+`)\)`
- Replace: `ADD PRIMARY KEY ($1))`

3. Replace All را بزنید
4. فایل را ذخیره کنید

---

## راه حل 3️⃣: Export صحیح از دیتابیس لوکال (بهترین روش)

اگر هنوز فایل SQL را export نکرده‌اید، از دستور زیر استفاده کنید:

### در Windows (با XAMPP/Laragon)

```powershell
# مسیر mysqldump را پیدا کنید
# XAMPP: C:\xampp\mysql\bin\mysqldump.exe
# Laragon: C:\laragon\bin\mysql\mysql-x.x.x-winx64\bin\mysqldump.exe

& "C:\xampp\mysql\bin\mysqldump.exe" `
  --user=root `
  --password=your_password `
  --single-transaction `
  --routines `
  --triggers `
  --events `
  --add-drop-table `
  --quote-names `
  --default-character-set=utf8mb4 `
  your_database_name > database_export.sql
```

### یا استفاده از Laravel Artisan

```bash
php artisan db:seed --class=DatabaseExportSeeder
```

---

## بررسی پس از اصلاح

پیش از آپلود به سرور، فایل SQL را بررسی کنید:

```powershell
# جستجوی الگوهای اشتباه
Select-String -Path "database.sql" -Pattern "ADD KEY\s+\`[^\`]+\`\s+\`[^\`]+\`\)" | Select-Object -First 5
```

اگر هیچ نتیجه‌ای نیامد، یعنی فایل اصلاح شده است ✓

---

## تست Import در لوکال (قبل از سرور)

```bash
# ایجاد دیتابیس تست
mysql -u root -p -e "CREATE DATABASE test_import;"

# تست import
mysql -u root -p test_import < database.sql

# در صورت موفقیت، حذف دیتابیس تست
mysql -u root -p -e "DROP DATABASE test_import;"
```

---

## مشکلات رایج و راه حل

### 1. خطای "Access denied"
```bash
# بررسی کاربر و رمز عبور
mysql -u username -p -e "SELECT USER();"
```

### 2. خطای "Unknown database"
```bash
# ایجاد دیتابیس قبل از import
mysql -u username -p -e "CREATE DATABASE database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. خطای "max_allowed_packet"
```bash
# در my.cnf یا my.ini افزایش دهید:
[mysqld]
max_allowed_packet=512M
```

### 4. خطای timeout در import
```bash
# import به صورت تکه‌تکه
split -l 10000 database.sql database_part_

# سپس هر تکه را جداگانه import کنید
for file in database_part_*; do
    mysql -u username -p database_name < "$file"
done
```

---

## چک‌لیست قبل از Import به سرور Production

- [ ] Backup از دیتابیس فعلی سرور گرفته شده
- [ ] فایل SQL در لوکال تست شده
- [ ] سینتکس SQL اصلاح شده (با اسکریپت یا دستی)
- [ ] فایل در محیط test import تست شده
- [ ] اندازه فایل و زمان import برآورد شده
- [ ] دسترسی SSH/FTP به سرور بررسی شده
- [ ] تنظیمات `.env` سرور بررسی شده

---

## نکات امنیتی

⚠️ **هرگز فایل SQL حاوی اطلاعات حساس را در Git commit نکنید**

```bash
# اضافه کردن به .gitignore
echo "*.sql" >> .gitignore
echo "database_export*.sql" >> .gitignore
```

---

**تاریخ ایجاد:** 2026-09-23  
**مرتبط با:** Database Migration, Server Deployment
