<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('supplier_tier_discount')
            ->where('label', 'بار')
            ->update(['label' => 'کل موجودی']);

        DB::table('supplier_tier_percentages')
            ->where('tier_label', 'بار')
            ->update(['tier_label' => 'کل موجودی']);

        DB::table('products')
            ->whereNotNull('quantity_tiers')
            ->select(['id'])
            ->chunkById(200, function ($products): void {
                DB::transaction(function () use ($products): void {
                    $lockedProducts = DB::table('products')
                        ->whereIn('id', $products->pluck('id'))
                        ->lockForUpdate()
                        ->get(['id', 'quantity_tiers']);

                    foreach ($lockedProducts as $product) {
                        $tiers = json_decode($product->quantity_tiers ?? 'null', true);
                        if (!is_array($tiers)) {
                            continue;
                        }

                        $changed = false;
                        foreach ($tiers as $index => $tier) {
                            if (is_array($tier) && ($tier['name'] ?? null) === 'بار') {
                                $tiers[$index]['name'] = 'کل موجودی';
                                $changed = true;
                            }
                        }

                        if ($changed) {
                            DB::table('products')->where('id', $product->id)->update([
                                'quantity_tiers' => json_encode($tiers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            ]);
                        }
                    }
                }, 3);
            });
    }

    public function down(): void
    {
        // Existing custom names cannot be distinguished from migrated names.
    }
};
