<?php

namespace App\Observers;

use App\Services\CategoryCatalog;
use App\Models\Product;
use App\Services\SupplierBranchPublicCacheInvalidator;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class CategoryCatalogObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void { $this->invalidate($model); }
    public function deleted(Model $model): void { $this->invalidate($model); }
    public function restored(Model $model): void { $this->invalidate($model); }

    private function invalidate(Model $model): void
    {
        CategoryCatalog::invalidate();

        if ($model instanceof Product) {
            app(SupplierBranchPublicCacheInvalidator::class)->invalidate((int) $model->supplier_id);
        }
    }
}
