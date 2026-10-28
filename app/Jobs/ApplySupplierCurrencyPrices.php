<?php

namespace App\Jobs;

use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierExchangeRateSetting;
use App\Services\ForeignCurrencyPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ApplySupplierCurrencyPrices implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;
    public int $uniqueFor = 1800;
    // Keep this property initialized for queued payloads created before the
    // percentage revision fence was added.
    public ?int $tierPricingRevision = null;

    public function __construct(
        public readonly int $supplierId,
        public readonly string $currency,
        public readonly int $settingRevision,
        public readonly bool $automaticOnly = false,
        ?int $tierPricingRevision = null,
    ) {
        $this->tierPricingRevision = $tierPricingRevision;
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'supplier-currency-prices',
            $this->supplierId,
            $this->currency,
            $this->settingRevision,
            $this->tierPricingRevision ?? 0,
            (int) $this->automaticOnly,
        ]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public static function dispatchCurrentGeneration(
        int $supplierId,
        string $currency,
        bool $automaticOnly,
        ForeignCurrencyPriceService $service,
    ): void {
        $generation = DB::transaction(function () use ($supplierId, $currency, $automaticOnly, $service): ?array {
            // Keep the same parent-first lock order used by percentage and FX settings writers.
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->first();
            $setting = SupplierExchangeRateSetting::query()
                ->where('supplier_id', $supplierId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (!$supplier
                || $supplier->pricing_currency !== $currency
                || !$setting
                || ($automaticOnly && !$setting->auto_update)
                || !$service->isUsableSupplierRate($setting)) {
                return null;
            }

            return [
                'setting_revision' => (int) $setting->revision,
                'tier_pricing_revision' => (int) $supplier->tier_pricing_revision,
            ];
        }, 3);

        if ($generation !== null) {
            self::dispatch(
                $supplierId,
                $currency,
                $generation['setting_revision'],
                $automaticOnly,
                $generation['tier_pricing_revision'],
            );
        }
    }

    public function handle(ForeignCurrencyPriceService $service): void
    {
        $generationIsCurrent = DB::transaction(function (): bool {
            $supplier = Supplier::query()->whereKey($this->supplierId)->lockForUpdate()->first();
            $setting = SupplierExchangeRateSetting::query()
                ->where('supplier_id', $this->supplierId)
                ->where('currency', $this->currency)
                ->lockForUpdate()
                ->first();

            if (!$supplier || !$setting || $supplier->pricing_currency !== $this->currency) {
                return false;
            }

            return (int) $setting->revision === $this->settingRevision
                && ($this->tierPricingRevision === null
                    || (int) $supplier->tier_pricing_revision === $this->tierPricingRevision);
        }, 3);

        $setting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplierId)
            ->where('currency', $this->currency)
            ->first();

        if (!$generationIsCurrent
            || !$setting
            || ($this->automaticOnly && !$setting->auto_update)
            || !$service->isUsableSupplierRate($setting)) {
            self::dispatchCurrentGeneration($this->supplierId, $this->currency, $this->automaticOnly, $service);
            return;
        }

        $supplier = Supplier::query()->find($this->supplierId);
        if (!$supplier) {
            return;
        }
        $tierPricingRevision = $this->tierPricingRevision ?? (int) $supplier->tier_pricing_revision;

        ProductSupplier::query()
            ->where('supplier_id', $this->supplierId)
            ->where('price_currency', $this->currency)
            ->whereHas('supplier', fn ($query) => $query->where('pricing_currency', $this->currency))
            ->whereNotNull('foreign_price_amount')
            ->where('foreign_price_amount', '>', 0)
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($tierPricingRevision): void {
                foreach ($rows->pluck('id')->chunk(25) as $ids) {
                    ApplySupplierCurrencyPriceChunk::dispatch(
                        $this->supplierId,
                        $this->currency,
                        $ids->map(fn ($id): int => (int) $id)->values()->all(),
                        $this->settingRevision,
                        $this->automaticOnly,
                        0,
                        $tierPricingRevision,
                    );
                }
            });
    }
}
