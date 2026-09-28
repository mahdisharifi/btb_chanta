# اسکریپت‌های کمکی پروژه B2B Marketplace

این پوشه شامل اسکریپت‌های PowerShell برای مدیریت و نگهداری پروژه است.

## فهرست اسکریپت‌ها

### 1. بررسی BOM (check-bom.ps1)
**هدف:** بررسی تمام فایل‌های PHP و Blade برای وجود BOM (Byte Order Mark)

**استفاده:**
```powershell
.\.kiro\scripts\check-bom.ps1
```

**خروجی:**
- لیست فایل‌هایی که دارای BOM هستند
- تعداد کل فایل‌های مشکل‌دار

**زمان استفاده:**
- قبل از commit
- بعد از دریافت فایل‌ها از منابع خارجی
- هنگام بروز مشکلات Layout یا فاصله‌های اضافی در HTML

---

### 2. حذف BOM (remove-bom.ps1)
**هدف:** حذف خودکار BOM از تمام فایل‌های PHP و Blade

**استفاده:**
```powershell
.\.kiro\scripts\remove-bom.ps1
```

**ویژگی‌ها:**
- درخواست تأیید قبل از اعمال تغییرات
- تبدیل خودکار فایل‌ها به UTF-8 بدون BOM
- پاک‌سازی خودکار کش Blade بعد از اصلاح
- نمایش گزارش تعداد فایل‌های اصلاح شده

**⚠️ هشدار:** این اسکریپت فایل‌های شما را تغییر می‌دهد. قبل از اجرا backup تهیه کنید.

---

## درباره BOM (Byte Order Mark)

### چیست؟
BOM یک کاراکتر نامرئی (`EF BB BF` در UTF-8) است که در ابتدای برخی فایل‌های متنی قرار می‌گیرد.

### چرا مشکل‌ساز است؟
- در فایل‌های Blade به صورت `&#xFEFF;` در HTML رندر می‌شود
- باعث خرابی Layout، Grid، و فاصله‌های اضافی می‌شود
- در PHP می‌تواند خطای "headers already sent" ایجاد کند
- در JSON باعث خطای Parse می‌شود

### جلوگیری از BOM
**تنظیمات Editor:**

**VS Code:**
1. فایل را باز کنید
2. در نوار پایین روی "UTF-8 with BOM" کلیک کنید
3. "Save with Encoding" را انتخاب کنید
4. "UTF-8" (بدون BOM) را انتخاب کنید

**PhpStorm:**
1. Settings → Editor → File Encodings
2. تیک "Transparent native-to-ascii conversion" را بردارید
3. "Default encoding for properties files" را UTF-8 کنید

**Notepad++:**
1. Encoding → "Encode in UTF-8 without BOM"

---

## Workflow پیشنهادی

### قبل از Commit:
```powershell
# 1. بررسی BOM
.\.kiro\scripts\check-bom.ps1

# 2. در صورت یافتن BOM، حذف کنید
.\.kiro\scripts\remove-bom.ps1

# 3. تست کنید
php artisan serve

# 4. اگر همه چیز OK بود، commit کنید
git add .
git commit -m "feat: your changes"
```

### بعد از Pull از Git:
```powershell
# بررسی فایل‌های جدید
.\.kiro\scripts\check-bom.ps1
```

---

## پشتیبانی و گزارش مشکل

اگر با مشکلی مواجه شدید:
1. محتوای خطا را کپی کنید
2. به تیم توسعه گزارش دهید
3. اگر ممکن بود، اسکریپت را با `-Verbose` اجرا کنید

---

**آخرین بروزرسانی:** 2026-09-26  
**نگهدارنده:** DevOps Team
