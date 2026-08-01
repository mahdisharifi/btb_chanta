<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اصلاح FK در attribute_values (جلوگیری از حذف ویژگی در صورت وجود مقدار)
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropForeign(['attribute_id']);
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->onDelete('restrict');
        });

        // اصلاح FK در attribute_product (جلوگیری از حذف مقدار ویژگی در صورت وجود در محصول)
        Schema::table('attribute_product', function (Blueprint $table) {
            $table->dropForeign(['attribute_value_id']);
            $table->foreign('attribute_value_id')
                ->references('id')->on('attribute_values')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // بازگشت به حالت پیش‌فرض (بدون محدودیت حذف) در صورت نیاز
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropForeign(['attribute_id']);
            $table->foreign('attribute_id')->references('id')->on('attributes');
        });

        Schema::table('attribute_product', function (Blueprint $table) {
            $table->dropForeign(['attribute_value_id']);
            $table->foreign('attribute_value_id')->references('id')->on('attribute_values');
        });
    }
};
