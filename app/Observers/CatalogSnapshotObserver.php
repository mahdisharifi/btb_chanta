<?php

namespace App\Observers;

use App\Services\CatalogSnapshotRevisionService;
use Illuminate\Database\Eloquent\Model;

final class CatalogSnapshotObserver
{
    public function saved(Model $model): void
    {
        $this->invalidate();
    }

    public function deleted(Model $model): void
    {
        $this->invalidate();
    }

    public function restored(Model $model): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        app(CatalogSnapshotRevisionService::class)->invalidateGlobal();
    }
}
