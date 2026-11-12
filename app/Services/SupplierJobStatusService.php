<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SupplierJobStatusService
{
    /** Read attribution without instantiating queued classes or invoking their magic methods. */
    public function describe(string $payload): ?array
    {
        $data = json_decode($payload, true);
        if (!is_array($data) || !is_string($data['data']['command'] ?? null)) {
            return null;
        }
        $command = @unserialize($data['data']['command'], ['allowed_classes' => false, 'max_depth' => 32]);
        if (!is_object($command)) {
            return null;
        }
        $properties = (array) $command;
        $class = $properties['__PHP_Incomplete_Class_Name'] ?? '';
        // Laravel wraps broadcast events. Attribution belongs to the event,
        // not the outer BroadcastEvent job. Only inspect this known wrapper.
        if ($class === \Illuminate\Broadcasting\BroadcastEvent::class) {
            $event = $properties['event'] ?? null;
            if (!is_object($event)) {
                return null;
            }
            $properties = (array) $event;
            $class = $properties['__PHP_Incomplete_Class_Name'] ?? '';
            if ($class === \App\Events\ProductPricesUpdated::class
                && ($properties['supplierIds'] ?? []) === []) {
                // Legacy events include supplier attribution only in audiences
                // allowed to see supplier information. Never infer it from productId.
                $properties['supplierIds'] = array_column(
                    is_array($properties['supplierUpdates'] ?? null) ? $properties['supplierUpdates'] : [],
                    'supplier_id',
                );
            }
        }
        $ids = [];
        foreach (['supplierId', 'supplier_id', 'supplierIds', 'supplier_ids'] as $key) {
            $value = $properties[$key] ?? [];
            foreach (is_array($value) ? $value : [$value] as $id) {
                if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
        }
        if (!$ids) {
            return null;
        }
        $type = class_basename($class);
        $label = match ($type) {
            'ApplySupplierCurrencyPrices' => 'آماده‌سازی تغییر قیمت‌ها بر اساس نرخ ارز',
            'ApplySupplierCurrencyPriceChunk' => 'به‌روزرسانی قیمت‌ها بر اساس نرخ ارز',
            'RebaseSupplierCurrencyPrices' => 'آماده‌سازی تغییر ارز پایه',
            'RebaseSupplierCurrencyPriceChunk' => 'تغییر ارز پایه محصولات',
            'ApplySupplierTomanTierPrices' => 'آماده‌سازی محاسبهٔ قیمت‌ها با درصدهای تیراژ',
            'ApplySupplierTomanTierPriceChunk' => 'محاسبهٔ قیمت‌های تومانی با درصدهای تیراژ',
            'BroadcastSupplierProductPrices' => 'انتشار تغییر قیمت محصولات',
            'ProductPricesUpdated' => 'ارسال قیمت‌های به‌روز به صفحات محصولات',
            'InvalidateSupplierBranchPublicCaches' => 'تازه‌سازی نمایش عمومی شعبه‌ها',
            default => 'سایر عملیات شعبه',
        };
        $description = match ($type) {
            'ApplySupplierCurrencyPrices', 'ApplySupplierCurrencyPriceChunk' =>
                'محاسبهٔ قیمت محصولات بر اساس نرخ ارز و تنظیمات ذخیره‌شدهٔ شما؛ شامل دریافت خودکار نرخ در صورت فعال بودن آن.',
            'RebaseSupplierCurrencyPrices', 'RebaseSupplierCurrencyPriceChunk' =>
                'تطبیق مبنای قیمت محصولات با ارز پایه‌ای که انتخاب کرده‌اید.',
            'ApplySupplierTomanTierPrices', 'ApplySupplierTomanTierPriceChunk' =>
                'محاسبهٔ دوبارهٔ قیمت‌های تومانی بر اساس درصدهای تیراژ ذخیره‌شدهٔ شما.',
            'BroadcastSupplierProductPrices', 'ProductPricesUpdated' =>
                'به‌روزرسانی نمایش قیمت محصولات پس از پردازش تغییرات قیمت.',
            'InvalidateSupplierBranchPublicCaches' =>
                'تازه‌سازی اطلاعات نمایشی شعبه‌ها پس از تغییرات محصولات یا قیمت‌ها.',
            default => 'پردازش عملیات مرتبط با این شعبه؛ جزئیات نوع درخواست در اطلاعات این عملیات ثبت نشده است.',
        };

        return ['supplier_ids' => array_values(array_unique($ids)), 'type' => $type,
            'label' => $label, 'description' => $description];
    }

    /** Counts queue records, not products. Reserved records remain unfinished until deleted. */
    public function summary(int $supplierId): array
    {
        $groups = [];
        $available = false;
        $seen = [];
        foreach (config('queue.connections', []) as $connection) {
            if (($connection['driver'] ?? null) !== 'database') {
                continue;
            }
            $table = $connection['table'] ?? 'jobs';
            $database = $connection['connection'] ?? config('database.default');
            $identity = $database.':'.$table;
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $db = DB::connection($database);
            if (!$db->getSchemaBuilder()->hasTable($table)) {
                continue;
            }
            $available = true;
            foreach ($db->table($table)->where(function ($query) {
                $query->where('payload', 'like', '%supplierId%')->orWhere('payload', 'like', '%supplier_id%');
            })->select(['id', 'payload', 'reserved_at', 'available_at'])->lazyById(200) as $row) {
                $this->count($groups, $row->payload, $supplierId,
                    $row->reserved_at !== null ? 'processing' : ($row->available_at > time() ? 'delayed' : 'waiting'));
            }
        }
        $failed = config('queue.failed', []);
        if (in_array($failed['driver'] ?? '', ['database', 'database-uuids'], true)) {
            $db = DB::connection($failed['database'] ?? config('database.default'));
            $table = $failed['table'] ?? 'failed_jobs';
            if ($db->getSchemaBuilder()->hasTable($table)) {
                foreach ($db->table($table)->where(function ($query) {
                    $query->where('payload', 'like', '%supplierId%')->orWhere('payload', 'like', '%supplier_id%');
                })->select(['id', 'payload'])->lazyById(200) as $row) {
                    $this->count($groups, $row->payload, $supplierId, 'failed');
                }
            }
        }
        ksort($groups);

        return ['available' => $available, 'groups' => array_values($groups),
            'remaining' => array_sum(array_column($groups, 'remaining')),
            'failed' => array_sum(array_column($groups, 'failed'))];
    }

    private function count(array &$groups, string $payload, int $supplierId, string $state): void
    {
        $job = $this->describe($payload);
        if (!$job || !in_array($supplierId, $job['supplier_ids'], true)) {
            return;
        }
        $type = $job['type'];
        $groups[$type] ??= ['type' => $type, 'label' => $job['label'], 'description' => $job['description'], 'waiting' => 0,
            'processing' => 0, 'delayed' => 0, 'failed' => 0, 'remaining' => 0];
        $groups[$type][$state]++;
        if ($state !== 'failed') {
            $groups[$type]['remaining']++;
        }
    }
}
