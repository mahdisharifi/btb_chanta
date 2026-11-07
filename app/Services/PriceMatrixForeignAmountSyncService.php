<?php

namespace App\Services;

use App\Models\ProductSupplier;

class PriceMatrixForeignAmountSyncService
{
    /**
     * Move the offer's stored foreign amounts with manually edited or
     * percentage-adjusted cash prices. Without this, the next scheduled rate
     * application would recalculate prices from the previous amounts and undo
     * the supplier's change.
     *
     * @param array<int, array{old: int|null, new: int}> $cashChanges
     */
    public function syncCashChanges(ProductSupplier $productSupplier, array $cashChanges): void
    {
        if ($productSupplier->price_currency === 'TOM' || $cashChanges === []) {
            return;
        }

        $rate = is_numeric($productSupplier->foreign_price_rate) && (float) $productSupplier->foreign_price_rate > 0
            ? (float) $productSupplier->foreign_price_rate
            : null;

        $referenceChange = $cashChanges[1] ?? reset($cashChanges);
        if (is_array($referenceChange)) {
            $amount = (float) $productSupplier->foreign_price_amount;
            $oldReference = (int) ($referenceChange['old'] ?? 0);
            $newReference = (int) $referenceChange['new'];

            if ($amount > 0 && $oldReference > 0) {
                $productSupplier->foreign_price_amount = $this->decimal(
                    $amount * ($newReference / $oldReference)
                );
            } elseif ($rate !== null) {
                $productSupplier->foreign_price_amount = $this->decimal($newReference / $rate);
            }
        }

        $tierAmounts = is_array($productSupplier->foreign_tier_amounts)
            ? $productSupplier->foreign_tier_amounts
            : [];

        foreach ($cashChanges as $tierIndex => $change) {
            $oldPrice = (int) ($change['old'] ?? 0);
            $newPrice = (int) $change['new'];
            $stored = $tierAmounts[$tierIndex] ?? $tierAmounts[(string) $tierIndex] ?? null;
            $stored = is_numeric($stored) ? (float) $stored : 0.0;

            if ($stored > 0 && $oldPrice > 0) {
                $tierAmounts[$tierIndex] = $this->decimal($stored * ($newPrice / $oldPrice));
            } elseif ($rate !== null) {
                $tierAmounts[$tierIndex] = $this->decimal($newPrice / $rate);
            }
        }

        $productSupplier->forceFill([
            'foreign_tier_amounts' => $tierAmounts === [] ? null : $tierAmounts,
            'foreign_price_applied_currency' => null,
            'foreign_price_applied_setting_revision' => 0,
            'foreign_price_applied_tier_revision' => null,
            'foreign_price_updated_at' => now(),
            'foreign_price_last_error' => null,
            'foreign_price_revision' => ((int) $productSupplier->foreign_price_revision) + 1,
        ]);
    }

    private function decimal(float $amount): string
    {
        return number_format($amount, 4, '.', '');
    }
}
