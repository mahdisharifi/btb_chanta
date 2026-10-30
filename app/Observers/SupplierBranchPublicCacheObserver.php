<?php

namespace App\Observers;

use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Services\SupplierBranchPublicCacheInvalidator;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SupplierBranchPublicCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(ProductSupplier|ProductPrice $model): void
    {
        $this->syncDerivedBranchPrices($model);
        $this->invalidateFor($model);
    }

    public function deleted(ProductSupplier|ProductPrice $model): void
    {
        if ($model instanceof ProductPrice) {
            app(\App\Services\SupplierBranchPriceSyncService::class)
                ->removeBranchesForDeletedPrimaryPrice($model);
        }
        $this->invalidateFor($model);
    }

    public function restored(ProductSupplier|ProductPrice $model): void
    {
        $this->syncDerivedBranchPrices($model);
        $this->invalidateFor($model);
    }

    private function syncDerivedBranchPrices(ProductSupplier|ProductPrice $model): void
    {
        if ($model instanceof ProductPrice) {
            app(\App\Services\SupplierBranchPriceSyncService::class)
                ->syncBranchesForPrimaryPrice($model);
        }
    }

    private function invalidateFor(ProductSupplier|ProductPrice $model): void
    {
        $supplierId = $model instanceof ProductSupplier
            ? (int) $model->supplier_id
            : (int) $model->productSupplier()->withTrashed()->value('supplier_id');

        app(SupplierBranchPublicCacheInvalidator::class)->invalidate($supplierId ?: null);
    }
}
