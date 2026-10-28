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
            if (Schema::hasIndex('supplier_price_rounding_ranges', 'idx_supplier_priority')) {
                $table->dropIndex('idx_supplier_priority');
            }

            if (Schema::hasColumn('supplier_price_rounding_ranges', 'priority')) {
                $table->dropColumn('priority');
            }

            if (!Schema::hasColumn('supplier_price_rounding_ranges', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active')
                    ->comment('ترتیب نمایش در لیست - پیش‌فرض: مرتب بر اساس min_price');
            }

            if (!Schema::hasIndex('supplier_price_rounding_ranges', 'idx_supplier_sort')) {
                $table->index(['supplier_id', 'sort_order'], 'idx_supplier_sort');
            }
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
