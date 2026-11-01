<?php

namespace App\Observers;

use App\Models\ProductSupplier;
use App\Services\CatalogSnapshotRevisionService;

final class ProductSupplierCatalogObserver
{
    public function created(ProductSupplier $productSupplier): void
    {
        $this->invalidate($productSupplier);
    }

    public function updated(ProductSupplier $productSupplier): void
    {
        $this->invalidate($productSupplier, (int) $productSupplier->getOriginal('supplier_id'));
    }

    public function deleted(ProductSupplier $productSupplier): void
    {
        $this->invalidate($productSupplier);
    }

    public function restored(ProductSupplier $productSupplier): void
    {
        $this->invalidate($productSupplier);
    }

    private function invalidate(ProductSupplier $productSupplier, int $previousSupplierId = 0): void
    {
        $supplierIds = array_filter([
            (int) $productSupplier->supplier_id,
            $previousSupplierId,
        ]);

        app(CatalogSnapshotRevisionService::class)->invalidateSuppliers($supplierIds);
    }
}
