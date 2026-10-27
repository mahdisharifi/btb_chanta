<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * این migration ساختار جدول product_prices را برای پشتیبانی از ماتریس قیمت‌گذاری تغییر می‌دهد.
     */
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            // حذف ستون قدیمی
            if (Schema::hasColumn('product_prices', 'price_type')) {
                $table->dropColumn('price_type');
            }
            
            // اضافه کردن ستون‌های جدید
            $table->foreignId('price_type_id')
                ->after('product_supplier_id')
                ->nullable()
                ->constrained('price_types')
                ->nullOnDelete()
                ->comment('نوع قیمت: نقدی، چکی، گارانتی');
            
            $table->unsignedTinyInteger('tier_index')
                ->default(0)
                ->after('price_type_id')
                ->comment('ایندکس تیراژ: 0-4');
            
            $table->foreignId('check_period_id')
                ->after('tier_index')
                ->nullable()
                ->constrained('check_periods')
                ->nullOnDelete()
                ->comment('دوره چک');
            
            // فیلدهای گارانتی
            $table->string('warranty_type', 50)
                ->nullable()
                ->after('price')
                ->comment('نوع گارانتی: test, limited, lifetime');
            
            $table->unsignedInteger('warranty_duration')
                ->nullable()
                ->after('warranty_type')
                ->comment('مدت گارانتی به روز (فقط برای limited)');
            
            // ایندکس‌ها
            $table->index(['product_supplier_id', 'price_type_id', 'tier_index'], 'price_lookup_index');
            $table->index(['is_active', 'valid_from', 'valid_until'], 'active_valid_index');
            
            // Unique constraint
            $table->unique(
                ['product_supplier_id', 'price_type_id', 'tier_index', 'check_period_id'],
                'unique_price_matrix'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropUnique('unique_price_matrix');
            $table->dropIndex('price_lookup_index');
            $table->dropIndex('active_valid_index');
            
            $table->dropForeign(['price_type_id']);
            $table->dropColumn('price_type_id');
            $table->dropColumn('tier_index');
            $table->dropForeign(['check_period_id']);
            $table->dropColumn('check_period_id');
            $table->dropColumn('warranty_type');
            $table->dropColumn('warranty_duration');
            
            $table->string('price_type', 50)->after('product_supplier_id');
        });
    }
};
