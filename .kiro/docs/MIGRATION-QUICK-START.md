# 🚀 راهنمای سریع مهاجرت - سیستم تیراژبندی

**⏱️ زمان تخمینی: 5-10 دقیقه**

---

## ✅ Checklist سریع

```bash
# 1️⃣ پشتیبان‌گیری (الزامی!)
mysqldump -u root -p b2b_marketplace_server > backup_$(date +%Y%m%d_%H%M%S).sql
git add . && git commit -m "Backup before migration" && git branch backup-before-migration

# 2️⃣ Import دیتابیس اصلی
mysql -u root -p -e "DROP DATABASE IF EXISTS b2b_marketplace_server;"
mysql -u root -p -e "CREATE DATABASE b2b_marketplace_server CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p b2b_marketplace_server < database_production.sql

# 3️⃣ بررسی سلامت دیتابیس
php artisan products:check-health

# 4️⃣ اصلاح مشکلات (در صورت نیاز)
# اگر محصولات بدون packaging_unit_type دارید:
php artisan tinker --execute="DB::table('products')->whereNull('packaging_unit_type')->update(['packaging_unit_type' => 'carton']);"

# اگر محصولات با quantity_per_packaging نامعتبر دارید:
php artisan tinker --execute="DB::table('products')->where('quantity_per_packaging', '<=', 0)->update(['quantity_per_packaging' => 100]);"

# 5️⃣ پیش‌نمایش
php artisan products:generate-tiers --dry-run

# 6️⃣ اجرا
php artisan products:generate-tiers

# 7️⃣ بررسی نتیجه
php artisan tinker --execute="echo 'محصولات با تیراژ: ' . App\Models\Product::whereNotNull('quantity_tiers')->count();"

# 8️⃣ تست در مرورگر
php artisan serve
# بروید به: http://127.0.0.1:8000/admin/products/create
```

---

## 📊 نتایج مورد انتظار

### قبل از Migration:
```
کل محصولات: 940
محصولات با تیراژ: 2
محصولات بدون تیراژ: 938
```

### بعد از Migration:
```
کل محصولات: 940
محصولات با تیراژ: 639
محصولات بدون تیراژ: 301 (محصولات با qty=1)
نرخ موفقیت: ~68%
```

---

## 🚨 Rollback (در صورت مشکل)

```bash
# بازگشت به کد قبلی
git checkout backup-before-migration

# بازگشت به دیتابیس قبلی
mysql -u root -p b2b_marketplace_server < backup_YYYYMMDD_HHMMSS.sql

# Clear cache
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

---

## 📝 تست‌های الزامی

در مرورگر:

1. ✅ `/admin/products/create` → ایجاد محصول جدید با تیراژ خودکار
2. ✅ `/admin/products/{id}/edit` → ویرایش محصول با تیراژ موجود
3. ✅ `/supplier/products/{id}/edit-price` → قیمت‌گذاری تیراژها
4. ✅ Error handling: ذخیره با 2 تیراژ → باید خطا دهد

---

## 📞 مستندات کامل

برای جزئیات بیشتر: `.kiro/docs/database-migration-quantity-tiers.md`

---

**✅ آماده برای مهاجرت!**
