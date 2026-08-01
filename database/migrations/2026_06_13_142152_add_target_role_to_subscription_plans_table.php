<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // اضافه کردن نقش هدف: تامین‌کننده، خرده‌فروش یا همه
            $table->enum('target_role', ['supplier', 'retailer_premium','retailer_basic', 'end_user','all'])->default('all')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('target_role');
        });
    }
};
