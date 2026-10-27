<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration برای تنظیمات درصد تخفیف/افزایش تیراژ نقدی
 * 
 * ساختار مشابه سیستم CheckPeriod:
 * - supplier_tier_discount: درصد پیشفرض هر تیراژ برای تامین‌کننده
 * - category_tier_discount: درصد اختصاصی هر تیراژ برای دسته‌بندی خاص
 */
return new class extends Migration
{
    public function up(): void
    {
        // جدول تنظیمات پیشفرض تیراژ برای تامین‌کننده
        Schema::create('supplier_tier_discount', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('tier_index'); // 0 = زیر کارتن, 1 = کارتن, 2 = عمده, 3 = بار
            $table->decimal('percentage_change', 8, 2)->default(0); // مثبت = افزایش، منفی = کاهش
            $table->string('label')->nullable(); // عنوان اختصاصی (اختیاری)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // یک تامین‌کننده فقط یک‌بار می‌تواند هر تیراژ را تعریف کند
            $table->unique(['supplier_id', 'tier_index']);
            
            $table->index(['supplier_id', 'is_active']);
        });
        
        // جدول تنظیمات اختصاصی تیراژ برای دسته‌بندی
        Schema::create('category_tier_discount', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('tier_index');
            $table->decimal('percentage_change', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // یک دسته‌بندی + تامین‌کننده + تیراژ فقط یک‌بار تعریف می‌شود
            $table->unique(['category_id', 'supplier_id', 'tier_index'], 'category_supplier_tier_unique');
            
            $table->index(['category_id', 'supplier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_tier_discount');
        Schema::dropIfExists('supplier_tier_discount');
    }
};
