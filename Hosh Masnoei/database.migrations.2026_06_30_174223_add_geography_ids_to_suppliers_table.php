<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ۱. اضافه کردن فیلدهای جدید
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('province')->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('city')->constrained('cities')->nullOnDelete();
        });

        // ۲. انتقال و همگام‌سازی دیتای متنی قدیمی با شناسه‌های جدید (Data Migration)
        DB::table('suppliers')->whereNotNull('province')->orWhereNotNull('city')->orderBy('id')->chunk(100, function ($suppliers) {
            foreach ($suppliers as $supplier) {
                $updateData = [];

                // پیدا کردن شناسه استان بر اساس نام متنی
                if ($supplier->province) {
                    $province = DB::table('provinces')->where('name', 'like', "%{$supplier->province}%")->first();
                    if ($province) {
                        $updateData['province_id'] = $province->id;
                    }
                }

                // پیدا کردن شناسه شهر بر اساس نام متنی
                if ($supplier->city) {
                    $city = DB::table('cities')->where('name', 'like', "%{$supplier->city}%")->first();
                    if ($city) {
                        $updateData['city_id'] = $city->id;
                    }
                }

                // بروزرسانی رکورد در صورت یافتن تطابق
                if (!empty($updateData)) {
                    DB::table('suppliers')->where('id', $supplier->id)->update($updateData);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // حذف کلیدهای خارجی و فیلدها در صورت رول‌بک
            $table->dropForeign(['province_id']);
            $table->dropForeign(['city_id']);
            $table->dropColumn(['province_id', 'city_id']);
        });
    }
};
