<?php

namespace App\Observers;

use App\Models\ProductPrice;
use App\Services\CatalogSnapshotRevisionService;

final class ProductPriceCatalogObserver
{
    public function created(ProductPrice $price): void
    {
        $this->invalidate($price);
    }

    public function updated(ProductPrice $price): void
    {
        $this->invalidate($price);
    }

    public function deleted(ProductPrice $price): void
    {
        $this->invalidate($price);
    }

    public function restored(ProductPrice $price): void
    {
        $this->invalidate($price);
    }

    private function invalidate(ProductPrice $price): void
    {
        $supplierId = $price->productSupplier?->supplier_id;

        if ($supplierId) {
            app(CatalogSnapshotRevisionService::class)->invalidateSupplier((int) $supplierId);

            return;
        }

        app(CatalogSnapshotRevisionService::class)->invalidateGlobal();
    }
}
