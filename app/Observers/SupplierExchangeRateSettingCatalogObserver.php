<?php

namespace App\Observers;

use App\Models\SupplierExchangeRateSetting;
use App\Services\CatalogSnapshotRevisionService;

final class SupplierExchangeRateSettingCatalogObserver
{
    private const BASIS_FIELDS = [
        'currency',
        'rate_toman',
        'manual_rate_toman',
        'auto_update',
        'fetched_at',
    ];

    public function saved(SupplierExchangeRateSetting $setting): void
    {
        if (!$setting->wasRecentlyCreated && !$setting->wasChanged(self::BASIS_FIELDS)) {
            return;
        }

        app(CatalogSnapshotRevisionService::class)
            ->invalidateSupplier((int) $setting->supplier_id);
    }

    public function deleted(SupplierExchangeRateSetting $setting): void
    {
        app(CatalogSnapshotRevisionService::class)
            ->invalidateSupplier((int) $setting->supplier_id);
    }
}
