# 📖 راهنمای کامل مهاجرت تیراژبندی خودکار

**نسخه:** 1.0  
**تاریخ:** 1404/06/07  
**وضعیت:** ✅ آماده برای Production

---

## 📑 فهرست مطالب

1. [خلاصه تغییرات](#خلاصه-تغییرات)
2. [پیش‌نیازها](#پیشنیازها)
3. [روش‌های مهاجرت](#روشهای-مهاجرت)
4. [مستندات تفصیلی](#مستندات-تفصیلی)
5. [عیب‌یابی](#عیبیابی)
6. [پشتیبانی](#پشتیبانی)

---

## 🎯 خلاصه تغییرات

### تغییرات در دیتابیس

**جدول:** `products`  
**فیلد تغییر یافته:** `quantity_tiers` (JSON)

```sql
-- قبل از Migration
quantity_tiers: NULL یا []

-- بعد از Migration (نمونه)
quantity_tiers: [
  {
    "name": "خرده‌فروشی",
    "min_quantity": 1,
    "max_quantity": 199,
    "description": "کمتر از یک کارتن (1 تا 199 عدد)"
  },
  {
    "name": "عمده‌فروشی کوچک", 
    "min_quantity": 200,
    "max_quantity": 1000,
    "description": "1 تا 5 کارتن (200 تا 1000 عدد)"
  },
  {
    "name": "عمده‌فروشی بزرگ",
    "min_quantity": 1001,
    "max_quantity": null,
    "description": "بیش از 5 کارتن (1001 عدد به بالا)"
  }
]
```

### تغییرات کد

**فایل‌های جدید:**
- ✅ `app/Helpers/QuantityTierValidator.php`
- ✅ `app/Console/Commands/GenerateQuantityTiersForProducts.php`
- ✅ `app/Console/Commands/CheckProductsHealth.php`

**فایل‌های بروزرسانی شده:**
- ✅ `app/Livewire/Admin/Products/Create.php`
- ✅ `resources/views/livewire/admin/products/create.blade.php`
- ✅ `resources/views/livewire/supplier/products/edit-price.blade.php`

**بدون تغییر Schema دیتابیس:** ✅ فقط داده‌ها تغییر می‌کنند

---

## 📋 پیش‌نیازها

### نرم‌افزارها

- ✅ PHP >= 8.1
- ✅ MySQL >= 5.7 یا MariaDB >= 10.3
- ✅ Composer
- ✅ Git
- ✅ Laravel 12.5

### دسترسی‌ها

- ✅ دسترسی MySQL به دیتابیس
- ✅ دسترسی خواندن/نوشتن به پوشه پروژه
- ✅ دسترسی اجرای دستورات Artisan

### فضای دیسک

- ✅ حداقل 500MB برای backup دیتابیس
- ✅ حداقل 1GB فضای آزاد توصیه می‌شود

---

## 🚀 روش‌های مهاجرت

### روش 1: اسکریپت خودکار (توصیه می‌شود)

#### Windows (PowerShell):
```powershell
cd E:\sharifi\projects\b2b-marketplace-new
.\scripts\migrate-quantity-tiers.ps1
```

#### Linux/Mac (Bash):
```bash
cd /path/to/b2b-marketplace-new
chmod +x scripts/migrate-quantity-tiers.sh
./scripts/migrate-quantity-tiers.sh
```

**مزایا:**
- ✅ تعاملی و کاربرپسند
- ✅ Backup خودکار
- ✅ Rollback در صورت خطا
- ✅ گزارش‌دهی کامل

---

### روش 2: دستی (گام به گام)

برای کنترل بیشتر:

```bash
# 1. Backup
mysqldump -u root -p b2b_marketplace_server > backup_$(date +%Y%m%d_%H%M%S).sql
git add . && git commit -m "Backup before migration"

# 2. Import (اختیاری)
mysql -u root -p b2b_marketplace_server < database_production.sql

# 3. بررسی سلامت
php artisan products:check-health

# 4. اصلاح (در صورت نیاز)
# دستورات اصلاح در مستندات تفصیلی

# 5. Dry-Run
php artisan products:generate-tiers --dry-run

# 6. اجرا
php artisan products:generate-tiers

# 7. Verification
php artisan tinker --execute="echo App\Models\Product::whereNotNull('quantity_tiers')->count();"
```

مستندات کامل: [database-migration-quantity-tiers.md](./database-migration-quantity-tiers.md)

---

### روش 3: سریع (Quick Start)

برای تست سریع:

```bash
# پیش‌نمایش
php artisan products:generate-tiers --dry-run

# اجرا
php artisan products:generate-tiers
```

راهنمای سریع: [MIGRATION-QUICK-START.md](./MIGRATION-QUICK-START.md)

---

## 📚 مستندات تفصیلی

### 📄 فایل‌های مستندات

| فایل | توضیحات | زمان مطالعه |
|------|---------|------------|
| [database-migration-quantity-tiers.md](./database-migration-quantity-tiers.md) | راهنمای کامل و جزئیات فنی | 15 دقیقه |
| [MIGRATION-QUICK-START.md](./MIGRATION-QUICK-START.md) | راهنمای سریع (Cheat Sheet) | 2 دقیقه |
| [price-matrix-security.md](./price-matrix-security.md) | راهنمای امنیتی تیراژبندی | 10 دقیقه |

### 🔧 Commands مفید

```bash
# بررسی سلامت دیتابیس
php artisan products:check-health

# تولید تیراژ (Dry-Run)
php artisan products:generate-tiers --dry-run

# تولید تیراژ (واقعی)
php artisan products:generate-tiers

# فقط محصولات فعال
php artisan products:generate-tiers --active-only

# فقط یک تامین‌کننده
php artisan products:generate-tiers --supplier=42
```

---

## 🐛 عیب‌یابی

### مشکل 1: محصولات Skip می‌شوند

**علت:** `packaging_unit_type` یا `quantity_per_packaging` نامعتبر است

**راه‌حل:**
```sql
-- بررسی
SELECT id, title, packaging_unit_type, quantity_per_packaging 
FROM products 
WHERE packaging_unit_type IS NULL 
   OR quantity_per_packaging IS NULL 
   OR quantity_per_packaging <= 0
LIMIT 10;

-- اصلاح
UPDATE products 
SET packaging_unit_type = 'carton', 
    quantity_per_packaging = 100
WHERE packaging_unit_type IS NULL 
   OR quantity_per_packaging IS NULL 
   OR quantity_per_packaging <= 0;
```

---

### مشکل 2: خطای Validation

**علت:** تیراژهای موجود نامعتبر هستند (کمتر از 3 یا بیشتر از 5)

**راه‌حل:**
```bash
# حذف تیراژهای نامعتبر
php artisan tinker --execute="
DB::table('products')
    ->whereJsonLength('quantity_tiers', '<', 3)
    ->orWhereJsonLength('quantity_tiers', '>', 5)
    ->update(['quantity_tiers' => null]);
"

# اجرای دوباره
php artisan products:generate-tiers
```

---

### مشکل 3: خطای MySQL Connection

**علت:** اطلاعات اتصال دیتابیس نادرست است

**راه‌حل:**
```bash
# بررسی .env
cat .env | grep DB_

# تست اتصال
php artisan tinker --execute="DB::connection()->getPdo();"
```

---

### مشکل 4: Memory Limit

**علت:** تعداد محصولات بسیار زیاد است

**راه‌حل:**
```bash
# افزایش Memory Limit موقت
php -d memory_limit=512M artisan products:generate-tiers

# یا در .env
# در php.ini: memory_limit = 512M
```

---

### مشکل 5: Timeout

**علت:** Command طولانی است

**راه‌حل:**
```bash
# افزایش Timeout موقت
php artisan products:generate-tiers --no-interaction

# یا اجرای دسته‌ای
php artisan products:generate-tiers --supplier=1
php artisan products:generate-tiers --supplier=2
# ...
```

---

## 📞 پشتیبانی

### لاگ‌ها

تمام عملیات Command در فایل لاگ ذخیره می‌شوند:

```bash
# ذخیره خروجی در فایل
php artisan products:generate-tiers > migration.log 2>&1

# مشاهده لاگ
cat migration.log
# یا در Windows:
Get-Content migration.log
```

### گزارش خطا

در صورت بروز مشکل، این اطلاعات را آماده کنید:

1. ✅ اسکرین‌شات خطا
2. ✅ محتوای `migration.log`
3. ✅ خروجی `php artisan products:check-health`
4. ✅ نسخه PHP: `php -v`
5. ✅ نسخه Laravel: `php artisan --version`
6. ✅ آمار دیتابیس:
   ```sql
   SELECT COUNT(*) FROM products;
   SELECT COUNT(*) FROM products WHERE quantity_tiers IS NOT NULL;
   ```

---

## 📊 آمار و گزارش

### Template گزارش نهایی

پس از اتمام Migration، این فرم را پر کنید:

```
✅ گزارش نهایی Migration

تاریخ: _____/_____/_____
زمان شروع: __:__
زمان پایان: __:__
مدت زمان کل: _____ دقیقه

═══════════════════════════════════════════
📊 آمار
═══════════════════════════════════════════
کل محصولات: __________
محصولات بروزرسانی شده: __________
محصولات Skip شده: __________
خطاها: __________

═══════════════════════════════════════════
✅ تست‌های انجام شده
═══════════════════════════════════════════
[ ] ایجاد محصول جدید
[ ] ویرایش محصول موجود
[ ] قیمت‌گذاری تامین‌کننده
[ ] Validation خطاها
[ ] Error Summary
[ ] Auto Scroll

═══════════════════════════════════════════
📝 مشکلات و راه‌حل‌ها
═══════════════════════════════════════════
_____________________________________________
_____________________________________________
_____________________________________________

═══════════════════════════════════════════
✅ Backup
═══════════════════════════════════════════
فایل Backup: ____________________________
حجم: _______ MB
Git Branch: ______________________________

═══════════════════════════════════════════
✍️ امضا
═══════════════════════════════════════════
نام: ____________________
تاریخ: _____/_____/_____
```

---

## 🎉 موفقیت!

اگر تمام مراحل را طی کردید:

✅ دیتابیس شما آماده است  
✅ همه محصولات تیراژ دارند  
✅ سیستم آماده استفاده است  

**مراحل بعدی:**
1. Commit تغییرات: `git add . && git commit -m "Complete quantity tier migration"`
2. آموزش تیم درباره سیستم جدید
3. نظارت بر عملکرد سیستم در روزهای اول

**منابع:**
- 📖 مستندات API: `/docs/api`
- 🎥 ویدیوهای آموزشی: (در صورت وجود)
- 💬 پشتیبانی: (اطلاعات تماس)

---

**🙏 سپاسگزاریم که از سیستم تیراژبندی خودکار استفاده می‌کنید!**
