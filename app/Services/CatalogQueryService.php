<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Enums\ProductStatus;
use App\Enums\PriceType;
use Illuminate\Database\Eloquent\Builder;

class CatalogQueryService
{
    public function getProductsForTemplate($template)
    {
        $settings = $template->settings ?? [];
        $config = $template->config ?? [];

        $supplierIds = $config['supplier_ids'] ?? ($template->supplier_id ? [$template->supplier_id] : []);
        $categoryIds = $settings['categories'] ?? [];
        $selectedPriceTypes = $settings['price_types'] ?? PriceType::values();

        $query = Product::query()
            ->where('status', ProductStatus::ACTIVE->value);

        // ۱. فیلتر دسته‌بندی
        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        // ۲. فیلتر تامین‌کننده و قیمت‌های فعال و معتبر
        $query->whereHas('productSuppliers', function (Builder $q) use ($supplierIds, $selectedPriceTypes) {
            $q->where('is_active', true)
                ->whereIn('supplier_id', $supplierIds)
                ->whereHas('prices', function ($priceQuery) use ($selectedPriceTypes) {
                    $priceQuery->activeAndValid()
                        ->whereIn('price_type', $selectedPriceTypes);
                });
        });

        // ۳. Eager Loading بهینه
        $query->with([
            'category',
            'primaryImage',
            'productSuppliers' => function ($q) use ($supplierIds, $selectedPriceTypes) {
                $q->where('is_active', true)
                    ->whereIn('supplier_id', $supplierIds)
                    ->with(['prices' => function ($priceQuery) use ($selectedPriceTypes) {
                        $priceQuery->activeAndValid()
                            ->whereIn('price_type', $selectedPriceTypes);
                    }]);
            }
        ]);

        // ۴. مرتب‌سازی با Subquery (پرفرمنس بهینه)
        $this->applySorting($query, $settings['sort'] ?? []);

        return $query->get();
    }

    protected function applySorting(Builder $query, array $sorts)
    {
        foreach ($sorts as $sort) {
            $field = $sort['field'] ?? null;
            $direction = $sort['direction'] ?? 'asc';
            if (!$field) continue;

            if (str_starts_with($field, 'price_')) {
                $type = str_replace('price_', '', $field);
                $query->orderBy(
                    ProductPrice::select('price')
                        ->whereColumn('product_id', 'products.id')
                        ->where('price_type', $type)
                        ->activeAndValid()
                        ->limit(1),
                    $direction
                );
            } else {
                $query->orderBy($field, $direction);
            }
        }
    }
}
