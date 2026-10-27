# 📋 دستورالعمل مهاجرت به دیتابیس اصلی - سیستم تیراژبندی

**تاریخ ایجاد:** 1404/06/07  
**وضعیت:** آماده برای اجرا  
**مدت زمان تخمینی:** 10-15 دقیقه

---

## 📦 تغییرات اعمال شده در دیتابیس تست

### 1️⃣ تیراژبندی خودکار
- **جدول:** `products`
- **فیلد:** `quantity_tiers` (JSON)
- **تعداد رکوردهای تغییر یافته:** 637 محصول (از 940 محصول بدون تیراژ)
- **ساختار داده:**
```json
[
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

### 2️⃣ سایر تغییرات کد
**فایل‌های PHP (بدون تغییر در دیتابیس):**
- `app/Helpers/QuantityTierValidator.php` ✅ جدید
- `app/Livewire/Admin/Products/Create.php` ✅ بروزرسانی
- `app/Console/Commands/GenerateQuantityTiersForProducts.php` ✅ جدید
- `resources/views/livewire/admin/products/create.blade.php` ✅ بروزرسانی
- `resources/views/livewire/supplier/products/edit-price.blade.php` ✅ بروزرسانی

---

## 🔄 مراحل مهاجرت به دیتابیس اصلی

### مرحله 1: پشتیبان‌گیری (⚠️ الزامی)

```bash
# 1. پشتیبان از دیتابیس
mysqldump -u root -p b2b_marketplace_server > backup_before_migration_$(date +%Y%m%d_%H%M%S).sql

# 2. پشتیبان از کد فعلی
git add .
git commit -m "Backup before quantity tier migration"
git branch backup-before-migration
```

**✅ چک‌لیست پشتیبان‌گیری:**
- [ ] فایل SQL backup ایجاد شد
- [ ] حجم فایل backup معقول است (حدود X MB)
- [ ] Git commit انجام شد
- [ ] برنچ backup ایجاد شد

---

### مرحله 2: Import دیتابیس اصلی

```bash
# 1. Drop دیتابیس فعلی (تست)
mysql -u root -p -e "DROP DATABASE IF EXISTS b2b_marketplace_server;"

# 2. ایجاد دیتابیس جدید
mysql -u root -p -e "CREATE DATABASE b2b_marketplace_server CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Import دیتابیس اصلی
mysql -u root -p b2b_marketplace_server < database_production.sql
```

**✅ چک‌لیست Import:**
- [ ] دیتابیس قدیمی حذف شد
- [ ] دیتابیس جدید ایجاد شد
- [ ] Import بدون خطا انجام شد
- [ ] تعداد محصولات بررسی شد: `SELECT COUNT(*) FROM products;`

---

### مرحله 3: بررسی وضعیت دیتابیس اصلی

```bash
# تعداد کل محصولات
php artisan tinker --execute="echo 'کل محصولات: ' . App\Models\Product::count();"

# محصولات دارای تیراژ
php artisan tinker --execute="echo 'محصولات با تیراژ: ' . App\Models\Product::whereNotNull('quantity_tiers')->count();"

# محصولات بدون تیراژ
php artisan tinker --execute="echo 'محصولات بدون تیراژ: ' . App\Models\Product::whereNull('quantity_tiers')->orWhereJsonLength('quantity_tiers', 0)->count();"

# محصولات با packaging_unit_type
php artisan tinker --execute="echo 'محصولات با واحد بسته‌بندی: ' . App\Models\Product::whereNotNull('packaging_unit_type')->count();"
```

**📊 آمار مورد انتظار:**
```
کل محصولات: ≈ 900-1000
محصولات با تیراژ: 0-10 (محصولات قدیمی که دستی تیراژ داشتند)
محصولات بدون تیراژ: ≈ 890-990
محصولات با واحد بسته‌بندی: ≈ 900-1000
```

---

### مرحله 4: اجرای Migration (Dry-Run)

```bash
# پیش‌نمایش تغییرات
php artisan products:generate-tiers --dry-run
```

**✅ موارد بررسی در Dry-Run:**
- [ ] تعداد محصولات بدون تیراژ صحیح است؟
- [ ] تعداد محصولات قابل بروزرسانی منطقی است؟
- [ ] تعداد محصولات Skip شده کم است؟ (باید کمتر از 10% باشد)
- [ ] خطاهای نمایش داده شده قابل قبول هستند؟

**⚠️ اگر خطاهای زیاد دیدید:**
- محصولاتی که `quantity_per_packaging` تعیین نشده یا صفر است
- محصولاتی که `packaging_unit_type` خالی است
- این موارد باید قبل از اجرا اصلاح شوند

---

### مرحله 5: اصلاح محصولات مشکل‌دار (در صورت نیاز)

اگر در Dry-Run محصولات زیادی skip شدند:

```php
// در tinker
php artisan tinker

// محصولات بدون واحد بسته‌بندی
$products = App\Models\Product::whereNull('packaging_unit_type')->get();
echo "محصولات بدون واحد: " . $products->count() . PHP_EOL;

// محصولات با quantity_per_packaging نامعتبر
$products = App\Models\Product::where('quantity_per_packaging', '<=', 0)->get();
echo "محصولات با تعداد نامعتبر: " . $products->count() . PHP_EOL;
```

**اصلاح دسته‌جمعی (در صورت نیاز):**
```php
// مثال: تنظیم پیش‌فرض برای محصولاتی که واحد ندارند
DB::table('products')
    ->whereNull('packaging_unit_type')
    ->update([
        'packaging_unit_type' => 'carton',
        'quantity_per_packaging' => 100
    ]);
```

---

### مرحله 6: اجرای Migration (واقعی)

```bash
# اجرای واقعی
php artisan products:generate-tiers

# یا فقط محصولات فعال
php artisan products:generate-tiers --active-only
```

**⏱️ زمان تخمینی:**
- برای 1000 محصول: ≈ 2-3 دقیقه
- برای 5000 محصول: ≈ 10-15 دقیقه

**✅ موارد بررسی بعد از اجرا:**
- [ ] فرآیند بدون خطا تکمیل شد
- [ ] تعداد محصولات بروزرسانی شده صحیح است
- [ ] آمار نهایی نمایش داده شد

---

### مرحله 7: Verification (تایید نهایی)

```bash
# 1. بررسی آمار
php artisan tinker --execute="
echo '═══════════════════════════════════════' . PHP_EOL;
echo '📊 آمار نهایی دیتابیس' . PHP_EOL;
echo '═══════════════════════════════════════' . PHP_EOL;
echo 'کل محصولات: ' . App\Models\Product::count() . PHP_EOL;
echo 'محصولات با تیراژ: ' . App\Models\Product::whereNotNull('quantity_tiers')->count() . PHP_EOL;
echo 'محصولات بدون تیراژ: ' . App\Models\Product::whereNull('quantity_tiers')->orWhereJsonLength('quantity_tiers', 0)->count() . PHP_EOL;
echo '═══════════════════════════════════════' . PHP_EOL;
"

# 2. بررسی نمونه محصول
php artisan tinker --execute="
\$product = App\Models\Product::whereNotNull('quantity_tiers')->first();
echo 'نمونه محصول #' . \$product->id . ': ' . \$product->title . PHP_EOL;
echo 'واحد بسته‌بندی: ' . \$product->packaging_unit_type . PHP_EOL;
echo 'تعداد در واحد: ' . \$product->quantity_per_packaging . PHP_EOL;
echo 'تعداد تیراژها: ' . count(\$product->quantity_tiers) . PHP_EOL;
print_r(\$product->quantity_tiers);
"

# 3. Validation همه تیراژها
php artisan tinker --execute="
use App\Helpers\QuantityTierValidator;
\$products = App\Models\Product::whereNotNull('quantity_tiers')->get();
\$invalid = 0;
foreach (\$products as \$product) {
    try {
        QuantityTierValidator::validate(\$product->quantity_tiers);
    } catch (\Exception \$e) {
        \$invalid++;
        echo 'محصول #' . \$product->id . ': ' . \$e->getMessage() . PHP_EOL;
    }
}
echo PHP_EOL . 'تعداد محصولات نامعتبر: ' . \$invalid . PHP_EOL;
"
```

**✅ نتایج مورد انتظار:**
```
✅ کل محصولات: 940
✅ محصولات با تیراژ: 640-650
✅ محصولات بدون تیراژ: 290-300
✅ تعداد محصولات نامعتبر: 0
```

---

### مرحله 8: تست در مرورگر

```bash
# راه‌اندازی سرور
php artisan serve
```

**📝 تست‌های دستی:**

1. **ایجاد محصول جدید** (`/admin/products/create`):
   - [ ] فیلد واحد بسته‌بندی نمایش داده می‌شود
   - [ ] بعد از تعیین واحد، پیشنهاد تیراژ خودکار نمایش داده می‌شود
   - [ ] کلیک روی "اعمال تیراژبندی خودکار" → 3 تیراژ ایجاد می‌شود
   - [ ] امکان ویرایش و افزودن تیراژ فعال است
   - [ ] ذخیره با کمتر از 3 تیراژ خطا می‌دهد
   - [ ] Error Summary نمایش داده می‌شود
   - [ ] Auto scroll به خطا کار می‌کند

2. **ویرایش محصول موجود** (`/admin/products/{id}/edit`):
   - [ ] محصولات با تیراژ → تیراژها نمایش داده می‌شوند
   - [ ] محصولات با تیراژ → بخش تیراژ خودکار مخفی است
   - [ ] امکان ویرایش و افزودن تیراژ فعال است

3. **ویرایش قیمت تامین‌کننده** (`/supplier/products/{id}/edit-price`):
   - [ ] محصولات با 3-5 تیراژ بدون مشکل نمایش داده می‌شوند
   - [ ] قیمت‌گذاری برای هر تیراژ کار می‌کند
   - [ ] اگر <3 تیراژ باشد، پیام هشدار نمایش داده می‌شود

4. **Validation:**
   - [ ] ذخیره با 2 تیراژ → خطا
   - [ ] ذخیره با 6 تیراژ → خطا
   - [ ] ذخیره با تیراژهای overlap → خطا
   - [ ] ذخیره با آخرین تیراژ محدود → خطا

---

## 🔧 اصلاح مشکلات رایج

### مشکل 1: محصولات با quantity_per_packaging = 0 یا NULL

```sql
-- شناسایی
SELECT id, title, packaging_unit_type, quantity_per_packaging 
FROM products 
WHERE quantity_per_packaging IS NULL OR quantity_per_packaging <= 0
LIMIT 20;

-- اصلاح (مثال: تنظیم پیش‌فرض)
UPDATE products 
SET quantity_per_packaging = 100 
WHERE quantity_per_packaging IS NULL OR quantity_per_packaging <= 0;
```

### مشکل 2: محصولات بدون packaging_unit_type

```sql
-- شناسایی
SELECT id, title, packaging_unit_type 
FROM products 
WHERE packaging_unit_type IS NULL
LIMIT 20;

-- اصلاح
UPDATE products 
SET packaging_unit_type = 'carton' 
WHERE packaging_unit_type IS NULL;
```

### مشکل 3: اجرای دوباره Command (Safe)

Command به طور خودکار محصولاتی که تیراژ دارند را skip می‌کند:

```bash
# اجرای دوباره بدون مشکل
php artisan products:generate-tiers
```

### مشکل 4: پاک کردن تیراژهای نادرست

```sql
-- حذف تیراژهای همه محصولات (در صورت نیاز به reset)
UPDATE products SET quantity_tiers = NULL;

-- اجرای دوباره
php artisan products:generate-tiers
```

---

## 📊 گزارش نهایی

بعد از تکمیل موفقیت‌آمیز، این اطلاعات را ثبت کنید:

```
✅ تاریخ مهاجرت: _____/_____/_____
✅ تعداد کل محصولات: _________
✅ محصولات بروزرسانی شده: _________
✅ محصولات Skip شده: _________
✅ خطاها: _________
✅ مدت زمان اجرا: _________ دقیقه
✅ تست‌های دستی: ☑️ همه موفق
✅ مشکلات: _________________________
```

---

## 🚨 Rollback (بازگشت به حالت قبل)

در صورت بروز مشکل جدی:

```bash
# 1. بازگشت به کد قبلی
git checkout backup-before-migration

# 2. بازگشت به دیتابیس قبلی
mysql -u root -p b2b_marketplace_server < backup_before_migration_YYYYMMDD_HHMMSS.sql

# 3. Clear cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

---

## 📞 تماس در صورت مشکل

اگر مشکلی پیش آمد:
1. ✅ اسکرین‌شات از خطا
2. ✅ آمار دیتابیس (`SELECT COUNT(*) FROM products WHERE ...`)
3. ✅ لاگ Command (`php artisan products:generate-tiers > migration.log 2>&1`)

---

## 📝 Checklist نهایی قبل از Production

- [ ] ✅ Backup دیتابیس گرفته شد
- [ ] ✅ Backup کد گرفته شد
- [ ] ✅ دیتابیس اصلی import شد
- [ ] ✅ Dry-run اجرا شد و بررسی شد
- [ ] ✅ محصولات مشکل‌دار اصلاح شدند
- [ ] ✅ Command واقعی اجرا شد
- [ ] ✅ Verification انجام شد
- [ ] ✅ تست‌های دستی موفق بودند
- [ ] ✅ هیچ خطای validation نیست
- [ ] ✅ گزارش نهایی ثبت شد

---

**🎉 موفق باشید!**
