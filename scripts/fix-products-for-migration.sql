-- ═══════════════════════════════════════════════════════════════
-- 🔧 اسکریپت اصلاح محصولات قبل از Migration
-- ═══════════════════════════════════════════════════════════════
-- 
-- این اسکریپت محصولات را برای Migration تیراژبندی خودکار آماده می‌کند
-- 
-- استفاده:
--   mysql -u root -p b2b_marketplace_server < scripts/fix-products-for-migration.sql
-- 
-- یا در MySQL:
--   source scripts/fix-products-for-migration.sql;
-- 
-- ═══════════════════════════════════════════════════════════════

-- فعال‌سازی نمایش Warnings
SET sql_notes = 1;

-- ═══════════════════════════════════════════════════════════════
-- بررسی اولیه
-- ═══════════════════════════════════════════════════════════════

SELECT '═══════════════════════════════════════════════════' AS '═';
SELECT '📊 آمار قبل از اصلاح' AS 'مرحله';
SELECT '═══════════════════════════════════════════════════' AS '═';

SELECT 
    COUNT(*) AS 'کل محصولات'
FROM products;

SELECT 
    COUNT(*) AS 'محصولات بدون packaging_unit_type'
FROM products 
WHERE packaging_unit_type IS NULL;

SELECT 
    COUNT(*) AS 'محصولات با quantity_per_packaging نامعتبر'
FROM products 
WHERE quantity_per_packaging IS NULL 
   OR quantity_per_packaging <= 0;

SELECT 
    COUNT(*) AS 'محصولات بدون تیراژ'
FROM products 
WHERE quantity_tiers IS NULL 
   OR JSON_LENGTH(quantity_tiers) = 0;

-- ═══════════════════════════════════════════════════════════════
-- اصلاح 1: packaging_unit_type
-- ═══════════════════════════════════════════════════════════════

SELECT '═══════════════════════════════════════════════════' AS '═';
SELECT '🔧 اصلاح 1: packaging_unit_type' AS 'مرحله';
SELECT '═══════════════════════════════════════════════════' AS '═';

-- نمایش محصولات بدون واحد (نمونه)
SELECT 
    '⚠️  نمونه محصولات بدون واحد بسته‌بندی:' AS 'توضیحات';

SELECT 
    id,
    title,
    packaging_unit_type,
    quantity_per_packaging
FROM products 
WHERE packaging_unit_type IS NULL
LIMIT 5;

-- اصلاح: تنظیم پیش‌فرض به 'carton'
UPDATE products 
SET packaging_unit_type = 'carton'
WHERE packaging_unit_type IS NULL;

SELECT 
    CONCAT('✅ تعداد محصولات اصلاح شده: ', ROW_COUNT()) AS 'نتیجه';

-- ═══════════════════════════════════════════════════════════════
-- اصلاح 2: quantity_per_packaging (NULL)
-- ═══════════════════════════════════════════════════════════════

SELECT '═══════════════════════════════════════════════════' AS '═';
SELECT '🔧 اصلاح 2: quantity_per_packaging (NULL)' AS 'مرحله';
SELECT '═══════════════════════════════════════════════════' AS '═';

-- نمایش محصولات با quantity_per_packaging نامعتبر
SELECT 
    '⚠️  نمونه محصولات با quantity_per_packaging نامعتبر:' AS 'توضیحات';

SELECT 
    id,
    title,
    packaging_unit_type,
    quantity_per_packaging
FROM products 
WHERE quantity_per_packaging IS NULL
LIMIT 5;

-- اصلاح: تنظیم پیش‌فرض به 100
UPDATE products 
SET quantity_per_packaging = 100
WHERE quantity_per_packaging IS NULL;

SELECT 
    CONCAT('✅ تعداد محصولات اصلاح شده: ', ROW_COUNT()) AS 'نتیجه';

-- ═══════════════════════════════════════════════════════════════
-- اصلاح 3: quantity_per_packaging (صفر یا منفی)
-- ═══════════════════════════════════════════════════════════════

SELECT '═══════════════════════════════════════════════════' AS '═';
SELECT '🔧 اصلاح 3: quantity_per_packaging (≤0)' AS 'مرحله';
SELECT '═══════════════════════════════════════════════════' AS '═';

-- نمایش محصولات با quantity_per_packaging صفر یا منفی
SELECT 
    '⚠️  نمونه محصولات با quantity_per_packaging ≤ 0:' AS 'توضیحات';

SELECT 
    id,
    title,
    packaging_unit_type,
    quantity_per_packaging
FROM products 
WHERE quantity_per_packaging <= 0
LIMIT 5;

-- اصلاح
UPDATE products 
SET quantity_per_packaging = 100
WHERE quantity_per_packaging <= 0;

SELECT 
    CONCAT('✅ تعداد محصولات اصلاح شده: ', ROW_COUNT()) AS 'نتیجه';

-- ═══════════════════════════════════════════════════════════════
-- اصلاح 4: حذف تیراژهای نامعتبر (اختیاری)
-- ═══════════════════════════════════════════════════════════════

SELECT '═══════════════════════════════════════════════════' AS '═';
SELECT '🔧 اصلاح 4: حذف تیراژهای نامعتبر (اختیاری)' AS 'مرحله';
SELECT '═══════════════════════════════════════════════════' AS '═';

-- این بخش فقط در صورتی اجرا شود که تیراژهای نامعتبر دارید
-- برای فعال‌سازی، خط زیر را uncomment کنید:

-- UPDATE products 
-- SET quantity_tiers = NULL
-- WHERE JSON_LENGTH(quantity_tiers) < 3 
--    OR JSON_LENGTH(quantity_tiers) > 5;

SELECT 
    '⚠️  این بخش غیرفعال است. در صورت نیاز فعال کنید.' AS 'توضیحات';

-- ═══════════════════════════════════════════════════════════════
-- بررسی نهایی
-- ═══════════════════════════════════════════════════════════════

SELECT '═══════════════════════════════════════════════════' AS '═';
SELECT '📊 آمار بعد از اصلاح' AS 'مرحله';
SELECT '═══════════════════════════════════════════════════' AS '═';

SELECT 
    COUNT(*) AS 'کل محصولات'
FROM products;

SELECT 
    COUNT(*) AS 'محصولات با packaging_unit_type معتبر'
FROM products 
WHERE packaging_unit_type IS NOT NULL;

SELECT 
    COUNT(*) AS 'محصولات با quantity_per_packaging معتبر'
FROM products 
WHERE quantity_per_packaging > 0;

SELECT 
    COUNT(*) AS 'محصولات آماده برای Migration'
FROM products 
WHERE packaging_unit_type IS NOT NULL 
  AND quantity_per_packaging > 0
  AND (quantity_tiers IS NULL OR JSON_LENGTH(quantity_tiers) = 0);

-- ═══════════════════════════════════════════════════════════════
-- نتیجه‌گیری
-- ═══════════════════════════════════════════════════════════════

SELECT '═══════════════════════════════════════════════════' AS '═';
SELECT '✅ اصلاحات کامل شد' AS 'نتیجه';
SELECT '═══════════════════════════════════════════════════' AS '═';

SELECT 
    '
🎉 محصولات برای Migration آماده شدند!

📝 مراحل بعدی:
  1. بررسی سلامت: php artisan products:check-health
  2. پیش‌نمایش: php artisan products:generate-tiers --dry-run
  3. اجرا: php artisan products:generate-tiers

📖 مستندات: .kiro/docs/database-migration-quantity-tiers.md
' AS 'راهنما';

-- ═══════════════════════════════════════════════════════════════
-- پایان اسکریپت
-- ═══════════════════════════════════════════════════════════════
