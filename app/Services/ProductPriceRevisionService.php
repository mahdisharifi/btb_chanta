<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProductPriceRevisionService
{
    /** Call from the same transaction that changes the product price matrix. */
    public function increment(int $productId, int|array|null $supplierIds = null): void
    {
        DB::table('products')
            ->where('id', $productId)
            ->increment('price_revision');

        if ($supplierIds === null) {
            $supplierIds = DB::table('product_supplier')
                ->where('product_id', $productId)
                ->distinct()
                ->pluck('supplier_id')
                ->all();
        } elseif (is_int($supplierIds)) {
            $supplierIds = [$supplierIds];
        }

        app(CatalogSnapshotRevisionService::class)->invalidateSuppliers($supplierIds);
    }
}
