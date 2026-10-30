<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierBranchPrimaryService
{
    /** Promote a child branch and rebase each shared family without changing its price ratios. */
    public function promote(Supplier $currentPrimary, int $branchId): Supplier
    {
        $newPrimary = DB::transaction(function () use ($currentPrimary, $branchId): Supplier {
            $primary = Supplier::query()
                ->whereKey($currentPrimary->id)
                ->whereNull('parent_supplier_id')
                ->where('user_id', $currentPrimary->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $candidate = Supplier::query()
                ->whereKey($branchId)
                ->where('user_id', $primary->user_id)
                ->where('parent_supplier_id', $primary->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $branches = Supplier::query()
                ->where('parent_supplier_id', $primary->id)
                ->where('user_id', $primary->user_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $eligibleTransfer = Product::query()
                ->where('supplier_id', $candidate->id)
                ->eligiblePrimarySwitchCopy($primary)
                ->exists();

            if (!$eligibleTransfer) {
                throw ValidationException::withMessages([
                    'primaryBranch' => 'برای اصلی‌کردن این شعبه، ابتدا دست‌کم یک محصول از فروشگاه فعلی را در آن تأیید کنید.',
                ]);
            }

            $otherBranches = $branches->where('id', '!=', $candidate->id)->values();
            $futureBranches = $otherBranches->push($primary);
            $plans = $this->buildFamilyPlans($candidate, $futureBranches);

            $oldPrimarySlug = (string) $primary->slug;
            $candidateSlug = (string) $candidate->slug;
            $temporarySlug = 'branch-switch-' . Str::lower(Str::random(24));

            DB::table('suppliers')->where('id', $primary->id)->update(['slug' => $temporarySlug]);
            DB::table('suppliers')->where('id', $candidate->id)->update([
                'parent_supplier_id' => null,
                'branch_name' => null,
                'slug' => $oldPrimarySlug,
                'updated_at' => now(),
            ]);

            DB::table('suppliers')->where('id', $primary->id)->update([
                'parent_supplier_id' => $candidate->id,
                'branch_name' => $this->uniqueFormerPrimaryName($primary, $candidate->id),
                'slug' => $candidateSlug,
                'updated_at' => now(),
            ]);

            if ($otherBranches->isNotEmpty()) {
                DB::table('suppliers')
                    ->whereIn('id', $otherBranches->modelKeys())
                    ->update(['parent_supplier_id' => $candidate->id, 'updated_at' => now()]);
            }

            $now = now();
            DB::table('supplier_branch_product_transfers')
                ->where('source_supplier_id', $primary->id)
                ->where('status', SupplierBranchProductTransfer::PENDING)
                ->update([
                    'status' => SupplierBranchProductTransfer::CANCELLED,
                    'decided_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($plans as $plan) {
                $this->promoteFamily($plan);
            }

            $transferService = app(SupplierBranchTransferService::class);
            foreach ($futureBranches as $target) {
                $target = Supplier::query()->findOrFail($target->id);
                $transferService->seedPendingTransfers($candidate, $target);
            }

            return Supplier::query()->findOrFail($candidate->id);
        }, 3);

        app(SupplierBranchPublicCacheInvalidator::class)->invalidate($newPrimary->id);
        app(SupplierBranchPublicCacheInvalidator::class)->invalidate($currentPrimary->id);

        return $newPrimary->fresh();
    }

    /** @return Collection<int, array{product: Product, family_id: int, root_offer: ProductSupplier, root_percentage: float, branches: array<int, array{product: Product, offer: ProductSupplier, percentage: float}>}> */
    private function buildFamilyPlans(Supplier $candidate, Collection $branches): Collection
    {
        $products = Product::query()
            ->where('supplier_id', $candidate->id)
            ->where('is_active', true)
            ->where('status', ProductStatus::ACTIVE->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $plans = collect();

        foreach ($products as $product) {
            $familyId = (int) ($product->family_product_id ?: $product->id);
            if ($familyId === (int) $product->id) {
                continue;
            }

            $rootOffer = ProductSupplier::query()
                ->where('product_id', $product->id)
                ->where('supplier_id', $candidate->id)
                ->lockForUpdate()
                ->first();

            $rootPrices = ProductPrice::query()
                ->where('product_id', $product->id)
                ->where(function ($prices) use ($rootOffer): void {
                    if ($rootOffer) {
                        $prices->where('product_supplier_id', $rootOffer->id)
                            ->orWhereNull('product_supplier_id');
                    } else {
                        $prices->whereNull('product_supplier_id');
                    }
                })
                ->lockForUpdate()
                ->get();

            // Only rebase actual prices when the promoted offer has an active
            // matrix. Otherwise keep each branch's saved snapshots and ratios.
            $hasPriceMatrix = $rootOffer
                && $rootOffer->is_active
                && $rootOffer->status === ProductSupplier::STATUS_ACTIVE
                && $rootPrices->isNotEmpty();

            $rootPercentage = $hasPriceMatrix ? (float) ($rootOffer->branch_price_percentage ?? 0) : 0.0;
            $denominator = 1 + ($rootPercentage / 100);
            $branchPlans = [];
            $familyProducts = Product::withTrashed()
                ->where('family_product_id', $familyId)
                ->whereIn('supplier_id', $branches->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($familyProducts as $branchProduct) {
                $branchOffer = ProductSupplier::query()
                    ->where('product_id', $branchProduct->id)
                    ->where('supplier_id', $branchProduct->supplier_id)
                    ->lockForUpdate()
                    ->first();

                if (!$branchOffer) {
                    continue;
                }

                $preservedPriceStates = $this->captureBranchPriceStates($branchOffer, $rootPrices);

                $currentPercentage = (float) ($branchOffer->branch_price_percentage ?? 0);
                $rebasedPercentage = $hasPriceMatrix
                    ? round(
                        (((1 + ($currentPercentage / 100)) / $denominator) - 1) * 100,
                        12,
                        PHP_ROUND_HALF_UP
                    )
                    : $currentPercentage;

                if ($hasPriceMatrix && ($rebasedPercentage < -99.999999 || $rebasedPercentage > 999.999999)) {
                    throw ValidationException::withMessages([
                        'primaryBranch' => "درصد قیمت «{$product->title}» پس از تغییر از محدودهٔ مجاز خارج می‌شود؛ ابتدا درصد شعبه‌های این محصول را اصلاح کنید.",
                    ]);
                }

                $branchPlans[] = [
                    'product' => $branchProduct,
                    'offer' => $branchOffer,
                    'percentage' => $rebasedPercentage,
                    'preserved_price_states' => $preservedPriceStates,
                ];
            }

            $plans->push([
                'product' => $product,
                'family_id' => $familyId,
                'root_offer' => $rootOffer,
                'root_percentage' => $rootPercentage,
                'has_price_matrix' => (bool) $hasPriceMatrix,
                'branches' => $branchPlans,
            ]);
        }

        return $plans;
    }

    /**
     * Map each branch's saved matrix rows to the promoted matrix so promotion
     * can retain exact displayed prices and active states despite rounding.
     * Child rows match through their old root source id; the former root's rows
     * match by ID. A unique matrix shape is the fallback for standalone rows.
     *
     * @return array<int, array{is_active: bool, price?: int}> New root price id to branch-local state.
     */
    private function captureBranchPriceStates(ProductSupplier $branchOffer, Collection $newRootPrices): array
    {
        $branchPrices = ProductPrice::query()
            ->where('product_supplier_id', $branchOffer->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($branchPrices->isEmpty() || $newRootPrices->isEmpty()) {
            return [];
        }

        $preserved = [];
        $matchedBranchIds = [];
        $matchedRootIds = [];

        foreach ($newRootPrices as $rootPrice) {
            $sourceId = $rootPrice->branch_source_price_id === null
                ? null
                : (int) $rootPrice->branch_source_price_id;
            $branchRow = $sourceId === null ? null : $branchPrices->firstWhere('id', $sourceId);

            // Sibling child rows point to the same old root source price ID.
            if (!$branchRow && $sourceId !== null) {
                $branchRow = $branchPrices->firstWhere('branch_source_price_id', $sourceId);
            }

            if (!$branchRow) {
                continue;
            }

            $preserved[(int) $rootPrice->id] = [
                'is_active' => (bool) $branchRow->is_active,
                'price' => (int) $branchRow->price,
            ];
            $matchedBranchIds[(int) $branchRow->id] = true;
            $matchedRootIds[(int) $rootPrice->id] = true;
        }

        $unmatchedBranchRows = $branchPrices->reject(fn (ProductPrice $price): bool => isset($matchedBranchIds[(int) $price->id]));
        $unmatchedRootRows = $newRootPrices->reject(fn (ProductPrice $price): bool => isset($matchedRootIds[(int) $price->id]));
        $branchRowsByShape = $unmatchedBranchRows->groupBy(fn (ProductPrice $price): string => self::matrixShape($price));

        foreach ($unmatchedRootRows->groupBy(fn (ProductPrice $price): string => self::matrixShape($price)) as $shape => $rootRows) {
            $branchRows = $branchRowsByShape->get($shape);
            if (!$branchRows) {
                continue;
            }

            $rootRows = $rootRows->values();
            $branchRows = $branchRows->values();
            if ($rootRows->count() > 1 || $branchRows->count() > 1) {
                throw ValidationException::withMessages([
                    'primaryBranch' => 'به‌دلیل ردیف‌های قیمت هم‌شکل، جابه‌جایی شعبهٔ اصلی برای این محصول امن نیست؛ ابتدا ردیف‌های قیمت تکراری را اصلاح کنید.',
                ]);
            }

            if ($rootRows->count() === 1 && $branchRows->count() === 1) {
                $rootPrice = $rootRows->first();
                $branchRow = $branchRows->first();
                $preserved[(int) $rootPrice->id] = [
                    'is_active' => (bool) $branchRow->is_active,
                    'price' => (int) $branchRow->price,
                ];
                $matchedBranchIds[(int) $branchRow->id] = true;
                $matchedRootIds[(int) $rootPrice->id] = true;
            }
        }

        $rootShapes = array_fill_keys(
            $newRootPrices->map(fn (ProductPrice $price): string => self::matrixShape($price))->all(),
            true
        );
        $unmatchedBranchRows = $branchPrices->reject(fn (ProductPrice $price): bool => isset($matchedBranchIds[(int) $price->id]));
        foreach ($unmatchedBranchRows as $branchPrice) {
            if ($branchPrice->branch_source_price_id === null
                || isset($rootShapes[self::matrixShape($branchPrice)])) {
                throw ValidationException::withMessages([
                    'primaryBranch' => 'برای حفظ همهٔ قیمت‌های این محصول، ردیف ماتریس شعبه تطبیق‌پذیر نیست؛ ردیف‌های قیمت را بررسی کنید و دوباره تلاش کنید.',
                ]);
            }
        }

        return $preserved;
    }

    private static function matrixShape(ProductPrice $price): string
    {
        $attributes = $price->getAttributes();

        return json_encode([
            'price_type_id' => $attributes['price_type_id'] ?? null,
            'price_type' => $attributes['price_type'] ?? null,
            'tier_index' => $attributes['tier_index'] ?? null,
            'check_period_id' => $attributes['check_period_id'] ?? null,
            'min_quantity' => $attributes['min_quantity'] ?? null,
            'max_quantity' => $attributes['max_quantity'] ?? null,
            'percentage_override' => $attributes['percentage_override'] ?? null,
            'warranty_type' => $attributes['warranty_type'] ?? null,
            'warranty_duration' => $attributes['warranty_duration'] ?? null,
            'warranty_months' => $attributes['warranty_months'] ?? null,
            'check_months' => $attributes['check_months'] ?? null,
            'warranty_price' => $attributes['warranty_price'] ?? null,
            'description' => $attributes['description'] ?? null,
            'valid_from' => self::dateShape($attributes['valid_from'] ?? null),
            'valid_until' => self::dateShape($attributes['valid_until'] ?? null),
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private static function dateShape(mixed $date): ?string
    {
        return $date === null ? null : substr((string) $date, 0, 10);
    }

    private function promoteFamily(array $plan): void
    {
        /** @var Product $newCanonical */
        $newCanonical = $plan['product'];
        $oldFamilyId = (int) $plan['family_id'];

        $oldCanonical = Product::withTrashed()->find($oldFamilyId);
        if ($oldCanonical && (int) $oldCanonical->id !== (int) $newCanonical->id) {
            $temporarySlug = 'branch-product-switch-' . Str::lower(Str::random(24));
            $newSlug = (string) $newCanonical->slug;
            $canonicalSlug = (string) $oldCanonical->slug;

            DB::table('products')->where('id', $newCanonical->id)->update(['slug' => $temporarySlug]);
            DB::table('products')->where('id', $oldCanonical->id)->update([
                'slug' => $newSlug,
                'seo_canonical_url' => null,
                'updated_at' => now(),
            ]);
            DB::table('products')->where('id', $newCanonical->id)->update([
                'slug' => $canonicalSlug,
                'seo_canonical_url' => $oldCanonical->seo_canonical_url,
                'updated_at' => now(),
            ]);
        }

        DB::table('products')->where('id', $newCanonical->id)->update([
            'family_product_id' => $newCanonical->id,
            'updated_at' => now(),
        ]);
        DB::table('products')
            ->where('family_product_id', $oldFamilyId)
            ->where('id', '!=', $newCanonical->id)
            ->update(['family_product_id' => $newCanonical->id, 'updated_at' => now()]);

        /** @var ProductSupplier|null $rootOffer */
        $rootOffer = $plan['root_offer'];
        if ($rootOffer) {
            DB::table('product_supplier')->where('id', $rootOffer->id)->update([
                'branch_price_percentage' => 0,
                'is_primary' => true,
                'updated_at' => now(),
            ]);

            if ($plan['has_price_matrix']) {
                // Candidate prices already include its former branch percentage.
                DB::table('product_prices')
                    ->where('product_supplier_id', $rootOffer->id)
                    ->whereNotNull('branch_source_price_id')
                    ->update(['branch_source_price_id' => null, 'updated_at' => now()]);
            }
        }

        foreach ($plan['branches'] as $branchPlan) {
            /** @var ProductSupplier $offer */
            $offer = $branchPlan['offer'];
            DB::table('product_supplier')->where('id', $offer->id)->update([
                'branch_price_percentage' => $branchPlan['percentage'],
                'is_primary' => false,
                'updated_at' => now(),
            ]);

            // Previous primary matrix rows belong to the old base. The dependent
            // rows are then regenerated from the promoted branch prices below.
            if ($plan['has_price_matrix']) {
                DB::table('product_prices')
                    ->where('product_supplier_id', $offer->id)
                    ->whereNull('branch_source_price_id')
                    ->delete();
            }
        }

        if (!$plan['has_price_matrix']) {
            return;
        }

        $sync = app(SupplierBranchPriceSyncService::class);
        foreach ($plan['branches'] as $branchPlan) {
            $branch = Supplier::query()->findOrFail($branchPlan['offer']->supplier_id);
            $sync->syncBranchProduct(
                Product::withTrashed()->findOrFail($branchPlan['product']->id),
                $branch,
                (float) $branchPlan['percentage'],
                $branchPlan['preserved_price_states']
            );
        }
    }

    private function uniqueFormerPrimaryName(Supplier $formerPrimary, int $promotedBranchId): string
    {
        $base = trim((string) $formerPrimary->city) !== ''
            ? 'شعبهٔ ' . trim((string) $formerPrimary->city)
            : 'شعبهٔ قبلی';
        $base = Str::limit($base, 120, '');
        $suffixNumber = 1;

        do {
            $suffix = $suffixNumber === 1 ? '' : ' ' . $suffixNumber;
            $candidate = Str::limit($base, 120 - Str::length($suffix), '') . $suffix;
            $exists = Supplier::withTrashed()
                ->where('parent_supplier_id', $formerPrimary->id)
                ->where('id', '!=', $promotedBranchId)
                ->where('branch_name', $candidate)
                ->exists();
            $suffixNumber++;
        } while ($exists);

        return $candidate;
    }
}
