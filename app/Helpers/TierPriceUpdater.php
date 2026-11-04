<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Supplier;
use App\Models\SupplierTierDiscount;
use App\Models\PriceType;
use App\Models\ProductPrice;
use App\Services\SupplierBranchPriceSyncService;

class TierPriceUpdater
{
    private const UPDATE_CHUNK_SIZE = 10;

    /**
     * بروزرسانی قیمت‌های نقدی تیراژها برای یک تامین‌کننده
     * 
     * @param int $supplierId
     * @param int|null $categoryId اگر مشخص باشد، فقط محصولات این دسته‌بندی بروز می‌شوند
     * @param array<int, int>|null $productSupplierIds Optional bounded batch of supplier-product IDs.
     * @return array{updated:int, errors:int, settings_changed:bool, failed_product_supplier_ids:array<int, int>}
     */
    public static function updateSupplierPrices(
        int $supplierId,
        ?int $categoryId = null,
        ?array $productSupplierIds = null,
        ?int $expectedCurrencyRevision = null,
        ?int $expectedTierPricingRevision = null,
    ): array
    {
        $updated = 0;
        $errors = 0;
        $failedProductSupplierIds = [];
        $settingsChangedDuringRun = false;

        if ($productSupplierIds !== null) {
            $productSupplierIds = array_values(array_unique(array_map('intval', $productSupplierIds)));
            if ($productSupplierIds === []) {
                return [
                    'updated' => 0,
                    'errors' => 0,
                    'settings_changed' => false,
                    'failed_product_supplier_ids' => [],
                ];
            }
        }

        try {
            $cashPriceTypeId = PriceType::where('code', 'cash')->value('id');
            if (!$cashPriceTypeId) {
                throw new \Exception('Cash price type not found');
            }
            $checkPriceTypeId = PriceType::where('code', 'check')->value('id');

            $referenceTierIndex = 1;
            $supplierSnapshot = Supplier::query()->findOrFail($supplierId);
            $expectedCurrencyRevision ??= (int) $supplierSnapshot->pricing_currency_revision;
            $expectedTierPricingRevision ??= (int) $supplierSnapshot->tier_pricing_revision;
            $lastId = 0;

            while (true) {
                $page = DB::transaction(function () use (
                    $supplierId,
                    $categoryId,
                    $cashPriceTypeId,
                    $referenceTierIndex,
                    $checkPriceTypeId,
                    $expectedCurrencyRevision,
                    $expectedTierPricingRevision,
                    $productSupplierIds,
                    $lastId,
                    &$updated,
                    &$errors,
                    &$failedProductSupplierIds,
                ): array {
                    // Lock only one bounded chunk. The parent-first order matches
                    // EditPrice and FX rebase writers, without blocking a supplier
                    // for a full-catalog recalculation.
                    $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->first();
                    if (!$supplier) {
                        throw new \Exception("Supplier not found: {$supplierId}");
                    }

                    if ($supplier->pricing_currency !== 'TOM') {
                        return ['stop' => true, 'settings_changed' => false, 'last_id' => $lastId, 'changed_offer_ids' => []];
                    }

                    if ((int) $supplier->pricing_currency_revision !== $expectedCurrencyRevision
                        || (int) $supplier->tier_pricing_revision !== $expectedTierPricingRevision) {
                        return ['stop' => true, 'settings_changed' => true, 'last_id' => $lastId, 'changed_offer_ids' => []];
                    }

                    $tierDiscounts = SupplierTierDiscount::getAllForSupplier($supplierId)->keyBy('tier_index');
                    $query = DB::table('product_supplier as ps')
                        ->join('products as p', 'ps.product_id', '=', 'p.id')
                        ->where('ps.supplier_id', $supplierId)
                        ->where('ps.id', '>', $lastId)
                        ->where('ps.price_currency', 'TOM')
                        ->where('ps.auto_calculate_tiers', true)
                        ->orderBy('ps.id')
                        ->limit(self::UPDATE_CHUNK_SIZE)
                        ->select('ps.*', 'p.category_id', 'p.quantity_tiers as product_quantity_tiers');

                    if ($productSupplierIds !== null) {
                        $query->whereIn('ps.id', $productSupplierIds);
                    }

                    if ($categoryId) {
                        $query->where('p.category_id', $categoryId);
                    }

                    $productSuppliers = $query->get();
                    if ($productSuppliers->isEmpty()) {
                        return ['stop' => true, 'settings_changed' => false, 'last_id' => $lastId, 'changed_offer_ids' => []];
                    }

                    $nextId = (int) $productSuppliers->last()->id;
                    $productSupplierIds = $productSuppliers->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $lockedProductSuppliers = DB::table('product_supplier')
                        ->whereIn('id', $productSupplierIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get(['id', 'price_currency', 'is_active', 'auto_calculate_tiers'])
                        ->keyBy('id');
                    $cashRows = DB::table('product_prices')
                        ->whereIn('product_supplier_id', $productSupplierIds)
                        ->where('price_type_id', $cashPriceTypeId)
                        ->whereNull('check_period_id')
                        ->get(['product_supplier_id', 'tier_index', 'price', 'is_active'])
                        ->groupBy('product_supplier_id');
                    $referencePrices = $cashRows
                        ->map(fn ($rows) => $rows->firstWhere('tier_index', $referenceTierIndex))
                        ->filter()
                        ->keyBy('product_supplier_id');
                    $fallbackTierIndexes = $cashRows
                        ->map(fn ($rows) => $rows->pluck('tier_index')->map(fn ($index) => (int) $index)->all())
                        ->all();
                    $oldCashPrices = $cashRows
                        ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [(int) $row->tier_index => (int) $row->price])->all())
                        ->all();
                    $categoryIds = $productSuppliers->pluck('category_id')
                        ->filter(fn ($categoryId) => $categoryId !== null)
                        ->unique()
                        ->values()
                        ->all();
                    $categoryPercentages = [];
                    if ($categoryIds !== []) {
                        DB::table('category_tier_discount')
                            ->where('supplier_id', $supplierId)
                            ->where('is_active', true)
                            ->whereIn('category_id', $categoryIds)
                            ->get(['category_id', 'tier_index', 'percentage_change'])
                            ->each(function ($setting) use (&$categoryPercentages): void {
                                $categoryPercentages[((int) $setting->category_id) . ':' . ((int) $setting->tier_index)]
                                    = (float) $setting->percentage_change;
                            });
                    }

                    $checkRowsByProduct = collect();
                    $defaultCheckPercentages = [];
                    $categoryCheckPercentages = [];
                    if ($checkPriceTypeId) {
                        $today = now()->startOfDay()->toDateString();
                        $checkRowsByProduct = DB::table('product_prices')
                            ->whereIn('product_supplier_id', $productSupplierIds)
                            ->where('price_type_id', $checkPriceTypeId)
                            ->whereNotNull('check_period_id')
                            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get([
                                'id',
                                'product_id',
                                'product_supplier_id',
                                'price_type_id',
                                'tier_index',
                                'check_period_id',
                                'price',
                                'percentage_override',
                                'calculation_mode',
                                'is_active',
                                'created_at',
                            ])
                            ->groupBy('product_supplier_id');
                        $periodIds = $checkRowsByProduct
                            ->flatten(1)
                            ->pluck('check_period_id')
                            ->map(fn ($periodId) => (int) $periodId)
                            ->unique()
                            ->values()
                            ->all();

                        if ($periodIds !== []) {
                            DB::table('supplier_check_period')
                                ->where('supplier_id', $supplierId)
                                ->where('is_active', true)
                                ->whereIn('check_period_id', $periodIds)
                                ->get(['check_period_id', 'percentage_increase'])
                                ->each(function ($setting) use (&$defaultCheckPercentages): void {
                                    $defaultCheckPercentages[(int) $setting->check_period_id]
                                        = (float) $setting->percentage_increase;
                                });

                            if ($categoryIds !== []) {
                                DB::table('category_check_period')
                                    ->where('supplier_id', $supplierId)
                                    ->where('is_active', true)
                                    ->whereIn('category_id', $categoryIds)
                                    ->whereIn('check_period_id', $periodIds)
                                    ->get(['category_id', 'check_period_id', 'percentage_increase'])
                                    ->each(function ($setting) use (&$categoryCheckPercentages): void {
                                        $categoryCheckPercentages[
                                            (int) $setting->category_id . ':' . (int) $setting->check_period_id
                                        ] = (float) $setting->percentage_increase;
                                    });
                            }
                        }
                    }

                    $upsertRows = [];
                    $checkUpsertRows = [];
                    foreach ($productSuppliers as $productSupplier) {
                        try {
                            $lockedProductSupplier = $lockedProductSuppliers->get($productSupplier->id);

                            if (!$lockedProductSupplier
                                || $lockedProductSupplier->price_currency !== 'TOM'
                                || !(bool) $lockedProductSupplier->auto_calculate_tiers) {
                                continue;
                            }

                            $referencePriceRow = $referencePrices->get($productSupplier->id);
                            $referencePrice = $referencePriceRow?->price;

                            if (!$referencePriceRow
                                || !$referencePrice
                                || !is_numeric($referencePrice)
                                || ($lockedProductSupplier->is_active && !$referencePriceRow->is_active)) {
                                continue;
                            }

                            $quantityTiers = is_array($productSupplier->product_quantity_tiers)
                                ? $productSupplier->product_quantity_tiers
                                : (json_decode((string) $productSupplier->product_quantity_tiers, true) ?: []);
                            $productTierPercentages = self::decodeJsonArray($productSupplier->tier_percentages ?? null);
                            $tierIndexes = array_keys($quantityTiers);
                            if ($tierIndexes === []) {
                                $tierIndexes = $fallbackTierIndexes[$productSupplier->id] ?? [];
                            }

                            $productRows = [];
                            $newCashPrices = [$referenceTierIndex => (int) $referencePrice];
                            foreach ($tierIndexes as $tierIndex) {
                                $tierIndex = (int) $tierIndex;
                                if ($tierIndex === $referenceTierIndex) {
                                    continue;
                                }

                                $hasProductOverride = array_key_exists($tierIndex, $productTierPercentages)
                                    || array_key_exists((string) $tierIndex, $productTierPercentages);
                                $productOverride = $productTierPercentages[$tierIndex]
                                    ?? $productTierPercentages[(string) $tierIndex]
                                    ?? null;

                                $percentage = $hasProductOverride
                                    ? (float) $productOverride
                                    : self::getEffectivePercentage(
                                        $productSupplier->category_id,
                                        $tierIndex,
                                        (float) ($tierDiscounts->get($tierIndex)?->percentage_change
                                            ?? SupplierTierDiscount::defaultPercentage($tierIndex)),
                                        $categoryPercentages
                                    );
                                $roundedPrice = PriceRoundingHelper::round(
                                    $referencePrice * (1 + ($percentage / 100)),
                                    $supplier
                                );
                                $newCashPrices[$tierIndex] = (int) $roundedPrice;

                                $productRows[] = [
                                    'product_id' => $productSupplier->product_id,
                                    'product_supplier_id' => $productSupplier->id,
                                    'price_type_id' => $cashPriceTypeId,
                                    'tier_index' => $tierIndex,
                                    'check_period_id' => null,
                                    'price' => $roundedPrice,
                                    'is_active' => (bool) $lockedProductSupplier->is_active
                                        && (bool) $referencePriceRow->is_active,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }

                            $productCheckRows = self::buildCheckPriceUpdates(
                                $checkRowsByProduct->get($productSupplier->id, collect())->all(),
                                $oldCashPrices[$productSupplier->id] ?? [],
                                $newCashPrices,
                                (int) $productSupplier->category_id,
                                $defaultCheckPercentages,
                                $categoryCheckPercentages,
                                $supplier,
                            );

                            foreach ($productRows as $row) {
                                $upsertRows[] = $row;
                            }
                            foreach ($productCheckRows as $row) {
                                $checkUpsertRows[] = $row;
                            }
                        } catch (\Exception $e) {
                            $errors++;
                            $failedProductSupplierIds[] = (int) $productSupplier->id;
                            Log::error("Error updating prices for product_supplier {$productSupplier->id}: " . $e->getMessage());
                        }
                    }

                    if ($upsertRows !== []) {
                        DB::table('product_prices')->upsert($upsertRows, [
                            'product_supplier_id',
                            'price_type_id',
                            'tier_index',
                            'normalized_check_period_id',
                        ], [
                            'price',
                            'updated_at',
                        ]);
                        $updated += count($upsertRows);
                    }

                    if ($checkUpsertRows !== []) {
                        DB::table('product_prices')->upsert($checkUpsertRows, [
                            'product_supplier_id',
                            'price_type_id',
                            'tier_index',
                            'normalized_check_period_id',
                        ], [
                            'price',
                            'calculation_mode',
                            'updated_at',
                        ]);
                        $updated += count($checkUpsertRows);
                    }

                    if ($upsertRows !== [] || $checkUpsertRows !== []) {
                        app(\App\Services\CatalogSnapshotRevisionService::class)
                            ->invalidateSupplier($supplierId);
                    }

                    $changedOfferIds = collect($upsertRows)
                        ->merge($checkUpsertRows)
                        ->pluck('product_supplier_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    return [
                        'stop' => false,
                        'settings_changed' => false,
                        'last_id' => $nextId,
                        'changed_offer_ids' => $changedOfferIds,
                    ];
                });

                if ($page['settings_changed']) {
                    $settingsChangedDuringRun = true;
                    break;
                }

                if ($page['stop']) {
                    break;
                }

                $changedOfferIds = $page['changed_offer_ids'] ?? [];
                if ($changedOfferIds !== []) {
                    $sourcePrices = ProductPrice::query()
                        ->whereIn('product_supplier_id', $changedOfferIds)
                        ->get();

                    app(SupplierBranchPriceSyncService::class)
                        ->syncBranchesForPrimaryPrices($sourcePrices);
                }

                $lastId = (int) $page['last_id'];
                if ($productSupplierIds !== null) {
                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in TierPriceUpdater: ' . $e->getMessage(), [
                'supplier_id' => $supplierId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
            'settings_changed' => $settingsChangedDuringRun,
            'failed_product_supplier_ids' => array_values(array_unique($failedProductSupplierIds)),
        ];
    }

    /**
     * Recalculate stored check rows that derive from cash; explicit manual rows stay untouched.
     *
     * @param array<int, object> $checkRows
     * @param array<int, int> $oldCashPrices
     * @param array<int, int> $newCashPrices
     * @param array<int, float> $defaultPercentages
     * @param array<string, float> $categoryPercentages
     * @return array<int, array<string, mixed>>
     */
    private static function buildCheckPriceUpdates(
        array $checkRows,
        array $oldCashPrices,
        array $newCashPrices,
        int $categoryId,
        array $defaultPercentages,
        array $categoryPercentages,
        Supplier $supplier,
    ): array {
        $updates = [];

        foreach ($checkRows as $checkRow) {
            $tierIndex = (int) $checkRow->tier_index;
            if (!array_key_exists($tierIndex, $newCashPrices)) {
                continue;
            }

            $periodId = (int) $checkRow->check_period_id;
            $defaultPercentage = $categoryPercentages[$categoryId . ':' . $periodId]
                ?? $defaultPercentages[$periodId]
                ?? null;
            $mode = $checkRow->calculation_mode;
            $modeWasMissing = $mode === null;

            if ($modeWasMissing) {
                if ($checkRow->percentage_override !== null) {
                    $mode = 'percentage';
                } else {
                    $oldCash = $oldCashPrices[$tierIndex] ?? null;
                    if ($oldCash === null || $defaultPercentage === null) {
                        $mode = 'manual';
                    } else {
                        $expectedLegacyPrice = (int) round(PriceRoundingHelper::calculateCheckPrice(
                            (float) $oldCash,
                            $defaultPercentage,
                            $supplier
                        ));
                        $mode = $expectedLegacyPrice === (int) $checkRow->price ? 'default' : 'manual';
                    }
                }
            }

            if (!in_array($mode, ['default', 'percentage'], true) && !$modeWasMissing) {
                continue;
            }

            $price = (int) $checkRow->price;
            if (in_array($mode, ['default', 'percentage'], true)) {
                $percentage = $mode === 'percentage'
                    ? ($checkRow->percentage_override === null ? null : (float) $checkRow->percentage_override)
                    : ($checkRow->percentage_override === null
                        ? $defaultPercentage
                        : (float) $checkRow->percentage_override);

                if ($percentage === null) {
                    continue;
                }

                $price = (int) round(PriceRoundingHelper::calculateCheckPrice(
                    (float) $newCashPrices[$tierIndex],
                    $percentage,
                    $supplier
                ));
            }

            // For a legacy row inferred as manual, persist only its mode and preserve its price.
            $updates[] = [
                'product_id' => (int) $checkRow->product_id,
                'product_supplier_id' => (int) $checkRow->product_supplier_id,
                'price_type_id' => (int) $checkRow->price_type_id,
                'tier_index' => $tierIndex,
                'check_period_id' => $periodId,
                'price' => $price,
                'percentage_override' => $checkRow->percentage_override,
                'calculation_mode' => $mode,
                'is_active' => (bool) $checkRow->is_active,
                'created_at' => $checkRow->created_at ?? now(),
                'updated_at' => now(),
            ];
        }

        return $updates;
    }

    /**
     * دریافت درصد مؤثر (با اولویت‌بندی)
     * اولویت: دسته‌بندی > پیشفرض
     * 
     * @param int $supplierId
     * @param int|null $categoryId
     * @param int $tierIndex
     * @param float $defaultPercentage
     * @return float
     */
    private static function getEffectivePercentage(
        ?int $categoryId,
        int $tierIndex,
        float $defaultPercentage,
        array $categoryPercentages
    ): float {
        if ($categoryId) {
            $key = $categoryId . ':' . $tierIndex;
            if (array_key_exists($key, $categoryPercentages)) {
                return $categoryPercentages[$key];
            }
        }

        return $defaultPercentage;
    }

    /** @return array<int|string, mixed> */
    private static function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
