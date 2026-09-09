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
            // ✅ تنظیمات محاسبه خودکار تیراژها
            $table->boolean('auto_calculate_tiers')->default(false)->after('warranty_duration_days')->comment('فعال/غیرفعال محاسبه خودکار تیراژها');
            $table->json('tier_percentages')->nullable()->after('auto_calculate_tiers')->comment('درصدهای تیراژها نسبت به مرجع');
            $table->json('tier_enabled')->nullable()->after('tier_percentages')->comment('وضعیت فعال/غیرفعال هر تیراژ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropColumn(['auto_calculate_tiers', 'tier_percentages', 'tier_enabled']);
        });
    }
};
