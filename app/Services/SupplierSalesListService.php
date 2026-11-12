<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierSalesListService
{
    public function add(Product $product, Supplier $supplier): ProductSupplier
    {
        return DB::transaction(function () use ($product, $supplier): ProductSupplier {
            $primary = null;
            if ($supplier->parent_supplier_id !== null) {
                $primary = Supplier::query()->whereKey($supplier->parent_supplier_id)->lockForUpdate()->firstOrFail();
                abort_unless($primary->is_active && (int) $primary->user_id === (int) auth()->id(), 403);
            }
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            abort_unless($supplier->is_active && (int) $supplier->user_id === (int) auth()->id(), 403);
            abort_unless(!$supplier->isBranch() || (int) $supplier->parent_supplier_id === (int) $primary?->id, 403);

            $product = Product::query()->findOrFail($product->id);
            $offer = ProductSupplier::withTrashed()->where('product_id', $product->id)
                ->where('supplier_id', $supplier->id)->lockForUpdate()->first();
            abort_unless(
                $offer || (int) $product->supplier_id === (int) $supplier->id
                    || ($product->status === ProductStatus::ACTIVE && $product->is_active),
                404,
            );

            if (!$offer) {
                $offer = ProductSupplier::create([
                    'product_id' => $product->id,
                    'supplier_id' => $supplier->id,
                    'is_active' => false,
                    'status' => ProductSupplier::STATUS_INACTIVE,
                    'branch_price_percentage' => $supplier->branch_price_percentage ?? 0,
                ]);
                if ($supplier->usesPrimaryPricing()) {
                    app(SupplierBranchPriceSyncService::class)->syncBranchProduct($product, $supplier);
                }
            } elseif ($offer->trashed()) {
                $offer->forceFill(['is_active' => false, 'status' => ProductSupplier::STATUS_INACTIVE]);
                $offer->restore();
                $offer->prices()->update(['is_active' => false]);
            }

            return $offer;
        }, 3);
    }
}
