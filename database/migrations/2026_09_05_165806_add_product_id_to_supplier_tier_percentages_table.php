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
        Schema::table('supplier_tier_percentages', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('supplier_id')->constrained('products')->onDelete('cascade');
            
            // حذف unique constraint قدیمی
            $table->dropUnique(['supplier_id', 'tier_index']);
            
            // اضافه کردن unique constraint جدید (supplier + product + tier)
            $table->unique(['supplier_id', 'product_id', 'tier_index'], 'supplier_product_tier_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_tier_percentages', function (Blueprint $table) {
            $table->dropUnique('supplier_product_tier_unique');
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            
            // بازگردانی unique constraint قدیمی
            $table->unique(['supplier_id', 'tier_index']);
        });
    }
};
