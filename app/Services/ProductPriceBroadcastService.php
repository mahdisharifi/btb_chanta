<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\ProductPricesUpdated;
use App\Models\PriceVisibilitySetting;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Enums\ProductStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductPriceBroadcastService
{
    public function broadcast(int $productSupplierId, bool $revisionAlreadyIncremented = false, ?int $supplierId = null): void
    {
        $this->broadcastMany(
            [$productSupplierId],
            $revisionAlreadyIncremented,
            $supplierId === null ? [] : [$supplierId],
        );
    }

    /** Rebuild one product payload for a whole batch of changed supplier rows. */
    public function broadcastMany(
        array $productSupplierIds,
        bool $revisionAlreadyIncremented = false,
        array $supplierIds = [],
        bool $throwOnFailure = false,
    ): void
    {
        $productSupplierIds = array_values(array_unique(array_map('intval', $productSupplierIds)));
        if ($productSupplierIds === []) {
            return;
        }

        try {
            $this->invalidatePriceCaches($productSupplierIds, collect(), $supplierIds, $throwOnFailure);

            $changedProductSuppliers = ProductSupplier::withTrashed()
                ->with(['product', 'supplier'])
                ->whereIn('id', $productSupplierIds)
                ->get();

            $this->invalidateSupplierStatsCaches(
                array_values(array_diff(
                    $changedProductSuppliers->pluck('supplier_id')->map(fn ($id) => (int) $id)->unique()->all(),
                    array_map('intval', $supplierIds),
                )),
                $throwOnFailure,
            );

            foreach ($changedProductSuppliers->groupBy('product_id') as $productId => $changedForProduct) {
                $this->broadcastProduct((int) $productId, $changedForProduct, $revisionAlreadyIncremented, $throwOnFailure);
            }
        } catch (Throwable $exception) {
            Log::warning('Preparing updated product prices for broadcast failed.', [
                'product_supplier_ids' => $productSupplierIds,
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 250),
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }
        }
    }

    private function broadcastProduct(
        int $productId,
        Collection $changedProductSuppliers,
        bool $revisionAlreadyIncremented,
        bool $throwOnFailure,
    ): void
    {
        $product = $changedProductSuppliers->first()?->product;
        if (!$product?->is_active || $product->status !== ProductStatus::ACTIVE) {
            return;
        }

        $priceRevision = $revisionAlreadyIncremented
            ? DB::table('products')->where('id', $productId)->value('price_revision')
            : DB::transaction(function () use ($productId): ?int {
            $lockedProduct = DB::table('products')
                ->where('id', $productId)
                ->lockForUpdate()
                ->first(['price_revision']);

            if (!$lockedProduct) {
                return null;
            }

            $nextRevision = ((int) $lockedProduct->price_revision) + 1;
            DB::table('products')->where('id', $productId)->update(['price_revision' => $nextRevision]);

            return $nextRevision;
        });

        if ($priceRevision === null) {
            return;
        }

        $today = now()->startOfDay();
        $productSuppliers = ProductSupplier::query()
            ->with([
                'supplier',
                'prices' => fn ($query) => $query
                    ->activeAndValid()
                    ->with('priceType:id,code'),
            ])
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->verifiedSupplier()
            ->get();
        $productPriceRanges = $this->productPriceRanges($productId, $today);

        $productSuppliersById = $productSuppliers->keyBy('id');
        $requiresPageRefresh = $changedProductSuppliers->contains(
            fn (ProductSupplier $changedProductSupplier): bool => !$productSuppliersById->has($changedProductSupplier->id)
        );

        foreach (UserRole::cases() as $role) {
            $settings = PriceVisibilitySetting::getForUserRole($role);
            if (!$settings?->canSeeWholesalePrice()) {
                continue;
            }

            $allowedTypes = $settings->getAllowedPriceTypes();
            $roleRanges = $productPriceRanges->filter(function ($range, string $type) use ($allowedTypes): bool {
                return $allowedTypes === null || in_array($type, $allowedTypes, true);
            });
            $visibleCount = (int) $roleRanges->sum('price_count');
            $supplierUpdates = [];

            if ($settings->canSeeSupplierInfo()) {
                foreach ($changedProductSuppliers as $changedProductSupplier) {
                    $activeSupplier = $productSuppliersById->get($changedProductSupplier->id);
                    if (!$activeSupplier) {
                        continue;
                    }

                    $currentPrices = $activeSupplier?->prices ?? collect();
                    $rolePrices = $currentPrices->filter(function (ProductPrice $price) use ($allowedTypes): bool {
                        $type = $price->priceType?->code ?? 'cash';

                        return $allowedTypes === null || in_array($type, $allowedTypes, true);
                    });
                    $warrantyRows = $currentPrices
                        ->filter(fn (ProductPrice $price) => $price->priceType?->code === 'warranty')
                        ->sortBy('price')
                        ->unique('tier_index')
                        ->values();
                    $supplierPrices = $rolePrices->pluck('price');

                    $supplierUpdates[] = [
                        'supplier_available' => $activeSupplier !== null,
                        'product_supplier_id' => (int) $changedProductSupplier->id,
                        'supplier_id' => (int) $changedProductSupplier->supplier_id,
                        'prices' => $activeSupplier
                            ? $this->pricePayload($rolePrices, $activeSupplier, $warrantyRows, $allowedTypes)
                            : [],
                        'supplier_lowest' => $supplierPrices->isEmpty() ? null : (int) $supplierPrices->min(),
                        'supplier_highest' => $supplierPrices->isEmpty() ? null : (int) $supplierPrices->max(),
                        'supplier_tiers_count' => $rolePrices->pluck('tier_index')->unique()->count(),
                    ];
                }
            }

            $productMatrixPrices = $this->productMatrixPayload($productSuppliers, $allowedTypes);

            try {
                ProductPricesUpdated::dispatch(
                    productId: $productId,
                    priceRevision: $priceRevision,
                    audienceRole: $role->value,
                    supplierUpdates: $supplierUpdates,
                    productPrices: $productMatrixPrices,
                    productTiersCount: collect($productMatrixPrices)->pluck('tier_index')->unique()->count(),
                    productLowest: $roleRanges->isEmpty() ? null : (int) $roleRanges->min('lowest'),
                    productHighest: $roleRanges->isEmpty() ? null : (int) $roleRanges->max('highest'),
                    productAverage: $visibleCount > 0 ? (int) round($roleRanges->sum('total') / $visibleCount) : null,
                    updatedAt: now()->toIso8601String(),
                    requiresPageRefresh: $requiresPageRefresh,
                );
            } catch (Throwable $exception) {
                Log::warning('Broadcasting updated product prices failed for audience role.', [
                    'product_id' => $productId,
                    'changed_product_supplier_ids' => $changedProductSuppliers->pluck('id')->all(),
                    'audience_role' => $role->value,
                    'exception' => $exception::class,
                ]);

                if ($throwOnFailure) {
                    throw $exception;
                }
            }
        }
    }

    /** @return array<int, array{type: string, tier_index: int, check_period_id: ?int, price: int, warranty_price: ?int}> */
    private function productMatrixPayload(Collection $productSuppliers, ?array $allowedTypes): array
    {
        $groups = [];

        foreach ($productSuppliers as $productSupplier) {
            $currentPrices = $productSupplier->prices;
            $warrantyRows = $currentPrices
                ->filter(fn (ProductPrice $price) => $price->priceType?->code === 'warranty')
                ->sortBy('price')
                ->unique('tier_index')
                ->values();
            $rolePrices = $currentPrices->filter(function (ProductPrice $price) use ($allowedTypes): bool {
                $type = $price->priceType?->code ?? 'cash';

                return $allowedTypes === null || in_array($type, $allowedTypes, true);
            });

            foreach ($this->pricePayload($rolePrices, $productSupplier, $warrantyRows, $allowedTypes) as $price) {
                if (!in_array($price['type'], ['cash', 'check'], true)) {
                    continue;
                }

                $key = implode(':', [
                    $price['type'],
                    $price['tier_index'],
                    $price['check_period_id'] ?? 0,
                ]);

                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'type' => $price['type'],
                        'tier_index' => $price['tier_index'],
                        'check_period_id' => $price['check_period_id'],
                        'price' => $price['price'],
                        'warranty_price' => $price['warranty_price'],
                    ];
                    continue;
                }

                $groups[$key]['price'] = min($groups[$key]['price'], $price['price']);
                if ($price['warranty_price'] !== null) {
                    $groups[$key]['warranty_price'] = $groups[$key]['warranty_price'] === null
                        ? $price['warranty_price']
                        : min($groups[$key]['warranty_price'], $price['warranty_price']);
                }
            }
        }

        return array_values($groups);
    }

    private function invalidatePriceCaches(
        array $productSupplierIds,
        Collection $changedProductSuppliers,
        array $supplierIds,
        bool $throwOnFailure,
    ): void
    {
        try {
            Cache::add('products.prices.version', 0, now()->addYears(10));
            Cache::increment('products.prices.version');

            $this->invalidateSupplierStatsCaches($supplierIds, $throwOnFailure);

            // Clear pre-versioned cache entries from earlier application versions.
            Cache::forget('products.price_range');
            Cache::forget('home:products:featured:priced-first-v2');
            Cache::forget('home:products:latest:priced-first-v2');
            Cache::forget('home:products:popular:priced-first-v2');
        } catch (Throwable $exception) {
            Log::warning('Invalidating price-dependent caches failed.', [
                'product_supplier_ids' => $productSupplierIds,
                'supplier_ids' => collect($supplierIds)->map(fn ($id) => (int) $id)->unique()->values()->all(),
                'exception' => $exception::class,
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }
        }
    }

    /** @param array<int, int> $supplierIds */
    private function invalidateSupplierStatsCaches(array $supplierIds, bool $throwOnFailure): void
    {
        try {
            foreach (array_values(array_unique(array_map('intval', $supplierIds))) as $supplierId) {
                $versionKey = "web:supplier:{$supplierId}:stats:version";
                Cache::add($versionKey, 0, now()->addYears(10));
                Cache::increment($versionKey);
                Cache::forget("web:supplier:{$supplierId}:stats");
            }
        } catch (Throwable $exception) {
            Log::warning('Invalidating supplier price statistics cache failed.', [
                'supplier_ids' => array_values(array_unique(array_map('intval', $supplierIds))),
                'exception' => $exception::class,
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }
        }
    }

    private function productPriceRanges(int $productId, \Carbon\CarbonInterface $today): Collection
    {
        $uniquePrices = ProductPrice::query()
            ->leftJoin('price_types as range_price_types', 'range_price_types.id', '=', 'product_prices.price_type_id')
            ->where('product_prices.product_id', $productId)
            ->where('product_prices.is_active', true)
            ->where(fn ($query) => $query->whereNull('product_prices.valid_from')->orWhere('product_prices.valid_from', '<=', $today))
            ->where(fn ($query) => $query->whereNull('product_prices.valid_until')->orWhere('product_prices.valid_until', '>=', $today))
            ->whereHas('productSupplier', fn ($query) => $query->where('is_active', true)->verifiedSupplier())
            ->selectRaw("product_prices.product_supplier_id, COALESCE(range_price_types.code, 'cash') as type, product_prices.tier_index, product_prices.check_period_id, MIN(product_prices.price) as price")
            ->groupBy('product_prices.product_supplier_id', 'product_prices.tier_index', 'product_prices.check_period_id')
            ->groupByRaw("COALESCE(range_price_types.code, 'cash')");

        return DB::query()
            ->fromSub($uniquePrices, 'unique_product_prices')
            ->selectRaw('type, MIN(price) as lowest, MAX(price) as highest, SUM(price) as total, COUNT(*) as price_count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');
    }

    /** @return array<int, array{type: string, tier_index: int, check_period_id: ?int, price: int, warranty_price: ?int}> */
    private function pricePayload(Collection $prices, ProductSupplier $productSupplier, Collection $warrantyRows, ?array $allowedTypes): array
    {
        $canSeeWarranty = $allowedTypes === null || in_array('warranty', $allowedTypes, true);

        $prices = $prices
            ->groupBy(fn (ProductPrice $price) => implode(':', [
                $price->priceType?->code ?? 'cash',
                $price->tier_index,
                $price->check_period_id ?? 0,
            ]))
            ->map(fn (Collection $rows) => $rows->sortBy('price')->first())
            ->values();

        return $prices
            ->map(function (ProductPrice $price) use ($productSupplier, $warrantyRows, $canSeeWarranty): array {
                $type = $price->priceType?->code ?? 'cash';
                $warrantyPrice = null;

                if ($canSeeWarranty && $productSupplier->has_warranty && in_array($type, ['cash', 'check'], true)) {
                    if ($productSupplier->warranty_type === 'custom') {
                        $warrantyAddon = $warrantyRows->firstWhere('tier_index', $price->tier_index);
                        $warrantyPrice = $warrantyAddon ? (int) $price->price + (int) $warrantyAddon->price : null;
                    } else {
                        $warrantyPrice = (int) round($productSupplier->calculatePriceWithWarranty((int) $price->price));
                    }
                }

                return [
                    'type' => $type,
                    'tier_index' => (int) $price->tier_index,
                    'check_period_id' => $price->check_period_id === null ? null : (int) $price->check_period_id,
                    'price' => (int) $price->price,
                    'warranty_price' => $warrantyPrice,
                ];
            })
            ->values()
            ->all();
    }
}
