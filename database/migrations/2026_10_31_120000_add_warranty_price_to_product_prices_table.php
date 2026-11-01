<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_prices', 'warranty_price')) {
            Schema::table('product_prices', function (Blueprint $table): void {
                $table->unsignedBigInteger('warranty_price')
                    ->nullable()
                    ->after('warranty_duration');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('product_prices', 'warranty_price')) {
            return;
        }

        if (DB::table('product_prices')->whereNotNull('warranty_price')->exists()) {
            throw new RuntimeException(
                'Warranty price data exists; export or clear it before rolling back this migration.'
            );
        }

        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropColumn('warranty_price');
        });
    }
};
