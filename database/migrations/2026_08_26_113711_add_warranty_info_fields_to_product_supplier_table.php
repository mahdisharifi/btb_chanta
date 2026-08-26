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
        Schema::table('product_supplier', function (Blueprint $table) {
            // آیا محصول با گارانتی است؟
            $table->boolean('has_warranty')
                ->default(false)
                ->after('warranty_value')
                ->comment('آیا محصول با گارانتی عرضه می‌شود؟');
            
            // نوع گارانتی: none (بدون), manufacturer (شرکتی), seller (فروشنده)
            $table->enum('warranty_provider', ['none', 'manufacturer', 'seller'])
                ->default('none')
                ->after('has_warranty')
                ->comment('ارائه‌دهنده گارانتی');
            
            // مدت گارانتی (روز) - برای همه انواع گارانتی
            $table->integer('warranty_duration_days')
                ->nullable()
                ->after('warranty_provider')
                ->comment('مدت گارانتی (روز)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropColumn(['has_warranty', 'warranty_provider', 'warranty_duration_days']);
        });
    }
};
