<?php

namespace App\Jobs;

use App\Helpers\TierPriceUpdater;
use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ApplySupplierTomanTierPriceChunk implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 55;
    public int $tries = 3;
    public int $uniqueFor = 3600;
    private const MAX_PRODUCT_ATTEMPTS = 3;
    private const PRODUCT_RETRY_DELAYS = [10, 30];

    /** @param array<int, int> $productSupplierIds */
    public function __construct(
        public readonly int $supplierId,
        public readonly array $productSupplierIds,
        public readonly int $currencyRevision,
        public readonly int $tierPricingRevision,
        public readonly int $retryAttempt = 0,
    ) {
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'supplier-toman-tier-price-chunk',
            $this->supplierId,
            $this->currencyRevision,
            $this->tierPricingRevision,
            $this->retryAttempt,
            hash('sha256', implode(',', $this->productSupplierIds)),
        ]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        $result = TierPriceUpdater::updateSupplierPrices(
            $this->supplierId,
            productSupplierIds: $this->productSupplierIds,
            expectedCurrencyRevision: $this->currencyRevision,
            expectedTierPricingRevision: $this->tierPricingRevision,
        );

        if ($result['settings_changed']) {
            ApplySupplierTomanTierPrices::dispatchCurrentGeneration($this->supplierId);
            return;
        }

        if ($result['updated'] > 0) {
            // This increments price revisions, invalidates listing caches and
            // broadcasts the committed matrix to existing live product pages.
            BroadcastSupplierProductPrices::dispatch(
                $this->supplierId,
                array_values(array_unique(array_map('intval', $this->productSupplierIds))),
                false,
            );
        }

        $failedIds = $result['failed_product_supplier_ids'];
        if ($failedIds === []) {
            return;
        }

        if ($this->retryAttempt + 1 < self::MAX_PRODUCT_ATTEMPTS) {
            $delaySeconds = self::PRODUCT_RETRY_DELAYS[$this->retryAttempt] ?? 30;
            self::dispatch(
                $this->supplierId,
                $failedIds,
                $this->currencyRevision,
                $this->tierPricingRevision,
                $this->retryAttempt + 1,
            )->delay(now()->addSeconds($delaySeconds));

            return;
        }

        Log::error('A Toman tier-price batch exhausted product retries.', [
            'supplier_id' => $this->supplierId,
            'product_supplier_ids' => $failedIds,
            'attempts' => self::MAX_PRODUCT_ATTEMPTS,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        try {
            BroadcastSupplierProductPrices::dispatch(
                $this->supplierId,
                array_values(array_unique(array_map('intval', $this->productSupplierIds))),
                false,
            );
        } catch (\Throwable $broadcastException) {
            Log::error('Could not queue a final product-price broadcast after a Toman tier chunk failed.', [
                'supplier_id' => $this->supplierId,
                'product_supplier_ids' => $this->productSupplierIds,
                'exception' => $broadcastException::class,
            ]);
        }

        Log::error('A Toman tier-price batch exhausted its retries.', [
            'supplier_id' => $this->supplierId,
            'product_supplier_ids' => $this->productSupplierIds,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
