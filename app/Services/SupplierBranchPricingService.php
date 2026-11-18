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
                ProductSupplier::query()->whereIn('id', $offers->modelKeys())
                    ->update(['is_branch_pricing_independent' => false]);
                ProductPrice::query()->whereIn('product_supplier_id', $offers->modelKeys())
                    ->update(['branch_source_price_id' => null]);
            } else {
                foreach ($offers as $offer) {
                    $offer->forceFill(['branch_price_percentage' => $percentage])->save();
                    if ($offer->is_branch_pricing_independent) {
                        continue;
                    }
                    app(SupplierBranchPriceSyncService::class)->syncBranchProduct($offer->product, $branch);
                }
            }

            app(CatalogSnapshotRevisionService::class)->invalidateSuppliers([$branch->id]);
            DB::afterCommit(fn () => app(SupplierBranchPublicCacheInvalidator::class)->invalidateMany([$branch->id]));
        }, 3);
    }

    public function configureProduct(Supplier $primary, int $branchId, int $offerId, string $mode): void
    {
        Validator::make(['pricingMode' => $mode], [
            'pricingMode' => 'required|in:independent,percentage',
        ])->validate();

        DB::transaction(function () use ($primary, $branchId, $offerId, $mode): void {
            $primary = Supplier::query()->whereKey($primary->id)->lockForUpdate()->firstOrFail();
            $branch = app(SupplierBranchCatalogService::class)->branch($primary, $branchId, true);
            abort_unless($branch->usesPrimaryPricing(), 422, 'قیمت مستقل برای هر محصول فقط وقتی روش کلی شعبه درصدی است قابل تنظیم است.');

            $offer = ProductSupplier::query()->where('supplier_id', $branch->id)
                ->whereKey($offerId)->with('product')->lockForUpdate()->firstOrFail();

            if ($mode === 'independent') {
                if (!$offer->is_branch_pricing_independent) {
                    $offer->forceFill(['is_branch_pricing_independent' => true])->save();
                    $offer->prices()->whereNotNull('branch_source_price_id')
                        ->update(['branch_source_price_id' => null]);

                    app(\App\Services\ProductPriceRevisionService::class)->increment(
                        (int) $offer->product_id,
                        (int) $branch->id,
                    );
                    DB::afterCommit(static function () use ($branch, $offer): void {
                        app(SupplierBranchPublicCacheInvalidator::class)->invalidateMany([(int) $branch->id]);
                        \App\Jobs\BroadcastSupplierProductPrices::dispatch(
                            (int) $branch->id,
                            [(int) $offer->id],
                            false,
                        );
                    });
                }

                return;
            }

            $offer->forceFill(['is_branch_pricing_independent' => false])->save();
            $percentage = (float) ($offer->branch_price_percentage ?? $branch->branch_price_percentage ?? 0);
            app(SupplierBranchPriceSyncService::class)->syncBranchProduct($offer->product, $branch, $percentage);
        }, 3);
    }
}
