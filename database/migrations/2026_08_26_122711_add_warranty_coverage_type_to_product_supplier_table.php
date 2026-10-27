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
            // نوع پوشش گارانتی: full (کامل), limited (محدود), parts_only (فقط قطعات), service_only (فقط خدمات)
            $table->enum('warranty_coverage_type', ['full', 'limited', 'parts_only', 'service_only'])
                ->default('full')
                ->after('warranty_provider')
                ->comment('نوع پوشش گارانتی');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropColumn('warranty_coverage_type');
        });
    }
};
