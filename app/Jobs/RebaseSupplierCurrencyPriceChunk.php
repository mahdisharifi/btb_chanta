<?php

namespace App\Jobs;

use App\Models\PriceType;
use App\Models\Supplier;
use App\Models\SupplierExchangeRateSetting;
use App\Services\ForeignCurrencyPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebaseSupplierCurrencyPriceChunk implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 55;
    public int $tries = 3;
    public int $uniqueFor = 3600;

    /** @param array<int, int> $productSupplierIds */
    public function __construct(
        public readonly int $supplierId,
        public readonly string $currency,
        public readonly array $productSupplierIds,
        public readonly int $currencyRevision,
    ) {
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'supplier-currency-rebase-chunk',
            $this->supplierId,
            $this->currency,
            $this->currencyRevision,
            hash('sha256', implode(',', $this->productSupplierIds)),
        ]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ForeignCurrencyPriceService $service): void
    {
        $supplier = Supplier::query()->find($this->supplierId);
        if (!$supplier
            || $supplier->pricing_currency !== $this->currency
            || (int) $supplier->pricing_currency_revision !== $this->currencyRevision) {
            return;
        }
        $tierPricingRevision = (int) $supplier->tier_pricing_revision;

        $priceTypes = PriceType::query()
            ->whereIn('code', ['cash', 'check', 'warranty'])
            ->get()
            ->keyBy('code');
        $settingRevision = $this->currency === 'TOM'
            ? null
            : SupplierExchangeRateSetting::query()
                ->where('supplier_id', $this->supplierId)
                ->where('currency', $this->currency)
                ->value('revision');
        $changedIds = [];

        foreach ($this->productSupplierIds as $productSupplierId) {
            $rebased = $service->rebaseProductCurrency(
                (int) $productSupplierId,
                $this->supplierId,
                $this->currency,
                $this->currencyRevision,
            );

            if (!$rebased) {
                continue;
            }

            if ($this->currency === 'TOM') {
                $result = \App\Helpers\TierPriceUpdater::updateSupplierPrices(
                    $this->supplierId,
                    productSupplierIds: [(int) $productSupplierId],
                    expectedCurrencyRevision: $this->currencyRevision,
                    expectedTierPricingRevision: $tierPricingRevision,
                );

                if ($result['settings_changed']) {
                    ApplySupplierTomanTierPrices::dispatchCurrentGeneration($this->supplierId);
                }
                if ($result['updated'] > 0) {
                    $changedIds[] = (int) $productSupplierId;
                }
                continue;
            }

            if ($settingRevision === null) {
                continue;
            }

            $service->applyConfiguredRate(
                (int) $productSupplierId,
                $this->supplierId,
                $this->currency,
                (int) $settingRevision,
                $priceTypes,
                $changedIds,
                $tierPricingRevision,
            );
        }

        $currentSupplier = Supplier::query()->find($this->supplierId);
        if ($currentSupplier
            && $currentSupplier->pricing_currency === $this->currency
            && (int) $currentSupplier->pricing_currency_revision === $this->currencyRevision
            && (int) $currentSupplier->tier_pricing_revision !== $tierPricingRevision) {
            // Re-scan products not yet rebased, and reapply current percentages
            // to rows that were already moved to the new currency.
            RebaseSupplierCurrencyPrices::dispatch(
                $this->supplierId,
                $this->currency,
                $this->currencyRevision,
            );

            if ($this->currency === 'TOM') {
                ApplySupplierTomanTierPrices::dispatchCurrentGeneration($this->supplierId);
            } else {
                ApplySupplierCurrencyPrices::dispatchCurrentGeneration(
                    $this->supplierId,
                    $this->currency,
                    automaticOnly: false,
                    service: $service,
                );
            }
        }

        if ($changedIds !== []) {
            BroadcastSupplierProductPrices::dispatch(
                $this->supplierId,
                array_values(array_unique(array_map('intval', $changedIds))),
            );
        }
    }
}
