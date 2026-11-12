<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SupplierBranchDeletionService
{
    public function summary(Supplier $primary, int $branchId): array
    {
        $branch = $this->branchQuery($primary)->whereKey($branchId)->firstOrFail();

        return [
            'name' => $branch->branchLabel(),
            'products' => Product::withTrashed()->where('supplier_id', $branchId)->count(),
            'offers' => ProductSupplier::withTrashed()->where('supplier_id', $branchId)->count(),
            'prices' => $this->prices($branchId)->count(),
            'priced_offers' => $this->pricedOffers($branchId)->count(),
            'external_period_references' => $this->externalPeriodReferences($branchId),
            'pending_transfers' => DB::table('supplier_branch_product_transfers')
                ->where('target_supplier_id', $branchId)->where('status', 'pending')->count(),
        ];
    }

    public function delete(Supplier $primary, int $branchId, bool $removePrices = false): void
    {
        DB::transaction(function () use ($primary, $branchId, $removePrices): void {
            // Use the same lock order as branch copying and pricing.
            $primary = Supplier::query()->whereKey($primary->id)->where('user_id', auth()->id())
                ->whereNull('parent_supplier_id')->lockForUpdate()->firstOrFail();
            $branch = $this->branchQuery($primary)->whereKey($branchId)->lockForUpdate()->firstOrFail();
            Product::withTrashed()->where('supplier_id', $branchId)->lockForUpdate()->get(['id']);
            ProductSupplier::withTrashed()->where('supplier_id', $branchId)->lockForUpdate()->get(['id']);

            if ($this->externalPeriodReferences($branchId) > 0) {
                throw ValidationException::withMessages([
                    'branchDeletion' => 'دوره‌های چک اختصاصی این شعبه در قیمت‌ها یا تنظیمات شعبه‌های دیگر استفاده شده‌اند. ابتدا این وابستگی‌ها را تعیین تکلیف کنید.',
                ]);
            }

            if (!$removePrices && ($this->prices($branchId)->exists() || $this->pricedOffers($branchId)->exists())) {
                throw ValidationException::withMessages([
                    'branchDeletion' => 'قیمت‌های شعبه هنوز تعیین تکلیف نشده‌اند. ابتدا آن‌ها را حذف کنید یا حذف قیمت‌های همین شعبه را تأیید کنید.',
                ]);
            }

            // Only this supplier's offers and legacy, unassigned prices belong to
            // the branch. Other suppliers' offers on the same products survive.
            $this->prices($branchId)->delete();
            DB::table('product_supplier')->where('supplier_id', $branchId)->delete();
            if (Schema::hasTable('check_periods')) {
                DB::table('check_periods')->where('supplier_id', $branchId)->delete();
            }

            // Explicitly detach even deleted products before deleting the supplier;
            // this also protects installations with the older CASCADE foreign key.
            DB::table('products')->where('supplier_id', $branchId)->update(['supplier_id' => null]);
            DB::table('supplier_branch_product_transfers')
                ->where(fn (Builder $query) => $query->where('source_supplier_id', $branchId)->orWhere('target_supplier_id', $branchId))
                ->delete();

            $branch->forceDelete();
            app(CatalogSnapshotRevisionService::class)->invalidateGlobal();
        }, 3);
    }

    private function branchQuery(Supplier $primary)
    {
        abort_unless($primary->parent_supplier_id === null && (int) $primary->user_id === (int) auth()->id(), 403);

        return $primary->branches()->withTrashed()->where('user_id', $primary->user_id);
    }

    private function prices(int $branchId): Builder
    {
        return DB::table('product_prices')->where(function (Builder $query) use ($branchId): void {
            $query->whereIn('product_supplier_id', DB::table('product_supplier')->select('id')->where('supplier_id', $branchId))
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('product_supplier_id')
                    ->whereIn('product_id', DB::table('products')->select('id')->where('supplier_id', $branchId)));
        });
    }

    private function pricedOffers(int $branchId): Builder
    {
        return DB::table('product_supplier')->where('supplier_id', $branchId)
            ->where('foreign_price_amount', '>', 0);
    }

    private function externalPeriodReferences(int $branchId): int
    {
        if (!Schema::hasTable('check_periods')) {
            return 0;
        }

        $periods = fn () => DB::table('check_periods')->select('id')->where('supplier_id', $branchId);
        $count = DB::table('product_prices')->whereIn('check_period_id', $periods())
            ->whereNotIn('id', $this->prices($branchId)->select('id'))->count();
        foreach (['supplier_check_period', 'category_check_period'] as $table) {
            if (Schema::hasTable($table)) {
                $count += DB::table($table)->whereIn('check_period_id', $periods())->where('supplier_id', '!=', $branchId)->count();
            }
        }

        return $count;
    }
}
