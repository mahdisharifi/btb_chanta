<?php

namespace App\Observers;

use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $this->clearSupplierCaches();
        
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
        $this->clearSupplierCaches();
        
        // اگر استان یا شهر تغییر کرد، لیست provinces هم باید بروز شود
        if ($supplier->isDirty('province') || $supplier->isDirty('city')) {
            $this->clearGeographyCaches();
        }
        
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
        $this->clearSupplierCaches();
        $this->clearGeographyCaches();
        
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
        $this->clearSupplierCaches();
        
        Log::info('Supplier restored, cache cleared', [
            'supplier_id' => $supplier->id,
        ]);
    }

    /**
     * Handle the Supplier "force deleted" event.
     */
    public function forceDeleted(Supplier $supplier): void
    {
        $this->clearSupplierCaches();
        $this->clearGeographyCaches();
        
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
    private function clearSupplierCaches(): void
    {
        // کش‌های صفحه لیست تأمین‌کنندگان
        Cache::forget('web:suppliers:stats');
        Cache::forget('home:suppliers:top'); // صفحه اصلی
        
        // نکته: کش‌های filtered results با pattern 'suppliers:list:*' 
        // در Laravel با database driver قابل حذف گروهی نیستند
        // برای این کار نیاز به Redis + cache tags داریم
        // فعلاً TTL کوتاه (10 دقیقه) کافی است
    }

    /**
     * پاک کردن کش‌های جغرافیایی (استان‌ها و شهرها)
     */
    private function clearGeographyCaches(): void
    {
        Cache::forget('web:suppliers:provinces');
        Cache::forget('web:suppliers:cities');
    }
}
