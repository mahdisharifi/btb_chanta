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
        Schema::table('supplier_price_rounding_ranges', function (Blueprint $table) {
            // حذف priority (معنی نداره چون رنج‌ها با min_price مرتب می‌شوند)
            $table->dropColumn('priority');
            
            // اضافه sort_order برای ترتیب نمایش در لیست (اختیاری)
            $table->unsignedInteger('sort_order')->default(0)->after('is_active')
                ->comment('ترتیب نمایش در لیست - پیش‌فرض: مرتب بر اساس min_price');
        });
        
        // حذف index قدیمی و ایجاد index جدید
        Schema::table('supplier_price_rounding_ranges', function (Blueprint $table) {
            $table->dropIndex('idx_supplier_priority');
            $table->index(['supplier_id', 'sort_order'], 'idx_supplier_sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_price_rounding_ranges', function (Blueprint $table) {
            $table->dropColumn('sort_order');
            $table->integer('priority')->default(0);
        });
        
        Schema::table('supplier_price_rounding_ranges', function (Blueprint $table) {
            $table->dropIndex('idx_supplier_sort');
            $table->index(['supplier_id', 'priority'], 'idx_supplier_priority');
        });
    }
};
