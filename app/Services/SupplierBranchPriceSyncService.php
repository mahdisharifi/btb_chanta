<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierBranchPriceSyncService
{
    /** Apply the primary branch price matrix to one accepted branch product. */
    public function syncBranchProduct(
        Product $branchProduct,
        Supplier $branch,
        ?float $percentage = null,
        array $preservedPriceStatesBySourceId = []
    ): void
    {
        abort_unless(
            $branch->parent_supplier_id !== null
                && $branch->is_active
                && (int) $branchProduct->supplier_id === (int) $branch->id
                && (int) $branch->family_product_id !== (int) $branchProduct->id,
            403,
            'قیمت‌گذاری درصدی فقط برای نسخهٔ مستقل محصول در شعبه مجاز است.'
        );

        $offer = ProductSupplier::query()
            ->where('product_id', $branchProduct->id)
            ->where('supplier_id', $branch->id)
            ->firstOrFail();

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
                array_key_exists('price', $preservedState) ? (int) $preservedState['price'] : null
            );
        }

        $stalePrices = DB::table('product_prices')
            ->where('product_supplier_id', $offer->id)
            ->whereNotNull('branch_source_price_id');

        if ($sourceIds) {
            $stalePrices->whereNotIn('branch_source_price_id', $sourceIds);
        }

        $stalePrices->delete();
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
            $branches = $this->activeBranches($primary);
            if ($branches->isEmpty()) {
                continue;
            }

            $branchIds = $branches->modelKeys();
            $copies = Product::query()
                ->where('family_product_id', $referenceProduct->family_product_id ?: $referenceProduct->id)
                ->whereIn('supplier_id', $branchIds)
                ->with('productSuppliers')
                ->get();

            foreach ($copies as $copy) {
                $branch = $branches->firstWhere('id', $copy->supplier_id);
                $offer = $copy->productSuppliers->firstWhere('supplier_id', $copy->supplier_id);
                if (!$branch || !$offer) {
                    continue;
                }

                foreach ($sourcePrices as $sourcePrice) {
                    $this->syncDerivedPrice(
                        $sourcePrice,
                        $copy,
                        $offer,
                        (float) ($offer->branch_price_percentage ?? 0)
                    );
                }
            }
        }
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

        $copyIds = Product::query()
            ->where('family_product_id', $referenceProduct->family_product_id ?: $referenceProduct->id)
            ->whereIn('supplier_id', $branchIds)
            ->pluck('id');

        if ($copyIds->isEmpty()) {
            return;
        }

        $offerIds = ProductSupplier::query()
            ->whereIn('product_id', $copyIds)
            ->whereIn('supplier_id', $branchIds)
            ->pluck('id');

        if ($offerIds->isNotEmpty()) {
            DB::table('product_prices')
                ->whereIn('product_supplier_id', $offerIds)
                ->where('branch_source_price_id', $price->id)
                ->delete();
        }
    }

    private function syncDerivedPrice(
        ProductPrice $sourcePrice,
        Product $branchProduct,
        ProductSupplier $offer,
        float $percentage,
        ?bool $preservedActiveState = null,
        ?int $preservedPrice = null
    ): void {
        $branchPrice = ProductPrice::query()
            ->where('product_supplier_id', $offer->id)
            ->where('branch_source_price_id', $sourcePrice->id)
            ->first();
        $isNewDerivedPrice = $branchPrice === null;

        if (!$branchPrice) {
            $attributes = $sourcePrice->getAttributes();
            unset(
                $attributes['id'],
                $attributes['product_id'],
                $attributes['product_supplier_id'],
                $attributes['branch_source_price_id'],
                $attributes['created_at'],
                $attributes['updated_at']
            );
            $branchPrice = new ProductPrice();
            $branchPrice->forceFill($attributes);
        }

        $branchPrice->forceFill([
            'product_id' => $branchProduct->id,
            'product_supplier_id' => $offer->id,
            'branch_source_price_id' => $sourcePrice->id,
            // A primary switch changes the basis, but must not mutate any saved
            // branch price because a rounded percentage can shift it by one unit.
            'price' => $preservedPrice ?? $this->adjustedPrice((int) $sourcePrice->price, $percentage),
            'is_active' => $isNewDerivedPrice
                ? ($preservedActiveState ?? (bool) $sourcePrice->is_active)
                : (bool) $branchPrice->is_active,
            'valid_from' => $sourcePrice->valid_from,
            'valid_until' => $sourcePrice->valid_until,
        ]);
        $branchPrice->saveQuietly();
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

        $belongsToPrimaryMatrix = $sourceOffer
            ? ((int) $price->product_supplier_id === (int) $sourceOffer->id || $price->product_supplier_id === null)
            : $price->product_supplier_id === null;

        return $belongsToPrimaryMatrix ? [$product, $primary, $referenceProduct] : null;
    }

    private function activeBranches(Supplier $primary): Collection
    {
        return Supplier::query()
            ->where('parent_supplier_id', $primary->id)
            ->where('user_id', $primary->user_id)
            ->where('is_active', true)
            ->get();
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
            ->where(function ($query) use ($sourceOffer): void {
                if ($sourceOffer) {
                    $query->where('product_supplier_id', $sourceOffer->id)
                        ->orWhereNull('product_supplier_id');
                } else {
                    $query->whereNull('product_supplier_id');
                }
            })
            ->orderBy('id')
            ->get();
    }

    private function adjustedPrice(int $price, float $percentage): int
    {
        return max(0, (int) round($price * (1 + ($percentage / 100)), 0, PHP_ROUND_HALF_UP));
    }
}
