<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            // شمارنده دفعات تلاش برای تایید یک کد (برای مسدودسازی پس از چند تلاش ناموفق)
            $table->unsignedTinyInteger('attempts')->default(0)->after('is_used');

            // ایندکس برای شمارش سریع ارسال‌ها بر اساس موبایل و بازه زمانی (محدودسازی ساعتی/روزانه)
            $table->index(['mobile', 'created_at'], 'otp_mobile_created_idx');

            // ایندکس برای شمارش سریع ارسال‌ها بر اساس IP و بازه زمانی (محدودسازی per-IP)
            $table->index(['ip_address', 'created_at'], 'otp_ip_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropIndex('otp_mobile_created_idx');
            $table->dropIndex('otp_ip_created_idx');
            $table->dropColumn('attempts');
        });
    }
};
