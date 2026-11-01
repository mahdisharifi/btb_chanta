<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SupplierBranchPublicCacheInvalidator
{
    public const GENERATION_KEY = 'public:branch-catalog:generation';

    public function invalidate(?int $supplierId = null): void
    {
        $this->invalidateMany($supplierId === null ? [] : [$supplierId]);
    }

    /** @param iterable<int, int|string> $supplierIds */
    public function invalidateMany(iterable $supplierIds): void
    {
        $supplierIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($supplierIds) ? $supplierIds : iterator_to_array($supplierIds)),
            static fn (int $supplierId): bool => $supplierId > 0,
        )));

        Cache::forever(self::GENERATION_KEY, (string) Str::uuid());

        foreach ([
            'home:products:featured:priced-first-v2',
            'home:products:latest:priced-first-v2',
            'home:products:popular:priced-first-v2',
            'home:suppliers:top',
            'home:stats:site',
            'products.price_range',
            'web:suppliers:stats',
        ] as $key) {
            Cache::forget($key);
        }

        $suppliersById = Supplier::withTrashed()
            ->whereIn('id', $supplierIds)
            ->get(['id', 'parent_supplier_id'])
            ->keyBy('id');

        foreach ($supplierIds as $supplierId) {
            $supplier = $suppliersById->get($supplierId);
            $rootId = $supplier?->parent_supplier_id ?: $supplierId;

            Cache::forget("web:supplier:{$supplierId}:stats");
            Cache::forget("web:supplier:{$rootId}:stats");
        }
    }
}
