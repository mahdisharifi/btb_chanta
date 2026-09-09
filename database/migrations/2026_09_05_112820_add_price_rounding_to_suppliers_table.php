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
        Schema::table('suppliers', function (Blueprint $table) {
            // تنظیمات رُند کردن قیمت‌ها
            $table->enum('price_rounding_type', [
                'none',      // بدون رُند (اعشار کامل)
                'nearest_1', // رُند به نزدیک‌ترین عدد صحیح (1 تومان)
                'nearest_5', // رُند به نزدیک‌ترین 5 تومان
                'nearest_10', // رُند به نزدیک‌ترین 10 تومان
                'nearest_50', // رُند به نزدیک‌ترین 50 تومان
                'nearest_100', // رُند به نزدیک‌ترین 100 تومان
                'nearest_500', // رُند به نزدیک‌ترین 500 تومان
                'nearest_1000', // رُند به نزدیک‌ترین 1000 تومان
                'ceil_1',    // رُند به بالا به صحیح
                'ceil_10',   // رُند به بالا به 10
                'ceil_100',  // رُند به بالا به 100
                'ceil_1000', // رُند به بالا به 1000
                'floor_1',   // رُند به پایین به صحیح
                'floor_10',  // رُند به پایین به 10
                'floor_100', // رُند به پایین به 100
                'floor_1000', // رُند به پایین به 1000
            ])->default('nearest_1')->after('company_name');
            
            $table->comment('تنظیمات رُند کردن قیمت‌ها هنگام محاسبات درصدی');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('price_rounding_type');
        });
    }
};
