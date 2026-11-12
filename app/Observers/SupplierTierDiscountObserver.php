<?php

namespace App\Observers;

use App\Models\SupplierTierDiscount;
use App\Services\CatalogSnapshotRevisionService;
use App\Services\SupplierBranchPublicCacheInvalidator;
use App\Services\SupplierTierAvailabilityService;
use Illuminate\Support\Facades\DB;

final class SupplierTierDiscountObserver
{
    public function saved(SupplierTierDiscount $setting): void
    {
        $this->invalidate($setting);
    }

    public function deleted(SupplierTierDiscount $setting): void
    {
        $this->invalidate($setting);
    }

    private function invalidate(SupplierTierDiscount $setting): void
    {
        $supplierId = (int) $setting->supplier_id;
        app(SupplierTierAvailabilityService::class)->forget($supplierId);
        app(CatalogSnapshotRevisionService::class)->invalidateSupplier($supplierId);
        DB::afterCommit(static function () use ($supplierId): void {
            app(SupplierTierAvailabilityService::class)->forget($supplierId);
            try {
                app(SupplierBranchPublicCacheInvalidator::class)->invalidate($supplierId);
            } catch (\Throwable $exception) {
                report($exception);
                try {
                    \App\Jobs\InvalidateSupplierBranchPublicCaches::dispatch([$supplierId], [], false, false);
                } catch (\Throwable $dispatchException) {
                    report($dispatchException);
                }
            }
        });
    }
}
