<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * اضافه کردن فیلد quantity_tiers به جدول products برای تعریف تیراژهای هر محصول.
     * ساختار JSON: [{'name': 'تیراژ 1', 'min': 1, 'max': 100}, ...]
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('quantity_tiers')
                ->nullable()
                ->after('dimensions')
                ->comment('تیراژهای محصول به صورت JSON');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('quantity_tiers');
        });
    }
};
