<?php

namespace App\Services;

use App\Models\SupplierTierDiscount;

/** Supplier visibility is a ceiling; product flags can only narrow it. */
final class SupplierTierAvailabilityService
{
    private array $settings = [];

    public function prime(iterable $supplierIds): void
    {
        $missing = [];
        foreach ($supplierIds as $id) {
            $id = (int) $id;
            if ($id > 0 && !array_key_exists($id, $this->settings)) {
                $missing[$id] = $id;
            }
        }
        if ($missing === []) {
            return;
        }
        foreach ($missing as $id) {
            // Only the carton reference is enabled until explicitly configured.
            $this->settings[$id] = SupplierTierDiscount::DEFAULT_ENABLED;
        }
        foreach (SupplierTierDiscount::query()->whereIn('supplier_id', $missing)->get(['supplier_id', 'tier_index', 'is_active']) as $setting) {
            $this->settings[(int) $setting->supplier_id][(int) $setting->tier_index] =
                (int) $setting->tier_index === 1 || (bool) $setting->is_active;
        }
    }

    public function forSupplier(int $supplierId): array
    {
        $this->prime([$supplierId]);

        return $this->settings[$supplierId] ?? SupplierTierDiscount::DEFAULT_ENABLED;
    }

    public function effective(int $supplierId, array $productFlags): array
    {
        foreach ($this->forSupplier($supplierId) as $index => $enabled) {
            $productFlags[$index] = $enabled && (bool) ($productFlags[$index] ?? SupplierTierDiscount::DEFAULT_ENABLED[$index] ?? false);
        }

        return $productFlags;
    }

    public function forget(int $supplierId): void
    {
        unset($this->settings[$supplierId]);
    }

    /** Apply the same supplier ceiling before SQL aggregates/pagination. */
    public static function constrainPrices($query, string $priceTable = 'product_prices'): void
    {
        $query->where(function ($visible) use ($priceTable): void {
            $visible->where($priceTable.'.tier_index', 1)
                ->orWhereExists(function ($enabled) use ($priceTable): void {
                    $enabled->selectRaw('1')->from('supplier_tier_discount as enabled_tier')
                        ->where('enabled_tier.is_active', true)
                        ->whereColumn('enabled_tier.tier_index', $priceTable.'.tier_index')
                        ->where(function ($owner) use ($priceTable): void {
                            $owner->whereExists(function ($offer) use ($priceTable): void {
                                $offer->selectRaw('1')->from('product_supplier as tier_owner')
                                    ->whereColumn('tier_owner.id', $priceTable.'.product_supplier_id')
                                    ->whereColumn('tier_owner.supplier_id', 'enabled_tier.supplier_id');
                            })->orWhere(function ($legacy) use ($priceTable): void {
                                $legacy->whereNull($priceTable.'.product_supplier_id')
                                    ->whereExists(function ($product) use ($priceTable): void {
                                        $product->selectRaw('1')->from('products as tier_product')
                                            ->whereColumn('tier_product.id', $priceTable.'.product_id')
                                            ->whereColumn('tier_product.supplier_id', 'enabled_tier.supplier_id');
                                    });
                            });
                        });
                });
        });
    }
}
