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
            // نام پایه برای مدیریت نسخه‌های مختلف تصویر
            $table->string('file_name', 255)->nullable()->after('product_id')->index();
            $table->string('base_name', 255)->nullable()->after('product_id')->index();
            // ذخیره JSON مسیرهای نسخه‌های مختلف تصویر
            $table->json('versions')->nullable()->after('height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_galleries', function (Blueprint $table) {
            $table->dropColumn(['base_name', 'versions']);
        });
    }
};
