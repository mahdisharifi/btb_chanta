<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            // اضافه کردن ستون stock_quantity که مقادیر null را نیز می‌پذیرد (طبق کدهای اعتبار سنجی)
            $table->integer('stock_quantity')->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }
};
