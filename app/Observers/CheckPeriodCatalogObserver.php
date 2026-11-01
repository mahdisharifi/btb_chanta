<?php

namespace App\Observers;

use App\Models\CheckPeriod;
use App\Services\CatalogSnapshotRevisionService;

final class CheckPeriodCatalogObserver
{
    public function saved(CheckPeriod $period): void
    {
        $this->invalidate($period);
    }

    public function deleted(CheckPeriod $period): void
    {
        $this->invalidate($period);
    }

    public function restored(CheckPeriod $period): void
    {
        $this->invalidate($period);
    }

    private function invalidate(CheckPeriod $period): void
    {
        $originalScope = (string) $period->getOriginal('scope');
        $currentScope = (string) $period->scope;
        $supplierIds = array_values(array_unique(array_filter([
            (int) $period->getOriginal('supplier_id'),
            (int) $period->supplier_id,
        ])));

        if ($originalScope === 'global' || $currentScope === 'global' || $supplierIds === []) {
            app(CatalogSnapshotRevisionService::class)->invalidateGlobal();
        }

        if ($supplierIds !== []) {
            app(CatalogSnapshotRevisionService::class)->invalidateSuppliers($supplierIds);
        }
    }
}
