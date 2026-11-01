<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('pricing_currency', 3)->default('TOM');
            $table->unsignedInteger('pricing_currency_revision')->default(1);
        });

        // Preserve a supplier's existing currency only when every saved foreign
        // amount agrees. Mixed legacy catalogs fall back to TOM; their existing
        // Toman prices remain intact until the supplier chooses a global currency.
        DB::table('suppliers')->select('id')->orderBy('id')->chunkById(200, function ($suppliers): void {
            foreach ($suppliers as $supplier) {
                $currencies = DB::table('product_supplier')
                    ->where('supplier_id', $supplier->id)
                    ->whereNotNull('foreign_price_amount')
                    ->where('foreign_price_amount', '>', 0)
                    ->distinct()
                    ->pluck('price_currency')
                    ->filter(fn ($currency): bool => in_array($currency, ['USD', 'CNY', 'AED'], true))
                    ->values();

                $cashPriceTypeId = DB::table('price_types')->where('code', 'cash')->value('id');
                $hasTomanCashPrice = $cashPriceTypeId && DB::table('product_supplier as ps')
                    ->join('product_prices as pp', 'pp.product_supplier_id', '=', 'ps.id')
                    ->where('ps.supplier_id', $supplier->id)
                    ->where('ps.price_currency', 'TOM')
                    ->where('pp.price_type_id', $cashPriceTypeId)
                    ->whereNull('pp.check_period_id')
                    ->where('pp.price', '>', 0)
                    ->exists();

                if ($currencies->count() === 1 && !$hasTomanCashPrice) {
                    DB::table('suppliers')->where('id', $supplier->id)->update([
                        'pricing_currency' => $currencies->first(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['pricing_currency', 'pricing_currency_revision']);
        });
    }
};
