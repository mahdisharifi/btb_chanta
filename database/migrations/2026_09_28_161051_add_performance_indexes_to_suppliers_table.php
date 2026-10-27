<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * این migration برای بهبود عملکرد صفحه لیست تأمین‌کنندگان است
     * با اضافه کردن indexes روی ستون‌های پرتکرار
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // ─── Single Column Indexes ───
            
            // Index برای فیلتر استان
            $table->index('province', 'idx_suppliers_province');
            
            // Index برای فیلتر شهر
            $table->index('city', 'idx_suppliers_city');
            
            // Index برای مرتب‌سازی بر اساس نام
            $table->index('company_name', 'idx_suppliers_company_name');
            
            // ─── Composite Indexes ───
            
            // Index ترکیبی برای فیلتر فعال + تأیید شده
            $table->index(['is_active', 'is_verified'], 'idx_suppliers_active_verified');
            
            // Index ترکیبی برای لیست اصلی (فعال + تأیید شده + تاریخ ایجاد)
            // این index برای صفحه لیست با sort=verified_first استفاده می‌شود
            $table->index(['is_active', 'is_verified', 'created_at'], 'idx_suppliers_listing');
            
            // Index ترکیبی برای فیلتر استان + فعال
            $table->index(['province', 'is_active'], 'idx_suppliers_province_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // حذف indexes
            $table->dropIndex('idx_suppliers_province_active');
            $table->dropIndex('idx_suppliers_listing');
            $table->dropIndex('idx_suppliers_active_verified');
            $table->dropIndex('idx_suppliers_company_name');
            $table->dropIndex('idx_suppliers_city');
            $table->dropIndex('idx_suppliers_province');
        });
    }
};
