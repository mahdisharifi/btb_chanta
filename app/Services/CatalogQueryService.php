<?php

namespace App\Services;

use App\Models\CatalogTemplate;
use App\Models\ProductSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;


class CatalogQueryService
{
    /**
     * بازیابی و فیلتر کردن محصولات برای یک کاتالوگ مشخص
     */
    public function getProductsForTemplate(CatalogTemplate $template): Builder
    {
        // تشخیص نوع کاتالوگ بر اساس وجود supplier_id یا پیکربندی config
        $supplierId = $template->supplier_id;
        $config = $template->config ?? [];
        $supplierIds = $config['supplier_ids'] ?? [];

        $query = ProductSupplier::query()
            ->where('product_suppliers.is_active', true)
            ->join('products', 'product_suppliers.product_id', '=', 'products.id')
            ->select('product_suppliers.*', 'products.name as product_name', 'products.category_id');

        // فیلتر بر اساس تامین‌کننده (یک یا چند تامین‌کننده)
        if ($supplierId) {
            $query->where('product_suppliers.supplier_id', $supplierId);
        } elseif (!empty($supplierIds)) {
            $query->whereIn('product_suppliers.supplier_id', $supplierIds);
        }

        // فیلتر بر اساس دسته‌بندی‌ها (در قالب JSON در دیتابیس ذخیره شده است)
        if (!empty($template->categories)) {
            $categories = (array) $template->categories;
            $query->where(function ($q) use ($categories) {
                foreach ($categories as $categoryId) {
                    $q->orWhere('products.category_id', $categoryId);
                }
            });
        }

        // اعمال ترتیب‌ مرتب‌سازی‌های سه‌گانه کاتالوگ
        $query = $this->applySorting($query, $template);

        return $query;
    }

    /**
     * اعمال مرتب‌سازی پویا بر اساس تنظیمات کاتالوگ
     */
    protected function applySorting($query, CatalogTemplate $template)
    {
        $sorts = $template->settings['sort'] ?? [];
        $sortApplied = false;

        foreach ($sorts as $sort) {
            $field = $sort['field'] ?? null;
            $dir = strtolower($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            if (! $field) {
                continue;
            }

            $column = match (true) {
                $field === 'category' => 'products.category_id',
                $field === 'name' => 'products.title',
                $field === 'created_at' => 'products.created_at',
                $field === 'price_updated_at' => 'product_suppliers.price_updated_at',
                str_starts_with($field, 'price_') => 'product_suppliers.price',
                default => null,
            };

            if (! $column) {
                continue;
            }

            $query->orderBy($column, $dir);
            $sortApplied = true;
        }

        if (! $sortApplied) {
            $query->orderBy('products.title');
        }

        return $query;
    }
}
