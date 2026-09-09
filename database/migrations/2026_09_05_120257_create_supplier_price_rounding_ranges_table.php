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
        Schema::create('supplier_price_rounding_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            
            // رنج قیمتی
            $table->decimal('min_price', 15, 2)->default(0)->comment('حداقل قیمت رنج (شامل)');
            $table->decimal('max_price', 15, 2)->nullable()->comment('حداکثر قیمت رنج (شامل نمی‌شود) - NULL = بدون محدودیت');
            
            // تنظیمات رُند
            $table->enum('rounding_method', ['ceil', 'floor', 'nearest'])
                ->default('ceil')
                ->comment('ceil=بالا, floor=پایین, nearest=نزدیک‌ترین');
            
            $table->unsignedInteger('rounding_step')
                ->default(100)
                ->comment('مبلغ مرجع رُند کردن: 100, 500, 1000, ...');
            
            // اولویت (عدد کوچک‌تر = اولویت بالاتر)
            $table->integer('priority')->default(0)->comment('اولویت اعمال رنج - عدد کوچک‌تر اولویت بالاتر');
            
            // فعال/غیرفعال
            $table->boolean('is_active')->default(true)->comment('وضعیت فعال/غیرفعال');
            
            $table->timestamps();
            
            // Indexes برای بهبود عملکرد
            $table->index(['supplier_id', 'min_price', 'max_price', 'is_active'], 'idx_supplier_range_active');
            $table->index(['supplier_id', 'priority'], 'idx_supplier_priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_price_rounding_ranges');
    }
};
