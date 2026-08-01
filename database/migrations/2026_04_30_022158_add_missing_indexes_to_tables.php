<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Index ترکیبی برای فیلتر محصولات فعال با وضعیت خاص
            $table->index(['is_active', 'status'], 'products_is_active_status_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            // Index ترکیبی برای فیلتر تأمین‌کنندگان فعال و تأیید شده
            $table->index(['is_active', 'is_verified'], 'suppliers_is_active_is_verified_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            // Index برای جستجوی نام دسته‌بندی
            $table->index('name', 'categories_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_is_active_status_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_is_active_is_verified_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_name_index');
        });
    }
};
