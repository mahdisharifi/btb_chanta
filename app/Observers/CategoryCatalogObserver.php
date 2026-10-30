<?php

namespace App\Observers;

use App\Services\CategoryCatalog;
use App\Services\CatalogSnapshotRevisionService;
use Illuminate\Database\Eloquent\Model;

class CategoryCatalogObserver
{
    public function saved(Model $model): void { $this->invalidate(); }
    public function deleted(Model $model): void { $this->invalidate(); }
    public function restored(Model $model): void { $this->invalidate(); }

    private function invalidate(): void
    {
        CategoryCatalog::invalidate();
        app(CatalogSnapshotRevisionService::class)->invalidateGlobal();
    }
}
