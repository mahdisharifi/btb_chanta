<?php

namespace App\Jobs;

use App\Services\ProductPriceBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastSupplierProductPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 45;
    public int $tries = 3;
    public bool $revisionAlreadyIncremented = true;

    /** @param array<int, int> $productSupplierIds */
    public function __construct(
        public readonly int $supplierId,
        public readonly array $productSupplierIds,
        bool $revisionAlreadyIncremented = true,
    ) {
        $this->revisionAlreadyIncremented = $revisionAlreadyIncremented;
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(ProductPriceBroadcastService $service): void
    {
        $service->broadcastMany(
            $this->productSupplierIds,
            revisionAlreadyIncremented: $this->revisionAlreadyIncremented,
            supplierIds: [$this->supplierId],
            throwOnFailure: true,
        );
    }
}
