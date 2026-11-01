<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_supplier', function (Blueprint $table): void {
            $table->decimal('branch_price_percentage', 18, 12)->default(0)->change();
        });
    }

    public function down(): void
    {
        $hasPrecisionBeyondSixPlaces = DB::table('product_supplier')
            ->whereRaw('branch_price_percentage <> ROUND(branch_price_percentage, 6)')
            ->exists();

        if ($hasPrecisionBeyondSixPlaces) {
            throw new RuntimeException(
                'Cannot reduce branch price percentage precision while values use more than six decimal places.'
            );
        }

        Schema::table('product_supplier', function (Blueprint $table): void {
            $table->decimal('branch_price_percentage', 9, 6)->default(0)->change();
        });
    }
};
