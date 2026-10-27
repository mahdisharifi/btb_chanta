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
        Schema::create('category_check_period', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->onDelete('cascade')
                ->comment('تامین‌کننده‌ای که این تنظیم را انجام داده');
            
            $table->foreignId('category_id')
                ->constrained('categories')
                ->onDelete('cascade')
                ->comment('دسته‌بندی مورد نظر');
            
            $table->foreignId('check_period_id')
                ->constrained('check_periods')
                ->onDelete('cascade')
                ->comment('دوره چک مورد نظر');
            
            // Settings
            $table->decimal('percentage_increase', 5, 2)
                ->default(0)
                ->comment('درصد افزایش قیمت برای این دسته‌بندی در این دوره چک');
            
            $table->boolean('is_active')
                ->default(true)
                ->comment('فعال/غیرفعال بودن این تنظیم');
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['supplier_id', 'category_id', 'check_period_id'], 'unique_supplier_category_period');
            $table->index(['supplier_id', 'category_id'], 'idx_supplier_category');
            $table->index(['category_id', 'is_active'], 'idx_category_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_check_period');
    }
};
