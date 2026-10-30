<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductGallery;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SupplierBranchProductCopyService
{
    /**
     * Copy a product into a child supplier branch. The callback runs in the same
     * transaction, which lets transfer state or a quick price be committed atomically.
     */
    public function copyForBranch(Product $source, Supplier $target, ?callable $withinTransaction = null): Product
    {
        $this->assertChildBranch($target);
        $copiedPaths = [];

        try {
            return DB::transaction(function () use ($source, $target, $withinTransaction, &$copiedPaths): Product {
                $target = Supplier::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
                $this->assertChildBranch($target);

                $familyId = (int) ($source->family_product_id ?: $source->id);
                $existing = Product::withTrashed()
                    ->where('supplier_id', $target->id)
                    ->where('family_product_id', $familyId)
                    ->lockForUpdate()
                    ->first();

                if ($existing?->trashed()) {
                    throw new RuntimeException('این محصول قبلاً در این شعبه حذف شده است.');
                }

                if ($existing) {
                    $offer = $this->ensureBranchOffer($existing, $target);
                    app(SupplierBranchPriceSyncService::class)->syncBranchProduct(
                        $existing,
                        $target,
                        (float) ($offer->branch_price_percentage ?? 0)
                    );
                    if ($withinTransaction) {
                        $withinTransaction($existing);
                    }

                    return $existing;
                }

                $product = $source->replicate([
                    'slug',
                    'sku',
                    'supplier_id',
                    'family_product_id',
                    'legacy_id',
                    'seo_canonical_url',
                    'view_count',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]);
                $product->supplier_id = $target->id;
                $product->family_product_id = $familyId;
                $product->legacy_id = null;
                $product->seo_canonical_url = null;
                // Product specifications are branch-specific and must be entered
                // again after acceptance, while identity/description/images copy.
                $product->specifications = null;
                $product->slug = $this->uniqueSlug($source->slug ?: $source->title, $target->id);
                $product->sku = $this->uniqueSku($source->sku, $target->id);
                $product->view_count = 0;
                $product->save();

                foreach ($source->galleries()->get() as $gallery) {
                    $product->galleries()->create($this->copyGalleryAttributes($gallery, $target, $copiedPaths));
                }

                $offer = $this->ensureBranchOffer($product, $target);
                app(SupplierBranchPriceSyncService::class)->syncBranchProduct(
                    $product,
                    $target,
                    (float) ($offer->branch_price_percentage ?? 0)
                );

                if ($withinTransaction) {
                    $withinTransaction($product);
                }

                return $product;
            }, 3);
        } catch (Throwable $exception) {
            if ($copiedPaths) {
                Storage::disk(ProductGallery::STORAGE_DISK)->delete($copiedPaths);
            }

            throw $exception;
        }
    }

    private function ensureBranchOffer(Product $product, Supplier $branch): ProductSupplier
    {
        return ProductSupplier::query()->firstOrCreate(
            ['product_id' => $product->id, 'supplier_id' => $branch->id],
            ['is_active' => true, 'is_primary' => false, 'status' => ProductSupplier::STATUS_ACTIVE]
        );
    }

    private function assertChildBranch(Supplier $target): void
    {
        $parent = $target->parentSupplier;
        if (!$parent
            || $parent->parent_supplier_id !== null
            || (int) $parent->user_id !== (int) $target->user_id
            || !$parent->is_active
            || !$target->is_active) {
            abort(403, 'شعبهٔ مقصد متعلق به این فروشگاه نیست.');
        }
    }

    private function uniqueSlug(string $source, int $branchId): string
    {
        $base = Str::slug($source) ?: 'product';
        $base = Str::limit($base, 170, '');
        $slug = $base . '-branch-' . $branchId;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-branch-' . $branchId . '-' . $suffix++;
        }

        return $slug;
    }

    private function uniqueSku(?string $source, int $branchId): string
    {
        $base = Str::limit((string) ($source ?: 'PRD'), 215, '');
        $sku = $base . '-B' . $branchId;
        $suffix = 1;

        while (Product::withTrashed()->where('sku', $sku)->exists()) {
            $sku = $base . '-B' . $branchId . '-' . $suffix++;
        }

        return $sku;
    }

    private function copyGalleryAttributes(ProductGallery $gallery, Supplier $branch, array &$copiedPaths): array
    {
        $attributes = $gallery->getAttributes();
        unset($attributes['id'], $attributes['product_id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

        $attributes['file_path'] = $this->copyStoredPath($gallery->file_path, $branch, $copiedPaths);
        if ($gallery->poster_path) {
            $attributes['poster_path'] = $this->copyStoredPath($gallery->poster_path, $branch, $copiedPaths);
        }

        $versions = $gallery->versions ?? [];
        foreach ($versions as $size => $version) {
            if (is_array($version) && !empty($version['path'])) {
                $versions[$size]['path'] = $this->copyStoredPath($version['path'], $branch, $copiedPaths);
            } elseif (is_string($version)) {
                $versions[$size] = $this->copyStoredPath($version, $branch, $copiedPaths);
            }
        }
        $attributes['versions'] = $versions;

        return $attributes;
    }

    private function copyStoredPath(?string $path, Supplier $branch, array &$copiedPaths): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            throw new RuntimeException('تصویر خارجی قابل کپی مستقل نیست؛ پیش از پذیرش، تصویر را در سامانه بارگذاری کنید.');
        }

        $disk = Storage::disk(ProductGallery::STORAGE_DISK);
        if (!$disk->exists($path)) {
            throw new RuntimeException('یکی از فایل‌های محصول مبدأ در فضای ذخیره‌سازی پیدا نشد.');
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $newPath = 'products/branches/' . $branch->id . '/' . Str::uuid() . ($extension ? '.' . $extension : '');
        if (!$disk->copy($path, $newPath)) {
            throw new RuntimeException('کپی مستقل یکی از فایل‌های محصول انجام نشد.');
        }

        $copiedPaths[] = $newPath;
        return $newPath;
    }
}
