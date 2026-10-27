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
            $table->string('poster_path')->nullable()->after('file_path')
                  ->comment('مسیر تصویر پیش‌نمایش برای ویدئو (thumbnail)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_galleries', function (Blueprint $table) {
            $table->dropColumn('poster_path');
        });
    }
};
