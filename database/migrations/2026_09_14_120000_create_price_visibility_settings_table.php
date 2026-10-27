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
        Schema::create('price_visibility_settings', function (Blueprint $table) {
            $table->id();
            
            // نقش کاربری (باید با UserRole enum همخوانی داشته باشد)
            $table->string('role', 50)->unique();
            
            // دسترسی‌های اصلی
            $table->boolean('can_see_retail_price')->default(true)
                ->comment('آیا می‌تواند قیمت خرده‌فروشی ببیند؟');
            
            $table->boolean('can_see_wholesale_price')->default(false)
                ->comment('آیا می‌تواند قیمت عمده ببیند؟');
            
            $table->boolean('can_see_supplier_info')->default(false)
                ->comment('آیا می‌تواند اطلاعات تامین‌کننده ببیند؟');
            
            $table->boolean('can_filter_by_price')->default(false)
                ->comment('آیا می‌تواند فیلتر قیمتی استفاده کند؟');
            
            $table->boolean('can_set_retail_price')->default(false)
                ->comment('آیا می‌تواند قیمت خرده‌فروشی ثبت کند؟');
            
            // محدودیت‌های انواع قیمت (JSON - اختیاری)
            // اگر null باشد، همه انواع قیمت نمایش داده می‌شود
            // اگر array باشد، فقط انواع مشخص شده نمایش داده می‌شود
            // مثال: ["cash", "check_30", "check_60"]
            $table->json('restricted_price_types')->nullable()
                ->comment('محدودیت انواع خاص قیمت (null = همه، array = فقط مشخص شده)');
            
            // توضیحات و یادداشت‌ها
            $table->text('description')->nullable()
                ->comment('توضیحات درباره این نقش و دسترسی‌هایش');
            
            // فعال/غیرفعال بودن این تنظیمات
            $table->boolean('is_active')->default(true)
                ->comment('آیا این تنظیمات فعال است؟');
            
            $table->timestamps();
            
            // Indexes
            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_visibility_settings');
    }
};
