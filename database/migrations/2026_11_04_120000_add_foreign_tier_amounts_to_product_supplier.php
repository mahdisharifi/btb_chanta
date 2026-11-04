<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_supplier')
            || Schema::hasColumn('product_supplier', 'foreign_tier_amounts')) {
            return;
        }

        Schema::table('product_supplier', function (Blueprint $table): void {
            $table->json('foreign_tier_amounts')
                ->nullable()
                ->after('foreign_price_amount')
                ->comment('مبالغ ارزی تیراژهای نقدیِ دستی، بر اساس ارز پایه تامین‌کننده');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('product_supplier')
            && Schema::hasColumn('product_supplier', 'foreign_tier_amounts')) {
            Schema::table('product_supplier', function (Blueprint $table): void {
                $table->dropColumn('foreign_tier_amounts');
            });
        }
    }
};
