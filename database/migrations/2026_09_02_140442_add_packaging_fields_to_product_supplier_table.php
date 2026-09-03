<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * افزودن فیلدهای واحد بسته‌بندی و تعداد در هر واحد
     * این فیلدها برای تیراژبندی خودکار و نمایش به مشتریان استفاده می‌شوند
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // نوع واحد فروش (کارتن، جین، جورب، طاقه، شیرینگ، بسته، عدد)
            $table->enum('packaging_unit_type', [
                'carton',   // کارتن
                'jean',     // جین (پارچه)
                'jowab',    // جورب (جوراب)
                'package',  // بسته
                'bundle',   // شیرینگ
                'roll',     // طاقه (پارچه)
                'piece',    // عدد
            ])
            ->default('carton')
            ->after('quantity_tiers')
            ->comment('نوع واحد فروش/بسته‌بندی');
            
            // تعداد/مقدار در هر واحد فروش
            $table->unsignedInteger('quantity_per_packaging')
                ->default(1)
                ->after('packaging_unit_type')
                ->comment('تعداد در هر واحد فروش (مثلاً: 4 عدد در کارتن)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('packaging_unit_type');
            $table->dropColumn('quantity_per_packaging');
        });
    }
};
