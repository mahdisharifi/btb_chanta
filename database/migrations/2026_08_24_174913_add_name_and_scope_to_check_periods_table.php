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
        Schema::table('check_periods', function (Blueprint $table) {
            // اضافه کردن فیلد name
            $table->string('name', 100)->nullable()->after('supplier_id')->comment('نام دوره: چک 30 روزه');
            
            // اضافه کردن فیلد scope
            $table->enum('scope', ['global', 'supplier'])->default('supplier')->after('supplier_id')->comment('سطح دسترسی: global برای همه، supplier برای تامین‌کننده خاص');
            
            // بروزرسانی داده‌های موجود: کپی label به name
            // و تنظیم scope بر اساس supplier_id
        });
        
        // بروزرسانی رکوردهای موجود
        DB::table('check_periods')->update([
            'name' => DB::raw('label'),
            'scope' => DB::raw("CASE WHEN supplier_id IS NULL THEN 'global' ELSE 'supplier' END")
        ]);
        
        // حالا name را required می‌کنیم
        Schema::table('check_periods', function (Blueprint $table) {
            $table->string('name', 100)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_periods', function (Blueprint $table) {
            $table->dropColumn(['name', 'scope']);
        });
    }
};
