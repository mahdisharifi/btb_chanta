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

        if (!$supplierId) {
            return;
        }

        $supplier = Supplier::withTrashed()->find($supplierId);
        $rootId = $supplier?->parent_supplier_id ?: $supplierId;

        Cache::forget("web:supplier:{$supplierId}:stats");
        Cache::forget("web:supplier:{$rootId}:stats");
    }
}
