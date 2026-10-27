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
        $categories = app(\App\Services\CategoryCatalog::class)->tree();
        $totalProducts = (int) $categories->sum('total_products_count');
        $pageTitle = 'دسته‌بندی محصولات';
        $seoDescription = 'مرور محصولات بر اساس دسته‌بندی؛ مشاهده دسته‌ها، زیردسته‌ها و تعداد محصولات هر بخش.';
        $canonical = route('categories.index');
        $ogTitle = $pageTitle.' — '.config('app.name');
        $ogImage = asset('images/og-default.jpg');
        $structuredData = $this->schema($pageTitle, $seoDescription, $canonical, [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => 'دسته‌بندی‌ها', 'url' => $canonical],
        ], $categories->map(fn ($category) => [
            'name' => $category->name, 'url' => route('categories.show', $category->slug),
        ])->values()->all());

        return view('pages.categories.index', compact('categories', 'totalProducts', 'pageTitle', 'seoDescription', 'canonical', 'ogTitle', 'ogImage', 'structuredData'));
    }

    public function show(Category $category): View
    {
        $catalog = app(\App\Services\CategoryCatalog::class);
        $published = $catalog->flatten();
        $category = $published->get($category->id);
        abort_unless($category, 404);
        $subcategories = $category->getRelation('children');
        $categoryIds = $catalog->flatten(collect([$category]))->keys();
        $requestedSort = request()->query('sort', 'newest');
        $sort = is_string($requestedSort) && in_array($requestedSort, ['newest', 'oldest', 'name_asc', 'name_desc'], true)
            ? $requestedSort : 'newest';

        // Products in this category and subcategories
        $products = \App\Models\Product::query()
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->where('status', 'active')
            ->with([
                'category:id,name,slug,parent_id',
                'supplier:id,company_name,brand_name,slug,is_active,logo_path,logo_versions,is_verified',
                'galleries' => fn($q) => $q->select(['id', 'product_id', 'file_path', 'is_primary', 'sort_order', 'file_type', 'alt_text', 'versions', 'base_name'])
                    ->where('file_type', 'image')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->limit(1),
                // ✅ اضافه شد: eager loading برای قیمت‌های عمده
                'productSuppliers' => fn($q) => $q->where('is_active', true)->whereHas('supplier', fn ($s) => $s->where('is_active', true)),
                'productSuppliers.prices' => fn ($q) => $q->activeAndValid()->with('priceType'),
                'productSuppliers.supplier:id,company_name,brand_name,slug,is_active',
                // ✅ اضافه شد: قیمت‌های خرده‌فروشی
                'retailPrices' => fn($q) => $q->select(['id', 'product_id', 'user_id', 'retail_price', 'is_published'])
                    ->where('is_published', true)
                    ->orderBy('retail_price', 'asc'),
            ])
            ->when($sort === 'oldest', fn ($q) => $q->orderBy('products.created_at'))
            ->when($sort === 'name_asc', fn ($q) => $q->orderBy('products.title'))
            ->when($sort === 'name_desc', fn ($q) => $q->orderByDesc('products.title'))
            ->when($sort === 'newest', fn ($q) => $q->orderByDesc('products.created_at'))
            ->orderBy('products.id')
            ->paginate(16)
            ->appends($sort !== 'newest' ? ['sort' => $sort] : []);

        $breadcrumb = [['name' => 'دسته‌بندی‌ها', 'url' => route('categories.index')]];
        $ancestors = collect();
        $parentId = $category->parent_id;
        $visited = [];
        while ($parentId && !isset($visited[$parentId]) && ($ancestor = $published->get($parentId))) {
            $visited[$parentId] = true;
            $ancestors->prepend($ancestor);
            $parentId = $ancestor->parent_id;
        }
        foreach ($ancestors as $ancestor) {
            $breadcrumb[] = ['name' => $ancestor->name, 'url' => route('categories.show', $ancestor->slug)];
        }
        $breadcrumb[] = ['name' => $category->name, 'url' => null];
        $pageTitle = $category->seo_title ?: $category->name;
        $seoDescription = $category->seo_description ?: 'مشاهده و مقایسهٔ محصولات '.$category->name.' و قیمت‌های تأمین‌کنندگان';
        $canonical = route('categories.show', $category->slug).($products->currentPage() > 1 ? '?page='.$products->currentPage() : '');
        $ogTitle = $pageTitle.' — '.config('app.name');
        $ogImage = $category->image_url ?: asset('images/og-default.jpg');
        $schemaCrumbs = array_merge([['name' => 'خانه', 'url' => url('/')]], $breadcrumb);
        $schemaCrumbs[array_key_last($schemaCrumbs)]['url'] = route('categories.show', $category->slug);
        $structuredData = $this->schema($category->name, $seoDescription, $canonical, $schemaCrumbs,
            $products->getCollection()->map(fn ($product) => ['name' => $product->title, 'url' => route('products.show', $product->slug)])->all());

        return view('pages.categories.show', compact('category', 'products', 'breadcrumb', 'subcategories', 'sort', 'pageTitle', 'seoDescription', 'canonical', 'ogTitle', 'ogImage', 'structuredData'));
    }

    private function schema(string $name, string $description, string $url, array $breadcrumbs, array $items): array
    {
        return [
            '@context' => 'https://schema.org', '@type' => 'CollectionPage',
            'name' => $name, 'description' => $description, 'url' => $url,
            'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => array_map(
                fn ($item, $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'item' => $item['url']],
                $breadcrumbs, array_keys($breadcrumbs))],
            'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => array_map(
                fn ($item, $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['name'], 'url' => $item['url']],
                $items, array_keys($items))],
        ];
    }
}
