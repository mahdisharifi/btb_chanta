<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierBranchCatalogService
{
    public function branch(Supplier $primary, int $branchId, bool $lock = false): Supplier
    {
        abort_unless($primary->parent_supplier_id === null && $primary->is_active
            && (int) $primary->user_id === (int) auth()->id(), 403);

        return Supplier::query()->whereKey($branchId)
            ->where('parent_supplier_id', $primary->id)
            ->where('user_id', $primary->user_id)->where('is_active', true)
            ->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
    }

    public function comparison(ProductSupplier $offer, Supplier $primary, bool $lock = false): array
    {
        $product = $offer->product;
        $familyId = $product->family_product_id ?: $product->id;
        $reference = Product::query()->where('family_product_id', $familyId)
            ->where(fn ($query) => $query->where('supplier_id', $primary->id)
                ->orWhereHas('productSuppliers', fn ($offers) => $offers->where('supplier_id', $primary->id)))
            ->orderByRaw('CASE WHEN supplier_id = ? THEN 0 ELSE 1 END', [$primary->id])
            ->orderBy('id')->first();
        $primaryOfferId = $reference?->productSuppliers()->where('supplier_id', $primary->id)->value('id');
        $source = $reference ? ProductPrice::query()->where('product_id', $reference->id)
            ->where(fn ($query) => $query->whereNull('product_supplier_id')
                ->when($primaryOfferId, fn ($query) => $query->orWhere('product_supplier_id', $primaryOfferId)))
            ->with(['priceType', 'checkPeriod'])->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get() : collect();
        $destination = $offer->prices()->with(['priceType', 'checkPeriod'])->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $sourceByCell = $source->keyBy(fn ($price) => $this->cell($price));
        $destinationByCell = $destination->keyBy(fn ($price) => $this->cell($price));
        $percentage = (float) ($offer->branch_price_percentage ?? 0);
        $rows = collect();
        foreach ($sourceByCell->keys()->merge($destinationByCell->keys())->unique() as $cell) {
            $base = $sourceByCell->get($cell);
            $price = $destinationByCell->get($cell);
            $expected = $base ? max(0, (int) round((int) $base->price * (1 + $percentage / 100), 0, PHP_ROUND_HALF_UP)) : null;
            $difference = $base && $price ? (int) $price->price - (int) $base->price : null;
            $rows->push([
                'cell' => $cell, 'type' => ($base ?? $price)->price_type_id,
                'tier' => ($base ?? $price)->tier_index, 'period' => ($base ?? $price)->check_period_id,
                'type_label' => ($base ?? $price)->priceType?->name ?? 'قیمت',
                'period_label' => ($base ?? $price)->checkPeriod
                    ? (($base ?? $price)->checkPeriod->label ?: ($base ?? $price)->checkPeriod->days.' روزه') : '',
                'base' => $base ? (int) $base->price : null,
                'price' => $price ? (int) $price->price : null, 'expected' => $expected,
                'difference' => $difference,
                'percent' => $base && $price && (int) $base->price !== 0
                    ? round($difference / (int) $base->price * 100, 2) : null,
                'synced' => $base && $price && (int) $price->price === $expected
                    && (int) $price->branch_source_price_id === (int) $base->id,
            ]);
        }

        return [
            'rows' => $rows->all(), 'percentage' => $percentage,
            'state' => $source->isEmpty() ? 'no_base' : ($rows->every(fn ($row) => $row['synced']) ? 'synced' : 'drift'),
            'fingerprint' => hash('sha256', json_encode([
                $offer->id, $offer->updated_at?->format('Y-m-d H:i:s.u'), $percentage,
                $source->map->getAttributes()->all(), $destination->map->getAttributes()->all(),
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    public function synchronize(Supplier $primary, int $branchId, array $snapshots, ?float $percentage = null): void
    {
        abort_unless(count($snapshots) > 0 && count($snapshots) <= 20, 422);
        DB::transaction(function () use ($primary, $branchId, $snapshots, $percentage): void {
            Supplier::query()->whereKey($primary->id)->lockForUpdate()->firstOrFail();
            $branch = $this->branch($primary, $branchId, true);
            foreach ($snapshots as $offerId => $fingerprint) {
                $offer = ProductSupplier::query()->where('supplier_id', $branch->id)
                    ->whereKey($offerId)->lockForUpdate()->firstOrFail();
                $comparison = $this->comparison($offer, $primary, true);
                if (!hash_equals($comparison['fingerprint'], $fingerprint)) {
                    throw ValidationException::withMessages(['preview' => 'قیمت‌ها تغییر کرده‌اند؛ پیش‌نمایش را دوباره بگیرید.']);
                }
                if ($comparison['state'] === 'no_base') {
                    throw ValidationException::withMessages(['preview' => 'برای این محصول قیمت مبنا موجود نیست.']);
                }
                if ($percentage !== null) {
                    $offer->forceFill(['branch_price_percentage' => $percentage])->save();
                }
                app(SupplierBranchPriceSyncService::class)->syncBranchProduct($offer->product, $branch);
            }
        }, 3);
    }

    public function remove(Supplier $primary, int $branchId, int $offerId): void
    {
        DB::transaction(function () use ($primary, $branchId, $offerId): void {
            Supplier::query()->whereKey($primary->id)->lockForUpdate()->firstOrFail();
            $branch = $this->branch($primary, $branchId, true);
            $offer = ProductSupplier::query()->where('supplier_id', $branch->id)
                ->whereKey($offerId)->lockForUpdate()->firstOrFail();
            $productId = $offer->product_id;
            $offer->forceDelete();
            SupplierBranchProductTransfer::query()->where('target_supplier_id', $branch->id)
                ->where('destination_product_id', $productId)
                ->update(['status' => SupplierBranchProductTransfer::PENDING, 'decided_at' => null, 'updated_at' => now()]);
            app(CatalogSnapshotRevisionService::class)->invalidateSuppliers([$branch->id]);
            DB::afterCommit(fn () => app(SupplierBranchPublicCacheInvalidator::class)->invalidateMany([$branch->id]));
        }, 3);
    }

    private function cell(ProductPrice $price): string
    {
        return implode(':', [$price->price_type_id, $price->tier_index, $price->check_period_id ?? 0]);
    }
}
