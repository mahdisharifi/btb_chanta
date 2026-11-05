<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Services\PriceVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductPriceSnapshotController extends Controller
{
    public function __invoke(Product $product, Request $request, PriceVisibilityService $visibility): JsonResponse
    {
        abort_unless($product->is_active && $product->status === ProductStatus::ACTIVE, 404);
        abort_unless($visibility->canSeeWholesalePrice($request->user(), $product), 403);
        $settings = $visibility->getSettings($request->user());
        $allowedTypes = $settings?->getAllowedPriceTypes();
        $canSeeSupplierInfo = $settings?->canSeeSupplierInfo() ?? false;
        $today = now()->startOfDay();
        $familyId = (int) ($product->family_product_id ?: $product->id);
        $familyProductIds = Product::query()
            ->where('family_product_id', $familyId)
            ->where('is_active', true)
            ->where('status', ProductStatus::ACTIVE)
            ->pluck('id');

        if (!$familyProductIds->contains((int) $product->id)) {
            $familyProductIds->push((int) $product->id);
        }

        $suppliers = ProductSupplier::query()
            ->with([
                'prices' => function (Relation $query) use ($allowedTypes, $today): void {
                    $query->select([
                        'id',
                        'product_id',
                        'product_supplier_id',
                        'price_type_id',
                        'tier_index',
                        'check_period_id',
                        'price',
                        'is_active',
                        'valid_from',
                        'valid_until',
                    ])
                        ->where('is_active', true)
                        ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                        ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                        ->when($allowedTypes !== null, function (Builder $query) use ($allowedTypes): void {
                            $query->where(function (Builder $query) use ($allowedTypes): void {
                                $query->whereHas(
                                    'priceType',
                                    fn (Builder $typeQuery) => $typeQuery->whereIn('code', $allowedTypes)
                                );

                                if (in_array('cash', $allowedTypes, true)) {
                                    $query->orWhereNull('price_type_id');
                                }
                            });
                        });
                },
                'prices.priceType:id,code',
                'prices.checkPeriod:id,label,name',
            ])
            ->whereIn('product_id', $familyProductIds->all())
            ->where('is_active', true)
            ->verifiedSupplier()
            ->get();

        $visiblePricesBySupplier = $suppliers->mapWithKeys(function (ProductSupplier $productSupplier) use ($allowedTypes): array {
            $tierEnabled = (array) ($productSupplier->tier_enabled ?? []);
            $prices = $productSupplier->prices->filter(function (ProductPrice $price) use ($allowedTypes): bool {
                $type = $price->priceType?->code ?? 'cash';

                return $price->is_active
                    && $price->isValid()
                    && ($allowedTypes === null || in_array($type, $allowedTypes, true));
            })->filter(function (ProductPrice $price) use ($tierEnabled): bool {
                return empty($tierEnabled) || (bool) ($tierEnabled[(int) $price->tier_index] ?? true);
            })->values();

            // Keep the matrix and the range snapshot on the same enabled-tier set.
            $productSupplier->setRelation('prices', $prices);

            $prices = $prices
                ->groupBy(fn (ProductPrice $price) => implode(':', [
                    $price->priceType?->code ?? 'cash',
                    $price->tier_index,
                    $price->check_period_id ?? 0,
                ]))
                ->map(fn (Collection $rows) => $rows->sortBy('price')->first())
                ->values();

            return [(string) $productSupplier->id => $prices];
        });
        $allVisiblePrices = $visiblePricesBySupplier->flatten(1);
        $uniqueVisiblePrices = $allVisiblePrices
            ->groupBy(fn (ProductPrice $price) => implode(':', [
                $price->product_supplier_id,
                $price->price_type_id,
                $price->tier_index,
                $price->check_period_id ?? 0,
            ]))
            ->map(fn (Collection $rows) => $rows->sortBy('price')->first())
            ->values();

        $supplierSnapshots = [];

        if ($canSeeSupplierInfo) {
            foreach ($suppliers as $productSupplier) {
            $activePrices = $visiblePricesBySupplier->get((string) $productSupplier->id, collect());
            $warrantyRows = $activePrices->filter(fn (ProductPrice $price) => $price->priceType?->code === 'warranty');
            $canSeeWarranty = $allowedTypes === null || in_array('warranty', $allowedTypes, true);
            $prices = $activePrices
                ->map(function (ProductPrice $price) use ($productSupplier, $warrantyRows, $canSeeWarranty): array {
                    $warrantyPrice = null;
                    $type = $price->priceType?->code ?? 'cash';

                    if ($canSeeWarranty && $productSupplier->has_warranty && in_array($type, ['cash', 'check'], true)) {
                        if ($productSupplier->warranty_type === 'custom') {
                            $warrantyAddon = $warrantyRows->firstWhere('tier_index', $price->tier_index);
                            $warrantyPrice = $warrantyAddon
                                ? (int) $price->price + (int) $warrantyAddon->price
                                : null;
                        } else {
                            $warrantyPrice = (int) round(
                                $productSupplier->calculatePriceWithWarranty((int) $price->price)
                            );
                        }
                    }

                    return [
                        'type' => $price->priceType?->code ?? 'cash',
                        'tier_index' => (int) $price->tier_index,
                        'check_period_id' => $price->check_period_id === null ? null : (int) $price->check_period_id,
                        'price' => (int) $price->price,
                        'warranty_price' => $warrantyPrice,
                    ];
                })
                ->values()
                ->all();

            $supplierPrices = $activePrices->pluck('price');
            $supplierSnapshots[(string) $productSupplier->id] = [
                'product_supplier_id' => (int) $productSupplier->id,
                'supplier_id' => (int) $productSupplier->supplier_id,
                'prices' => $prices,
                'supplier_lowest' => $supplierPrices->isEmpty() ? null : (int) $supplierPrices->min(),
                'supplier_highest' => $supplierPrices->isEmpty() ? null : (int) $supplierPrices->max(),
                'supplier_tiers_count' => $activePrices->pluck('tier_index')->unique()->count(),
            ];
            }
        }

        $productPrices = $uniqueVisiblePrices->pluck('price');
        $productMatrixPrices = $this->productMatrixPayload($suppliers, $allowedTypes);
        $requestedCardSupplierId = $canSeeSupplierInfo
            ? filter_var($request->query('card_supplier_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $cardPrices = $uniqueVisiblePrices;
        if ($requestedCardSupplierId !== false && $requestedCardSupplierId !== null) {
            $cardOfferIds = $suppliers
                ->where('supplier_id', (int) $requestedCardSupplierId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $cardPrices = $cardPrices->whereIn('product_supplier_id', $cardOfferIds);
        } else {
            $requestedCardSupplierId = null;
        }
        $cardPriceValues = $cardPrices->pluck('price');
        $cardRange = [
            'lowest' => $cardPriceValues->isEmpty() ? null : (int) $cardPriceValues->min(),
            'highest' => $cardPriceValues->isEmpty() ? null : (int) $cardPriceValues->max(),
            'tiers_count' => $cardPrices->pluck('tier_index')->unique()->count(),
        ];
        $branchPrices = $canSeeSupplierInfo
            ? $this->branchPricePayload($product, $allowedTypes, $today, $request->query('supplier_id'))
            : [];

        return response()->json([
            'product_id' => (int) $product->id,
            'price_revision' => (int) $product->price_revision,
            'product_lowest' => $productPrices->isEmpty() ? null : (int) $productPrices->min(),
            'product_highest' => $productPrices->isEmpty() ? null : (int) $productPrices->max(),
            'product_average' => $productPrices->isEmpty() ? null : (int) round($productPrices->avg()),
            'product_prices' => $productMatrixPrices,
            'product_tiers_count' => collect($productMatrixPrices)->pluck('tier_index')->unique()->count(),
            'card_supplier_id' => $requestedCardSupplierId,
            'card_range' => $cardRange,
            'suppliers' => $supplierSnapshots,
            'branches' => $branchPrices,
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @return array<int, array{supplier_id:int, price:?int, price_type:?string, check_period_label:?string, percentage:float, is_primary:bool, can_view_price:bool}> */
    private function branchPricePayload(
        Product $product,
        ?array $allowedTypes,
        \Carbon\CarbonInterface $today,
        mixed $displayedSupplierId = null,
    ): array
    {
        $familyId = (int) ($product->family_product_id ?: $product->id);
        $requestedSupplierId = $displayedSupplierId === null
            ? (int) $product->supplier_id
            : filter_var($displayedSupplierId, FILTER_VALIDATE_INT);
        if (!$requestedSupplierId || $requestedSupplierId < 1) {
            return [];
        }

        $ownerSupplier = \App\Models\Supplier::query()
            ->whereKey($requestedSupplierId)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->first(['id', 'user_id', 'parent_supplier_id', 'is_verified']);

        if (!$ownerSupplier) {
            return [];
        }

        $primarySupplierId = (int) ($ownerSupplier->parent_supplier_id ?: $ownerSupplier->id);
        $familySuppliers = \App\Models\Supplier::query()
            ->where('user_id', $ownerSupplier->user_id)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->where(fn (Builder $query) => $query
                ->whereKey($primarySupplierId)
                ->orWhere('parent_supplier_id', $primarySupplierId))
            ->get(['id', 'parent_supplier_id'])
            ->keyBy('id');

        $primarySupplier = $familySuppliers->get($primarySupplierId);
        if (!$primarySupplier || $primarySupplier->parent_supplier_id !== null) {
            return [];
        }

        $branchSupplierIds = $familySuppliers
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($branchSupplierIds === []) {
            return [];
        }
        if (!in_array((int) $ownerSupplier->id, $branchSupplierIds, true)) {
            return [];
        }

        $familyProducts = Product::query()
            ->where('family_product_id', $familyId)
            ->where('is_active', true)
            ->where('status', ProductStatus::ACTIVE->value)
            ->with([
                'supplier:id,user_id,parent_supplier_id,is_active,is_verified',
                'productSuppliers' => function (Relation $query) use ($branchSupplierIds, $allowedTypes, $today): void {
                    $query->whereIn('supplier_id', $branchSupplierIds)
                        ->where('is_active', true)
                        ->where('status', 'active')
                        ->with([
                            'supplier:id,parent_supplier_id,is_active,is_verified',
                            'prices' => function (Relation $priceQuery) use ($allowedTypes, $today): void {
                                $priceQuery->where('is_active', true)
                                    ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                                    ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                                    ->when($allowedTypes !== null, function (Builder $query) use ($allowedTypes): void {
                                        $query->where(function (Builder $query) use ($allowedTypes): void {
                                            $query->whereHas(
                                                'priceType',
                                                fn (Builder $typeQuery) => $typeQuery->whereIn('code', $allowedTypes)
                                            );

                                            if (in_array('cash', $allowedTypes, true)) {
                                                $query->orWhereNull('price_type_id');
                                            }
                                        });
                                    });
                            },
                            'prices.priceType:id,code',
                            'prices.checkPeriod:id,label,name',
                        ]);
                },
            ])
            ->get();

        $familyHasOffer = $familyProducts->contains(
            static fn (Product $familyProduct): bool => $familyProduct->productSuppliers->isNotEmpty(),
        );
        if (!$familyHasOffer) {
            return [];
        }

        $rows = [];
        $branchSupplierIdSet = array_fill_keys($branchSupplierIds, true);
        $checkPeriodsBySupplier = collect();
        if ($allowedTypes === null || in_array('check', $allowedTypes, true)) {
            $checkPeriodsBySupplier = \App\Models\CheckPeriod::getActiveForSuppliers($branchSupplierIds)
                ->groupBy(static fn ($period) => (int) $period->resolved_supplier_id)
                ->map(static fn ($periods) => $periods->values());

            $primaryPeriods = $checkPeriodsBySupplier->get($primarySupplierId, collect());
            foreach ($branchSupplierIds as $branchSupplierId) {
                if ((int) $branchSupplierId === $primarySupplierId) {
                    continue;
                }

                $branchPeriods = $checkPeriodsBySupplier->get($branchSupplierId, collect());
                $checkPeriodsBySupplier[$branchSupplierId] = $primaryPeriods
                    ->merge($branchPeriods)
                    ->unique('id')
                    ->values();
            }
        }

        $appendBranchRow = function ($supplier, ?ProductSupplier $offer) use (&$rows, $branchSupplierIdSet, $allowedTypes, $checkPeriodsBySupplier): void {
            if (!$supplier?->is_active || !$supplier?->is_verified) {
                return;
            }

            $supplierId = (int) $supplier->id;
            if (!isset($branchSupplierIdSet[$supplierId]) || isset($rows[$supplierId])) {
                return;
            }

            if ($offer && (!$offer->is_active || $offer->status !== 'active')) {
                return;
            }

            $prices = $offer?->prices ?? collect();
            $tierEnabled = (array) ($offer?->tier_enabled ?? []);
            $visiblePrices = $prices->filter(function (ProductPrice $price) use ($allowedTypes): bool {
                $type = $price->priceType?->code ?? 'cash';

                return $price->is_active
                    && $price->isValid()
                    && ($allowedTypes === null || in_array($type, $allowedTypes, true));
            })->filter(function (ProductPrice $price) use ($tierEnabled): bool {
                return empty($tierEnabled) || (bool) ($tierEnabled[(int) $price->tier_index] ?? true);
            });
            $basePrices = $visiblePrices->filter(fn (ProductPrice $price): bool => in_array(
                $price->priceType?->code ?? 'cash',
                ['cash', 'check'],
                true,
            ));
            $lowestBasePrice = $basePrices->sortBy('price')->first();
            $priceType = $lowestBasePrice?->priceType?->code ?? ($lowestBasePrice ? 'cash' : null);

            $rows[$supplierId] = [
                'supplier_id' => $supplierId,
                'price' => $lowestBasePrice ? (int) $lowestBasePrice->price : null,
                'price_type' => $priceType,
                'check_period_label' => $priceType === 'check'
                    ? ($checkPeriodsBySupplier->get($supplierId, collect())
                        ->firstWhere('id', (int) $lowestBasePrice?->check_period_id)?->display_name
                        ?: $lowestBasePrice?->checkPeriod?->label
                        ?: $lowestBasePrice?->checkPeriod?->name)
                    : null,
                'percentage' => (float) ($offer?->branch_price_percentage ?? 0),
                'is_primary' => $supplier->parent_supplier_id === null,
                'can_view_price' => true,
            ];
        };

        foreach ($familyProducts->sortBy('id') as $familyProduct) {
            $supplier = $familyProduct->supplier;
            if (!$supplier?->is_active) {
                continue;
            }

            if ((int) ($supplier->parent_supplier_id ?: $supplier->id) === $primarySupplierId) {
                $ownerOffer = $familyProduct->productSuppliers->firstWhere('supplier_id', $supplier->id);
                $appendBranchRow($supplier, $ownerOffer);
            }

            foreach ($familyProduct->productSuppliers as $offer) {
                $appendBranchRow($offer->supplier, $offer);
            }
        }

        return array_values($rows);
    }

    /** @return array<int, array{type: string, tier_index: int, check_period_id: ?int, price: int, warranty_price: ?int}> */
    private function productMatrixPayload(Collection $suppliers, ?array $allowedTypes): array
    {
        $groups = [];

        foreach ($suppliers as $productSupplier) {
            $currentPrices = $productSupplier->prices
                ->groupBy(fn (ProductPrice $price) => implode(':', [
                    $price->priceType?->code ?? 'cash',
                    $price->tier_index,
                    $price->check_period_id ?? 0,
                ]))
                ->map(fn (Collection $rows) => $rows->sortBy('price')->first())
                ->values();
            $warrantyRows = $currentPrices->filter(fn (ProductPrice $price) => $price->priceType?->code === 'warranty');
            $rolePrices = $currentPrices->filter(function (ProductPrice $price) use ($allowedTypes): bool {
                $type = $price->priceType?->code ?? 'cash';

                return $allowedTypes === null || in_array($type, $allowedTypes, true);
            });
            $canSeeWarranty = $allowedTypes === null || in_array('warranty', $allowedTypes, true);

            foreach ($rolePrices as $price) {
                $type = $price->priceType?->code ?? 'cash';
                if (!in_array($type, ['cash', 'check'], true)) {
                    continue;
                }

                $warrantyPrice = null;
                if ($canSeeWarranty && $productSupplier->has_warranty) {
                    if ($productSupplier->warranty_type === 'custom') {
                        $warrantyAddon = $warrantyRows->firstWhere('tier_index', $price->tier_index);
                        $warrantyPrice = $warrantyAddon
                            ? (int) $price->price + (int) $warrantyAddon->price
                            : null;
                    } else {
                        $warrantyPrice = (int) round(
                            $productSupplier->calculatePriceWithWarranty((int) $price->price)
                        );
                    }
                }

                $key = implode(':', [$type, $price->tier_index, $price->check_period_id ?? 0]);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'type' => $type,
                        'tier_index' => (int) $price->tier_index,
                        'check_period_id' => $price->check_period_id === null ? null : (int) $price->check_period_id,
                        'price' => (int) $price->price,
                        'warranty_price' => $warrantyPrice,
                    ];
                    continue;
                }

                $groups[$key]['price'] = min($groups[$key]['price'], (int) $price->price);
                if ($warrantyPrice !== null) {
                    $groups[$key]['warranty_price'] = $groups[$key]['warranty_price'] === null
                        ? $warrantyPrice
                        : min($groups[$key]['warranty_price'], $warrantyPrice);
                }
            }
        }

        return array_values($groups);
    }
}
