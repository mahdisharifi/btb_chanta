<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * کلید کش درخت دسته‌بندی‌ها (هنگام ذخیره/ویرایش دسته در ادمین باید forget شود)
     */
    public const CACHE_KEY_TREE = 'categories.index.tree';

    public function index(): View
    {
        $categories = Cache::remember(self::CACHE_KEY_TREE, 3600, function () {
            $categories = Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('status', 'active')])
                        ->with(['children' => function ($q) {
                            $q->where('is_active', true)
                                ->orderBy('sort_order')
                                ->withCount(['products' => fn($qq) => $qq->where('is_active', true)->where('status', 'active')]);
                        }]);
                }])
                ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('status', 'active')])
                ->orderBy('sort_order')
                ->get();

            // محاسبه مجموع تجمیعی محصولات (دسته + زیردسته‌ها + زیرِ زیردسته‌ها)
            foreach ($categories as $category) {
                $sum = (int) ($category->products_count ?? 0);

                foreach ($category->children as $child) {
                    $childSum = (int) ($child->products_count ?? 0);

                    foreach ($child->children ?? collect() as $grandChild) {
                        $childSum += (int) ($grandChild->products_count ?? 0);
                    }

                    $child->total_products_count = $childSum;
                    $sum += $childSum;
                }

                $category->total_products_count = $sum;
            }

            return $categories;
        });

        $totalProducts = (int) $categories->sum('total_products_count');

        return view('pages.categories.index', [
            'categories'     => $categories,
            'totalProducts'  => $totalProducts,
            'pageTitle'      => 'دسته‌بندی محصولات',
            'seoDescription' => 'مرور محصولات بر اساس دسته‌بندی؛ مشاهده دسته‌ها، زیردسته‌ها و تعداد محصولات هر بخش.',
            'canonical'      => route('categories.index'),
            'ogImage'        => asset('images/og-default.jpg'),
        ]);
    }

    public function show(Category $category): View
    {
        abort_unless($category->is_active, 404);

        // Load children
        $category->load(['children' => function ($query) {
            $query->where('is_active', true)
                ->orderBy('sort_order')
                ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('status', 'active')]);
        }]);

        // Get all descendant IDs including self
        $categoryIds = $category->descendants()->pluck('id')->push($category->id);

        // Products in this category and subcategories
        $products = \App\Models\Product::query()
            ->pricedFirst()
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->where('status', 'active')
            ->with([
                'category:id,name,slug,parent_id',
                'supplier:id,company_name,brand_name,logo_path,logo_versions,is_verified',
                'galleries' => fn($q) => $q->select(['id', 'product_id', 'file_path', 'is_primary', 'sort_order', 'file_type', 'alt_text', 'versions', 'base_name'])
                    ->where('file_type', 'image')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->limit(1),
                // ✅ اضافه شد: eager loading برای قیمت‌های عمده
                'productSuppliers' => fn($q) => $q->where('is_active', true),
                'productSuppliers.prices' => fn($q) => $q->where('is_active', true)
                    ->where(function ($sub) {
                        $sub->whereNull('valid_until')
                            ->orWhere('valid_until', '>=', now());
                    })
                    ->where(function ($sub) {
                        $sub->whereNull('valid_from')
                            ->orWhere('valid_from', '<=', now());
                    }),
                'productSuppliers.supplier:id,company_name,brand_name',
                // ✅ اضافه شد: قیمت‌های خرده‌فروشی
                'retailPrices' => fn($q) => $q->select(['id', 'product_id', 'user_id', 'retail_price', 'is_published'])
                    ->where('is_published', true)
                    ->orderBy('retail_price', 'asc'),
            ])
            ->latest()
            ->paginate(16);

        // Breadcrumb
        $breadcrumb = [];
        $ancestors = $category->ancestors ?? collect();
        foreach ($ancestors as $ancestor) {
            $breadcrumb[] = ['name' => $ancestor->name, 'url' => route('categories.show', $ancestor->slug)];
        }
        $breadcrumb[] = ['name' => $category->name, 'url' => null];

        return view('pages.categories.show', compact('category', 'products', 'breadcrumb'));
    }
}
