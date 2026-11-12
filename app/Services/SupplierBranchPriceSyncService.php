<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupplierBranchPriceSyncService
{
    private const PRICE_UPSERT_BATCH_SIZE = 25;
    private const SOURCE_METADATA_FIELDS = [
        'percentage_override',
        'calculation_mode',
        'warranty_type',
        'warranty_duration',
        'warranty_price',
        'description',
        'valid_from',
        'valid_until',
    ];

    public function usesPrimaryPricing(Product $product, Supplier $branch): bool
    {
        if (!$branch->usesPrimaryPricing()) {
            return false;
        }

        $primary = $branch->parentSupplier;

        if (!$primary || (int) $primary->user_id !== (int) $branch->user_id) {
            return false;
        }
        $reference = $this->findPrimaryReferenceProduct((int) ($product->family_product_id ?: $product->id), $primary);

        return $reference && $this->primaryPrices($reference, $primary)->isNotEmpty();
    }

    /** Apply the primary branch price matrix to one accepted branch product. */
    public function syncBranchProduct(
        Product $branchProduct,
        Supplier $branch,
        ?float $percentage = null,
        array $preservedPriceStatesBySourceId = [],
        bool $broadcastCanonicalProduct = true
    ): void
    {
        abort_unless(
            $branch->parent_supplier_id !== null
                && $branch->is_active
                && (int) $branch->user_id === (int) $branch->parentSupplier?->user_id,
            403,
            'قیمت‌گذاری درصدی فقط برای پیشنهاد فروش شعبهٔ مجاز در دسترس است.'
        );

        $offer = ProductSupplier::query()
            ->where('product_id', $branchProduct->id)
            ->where('supplier_id', $branch->id)
            ->firstOrFail();

        if (!$branch->usesPrimaryPricing()) {
            return;
        }

        $percentage ??= (float) ($offer->branch_price_percentage ?? 0);
        $root = $branch->parentSupplier;
        if (!$root || $root->parent_supplier_id !== null || (int) $root->user_id !== (int) $branch->user_id) {
            abort(403, 'شعبهٔ اصلی فروشگاه پیدا نشد.');
        }

        $familyId = (int) ($branchProduct->family_product_id ?: $branchProduct->id);
        $referenceProduct = $this->findPrimaryReferenceProduct($familyId, $root);
        if (!$referenceProduct) {
            // If the original product was later removed, keep the last independent
            // branch price snapshot instead of erasing the branch's catalog.
            return;
        }

        $sourcePrices = $this->primaryPrices($referenceProduct, $root);

        $sourceIds = [];
        $syncedBranchPriceIds = [];
        foreach ($sourcePrices as $sourcePrice) {
            $sourceIds[] = (int) $sourcePrice->id;
            $sourceId = (int) $sourcePrice->id;
            $preservedState = $preservedPriceStatesBySourceId[$sourceId] ?? [];
            $this->syncDerivedPrice(
                $sourcePrice,
                $branchProduct,
                $offer,
                $percentage,
                array_key_exists('is_active', $preservedState) ? (bool) $preservedState['is_active'] : null,
                array_key_exists('price', $preservedState) ? (int) $preservedState['price'] : null,
                true,
                $syncedBranchPriceIds
            );
        }

        $stalePrices = DB::table('product_prices')
            ->where('product_supplier_id', $offer->id)
            ->whereNotNull('branch_source_price_id');

        if ($sourceIds) {
            $stalePrices->whereNotIn('branch_source_price_id', $sourceIds);
        }

        $stalePrices->delete();

        app(CatalogSnapshotRevisionService::class)->invalidateSuppliers([
            (int) $root->id,
            (int) $branch->id,
        ]);

        DB::afterCommit(function () use ($branch, $offer, $broadcastCanonicalProduct, $referenceProduct, $root): void {
            $this->runPostCommitRefreshes(
                [(int) $root->id, (int) $branch->id],
                [(int) $branch->id => [(int) $offer->id]],
                $broadcastCanonicalProduct ? $referenceProduct : null,
                $broadcastCanonicalProduct ? $root : null,
            );
        });
    }

    public function primaryReferencePrice(Product $branchProduct, Supplier $branch): ?int
    {
        $root = $branch->parentSupplier;
        if (!$root || $branch->parent_supplier_id === null) {
            return null;
        }

        $familyId = (int) ($branchProduct->family_product_id ?: $branchProduct->id);
        $referenceProduct = $this->findPrimaryReferenceProduct($familyId, $root);
        if (!$referenceProduct) {
            return null;
        }

        return $this->primaryPrices($referenceProduct, $root)
            ->filter(fn (ProductPrice $price): bool => $price->is_active && $price->isValid())
            ->min('price');
    }

    /** Sync only the changed primary matrix row to each accepted child copy. */
    public function syncBranchesForPrimaryPrice(ProductPrice $price): void
    {
        $this->syncBranchesForPrimaryPrices([$price]);
    }

    /** Sync changed primary matrix rows in groups so bulk pricing stays bounded. */
    public function syncBranchesForPrimaryPrices(iterable $prices): void
    {
        $groups = collect($prices)
            ->filter(fn ($price): bool => $price instanceof ProductPrice)
            ->groupBy(fn (ProductPrice $price): string => implode(':', [
                (int) $price->product_id,
                $price->product_supplier_id === null ? 'null' : (int) $price->product_supplier_id,
            ]));

        foreach ($groups as $sourcePrices) {
            /** @var ProductPrice|null $firstPrice */
            $firstPrice = $sourcePrices->first();
            if (!$firstPrice) {
                continue;
            }

            $context = $this->primaryMatrixContext($firstPrice);
            if (!$context) {
                continue;
            }

            [, $primary, $referenceProduct] = $context;
            $sourcePriceIds = $sourcePrices
                ->map(fn (ProductPrice $price): int => (int) $price->id)
                ->unique()
                ->values()
                ->all();
            $offersToRefresh = [];
            $branchIdsToInvalidate = [];

            // Keep one product's branch matrix atomic. If a later branch write
            // fails, the queue may retry this group without exposing a partial
            // set of prices to readers.
            DB::transaction(function () use (
                $sourcePrices,
                $sourcePriceIds,
                $primary,
                $referenceProduct,
                &$offersToRefresh,
                &$branchIdsToInvalidate,
            ): void {
                // Serialize FX fan-out with root and branch price writers.
                // All of them acquire the primary supplier row first.
                $lockedPrimary = Supplier::query()->lockForUpdate()->find((int) $primary->id);
                if (!$lockedPrimary || $lockedPrimary->parent_supplier_id !== null) {
                    return;
                }

                $sourceOffer = ProductSupplier::query()
                    ->where('product_id', $referenceProduct->id)
                    ->where('supplier_id', $lockedPrimary->id)
                    ->lockForUpdate()
                    ->first();

                $firstSourcePrice = $sourcePrices->first();
                if ($firstSourcePrice?->product_supplier_id !== null && !$sourceOffer) {
                    return;
                }
                $sourcePriceQuery = ProductPrice::query()
                    ->whereIn('id', $sourcePriceIds)
                    ->where('product_id', $referenceProduct->id);
                if ($firstSourcePrice?->product_supplier_id === null) {
                    $sourcePriceQuery->whereNull('product_supplier_id');
                } else {
                    $sourcePriceQuery->where('product_supplier_id', (int) $firstSourcePrice->product_supplier_id);
                }
                $freshSourcePrices = $sourcePriceQuery->orderBy('id')->lockForUpdate()->get();
                if ($freshSourcePrices->isEmpty()) {
                    return;
                }

                $branches = $this->activeBranches($lockedPrimary, lockForUpdate: true);
                if ($branches->isEmpty()) {
                    return;
                }

                $branchOffers = $this->branchOffers(
                    (int) ($referenceProduct->family_product_id ?: $referenceProduct->id), $branches,
                );

                if ($branchOffers->isEmpty()) {
                    return;
                }

                $offerIds = $branchOffers->modelKeys();
                $existingPrices = ProductPrice::query()
                    ->whereIn('product_supplier_id', $offerIds)
                    ->lockForUpdate()
                    ->get();
                $existingPricesBySource = [];
                $existingPricesByCell = [];
                foreach ($existingPrices as $existingPrice) {
                    $offerId = (int) $existingPrice->product_supplier_id;
                    if ($existingPrice->branch_source_price_id !== null) {
                        $existingPricesBySource[implode(':', [$offerId, (int) $existingPrice->branch_source_price_id])] = $existingPrice;
                    }

                    $cellKey = implode(':', [
                        $offerId,
                        $existingPrice->price_type_id === null ? 'null' : (int) $existingPrice->price_type_id,
                        (int) $existingPrice->tier_index,
                        (int) ($existingPrice->check_period_id ?? 0),
                    ]);
                    $existingPricesByCell[$cellKey] ??= $existingPrice;
                }

                $rowsToUpsert = [];
                $timestamp = now();
                foreach ($branchOffers as $offer) {
                    $copy = $offer->product;
                    $branch = $branches->firstWhere('id', $offer->supplier_id);
                    if (!$branch || !$offer) {
                        continue;
                    }

                    $offersToRefresh[(int) $branch->id][$offer->id] = (int) $offer->id;
                    $branchIdsToInvalidate[(int) $branch->id] = (int) $branch->id;

                    foreach ($freshSourcePrices as $sourcePrice) {
                        $sourceId = (int) $sourcePrice->id;
                        $existingKey = implode(':', [(int) $offer->id, $sourceId]);
                        $existingPrice = $existingPricesBySource[$existingKey] ?? null;
                        $cellKey = implode(':', [
                            (int) $offer->id,
                            $sourcePrice->price_type_id === null ? 'null' : (int) $sourcePrice->price_type_id,
                            (int) $sourcePrice->tier_index,
                            (int) ($sourcePrice->check_period_id ?? 0),
                        ]);

                        if (!$existingPrice && isset($existingPricesByCell[$cellKey])) {
                            throw new \LogicException(
                                'Branch price matrix identity does not match its primary source; a full matrix rebase is required.'
                            );
                        }

                        $price = $this->adjustedPrice(
                            (int) $sourcePrice->price,
                            (float) ($offer->branch_price_percentage ?? 0),
                        );
                        $isActive = $existingPrice
                            ? (bool) $existingPrice->is_active
                            : (bool) $sourcePrice->is_active;
                        $validFrom = $sourcePrice->valid_from?->format('Y-m-d');
                        $validUntil = $sourcePrice->valid_until?->format('Y-m-d');
                        $sourceAttributes = $sourcePrice->getAttributes();
                        $existingAttributes = $existingPrice?->getAttributes() ?? [];
                        $metadataChanged = !$existingPrice;
                        foreach (self::SOURCE_METADATA_FIELDS as $field) {
                            if (!$this->sameStoredValue(
                                $existingAttributes[$field] ?? null,
                                $sourceAttributes[$field] ?? null,
                            )) {
                                $metadataChanged = true;
                                break;
                            }
                        }

                        if ($existingPrice
                            && (int) $existingPrice->price === $price
                            && (bool) $existingPrice->is_active === $isActive
                            && !$metadataChanged) {
                            continue;
                        }

                        $attributes = $sourceAttributes;
                        unset($attributes['normalized_check_period_id']);
                        $attributes['id'] = $existingPrice ? (int) $existingPrice->id : null;
                        $attributes['product_id'] = (int) $copy->id;
                        $attributes['product_supplier_id'] = (int) $offer->id;
                        $attributes['branch_source_price_id'] = $sourceId;
                        $attributes['price'] = $price;
                        $attributes['is_active'] = $isActive;
                        $attributes['valid_from'] = $validFrom;
                        $attributes['valid_until'] = $validUntil;
                        $attributes['updated_at'] = $timestamp;
                        if ($existingPrice) {
                            $attributes['created_at'] = $existingAttributes['created_at'] ?? null;
                        } else {
                            $attributes['created_at'] = $timestamp;
                        }

                        $rowsToUpsert[] = $attributes;
                    }
                }

                if ($rowsToUpsert !== []) {
                    foreach (array_chunk($rowsToUpsert, self::PRICE_UPSERT_BATCH_SIZE) as $rows) {
                        ProductPrice::query()->upsert(
                            $rows,
                            ['id'],
                            [
                                'price',
                                'is_active',
                                ...self::SOURCE_METADATA_FIELDS,
                                'updated_at',
                            ],
                        );
                    }
                }

                app(CatalogSnapshotRevisionService::class)->invalidateSuppliers([
                    (int) $lockedPrimary->id,
                    ...array_values($branchIdsToInvalidate),
                ]);
            }, 3);

            $supplierIdsToInvalidate = array_values(array_unique([
                (int) $primary->id,
                ...array_values($branchIdsToInvalidate),
            ]));

            // Run even on an unchanged retry: the previous attempt may have
            // committed prices but failed before invalidating or broadcasting.
            DB::afterCommit(function () use ($supplierIdsToInvalidate, $offersToRefresh): void {
                $this->runPostCommitRefreshes($supplierIdsToInvalidate, $offersToRefresh);
            });
        }
    }

    /** Replace a primary product's full matrix on every accepted branch copy. */
    public function syncPrimaryMatrix(Product $primaryProduct, Supplier $primary, iterable $sourcePrices): void
    {
        if ($primary->parent_supplier_id !== null) {
            return;
        }

        $affectedOffers = [];
        DB::transaction(function () use ($primaryProduct, $primary, $sourcePrices, &$affectedOffers): void {
            $primary = Supplier::query()->whereKey($primary->id)->lockForUpdate()->firstOrFail();
            if ($primary->parent_supplier_id !== null) {
                return;
            }
            $familyId = (int) ($primaryProduct->family_product_id ?: $primaryProduct->id);
            $branches = $this->activeBranches($primary, lockForUpdate: true);
            if ($branches->isEmpty()) {
                return;
            }

            $sourcePrices = collect($sourcePrices)
                ->filter(fn ($price): bool => $price instanceof ProductPrice
                    && (int) $price->product_id === (int) $primaryProduct->id)
                ->values();
            $sourceIds = $sourcePrices->map(fn (ProductPrice $price): int => (int) $price->id)->all();
            $branchOffers = $this->branchOffers($familyId, $branches);
            $offerIds = $branchOffers->modelKeys();
            $existingPrices = $offerIds === []
                ? collect()
                : ProductPrice::query()->whereIn('product_supplier_id', $offerIds)->lockForUpdate()->get();
            $existingPricesBySource = [];
            $existingPricesByCell = [];
            foreach ($existingPrices as $existingPrice) {
                $offerId = (int) $existingPrice->product_supplier_id;
                if ($existingPrice->branch_source_price_id !== null) {
                    $existingPricesBySource[implode(':', [$offerId, (int) $existingPrice->branch_source_price_id])] = $existingPrice;
                }

                $cellKey = $this->matrixCellKey($existingPrice, $offerId);
                $existingPricesByCell[$cellKey][] = $existingPrice;
            }

            $usedBranchPriceIds = [];
            $rowsToUpsert = [];
            $timestamp = now();
            foreach ($branchOffers as $offer) {
                $copy = $offer->product;
                $branch = $branches->firstWhere('id', $offer->supplier_id);
                if (!$branch || !$offer) {
                    continue;
                }

                foreach ($sourcePrices as $sourcePrice) {
                    $offerId = (int) $offer->id;
                    $sourceId = (int) $sourcePrice->id;
                    $sourceKey = implode(':', [$offerId, $sourceId]);
                    $existingPrice = $existingPricesBySource[$sourceKey] ?? null;
                    if ($existingPrice && isset($usedBranchPriceIds[$offerId][(int) $existingPrice->id])) {
                        $existingPrice = null;
                    }

                    if (!$existingPrice) {
                        foreach ($existingPricesByCell[$this->matrixCellKey($sourcePrice, $offerId)] ?? [] as $candidate) {
                            if (!isset($usedBranchPriceIds[$offerId][(int) $candidate->id])) {
                                $existingPrice = $candidate;
                                break;
                            }
                        }
                    }

                    $sourceAttributes = $sourcePrice->getAttributes();
                    unset($sourceAttributes['normalized_check_period_id']);
                    $existingAttributes = $existingPrice?->getAttributes() ?? [];
                    $attributes = $sourceAttributes;
                    $attributes['id'] = $existingPrice ? (int) $existingPrice->id : null;
                    $attributes['product_id'] = (int) $copy->id;
                    $attributes['product_supplier_id'] = $offerId;
                    $attributes['branch_source_price_id'] = $sourceId;
                    $attributes['price'] = $this->adjustedPrice(
                        (int) $sourcePrice->price,
                        (float) ($offer->branch_price_percentage ?? 0),
                    );
                    $attributes['is_active'] = $existingPrice
                        ? (bool) $existingPrice->is_active
                        : (bool) $sourcePrice->is_active;
                    $attributes['valid_from'] = $sourcePrice->valid_from?->format('Y-m-d');
                    $attributes['valid_until'] = $sourcePrice->valid_until?->format('Y-m-d');
                    $attributes['updated_at'] = $timestamp;
                    $attributes['created_at'] = $existingAttributes['created_at'] ?? $timestamp;
                    $rowsToUpsert[] = $attributes;

                    if ($existingPrice) {
                        $usedBranchPriceIds[$offerId][(int) $existingPrice->id] = true;
                    }
                }

                $affectedOffers[(int) $branch->id][$offer->id] = $offerId;
            }

            if ($rowsToUpsert !== []) {
                foreach (array_chunk($rowsToUpsert, self::PRICE_UPSERT_BATCH_SIZE) as $rows) {
                    ProductPrice::query()->upsert(
                        $rows,
                        ['id'],
                        [
                            'branch_source_price_id',
                            'price',
                            'is_active',
                            ...self::SOURCE_METADATA_FIELDS,
                            'updated_at',
                        ],
                    );
                }
            }

            $stalePrices = DB::table('product_prices')
                ->whereIn('product_supplier_id', $offerIds)
                ->whereNotNull('branch_source_price_id');
            if ($sourceIds !== []) {
                $stalePrices->whereNotIn('branch_source_price_id', $sourceIds);
            }
            $stalePrices->delete();

            app(CatalogSnapshotRevisionService::class)->invalidateSuppliers([
                (int) $primary->id,
                ...array_map('intval', array_keys($affectedOffers)),
            ]);
        }, 3);

        $supplierIdsToInvalidate = array_values(array_unique([
            (int) $primary->id,
            ...array_map('intval', array_keys($affectedOffers)),
        ]));
        DB::afterCommit(function () use ($supplierIdsToInvalidate, $affectedOffers): void {
            $this->runPostCommitRefreshes($supplierIdsToInvalidate, $affectedOffers);
        });
    }

    public function removeBranchesForDeletedPrimaryPrice(ProductPrice $price): void
    {
        $context = $this->primaryMatrixContext($price);
        if (!$context) {
            return;
        }

        [, $primary, $referenceProduct] = $context;
        $branchIds = $this->activeBranches($primary)->modelKeys();
        if ($branchIds === []) {
            return;
        }

        $offerIds = ProductSupplier::query()
            ->whereHas('product', fn ($query) => $query
                ->where('family_product_id', $referenceProduct->family_product_id ?: $referenceProduct->id))
            ->whereIn('supplier_id', $branchIds)
            ->pluck('id');

        if ($offerIds->isNotEmpty()) {
            DB::table('product_prices')
                ->whereIn('product_supplier_id', $offerIds)
                ->where('branch_source_price_id', $price->id)
                ->delete();

            $affectedOffers = ProductSupplier::query()
                ->whereIn('id', $offerIds)
                ->get(['id', 'supplier_id'])
                ->groupBy('supplier_id')
                ->map(fn (Collection $offers): array => $offers->pluck('id')->map(fn ($id) => (int) $id)->all())
                ->all();
            $this->runPostCommitRefreshes(
                array_keys($affectedOffers),
                $affectedOffers,
                refreshRevision: true,
            );
        }
    }

    private function syncDerivedPrice(
        ProductPrice $sourcePrice,
        Product $branchProduct,
        ProductSupplier $offer,
        float $percentage,
        ?bool $preservedActiveState = null,
        ?int $preservedPrice = null,
        bool $allowMatrixRebind = false,
        ?array &$syncedBranchPriceIds = null,
        ?ProductPrice $preloadedBranchPrice = null,
        bool $branchPriceWasPreloaded = false,
    ): void {
        $syncedBranchPriceIds ??= [];

        $branchPrice = $branchPriceWasPreloaded
            ? $preloadedBranchPrice
            : ProductPrice::query()
                ->where('product_supplier_id', $offer->id)
                ->where('branch_source_price_id', $sourcePrice->id)
                ->first();

        if ($branchPrice && isset($syncedBranchPriceIds[(int) $branchPrice->id])) {
            $branchPrice = null;
        }

        // A primary-branch promotion replaces the source row IDs while keeping
        // each branch's matrix rows. Rebind the existing same-cell snapshot so
        // the normalized matrix unique key is not hit by a duplicate insert.
        if ($allowMatrixRebind) {
            $branchPrice ??= ProductPrice::query()
                ->where('product_supplier_id', $offer->id)
                ->where('price_type_id', $sourcePrice->price_type_id)
                ->where('tier_index', $sourcePrice->tier_index)
                ->where('normalized_check_period_id', $sourcePrice->normalized_check_period_id ?? 0)
                ->when(
                    $syncedBranchPriceIds !== [],
                    fn ($query) => $query->whereNotIn('id', array_keys($syncedBranchPriceIds))
                )
                ->first();
        }

        $isNewDerivedPrice = $branchPrice === null;

        if (!$branchPrice) {
            $attributes = $sourcePrice->getAttributes();
            unset(
                $attributes['id'],
                $attributes['product_id'],
                $attributes['product_supplier_id'],
                $attributes['branch_source_price_id'],
                $attributes['normalized_check_period_id'],
                $attributes['created_at'],
                $attributes['updated_at']
            );
            $branchPrice = new ProductPrice();
            $branchPrice->forceFill($attributes);
        } elseif ($allowMatrixRebind) {
            // The normalized matrix key identifies the cell. Other fields such
            // as calculation mode, percentage override, warranty, description,
            // and validity are source metadata and may have changed in-place.
            // Copy those fields while preserving this branch row's active state.
            $attributes = $sourcePrice->getAttributes();
            unset(
                $attributes['id'],
                $attributes['product_id'],
                $attributes['product_supplier_id'],
                $attributes['branch_source_price_id'],
                $attributes['normalized_check_period_id'],
                $attributes['created_at'],
                $attributes['updated_at'],
                $attributes['is_active']
            );
            $branchPrice->forceFill($attributes);
        }

        $branchPrice->forceFill([
            'product_id' => $branchProduct->id,
            'product_supplier_id' => $offer->id,
            'branch_source_price_id' => $sourcePrice->id,
            // A primary switch changes the basis, but must not mutate any saved
            // branch price because a rounded percentage can shift it by one unit.
            'price' => $preservedPrice ?? $this->adjustedPrice((int) $sourcePrice->price, $percentage),
            'is_active' => $preservedActiveState
                ?? ($isNewDerivedPrice ? (bool) $sourcePrice->is_active : (bool) $branchPrice->is_active),
            'valid_from' => $sourcePrice->valid_from,
            'valid_until' => $sourcePrice->valid_until,
        ]);
        $branchPrice->saveQuietly();
        $syncedBranchPriceIds[(int) $branchPrice->id] = true;
    }

    /** @param array<int, int> $branchIds */
    private function invalidateBranchPublicCaches(array $branchIds): void
    {
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));
        if ($branchIds === []) {
            return;
        }

        app(SupplierBranchPublicCacheInvalidator::class)->invalidateMany($branchIds);
    }

    /** Keep committed price writes successful even when cache or queue effects fail. */
    private function runPostCommitRefreshes(
        array $supplierIds,
        array $offersBySupplier,
        ?Product $canonicalProduct = null,
        ?Supplier $canonicalSupplier = null,
        bool $refreshRevision = false,
    ): void {
        if ($refreshRevision) {
            try {
                app(CatalogSnapshotRevisionService::class)->invalidateSuppliers($supplierIds);
            } catch (Throwable $exception) {
                Log::warning('Supplier branch prices committed, but catalog revision update failed.', [
                    'supplier_ids' => $supplierIds,
                    'exception' => $exception::class,
                    'message' => mb_substr($exception->getMessage(), 0, 250),
                ]);
                $this->queueCacheInvalidationRetry($supplierIds, $exception, true);
            }
        }

        try {
            $this->invalidateBranchPublicCaches($supplierIds);
        } catch (Throwable $exception) {
            Log::warning('Supplier branch prices committed, but cache invalidation failed.', [
                'supplier_ids' => $supplierIds,
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 250),
            ]);

            $this->queueCacheInvalidationRetry($supplierIds, $exception, false);
        }

        try {
            if ($offersBySupplier !== []) {
                $this->broadcastBranchOffers($offersBySupplier);
            }
        } catch (Throwable $exception) {
            Log::warning('Supplier branch prices committed, but live refresh dispatch failed.', [
                'supplier_ids' => $supplierIds,
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 250),
            ]);
        }

        if ($canonicalProduct && $canonicalSupplier) {
            try {
                $this->broadcastCanonicalProduct($canonicalProduct, $canonicalSupplier);
            } catch (Throwable $exception) {
                Log::warning('Supplier branch prices committed, but canonical live refresh dispatch failed.', [
                    'supplier_id' => (int) $canonicalSupplier->id,
                    'product_id' => (int) $canonicalProduct->id,
                    'exception' => $exception::class,
                    'message' => mb_substr($exception->getMessage(), 0, 250),
                ]);
            }
        }
    }

    /** @param array<int, int> $supplierIds */
    private function queueCacheInvalidationRetry(array $supplierIds, Throwable $cause, bool $refreshRevisions): void
    {
        try {
            \App\Jobs\InvalidateSupplierBranchPublicCaches::dispatch($supplierIds, [], false, $refreshRevisions);
        } catch (Throwable $dispatchException) {
            Log::critical('Could not queue retry for supplier branch public cache invalidation.', [
                'supplier_ids' => $supplierIds,
                'cause' => $cause::class,
                'exception' => $dispatchException::class,
                'message' => mb_substr($dispatchException->getMessage(), 0, 250),
            ]);
        }
    }

    /** @param array<int, array<int, int>> $offersBySupplier */
    private function broadcastBranchOffers(array $offersBySupplier): void
    {
        foreach ($offersBySupplier as $supplierId => $offerIds) {
            $supplierId = (int) $supplierId;
            $offerIds = array_values(array_unique(array_map('intval', $offerIds)));
            if ($supplierId <= 0 || $offerIds === []) {
                continue;
            }

            DB::afterCommit(static function () use ($supplierId, $offerIds): void {
                \App\Jobs\BroadcastSupplierProductPrices::dispatch($supplierId, $offerIds, false);
            });
        }
    }

    private function broadcastCanonicalProduct(Product $product, Supplier $supplier): void
    {
        $offerId = ProductSupplier::query()
            ->where('product_id', $product->id)
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->value('id');

        if (!$offerId) {
            return;
        }

        DB::afterCommit(static function () use ($supplier, $offerId): void {
            // Let already-open canonical product cards refresh branch rows from
            // their normal price snapshot after an individual branch edit.
            \App\Jobs\BroadcastSupplierProductPrices::dispatch(
                (int) $supplier->id,
                [(int) $offerId],
                false,
            );
        });
    }

    private function primaryMatrixContext(ProductPrice $price): ?array
    {
        $product = Product::query()->find($price->product_id);
        if (!$product) {
            return null;
        }

        $offer = $price->product_supplier_id
            ? ProductSupplier::withTrashed()->find($price->product_supplier_id)
            : null;
        $primary = $offer?->supplier ?: $product->supplier;
        if (!$primary || $primary->parent_supplier_id !== null) {
            return null;
        }

        $familyId = (int) ($product->family_product_id ?: $product->id);
        $referenceProduct = $this->findPrimaryReferenceProduct($familyId, $primary);
        if (!$referenceProduct || (int) $referenceProduct->id !== (int) $price->product_id) {
            return null;
        }

        $sourceOffer = ProductSupplier::query()
            ->where('product_id', $referenceProduct->id)
            ->where('supplier_id', $primary->id)
            ->first();

        $belongsToPrimaryMatrix = ($sourceOffer && (int) $price->product_supplier_id === (int) $sourceOffer->id)
            || ($price->product_supplier_id === null && (int) $referenceProduct->supplier_id === (int) $primary->id);

        return $belongsToPrimaryMatrix ? [$product, $primary, $referenceProduct] : null;
    }

    private function activeBranches(Supplier $primary, bool $lockForUpdate = false): Collection
    {
        $query = Supplier::query()
            ->where('parent_supplier_id', $primary->id)
            ->where('user_id', $primary->user_id)
            ->where('is_active', true)
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()->filter(fn (Supplier $branch): bool => $branch->usesPrimaryPricing())->values();
    }

    private function branchOffers(int $familyId, Collection $branches): Collection
    {
        return ProductSupplier::query()->whereIn('supplier_id', $branches->modelKeys())
            ->whereHas('product', fn ($query) => $query->where('family_product_id', $familyId))
            ->with('product')->orderBy('id')->lockForUpdate()->get();
    }

    private function findPrimaryReferenceProduct(int $familyId, Supplier $primary): ?Product
    {
        return Product::query()
            ->where('family_product_id', $familyId)
            ->where(function ($query) use ($primary): void {
                $query->where('supplier_id', $primary->id)
                    ->orWhereHas('productSuppliers', fn ($offers) => $offers->where('supplier_id', $primary->id));
            })
            ->orderByRaw('CASE WHEN supplier_id = ? THEN 0 ELSE 1 END', [$primary->id])
            ->orderBy('id')
            ->first();
    }

    private function primaryPrices(Product $referenceProduct, Supplier $primary): Collection
    {
        $sourceOffer = ProductSupplier::query()
            ->where('product_id', $referenceProduct->id)
            ->where('supplier_id', $primary->id)
            ->first();

        return ProductPrice::query()
            ->where('product_id', $referenceProduct->id)
            ->where(function ($query) use ($sourceOffer, $referenceProduct, $primary): void {
                $query->where('product_supplier_id', $sourceOffer?->id ?? 0);
                if ((int) $referenceProduct->supplier_id === (int) $primary->id) {
                    $query->orWhereNull('product_supplier_id');
                }
            })
            ->orderBy('id')
            ->get();
    }

    private function adjustedPrice(int $price, float $percentage): int
    {
        return max(0, (int) round($price * (1 + ($percentage / 100)), 0, PHP_ROUND_HALF_UP));
    }

    private function sameStoredValue(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return (string) $left === (string) $right;
    }

    private function matrixCellKey(ProductPrice $price, int $offerId): string
    {
        return implode(':', [
            $offerId,
            $price->price_type_id === null ? 'null' : (int) $price->price_type_id,
            (int) $price->tier_index,
            (int) ($price->check_period_id ?? 0),
        ]);
    }
}
