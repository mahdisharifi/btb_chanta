<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\CatalogSnapshotRevisionService;
use App\Services\CategoryCatalog;
use App\Services\SupplierBranchPublicCacheInvalidator;
use Illuminate\Database\Eloquent\Model;

class CategoryCatalogObserver
{
    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        CategoryCatalog::invalidate();
        app(CatalogSnapshotRevisionService::class)->invalidateGlobal();

        if ($model instanceof Product) {
            app(SupplierBranchPublicCacheInvalidator::class)->invalidate((int) $model->supplier_id);
        }
    }
}
