<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تغییر مستقیم enum بدون drop ستون (داده‌ها حفظ می‌شوند)
        DB::statement("ALTER TABLE suppliers MODIFY COLUMN verification_status ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending'");

        // اضافه کردن ایندکس اگر نیاز است
        if (!Schema::hasIndex('suppliers', ['verification_status'])) {
            Schema::table('suppliers', function ($table) {
                $table->index('verification_status');
            });
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY COLUMN verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
    }
};
