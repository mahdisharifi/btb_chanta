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

class RebaseSupplierCurrencyPrices implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;
    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $supplierId,
        public readonly string $currency,
        public readonly int $currencyRevision,
    ) {
    }

    public function uniqueId(): string
    {
        return implode(':', ['supplier-currency-rebase', $this->supplierId, $this->currency, $this->currencyRevision]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        $supplier = Supplier::query()->find($this->supplierId);
        if (!$supplier
            || $supplier->pricing_currency !== $this->currency
            || (int) $supplier->pricing_currency_revision !== $this->currencyRevision) {
            return;
        }

        $cashTypeId = PriceType::query()->where('code', 'cash')->value('id');
        if (!$cashTypeId) {
            return;
        }

        ProductSupplier::query()
            ->where('supplier_id', $this->supplierId)
            ->where(fn ($query) => $query->whereNull('price_currency')->orWhere('price_currency', '!=', $this->currency))
            ->whereHas('prices', fn ($query) => $query
                ->where('price_type_id', $cashTypeId)
                ->whereNull('check_period_id'))
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows->pluck('id')->chunk(25) as $ids) {
                    RebaseSupplierCurrencyPriceChunk::dispatch(
                        $this->supplierId,
                        $this->currency,
                        $ids->map(fn ($id): int => (int) $id)->values()->all(),
                        $this->currencyRevision,
                    );
                }
            });
    }
}
