<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Attribute;
use App\Models\ProductPrice;
use App\Models\RetailPrice;
use App\Enums\PriceType;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class AdvancedSearchService
{
    public function searchProducts(array $filters, $user = null)
    {
        $query = Product::query()->active();

        // جستجوی متنی
        if (!empty($filters['q'])) {
            $query->search($filters['q']);
        }

        // فیلتر دسته‌بندی
        if (!empty($filters['category_id'])) {
            $this->applyCategoryFilter($query, $filters['category_id']);
        }

        // فیلتر ویژگی‌های محصول
        if (!empty($filters['attributes'])) {
            $this->applyAttributeFilters($query, $filters['attributes']);
        }

        // فیلتر تامین‌کننده
        if (!empty($filters['supplier_id']) && $this->userCanFilterSupplier($user)) {
            $this->applySupplierFilter($query, $filters['supplier_id']);
        }

        // فیلتر نوع قیمت
        if (!empty($filters['price_type'])) {
            $this->applyPriceTypeFilter($query, $filters['price_type']);
        }

        // فیلتر محدوده قیمت
        if (!empty($filters['min_price']) || !empty($filters['max_price'])) {
            $this->applyPriceRangeFilter($query, $filters, $user);
        }

        // مرتب‌سازی
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // صفحه‌بندی
        $perPage = $filters['per_page'] ?? 16;

        return $query->with([
            'category:id,name,slug',
            'supplier:id,name,slug',
            'primaryImage',
            'activePrices',
            'retailPrices' => function($q) {
                $q->published();
            }
        ])->paginate($perPage)->withQueryString();
    }

    private function applyCategoryFilter($query, $categoryId)
    {
        $category = Category::find($categoryId);
        if ($category) {
            $categoryIds = $category->getDescendantsAndSelfIds();
            $query->whereIn('category_id', $categoryIds);
        }
    }

    private function applyAttributeFilters($query, array $attributes)
    {
        foreach ($attributes as $attributeId => $value) {
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                // فیلتر چند انتخابی
                $query->whereHas('attributeValues', function($q) use ($attributeId, $value) {
                    $q->where('attribute_id', $attributeId)
                        ->whereIn('value', array_filter($value));
                });
            } else {
                // فیلتر تک مقداری
                $query->whereHas('attributeValues', function($q) use ($attributeId, $value) {
                    $q->where('attribute_id', $attributeId)
                        ->where('value', $value);
                });
            }
        }
    }

    private function applySupplierFilter($query, $supplierId)
    {
        $query->whereHas('activeSuppliers', function($q) use ($supplierId) {
            $q->where('suppliers.id', $supplierId);
        });
    }

    private function applyPriceTypeFilter($query, $priceType)
    {
        if (!PriceType::tryFrom($priceType)) {
            return;
        }

        $query->whereHas('activePrices', function($q) use ($priceType) {
            $q->where('price_type', $priceType);
        });
    }

    private function applyPriceRangeFilter($query, array $filters, $user)
    {
        $minPrice = $filters['min_price'] ?? 0;
        $maxPrice = $filters['max_price'] ?? PHP_INT_MAX;

        // کاربر عادی: فیلتر بر اساس RetailPrice
        if (!$user || $user->role === UserRole::END_USER) {
            $query->whereHas('retailPrices', function($q) use ($minPrice, $maxPrice) {
                $q->published()
                    ->whereBetween('retail_price', [$minPrice, $maxPrice]);
            });
        } else {
            // تامین‌کننده/کاسب: فیلتر بر اساس ProductPrice
            $query->whereHas('activePrices', function($q) use ($minPrice, $maxPrice) {
                $q->whereBetween('price', [$minPrice, $maxPrice]);
            });
        }
    }

    private function userCanFilterSupplier($user): bool
    {
        if (!$user) {
            return false;
        }

        return in_array($user->role, [
            UserRole::RETAILER,
            UserRole::SUPPLIER,
            UserRole::SUPER_ADMIN
        ]);
    }

    public function getFilterOptions($categoryId = null, $user = null)
    {
        $options = [
            'attributes' => [],
            'suppliers' => [],
            'price_types' => PriceType::cases(),
            'price_range' => $this->getPriceRange($user),
        ];

        // ویژگی‌های دسته‌بندی
        if ($categoryId) {
            $options['attributes'] = Attribute::whereHas('categories', function($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            })->with(['values' => function($q) {
                $q->select('attribute_id', 'value')
                    ->distinct()
                    ->orderBy('value');
            }])->get();
        }

        // تامین‌کنندگان
        if ($categoryId && $this->userCanFilterSupplier($user)) {
            $options['suppliers'] = Supplier::whereHas('products', function($q) use ($categoryId) {
                $q->where('category_id', $categoryId)->active();
            })->active()->select('id', 'name', 'slug')->get();
        }

        return $options;
    }

    private function getPriceRange($user)
    {
        if (!$user || $user->role === UserRole::END_USER) {
            // کاربر عادی: بازه قیمت خرده‌فروشی
            $min = RetailPrice::published()->min('retail_price') ?? 0;
            $max = RetailPrice::published()->max('retail_price') ?? 10000000;
        } else {
            // تامین‌کننده/کاسب: بازه قیمت عمده
            $min = ProductPrice::activeAndValid()->min('price') ?? 0;
            $max = ProductPrice::activeAndValid()->max('price') ?? 10000000;
        }

        return [
            'min' => (int)$min,
            'max' => (int)$max,
        ];
    }

    public function getSearchStats($query, $filters, $user = null)
    {
        $baseQuery = Product::query()->active();

        if (!empty($query)) {
            $baseQuery->search($query);
        }

        if (!empty($filters['category_id'])) {
            $this->applyCategoryFilter($baseQuery, $filters['category_id']);
        }

        $totalProducts = $baseQuery->count();

        // تعداد محصولات در هر دسته‌بندی
        $categories = Category::whereHas('products', function($q) use ($baseQuery) {
            $q->mergeConstraintsFrom($baseQuery);
        })->withCount(['products' => function($q) use ($baseQuery) {
            $q->mergeConstraintsFrom($baseQuery);
        }])->get();

        return [
            'total_products' => $totalProducts,
            'categories' => $categories,
        ];
    }
}
