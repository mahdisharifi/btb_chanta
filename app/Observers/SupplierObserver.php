<?php

namespace App\Observers;

use App\Jobs\InvalidateSupplierBranchPublicCaches;
use App\Models\Supplier;
use App\Services\CatalogSnapshotRevisionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SupplierObserver
 * 
 * مدیریت Cache Invalidation برای تأمین‌کنندگان
 * هر وقت تأمین‌کننده‌ای تغییر کند، کش‌های مرتبط پاک می‌شوند
 */
class SupplierObserver
{
    /**
     * Handle the Supplier "created" event.
     */
    public function created(Supplier $supplier): void
    {
        $this->invalidateRevisionAndScheduleCacheClears((int) $supplier->id, [$supplier->province], true);
        
        Log::info('Supplier created, cache cleared', [
            'supplier_id' => $supplier->id,
            'company_name' => $supplier->company_name,
        ]);
    }

    /**
     * Handle the Supplier "updated" event.
     */
    public function updated(Supplier $supplier): void
    {
        // Supplier directory city lists are cached by province; clear both the
        // previous and current province entries when either legacy or FK location changes.
        $locationFields = ['province', 'city', 'province_id', 'city_id'];
        $locationChanged = $supplier->isDirty($locationFields);
        if ($locationChanged) {
            $provinceNames = [
                $supplier->getOriginal('province'),
                $supplier->province,
            ];
        } else {
            $provinceNames = [];
        }
        $this->invalidateRevisionAndScheduleCacheClears((int) $supplier->id, $provinceNames, $locationChanged);
        
        Log::debug('Supplier updated, cache cleared', [
            'supplier_id' => $supplier->id,
            'changed_fields' => array_keys($supplier->getDirty()),
        ]);
    }

    /**
     * Handle the Supplier "deleted" event.
     */
    public function deleted(Supplier $supplier): void
    {
        $this->invalidateRevisionAndScheduleCacheClears((int) $supplier->id, [$supplier->province], true);
        
        Log::info('Supplier deleted, cache cleared', [
            'supplier_id' => $supplier->id,
            'company_name' => $supplier->company_name,
        ]);
    }

    /**
     * Handle the Supplier "restored" event.
     */
    public function restored(Supplier $supplier): void
    {
        $this->invalidateRevisionAndScheduleCacheClears((int) $supplier->id, [$supplier->province], true);
        
        Log::info('Supplier restored, cache cleared', [
            'supplier_id' => $supplier->id,
        ]);
    }

    /**
     * Handle the Supplier "force deleted" event.
     */
    public function forceDeleted(Supplier $supplier): void
    {
        $this->invalidateRevisionAndScheduleCacheClears((int) $supplier->id, [$supplier->province], true);
        
        Log::info('Supplier force deleted, cache cleared', [
            'supplier_id' => $supplier->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    /**
     * پاک کردن کش‌های مرتبط با تأمین‌کنندگان
     */
    /**
     * @param array<int, mixed> $provinceNames
     */
    private function invalidateRevisionAndScheduleCacheClears(int $supplierId, array $provinceNames = [], bool $clearGeography = false): void
    {
        // Keep the revision bump inside the current database transaction so a
        // committed supplier/address change cannot retain a stale catalog revision.
        app(CatalogSnapshotRevisionService::class)->invalidateSupplier($supplierId);

        DB::afterCommit(function () use ($supplierId, $provinceNames, $clearGeography): void {
            try {
                $this->clearSupplierCaches($supplierId);

                if ($clearGeography) {
                    $this->clearGeographyCaches($provinceNames);
                }
            } catch (Throwable $exception) {
                Log::warning('Supplier cache invalidation failed after commit; scheduling a retry.', [
                    'supplier_id' => $supplierId,
                    'exception' => $exception->getMessage(),
                ]);

                try {
                    InvalidateSupplierBranchPublicCaches::dispatch([$supplierId], $provinceNames, $clearGeography, false);
                } catch (Throwable $dispatchException) {
                    // A cache backend or queue outage must not make a successfully
                    // committed supplier profile save look like a failed write.
                    Log::error('Unable to schedule supplier cache invalidation retry.', [
                        'supplier_id' => $supplierId,
                        'exception' => $dispatchException->getMessage(),
                    ]);
                }
            }
        });
    }

    private function clearSupplierCaches(int $supplierId): void
    {
        // کش‌های صفحه لیست تأمین‌کنندگان
        Cache::forget('web:suppliers:stats');
        Cache::forget('home:suppliers:top'); // صفحه اصلی
        app(\App\Services\SupplierBranchPublicCacheInvalidator::class)->invalidate($supplierId);
        
        // نکته: کش‌های filtered results با pattern 'suppliers:list:*' 
        // در Laravel با database driver قابل حذف گروهی نیستند
        // برای این کار نیاز به Redis + cache tags داریم
        // فعلاً TTL کوتاه (10 دقیقه) کافی است
    }

    /**
     * پاک کردن کش‌های جغرافیایی (استان‌ها و شهرها)
     */
    private function clearGeographyCaches(array $provinceNames = []): void
    {
        Cache::forget('web:suppliers:provinces');
        Cache::forget('web:suppliers:cities');

        foreach (array_unique(array_filter(array_map(
            static fn ($province): string => trim((string) $province),
            $provinceNames,
        ))) as $province) {
            Cache::forget('web:suppliers:cities:' . $province);
        }
    }
}
