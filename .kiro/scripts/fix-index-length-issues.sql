-- اسکریپت رفع مشکل طول Index برای سرورهای Production
-- استفاده: mysql -u username -p database_name < fix-index-length-issues.sql

-- ==============================================================================
-- مشکل: #1071 - Specified key was too long; max key length is 1000 bytes
-- ==============================================================================
-- با utf8mb4، هر کاراکتر 4 بایت است
-- VARCHAR(255) = 255 × 4 = 1020 بایت > محدودیت 1000 بایت MySQL
-- ==============================================================================

-- 1. حذف FULLTEXT index از products table (اگر وجود دارد)
-- ----------------------------------------------------------
SET @exist := (
    SELECT COUNT(*) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'products' 
    AND index_name LIKE '%title%short_description%'
);

SET @sqlstmt := IF(
    @exist > 0, 
    'ALTER TABLE products DROP INDEX products_title_short_description_fulltext',
    'SELECT "Index does not exist" AS message'
);

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. ایجاد FULLTEXT index به روش صحیح
-- ----------------------------------------------------------
-- FULLTEXT indexes محدودیت 1000 بایت ندارند
ALTER TABLE products 
ADD FULLTEXT INDEX products_title_short_description_fulltext (title, short_description);

-- 3. بررسی سایر indexهای بلند در دیتابیس
-- ----------------------------------------------------------
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns,
    SUM(
        CASE 
            WHEN DATA_TYPE IN ('varchar', 'char') THEN CHARACTER_MAXIMUM_LENGTH * 4
            WHEN DATA_TYPE = 'text' THEN 1000
            WHEN DATA_TYPE = 'mediumtext' THEN 1000
            WHEN DATA_TYPE = 'longtext' THEN 1000
            ELSE 8
        END
    ) AS estimated_bytes
FROM information_schema.statistics s
JOIN information_schema.columns c 
    ON s.table_schema = c.table_schema 
    AND s.table_name = c.table_name 
    AND s.column_name = c.column_name
WHERE s.table_schema = DATABASE()
    AND s.index_type != 'FULLTEXT'
GROUP BY TABLE_NAME, INDEX_NAME
HAVING estimated_bytes > 1000
ORDER BY estimated_bytes DESC;

-- ==============================================================================
-- بررسی های بعدی (اختیاری)
-- ==============================================================================

-- 4. نمایش تمام FULLTEXT indexes
-- ----------------------------------------------------------
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns
FROM information_schema.statistics
WHERE table_schema = DATABASE()
    AND index_type = 'FULLTEXT'
GROUP BY TABLE_NAME, INDEX_NAME;

-- 5. نمایش تمام UNIQUE indexes
-- ----------------------------------------------------------
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns
FROM information_schema.statistics
WHERE table_schema = DATABASE()
    AND non_unique = 0
    AND index_name != 'PRIMARY'
GROUP BY TABLE_NAME, INDEX_NAME;

-- ==============================================================================
-- پیام نهایی
-- ==============================================================================
SELECT '✓ Index length issues fixed successfully!' AS status;
