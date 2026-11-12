<?php

namespace App\Services;

use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierBranchPricingService
{
    public function configure(Supplier $primary, int $branchId, string $mode, ?string $percentage): void
    {
        Validator::make(['pricingMode' => $mode, 'branchPercentage' => $percentage], [
            'pricingMode' => 'required|in:independent,percentage',
            'branchPercentage' => 'required_if:pricingMode,percentage|nullable|numeric|decimal:0,12|between:-99.999999,999.999999',
        ])->validate();

        DB::transaction(function () use ($primary, $branchId, $mode, $percentage): void {
            $primary = Supplier::query()->whereKey($primary->id)->lockForUpdate()->firstOrFail();
            $branch = app(SupplierBranchCatalogService::class)->branch($primary, $branchId, true);
            $branch->forceFill([
                'branch_pricing_mode' => $mode,
                'branch_price_percentage' => $mode === 'percentage' ? $percentage : null,
            ])->save();

            $offers = ProductSupplier::query()->where('supplier_id', $branch->id)
                ->whereHas('product')->with('product')->orderBy('id')->lockForUpdate()->get();
            if ($mode === 'independent') {
                ProductPrice::query()->whereIn('product_supplier_id', $offers->modelKeys())
                    ->update(['branch_source_price_id' => null]);
            } else {
                foreach ($offers as $offer) {
                    $offer->forceFill(['branch_price_percentage' => $percentage])->save();
                    app(SupplierBranchPriceSyncService::class)->syncBranchProduct($offer->product, $branch);
                }
            }

            app(CatalogSnapshotRevisionService::class)->invalidateSuppliers([$branch->id]);
            DB::afterCommit(fn () => app(SupplierBranchPublicCacheInvalidator::class)->invalidateMany([$branch->id]));
        }, 3);
    }
}
