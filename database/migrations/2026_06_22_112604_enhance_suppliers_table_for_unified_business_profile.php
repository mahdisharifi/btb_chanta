<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // نوع کسب‌وکار (supplier یا retailer)
            $table->enum('type', ['supplier', 'retailer'])
                ->default('supplier')
                ->after('user_id')
                ->comment('supplier: تامین‌کننده، retailer: کاسب/فروشنده خرد');

            // شبکه‌های اجتماعی (JSON)
            $table->json('social_networks')->nullable()->after('website');

            // تبدیل is_verified به verification_status
            // ابتدا ستون جدید را اضافه می‌کنیم
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->after('is_active');

            // فیلدهای تایید که قبلاً وجود نداشتند
            $table->foreignId('verified_by')
                ->nullable()
                ->after('verified_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('rejection_reason')->nullable()->after('verified_by');

            // ایندکس‌ها
            $table->index('type');
            $table->index('verification_status');
        });

        // مهاجرت داده‌های قدیمی از is_verified به verification_status
        DB::statement("
            UPDATE suppliers
            SET verification_status = CASE
                WHEN is_verified = 1 THEN 'approved'
                WHEN is_verified = 0 AND verified_at IS NULL THEN 'pending'
                ELSE 'pending'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // حذف ایندکس‌ها
            $table->dropIndex(['type']);
            $table->dropIndex(['verification_status']);

            // حذف فیلدها
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'type',
                'social_networks',
                'verification_status',
                'verified_by',
                'rejection_reason'
            ]);
        });
    }
};
