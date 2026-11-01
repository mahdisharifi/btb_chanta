<?php

namespace App\Jobs;

use App\Services\SupplierBranchPublicCacheInvalidator;
use App\Services\CatalogSnapshotRevisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvalidateSupplierBranchPublicCaches implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @param array<int, int> $supplierIds
     * @param array<int, mixed> $provinceNames
     */
    public function __construct(
        public readonly array $supplierIds,
        public readonly array $provinceNames = [],
        public readonly bool $clearGeography = false,
        public readonly bool $refreshRevisions = true,
    )
    {
        // A retry dispatched while the app is configured for sync must be
        // persisted; otherwise a second transient cache failure is lost inline.
        if (config('queue.default') === 'sync') {
            $this->onConnection('database');
        }
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(
        CatalogSnapshotRevisionService $revisions,
        SupplierBranchPublicCacheInvalidator $invalidator,
    ): void
    {
        $invalidator->invalidateMany($this->supplierIds);

        if ($this->clearGeography) {
            Cache::forget('web:suppliers:provinces');
            Cache::forget('web:suppliers:cities');

            foreach (array_unique(array_filter(array_map(
                static fn ($province): string => trim((string) $province),
                $this->provinceNames,
            ))) as $province) {
                Cache::forget('web:suppliers:cities:' . $province);
            }
        }

        // Bump revisions last: if the cache backend fails, retries must not
        // repeatedly rotate catalog snapshot keys. Revision writes are
        // transactional, so a failed bump can safely be retried.
        if ($this->refreshRevisions) {
            $revisions->invalidateSuppliers($this->supplierIds);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::critical('Supplier branch public cache invalidation exhausted its retries.', [
            'supplier_ids' => $this->supplierIds,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
