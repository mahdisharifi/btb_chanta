<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupplierBranchTransferService
{
    public function seedPendingTransfers(Supplier $primary, Supplier $branch): void
    {
        $primaryId = (int) $primary->id;
        $branchId = (int) $branch->id;

        DB::transaction(function () use ($primaryId, $branchId): void {
            $primary = Supplier::query()->whereKey($primaryId)->lockForUpdate()->firstOrFail();
            $branch = Supplier::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
            $this->assertBranch($primary, $branch);

            Product::query()
                ->where(function ($query) use ($primary): void {
                    $query->where('products.supplier_id', $primary->id)
                        ->orWhereHas('productSuppliers', fn ($offers) => $offers
                            ->where('product_supplier.supplier_id', $primary->id)
                            ->where('product_supplier.is_active', true));
                })
                ->whereNotExists(function ($query) use ($branch): void {
                    $query->selectRaw('1')
                        ->from('products as existing_branch_product')
                        ->whereColumn('existing_branch_product.family_product_id', 'products.family_product_id')
                        ->where('existing_branch_product.supplier_id', $branch->id);
                })
                ->selectRaw(
                    'COALESCE(MIN(CASE WHEN products.supplier_id = ? THEN products.id END), MIN(products.id)) as id',
                    [$primary->id]
                )
                ->groupBy('products.family_product_id')
                ->orderBy('id')
                ->chunk(500, function ($products) use ($primary, $branch): void {
                    $now = now();
                    $rows = $products->map(fn (Product $product): array => [
                        'source_supplier_id' => $primary->id,
                        'target_supplier_id' => $branch->id,
                        'source_product_id' => $product->id,
                        'status' => SupplierBranchProductTransfer::PENDING,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    if ($rows) {
                        DB::table('supplier_branch_product_transfers')->insertOrIgnore($rows);
                    }
                });
        }, 3);
    }

    public function accept(Supplier $primary, Supplier $branch, int $transferId): Product
    {
        $this->assertBranch($primary, $branch);

        $transfer = SupplierBranchProductTransfer::query()
            ->whereKey($transferId)
            ->where('source_supplier_id', $primary->id)
            ->where('target_supplier_id', $branch->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($transfer->status === SupplierBranchProductTransfer::ACCEPTED) {
            return $transfer->destinationProduct()->firstOrFail();
        }

        if ($transfer->status !== SupplierBranchProductTransfer::PENDING) {
            throw new RuntimeException('این محصول قبلاً رد شده است و دیگر قابل پذیرش نیست.');
        }

        $source = Product::query()
            ->whereKey($transfer->source_product_id)
            ->where(function ($query) use ($primary): void {
                $query->where('supplier_id', $primary->id)
                    ->orWhereHas('productSuppliers', fn ($offers) => $offers
                        ->where('supplier_id', $primary->id)
                        ->where('is_active', true));
            })
            ->firstOrFail();

        return app(SupplierBranchProductCopyService::class)->copyForBranch(
            $source,
            $branch,
            function (Product $destination) use ($transfer): void {
                $lockedTransfer = SupplierBranchProductTransfer::query()
                    ->whereKey($transfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedTransfer->status === SupplierBranchProductTransfer::ACCEPTED
                    && (int) $lockedTransfer->destination_product_id === (int) $destination->id) {
                    return;
                }

                if ($lockedTransfer->status !== SupplierBranchProductTransfer::PENDING) {
                    throw new RuntimeException('این درخواست هم‌زمان در شعبه تعیین تکلیف شده است.');
                }

                $lockedTransfer->forceFill([
                    'destination_product_id' => $destination->id,
                    'status' => SupplierBranchProductTransfer::ACCEPTED,
                    'decided_at' => now(),
                ])->save();
            }
        );
    }

    public function reject(Supplier $primary, Supplier $branch, int $transferId): bool
    {
        $this->assertBranch($primary, $branch);

        return DB::transaction(function () use ($primary, $branch, $transferId): bool {
            $transfer = SupplierBranchProductTransfer::query()
                ->whereKey($transferId)
                ->where('source_supplier_id', $primary->id)
                ->where('target_supplier_id', $branch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status === SupplierBranchProductTransfer::REJECTED) {
                return true;
            }

            if ($transfer->status !== SupplierBranchProductTransfer::PENDING) {
                return false;
            }

            $transfer->forceFill([
                'status' => SupplierBranchProductTransfer::REJECTED,
                'decided_at' => now(),
            ])->save();

            return true;
        }, 3);
    }

    private function assertBranch(Supplier $primary, Supplier $branch): void
    {
        abort_unless(
            $primary->parent_supplier_id === null
                && (bool) $primary->is_active
                && (int) $branch->parent_supplier_id === (int) $primary->id
                && (int) $branch->user_id === (int) $primary->user_id
                && (bool) $branch->is_active,
            403,
            'شعبهٔ مقصد متعلق به این فروشگاه نیست.'
        );
    }
}
