<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_supplier', 'is_branch_pricing_independent')) {
            return;
        }

        Schema::table('product_supplier', function (Blueprint $table): void {
            $table->boolean('is_branch_pricing_independent')->default(false)->after('branch_price_percentage');
        });

        DB::table('product_supplier')->whereExists(function ($query): void {
            $query->selectRaw('1')->from('suppliers')
                ->whereColumn('suppliers.id', 'product_supplier.supplier_id')
                ->whereNotNull('suppliers.parent_supplier_id')
                ->where('suppliers.branch_pricing_mode', 'percentage');
        })->whereExists(function ($query): void {
            $query->selectRaw('1')->from('product_prices')
                ->whereColumn('product_prices.product_supplier_id', 'product_supplier.id')
                ->whereNull('product_prices.branch_source_price_id');
        })->update(['is_branch_pricing_independent' => true]);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('product_supplier', 'is_branch_pricing_independent')) {
            return;
        }

        Schema::table('product_supplier', function (Blueprint $table): void {
            $table->dropColumn('is_branch_pricing_independent');
        });
    }
};
