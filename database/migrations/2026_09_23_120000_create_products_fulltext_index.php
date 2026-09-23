<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * این migration برای سرورهای production که محدودیت 1000 بایت برای index دارند
     * FULLTEXT index را به صورت جداگانه ایجاد می‌کند.
     * 
     * علت جداسازی:
     * - با utf8mb4، هر کاراکتر 4 بایت است
     * - title (VARCHAR 255) = 1020 بایت > محدودیت 1000 بایت
     * - راه حل: ایجاد FULLTEXT بدون محدودیت طول
     */
    public function up(): void
    {
        // بررسی اینکه آیا index از قبل وجود دارد یا خیر
        $indexExists = DB::select(
            "SHOW INDEXES FROM products WHERE Key_name = 'products_title_short_description_fulltext'"
        );

        if (empty($indexExists)) {
            // ایجاد FULLTEXT index با raw SQL
            // FULLTEXT indexes در MySQL محدودیت 1000 بایت ندارند
            DB::statement('ALTER TABLE products ADD FULLTEXT products_title_short_description_fulltext (title, short_description)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // حذف FULLTEXT index
            $indexExists = DB::select(
                "SHOW INDEXES FROM products WHERE Key_name = 'products_title_short_description_fulltext'"
            );

            if (!empty($indexExists)) {
                DB::statement('ALTER TABLE products DROP INDEX products_title_short_description_fulltext');
            }
        });
    }
};
