<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_templates', function (Blueprint $table) {
            // ۱. فیلد config را برای ذخیره تنظیمات چندتأمین‌کننده اضافه می‌کنیم
            $table->json('config')->nullable()->after('supplier_id');

            // ۲. تغییر supplier_id به nullable برای کاتالوگ‌های ادمین
            // نکته: اگر دیتابیس فعلی شما داده‌هایی دارد، نگران نباشید، داده‌ها حفظ می‌شوند.
            // در mysql 8 این دستور به درستی کار می‌کند.
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_templates', function (Blueprint $table) {
            // در صورت بازگشت، ابتدا باید null ها را مدیریت کنید.
            // به صورت ساده فیلد config را حذف می‌کنیم.
            $table->dropColumn('config');

            // بازگرداندن supplier_id به حالت الزامی (در صورت وجود داده، این بخش ممکن است خطا دهد)
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
        });
    }
};
