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
        Schema::table('product_supplier', function (Blueprint $table) {
            // نوع هزینه گارانتی: none (بدون), percentage (درصد), fixed (مبلغ ثابت), custom (سفارشی در ماتریس)
            $table->enum('warranty_type', ['none', 'percentage', 'fixed', 'custom'])
                ->default('none')
                ->after('is_active')
                ->comment('نوع محاسبه هزینه گارانتی');
            
            // مقدار هزینه گارانتی (درصد یا مبلغ ثابت)
            $table->decimal('warranty_value', 10, 2)
                ->nullable()
                ->after('warranty_type')
                ->comment('مقدار درصد یا مبلغ ثابت گارانتی');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropColumn(['warranty_type', 'warranty_value']);
        });
    }
};
