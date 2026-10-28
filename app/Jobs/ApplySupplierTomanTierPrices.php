<?php

namespace App\Jobs;

use App\Models\PriceType;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplySupplierTomanTierPrices implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;
    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $supplierId,
        public readonly int $currencyRevision,
        public readonly int $tierPricingRevision,
    ) {
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'supplier-toman-tier-prices',
            $this->supplierId,
            $this->currencyRevision,
            $this->tierPricingRevision,
        ]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public static function dispatchCurrentGeneration(int $supplierId): void
    {
        $supplier = Supplier::query()->find($supplierId);
        if (!$supplier || $supplier->pricing_currency !== 'TOM') {
            return;
        }

        self::dispatch(
            $supplierId,
            (int) $supplier->pricing_currency_revision,
            (int) $supplier->tier_pricing_revision,
        );
    }

    public function handle(): void
    {
        $supplier = Supplier::query()->find($this->supplierId);
        if (!$supplier || $supplier->pricing_currency !== 'TOM') {
            return;
        }

        $currencyRevision = (int) $supplier->pricing_currency_revision;
        $tierPricingRevision = (int) $supplier->tier_pricing_revision;
        if ($currencyRevision !== $this->currencyRevision
            || $tierPricingRevision !== $this->tierPricingRevision) {
            self::dispatchCurrentGeneration($this->supplierId);
            return;
        }

        $cashTypeId = PriceType::query()->where('code', 'cash')->value('id');
        if (!$cashTypeId) {
            return;
        }

        // Include inactive supplier products too. They are not publicly visible
        // now, but their matrix must be current if the supplier activates them.
        ProductSupplier::query()
            ->where('supplier_id', $this->supplierId)
            ->where('price_currency', 'TOM')
            ->whereHas('prices', fn ($query) => $query
                ->where('price_type_id', $cashTypeId)
                ->where('tier_index', 1)
                ->whereNull('check_period_id'))
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($currencyRevision, $tierPricingRevision): void {
                foreach ($rows->pluck('id')->chunk(10) as $ids) {
                    ApplySupplierTomanTierPriceChunk::dispatch(
                        $this->supplierId,
                        $ids->map(fn ($id): int => (int) $id)->values()->all(),
                        $currencyRevision,
                        $tierPricingRevision,
                    );
                }
            });
    }
}
