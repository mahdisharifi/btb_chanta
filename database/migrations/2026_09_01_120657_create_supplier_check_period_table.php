<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول pivot برای مدیریت دوره‌های چک هر تامین‌کننده
     * هر تامین‌کننده می‌تواند دوره‌های چک خود را با درصد افزایش قیمت تعیین کند
     */
    public function up(): void
    {
        Schema::create('supplier_check_period', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete()->comment('تامین‌کننده');
            $table->foreignId('check_period_id')->constrained('check_periods')->cascadeOnDelete()->comment('دوره چک');
            $table->decimal('percentage_increase', 5, 2)->default(0)->comment('درصد افزایش قیمت نسبت به نقدی (0-100)');
            $table->boolean('is_active')->default(true)->comment('فعال/غیرفعال بودن این دوره برای تامین‌کننده');
            $table->timestamps();
            
            // Unique constraint: هر تامین‌کننده فقط یکبار می‌تونه یک دوره رو داشته باشه
            $table->unique(['supplier_id', 'check_period_id'], 'unique_supplier_check_period');
            
            // Index for performance
            $table->index(['supplier_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_check_period');
    }
};
