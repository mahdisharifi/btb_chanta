<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اضافه کردن فیلد label برای ذخیره عنوان اختصاصی دوره توسط هر تامین‌کننده
     * این عنوان فقط برای خود تامین‌کننده نمایش داده می‌شود
     */
    public function up(): void
    {
        Schema::table('supplier_check_period', function (Blueprint $table) {
            $table->string('label', 100)->nullable()->after('check_period_id')->comment('عنوان اختصاصی دوره برای این تامین‌کننده');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_check_period', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
