<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Services\PriceVisibilityService;
use Illuminate\Database\Eloquent\Builder;
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

        $suppliers = ProductSupplier::query()
            ->with([
                'prices' => function (Builder $query) use ($allowedTypes, $today): void {
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
            ])
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->verifiedSupplier()
            ->get();

        $visiblePricesBySupplier = $suppliers->mapWithKeys(function (ProductSupplier $productSupplier) use ($allowedTypes): array {
            $prices = $productSupplier->prices->filter(function (ProductPrice $price) use ($allowedTypes): bool {
                $type = $price->priceType?->code ?? 'cash';

                return $price->is_active
                    && $price->isValid()
                    && ($allowedTypes === null || in_array($type, $allowedTypes, true));
            });

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

        return response()->json([
            'product_id' => (int) $product->id,
            'price_revision' => (int) $product->price_revision,
            'product_lowest' => $productPrices->isEmpty() ? null : (int) $productPrices->min(),
            'product_highest' => $productPrices->isEmpty() ? null : (int) $productPrices->max(),
            'product_average' => $productPrices->isEmpty() ? null : (int) round($productPrices->avg()),
            'product_prices' => $productMatrixPrices,
            'product_tiers_count' => collect($productMatrixPrices)->pluck('tier_index')->unique()->count(),
            'suppliers' => $supplierSnapshots,
        ])->header('Cache-Control', 'private, no-store');
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
