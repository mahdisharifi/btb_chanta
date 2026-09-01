<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اضافه کردن فیلد percentage_override برای تعیین درصد اختصاصی هر محصول
     * سلسله مراتب درصد افزایش: محصول > دسته‌بندی > دوره چک
     */
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->decimal('percentage_override', 5, 2)
                ->nullable()
                ->after('check_period_id')
                ->comment('درصد افزایش اختصاصی این قیمت (اولویت بالا) - null یعنی از category یا check_period استفاده شود');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn('percentage_override');
        });
    }
};
