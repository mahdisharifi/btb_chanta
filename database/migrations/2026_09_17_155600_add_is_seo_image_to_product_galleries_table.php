<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_galleries', function (Blueprint $table) {
            // فیلد برای علامت‌گذاری تصویر به عنوان تصویر SEO (برای Schema.org و Google)
            $table->boolean('is_seo_image')->default(false)->after('is_primary');
            
            // ایندکس برای جستجوی سریع‌تر
            $table->index(['product_id', 'is_seo_image']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_galleries', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'is_seo_image']);
            $table->dropColumn('is_seo_image');
        });
    }
};
