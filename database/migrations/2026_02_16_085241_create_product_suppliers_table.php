<?php
// database/migrations/2026_02_16_create_product_supplier_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول واسط محصول-تأمین‌کننده
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable()->comment('SKU اختصاصی این تأمین‌کننده برای این محصول');
            $table->boolean('is_primary')->default(false)->comment('آیا تأمین‌کننده اصلی است؟');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // هر ترکیب محصول-تأمین‌کننده یکتا باشد
            $table->unique(['product_id', 'supplier_id']);
            // فقط یک تأمین‌کننده اصلی per product (با منطق application enforce می‌شود)
            $table->index(['product_id', 'is_primary']);
            $table->index(['supplier_id', 'is_active']);
        });

        // 2. اضافه کردن product_supplier_id به product_prices
        Schema::table('product_prices', function (Blueprint $table) {
            $table->foreignId('product_supplier_id')
                  ->nullable()
                  ->after('product_id')
                  ->constrained('product_supplier')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_supplier_id');
        });

        Schema::dropIfExists('product_supplier');
    }
};
