<?php

namespace App\Jobs;

use App\Models\PriceType;
use App\Models\Supplier;
use App\Models\SupplierExchangeRateSetting;
use App\Jobs\BroadcastSupplierProductPrices;
use App\Services\ForeignCurrencyPriceService;
use App\Models\ProductPrice;
use App\Services\SupplierBranchPriceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApplySupplierCurrencyPriceChunk implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 55;
    public int $tries = 3;
    public int $uniqueFor = 3600;
    private const MAX_PRODUCT_ATTEMPTS = 3;
    private const PRODUCT_RETRY_DELAYS = [10, 30];
    private const APPLY_FAILURE_MESSAGE = 'به‌روزرسانی گروهی یک یا چند محصول ناموفق بود؛ قیمت آخرین وضعیت معتبر حفظ شده است. برای تلاش دوباره، اعمال نرخ‌ها روی همهٔ محصولات را اجرا کنید.';
    // Nullable default keeps already-queued serialized chunks readable.
    public ?int $tierPricingRevision = null;

    /** @param array<int, int> $productSupplierIds */
    public function __construct(
        public readonly int $supplierId,
        public readonly string $currency,
        public readonly array $productSupplierIds,
        public readonly int $settingRevision,
        public readonly bool $automaticOnly = false,
        public readonly int $retryAttempt = 0,
        ?int $tierPricingRevision = null,
    ) {
        $this->tierPricingRevision = $tierPricingRevision;
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'supplier-currency-price-chunk',
            $this->supplierId,
            $this->currency,
            $this->settingRevision,
            $this->tierPricingRevision ?? 0,
            $this->retryAttempt,
            hash('sha256', implode(',', $this->productSupplierIds)),
        ]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ForeignCurrencyPriceService $service): void
    {
        $setting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplierId)
            ->where('currency', $this->currency)
            ->first();

        if (!$setting
            || (int) $setting->revision !== $this->settingRevision
            || ($this->automaticOnly && !$setting->auto_update)
            || !$service->isUsableSupplierRate($setting)) {
            // A previous attempt may have committed part of the chunk before
            // its generation became stale. Refresh persisted values and caches
            // without applying the obsolete rate again.
            $this->dispatchChunkBroadcast();
            ApplySupplierCurrencyPrices::dispatchCurrentGeneration(
                $this->supplierId,
                $this->currency,
                $this->automaticOnly,
                $service,
            );
            return;
        }

        $supplier = Supplier::query()->find($this->supplierId);
        if (!$supplier || $supplier->pricing_currency !== $this->currency) {
            $this->dispatchChunkBroadcast();
            return;
        }
        $tierPricingRevision = $this->tierPricingRevision ?? (int) $supplier->tier_pricing_revision;
        if ((int) $supplier->tier_pricing_revision !== $tierPricingRevision) {
            $this->dispatchChunkBroadcast();
            ApplySupplierCurrencyPrices::dispatchCurrentGeneration(
                $this->supplierId,
                $this->currency,
                $this->automaticOnly,
                $service,
            );
            return;
        }

        $priceTypes = PriceType::query()
            ->whereIn('code', ['cash', 'check', 'warranty'])
            ->get()
            ->keyBy('code');
        $changedProductSupplierIds = [];
        $failedProductSupplierIds = [];

        foreach ($this->productSupplierIds as $productSupplierId) {
            try {
                $service->applyConfiguredRate(
                    (int) $productSupplierId,
                    $this->supplierId,
                    $this->currency,
                    $this->settingRevision,
                    $priceTypes,
                    $changedProductSupplierIds,
                    $tierPricingRevision,
                );
            } catch (Throwable $exception) {
                $failedProductSupplierIds[] = (int) $productSupplierId;
                Log::error('Foreign-currency product price update failed.', [
                    'product_supplier_id' => (int) $productSupplierId,
                    'supplier_id' => $this->supplierId,
                    'currency' => $this->currency,
                    'exception' => $exception::class,
                    'message' => mb_substr($exception->getMessage(), 0, 250),
                ]);
            }
        }

        // Broadcast the full chunk: a previous queue attempt may have committed
        // some rows before timing out, and those rows are skipped by checkpoint.
        $this->dispatchChunkBroadcast();

        $currentSupplier = Supplier::query()->find($this->supplierId);
        $currentSetting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplierId)
            ->where('currency', $this->currency)
            ->first();
        if ($currentSupplier?->pricing_currency === $this->currency
            && $currentSetting
            && (((int) $currentSupplier->tier_pricing_revision !== $tierPricingRevision)
                || (int) $currentSetting->revision !== $this->settingRevision)) {
            ApplySupplierCurrencyPrices::dispatchCurrentGeneration(
                $this->supplierId,
                $this->currency,
                $this->automaticOnly,
                $service,
            );
        }

        if ($failedProductSupplierIds === []) {
            return;
        }

        if ($this->retryAttempt + 1 < self::MAX_PRODUCT_ATTEMPTS) {
            $nextAttempt = $this->retryAttempt + 1;
            $delaySeconds = self::PRODUCT_RETRY_DELAYS[$this->retryAttempt] ?? 30;

            self::dispatch(
                $this->supplierId,
                $this->currency,
                $failedProductSupplierIds,
                $this->settingRevision,
                $this->automaticOnly,
                $nextAttempt,
                $tierPricingRevision,
            )->delay(now()->addSeconds($delaySeconds));

            return;
        }

        Log::error('Foreign-currency product price update exhausted its product retries.', [
            'product_supplier_ids' => $failedProductSupplierIds,
            'supplier_id' => $this->supplierId,
            'currency' => $this->currency,
            'setting_revision' => $this->settingRevision,
        ]);

        $this->recordApplyFailure();
    }

    public function failed(?Throwable $exception): void
    {
        try {
            $this->dispatchChunkBroadcast();
        } catch (Throwable $broadcastException) {
            Log::error('Could not queue a final product-price broadcast after a currency chunk failed.', [
                'supplier_id' => $this->supplierId,
                'product_supplier_ids' => $this->productSupplierIds,
                'exception' => $broadcastException::class,
            ]);
        }

        $this->recordApplyFailure();
    }

    private function recordApplyFailure(): void
    {
        SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplierId)
            ->where('currency', $this->currency)
            ->where('revision', $this->settingRevision)
            ->update(['last_apply_error' => self::APPLY_FAILURE_MESSAGE]);
    }

    private function dispatchChunkBroadcast(): void
    {
        // Price rows are written quietly by the currency service so a large
        // matrix does not trigger one branch sync and cache flush per row.
        // Rebuild the complete bounded chunk once before broadcasting.
        $sourcePrices = ProductPrice::query()
            ->whereIn('product_supplier_id', array_values(array_unique(array_map('intval', $this->productSupplierIds))))
            ->get();
        app(SupplierBranchPriceSyncService::class)
            ->syncBranchesForPrimaryPrices($sourcePrices);

        BroadcastSupplierProductPrices::dispatch(
            $this->supplierId,
            array_values(array_unique(array_map('intval', $this->productSupplierIds))),
        );
    }
}
