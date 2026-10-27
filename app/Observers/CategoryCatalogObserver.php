<?php

namespace App\Observers;

use App\Services\CategoryCatalog;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class CategoryCatalogObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void { CategoryCatalog::invalidate(); }
    public function deleted(Model $model): void { CategoryCatalog::invalidate(); }
    public function restored(Model $model): void { CategoryCatalog::invalidate(); }
}
