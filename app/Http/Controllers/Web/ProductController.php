<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Category;
use App\Models\RetailPrice;
use App\Models\Supplier;
use App\Models\ProductSupplier;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Models\AttributeValue;

class ProductController extends Controller
{
    // ══════════════════════════════════════════════════════════
    //  CONSTANTS & CONFIGURATION
    // ══════════════════════════════════════════════════════════
    /** تعداد محصولات پیش‌فرض در هر صفحه */
    private const DEFAULT_PER_PAGE = 24;

    /** مقادیر مجاز برای تعداد آیتم در صفحه */
    private const ALLOWED_PER_PAGE = [12, 24, 48, 96];

    /** مقادیر مجاز برای مرتب‌سازی */
    private const ALLOWED_SORT_OPTIONS = [
        'latest',
        'oldest',
        'popular',
        'price_low',
        'price_high',
        'name_asc',
        'name_desc',
        'most_viewed',
    ];

    /** کلیدهای کش */
    private const CACHE_CATEGORIES_TREE   = 'web:categories:tree:active';
    private const CACHE_CATEGORIES_COUNTS = 'web:categories:product_counts';
    private const CACHE_FEATURED_PRODUCTS = 'web:products:featured';
    private const CACHE_PRICE_RANGE       = 'web:products:price_range';

    /** مدت زمان کش (ثانیه) */
    private const CACHE_TTL_SHORT  = 600;    // 10 دقیقه
    private const CACHE_TTL_MEDIUM = 1800;   // 30 دقیقه
    private const CACHE_TTL_LONG   = 3600;   // 1 ساعت

    /** روابط مشترک برای Eager Loading در لیست */
    private const INDEX_RELATIONS = [
        'category:id,name,slug,icon,parent_id',
        'supplier:id,company_name,brand_name,logo_path,is_verified',
    ];

    // ══════════════════════════════════════════════════════════
    //  INDEX - لیست محصولات با فیلترهای پیشرفته
    // ══════════════════════════════════════════════════════════

    /**
     * صفحه لیست محصولات عمومی
     */
    public function index(Request $request): View
    {
        // ─── 1. اعتبارسنجی پارامترهای ورودی ───
        $validated = $this->validateIndexRequest($request);

        // ─── 2. ساخت کوئری پایه ───
        $query = $this->buildBaseProductQuery();

        // ─── 3. اعمال فیلترها ───
        $query = $this->applyCategoryFilter($query, $request);
        $query = $this->applyAttributeFilter($query, $request);
        $query = $this->applyPriceFilter($query, $request);
        $query = $this->applySupplierFilter($query, $request);
        $query = $this->applyStockFilter($query, $request);
        $query = $this->applyFeaturedFilter($query, $request);


        // ─── 4. اعمال جستجو ───
        $query = $this->applySearch($query, $request);

        // ─── 5. اعمال مرتب‌سازی ───
        $query = $this->applySorting($query, $request);

        // ─── 6. صفحه‌بندی ───
        $perPage  = $this->resolvePerPage($request);
        $products = $query->paginate($perPage)->withQueryString();

        // ─── 7. داده‌های جانبی (کش شده) ───
        $sidebarData = $this->getIndexSidebarData($request);

        // ─── 8. SEO Meta Tags ───
        $seoData = $this->buildIndexSeo($request);

        return view('pages.products.index', array_merge([
            'products'      => $products,
            'activeFilters' => $this->getActiveFilters($request),
            'sortBy'        => $request->get('sort', 'latest'),
            'perPage'       => $perPage,
            'searchQuery'   => $request->get('q', ''),
            'seo'           => $seoData,
        ], $sidebarData));
    }

    // ══════════════════════════════════════════════════════════
    //  CATEGORY COUNTS — endpoint برای فیلترهای وابسته
    // ══════════════════════════════════════════════════════════

    /**
     * تعداد محصولات هر دسته‌بندی با در نظر گرفتن فیلترهای فعال
     * URL: GET /products/filters/category-counts?supplier_id=X&...
     * Response: JSON { counts: { "category_id": count }, total: N }
     */
    public function getCategoryCounts(Request $request): \Illuminate\Http\JsonResponse
    {
        // ساخت کوئری با همان فیلترهای فعال (بجز category — تا همه دسته‌ها نمایش داده شوند)
        $query = $this->buildBaseProductQuery();
        $query = $this->applySupplierFilter($query, $request);
        $query = $this->applyPriceFilter($query, $request);
        $query = $this->applyStockFilter($query, $request);
        $query = $this->applyFeaturedFilter($query, $request);
        $query = $this->applySearch($query, $request);

        // تعداد مستقیم هر category_id
        $directCounts = (clone $query)
            ->select('products.category_id', DB::raw('COUNT(*) as count'))
            ->groupBy('products.category_id')
            ->pluck('count', 'category_id')
            ->map(fn($v) => (int) $v)
            ->toArray();

        // لود همه دسته‌بندی‌های فعال برای ساختن درخت
        $allCategories = Category::query()
            ->where('is_active', true)
            ->select('id', 'parent_id')
            ->get();

        // شروع با تعداد مستقیم
        $aggregated = [];
        foreach ($allCategories as $cat) {
            $aggregated[$cat->id] = $directCounts[$cat->id] ?? 0;
        }

        // تجمیع از برگ‌ها به ریشه — هر فرزند تعداد خود را به والد اضافه می‌کند
        // برای درستی، باید از عمیق‌ترین سطح شروع کنیم
        // ابتدا map والد هر node
        $parentMap = $allCategories->pluck('parent_id', 'id')->toArray();

        foreach ($allCategories as $cat) {
            if (!$cat->parent_id) continue;
            $childCount  = $aggregated[$cat->id]  ?? 0;
            $parentId    = $cat->parent_id;

            // حرکت به بالا در درخت
            while ($parentId && isset($aggregated[$parentId])) {
                $aggregated[$parentId] += $childCount;
                $parentId = $parentMap[$parentId] ?? null;
            }
        }

        // فقط تعداد مستقیم را در پاسخ بگذار (UI خودش تجمیع می‌کند با درخت server-side)
        // ولی aggregated برای hide/show کافی‌تر است
        return response()->json([
            'counts' => $aggregated,
            'total'  => array_sum($directCounts),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  FILTERED SUPPLIERS — endpoint برای فیلترهای وابسته
    // ══════════════════════════════════════════════════════════

    /**
     * لیست تأمین‌کنندگان با تعداد محصول، وابسته به فیلترهای فعال
     * URL: GET /products/filters/suppliers?category=slug&...
     * Response: JSON { suppliers: [{id, name, count},...] }
     */
    public function getFilteredSuppliers(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!in_array($this->resolveUserRole(), ['super_admin', 'supplier', 'retailer_premium'])) {
            return response()->json(['suppliers' => []]);
        }

        // کوئری پایه با فیلترهای فعال (بجز supplier — تا همه supplierها نمایش داده شوند)
        $baseQuery = $this->buildBaseProductQuery();
        $baseQuery = $this->applyCategoryFilter($baseQuery, $request);
        $baseQuery = $this->applyPriceFilter($baseQuery, $request);
        $baseQuery = $this->applyStockFilter($baseQuery, $request);
        $baseQuery = $this->applyFeaturedFilter($baseQuery, $request);
        $baseQuery = $this->applySearch($baseQuery, $request);

        // supplier_id های محصولات فیلترشده (مستقیم)
        $directSupplierCounts = (clone $baseQuery)
            ->select('products.supplier_id', DB::raw('COUNT(DISTINCT products.id) as cnt'))
            ->whereNotNull('products.supplier_id')
            ->groupBy('products.supplier_id')
            ->pluck('cnt', 'supplier_id')
            ->map(fn($v) => (int) $v)
            ->toArray();

        // supplier_id های از طریق pivot
        $pivotSupplierCounts = (clone $baseQuery)
            ->join('product_supplier', 'products.id', '=', 'product_supplier.product_id')
            ->where('product_supplier.is_active', true)
            ->select('product_supplier.supplier_id', DB::raw('COUNT(DISTINCT products.id) as cnt'))
            ->groupBy('product_supplier.supplier_id')
            ->pluck('cnt', 'supplier_id')
            ->map(fn($v) => (int) $v)
            ->toArray();

        // ادغام تعداد (union)
        $mergedCounts = $directSupplierCounts;
        foreach ($pivotSupplierCounts as $sid => $cnt) {
            $mergedCounts[$sid] = ($mergedCounts[$sid] ?? 0) + $cnt;
            // از double-counting محصولاتی که هم supplier_id دارند هم pivot جلوگیری می‌کنیم
            // با MAX به جای جمع
            $mergedCounts[$sid] = max($directSupplierCounts[$sid] ?? 0, $pivotSupplierCounts[$sid] ?? 0);
        }

        if (empty($mergedCounts)) {
            return response()->json(['suppliers' => []]);
        }

        $supplierIds = array_keys($mergedCounts);

        $suppliers = Supplier::query()
            ->select('id', 'company_name', 'brand_name', 'is_verified')
            ->where('is_active', true)
            ->whereIn('id', $supplierIds)
            ->orderBy('company_name')
            ->get()
            ->map(fn($s) => [
                'id'       => $s->id,
                'name'     => $s->brand_name ?: $s->company_name,
                'verified' => $s->is_verified,
                'count'    => $mergedCounts[$s->id] ?? 0,
            ]);

        return response()->json(['suppliers' => $suppliers]);
    }

    // ══════════════════════════════════════════════════════════
    //  PRODUCT DETAIL
    // ══════════════════════════════════════════════════════════

    /**
     * صفحه جزئیات محصول - بهینه‌شده
     *
     * بهبودها:
     * - کاهش تعداد کوئری‌ها با eager loading هوشمند
     * - حذف N+1 در روابط تو در تو
     * - استفاده از select برای محدود کردن ستون‌ها
     * - بهینه‌سازی محاسبات قیمت
     */
    public function show(Product $product): \Illuminate\Http\Response
    {
        // ─── 1. اطمینان از فعال بودن محصول ───
        abort_unless(
            $product->is_active && $product->status === \App\Enums\ProductStatus::ACTIVE,
            404
        );

        // ─── 2. افزایش بازدید (Rate Limited) ───
        $this->incrementViewCount($product, request());

        // ─── 3. ذخیره در بازدیدهای اخیر ───
        $this->trackRecentlyViewed($product->id, request());

        // ─── 4. بارگذاری روابط پایه - بهینه‌شده با select ───
        $product->load([
            'category:id,name,slug,parent_id' => [
                'parent:id,name,slug,parent_id' => [
                    'parent:id,name,slug,parent_id'
                ]
            ],
            'supplier:id,user_id,company_name,company_name_en,brand_name,logo_path,phone,mobile,email,website,city,province,is_verified,is_active',
            // 👇 مشخصات فنی با ساختار EAV اضافه شد 👇
            'attributeValues.attribute',
            // 👇 فیلدهای file_type و alt_text به این بخش اضافه شدند 👇
            'galleries' => fn($q) => $q->select(['id', 'product_id', 'file_path', 'is_primary', 'sort_order', 'file_type', 'alt_text', 'versions', 'base_name', 'is_seo_image'])
                ->orderByDesc('is_primary')
                ->orderBy('sort_order'),
            // 🔴 DEPRECATED: ساختار قدیمی prices حذف شد - اکنون از productSuppliers.prices استفاده می‌شود
            'retailPrices' => fn($q) => $q->select(['id', 'product_id', 'user_id', 'retail_price', 'is_published'])
                ->where('is_published', true)
                ->with('user:id,name')
                ->orderBy('retail_price', 'asc'),
        ]);

        // ─── 5. چند تأمین‌کننده: بارگذاری از جدول واسط - بهینه‌شده با ساختار ماتریسی ───
        $productSuppliers = $product->productSuppliers()
            ->active()
            ->select([
                'id', 'product_id', 'supplier_id', 'is_primary', 'sort_order',
                // ✅ فیلدهای گارانتی
                'has_warranty', 'warranty_type', 'warranty_value', 'warranty_provider',
                'warranty_coverage_type', 'warranty_type_coverage', 'warranty_duration_days',
                // ✅ فیلدهای تیراژها
                'tier_enabled', 'tier_percentages', 'auto_calculate_tiers'
            ])
            ->with([
                'supplier:id,user_id,company_name,company_name_en,brand_name,logo_path,phone,mobile,email,website,city,province,is_verified,is_active,slug',
                // بارگذاری قیمت‌های ماتریسی جدید با relations (با fallback به ساختار قدیمی)
                'prices' => fn($q) => $q->select(['id', 'product_supplier_id', 'price', 'price_type_id', 'tier_index', 'check_period_id', 'is_active', 'valid_from', 'valid_until'])
                    ->activeAndValid()
                    ->with([
                        'priceType:id,name,code',
                        'checkPeriod:id,name,days,is_active'
                    ])
                    ->orderBy('tier_index', 'asc')
                    ->orderBy('price', 'asc'),
            ])
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get();

        // ✅ فیلتر کردن تامین‌کنندگانی که حداقل یک قیمت فعال دارند
        $productSuppliers = $productSuppliers->filter(function($ps) {
            return $ps->prices->isNotEmpty();
        })->values();

        // ✅ سورت تامین‌کنندگان بر اساس تعداد بهترین قیمت‌ها
        if ($productSuppliers->count() > 1) {
            $productSuppliers = $this->sortSuppliersByBestPrices($productSuppliers, $product);
        }

        // ✅ مهم: قرار دادن productSuppliers لود شده در product برای استفاده در متدهای دیگر
        $product->setRelation('productSuppliers', $productSuppliers);

        $hasMultipleSuppliers = $productSuppliers->count() > 1;

        // بررسی اینکه آیا ساختار ماتریسی موجود است یا خیر
        // (اگر هر قیمتی وجود داشته باشد، ماتریس نمایش داده می‌شود)
        $hasMatrixPricing = $productSuppliers->flatMap(fn($ps) => $ps->prices)->isNotEmpty();

        // ─── 6. محاسبه قیمت‌های کلی - بهینه‌شده با ساختار ماتریسی ───
        $allSupplierPrices = $productSuppliers->flatMap(fn($ps) => $ps->prices);
        $usePivotPrices = $allSupplierPrices->isNotEmpty();

        if ($usePivotPrices) {
            $overallLowestPrice = $allSupplierPrices->min('price');
            $overallHighestPrice = $allSupplierPrices->max('price');
            $directPrices = collect();
        } else {
            // ✅ جمع‌آوری قیمت‌ها از productSuppliers (ساختار جدید)
            $directPrices = collect();
            if ($product->relationLoaded('productSuppliers')) {
                foreach ($product->productSuppliers as $ps) {
                    if ($ps->relationLoaded('prices')) {
                        $directPrices = $directPrices->merge($ps->prices->where('is_active', true));
                    }
                }
            }
            $overallLowestPrice = $directPrices->isNotEmpty() ? $directPrices->min('price') : null;
            $overallHighestPrice = $directPrices->isNotEmpty() ? $directPrices->max('price') : null;
        }

        // ─── 8. آمار مقایسه تأمین‌کنندگان ───
        $supplierStats = [];
        if ($usePivotPrices && $productSuppliers->isNotEmpty()) {
            $supplierStats = [
                'total_suppliers'   => $productSuppliers->count(),
                'lowest_price'      => $overallLowestPrice,
                'highest_price'     => $overallHighestPrice,
                'avg_price'         => (int) round($allSupplierPrices->avg('price')),
                'price_types_count' => $allSupplierPrices->pluck('price_type')->unique()->count(),
            ];
        }

        // ─── 9. قیمت‌های خرده‌فروشی (بدون کوئری اضافی) ───
        $retailPrices = $product->retailPrices;
        $displayRetailPrice = $retailPrices->first(); // چون قبلاً order شده

        // ─── 10. تعیین نقش و داده‌های قابل مشاهده ───
        $userRole = $this->resolveUserRole();
        $roleData = $this->resolveVisibleDataByRole($product, $userRole);

        if ($usePivotPrices && in_array($userRole, ['retailer_premium', 'super_admin', 'supplier'])) {
            $roleData['showWholesalePrices'] = true;
        }

        $showWholesalePrices = $roleData['showWholesalePrices'] ?? false;
        $showSupplierInfo    = $roleData['showSupplierInfo'] ?? false;
        $canViewSupplierInfo = $roleData['showSupplierInfo'] ?? false;

        // ─── 11. محصولات مرتبط ───
        $relatedProducts = $this->getRelatedProducts($product, 8);

        // ─── 12. بازدیدهای اخیر ───
        $recentlyViewed = $this->getRecentlyViewed($product->id, request(), 8);

        // ─── 13. ساخت Breadcrumb ───
        $breadcrumb = [];
        if ($product->category) {
            $categoryChain = $this->getCategoryAncestors($product->category);
            foreach ($categoryChain as $cat) {
                $breadcrumb[] = [
                    'name' => $cat->name ?? 'دسته‌بندی',
                    'url'  => route('categories.show', ['category' => $cat->slug ?? 'unknown']),
                ];
            }
        }
        $breadcrumb[] = ['name' => $product->title, 'url' => null];

        $breadcrumbs = array_merge(
            [['name' => 'خانه', 'url' => route('home')]],
            $breadcrumb
        );

        // ─── 14. ساخت SEO Data ───
        $seo = $this->buildSeoData($product, $breadcrumbs);

        // ─── 15. ارسال به View ───
        return response()->view('pages.products.show', [
            'product'               => $product,
            'productSuppliers'      => $productSuppliers,
            'hasMultipleSuppliers'  => $hasMultipleSuppliers,
            'hasMatrixPricing'      => $hasMatrixPricing,
            'overallLowestPrice'    => $overallLowestPrice,
            'overallHighestPrice'   => $overallHighestPrice,
            'retailPrices'          => $retailPrices,
            'relatedProducts'       => $relatedProducts,

            'breadcrumbs'           => $breadcrumbs,
            'seo'                   => $seo,
            'recentlyViewed'        => $recentlyViewed,
            'userRole'              => $userRole,
            'displayRetailPrice'    => $displayRetailPrice,

            'usePivotPrices'        => $usePivotPrices,
            'directPrices'          => $directPrices,
            'supplierStats'         => $supplierStats,

            'canViewSupplierInfo'   => $canViewSupplierInfo,
            'showWholesalePrices'   => $showWholesalePrices,
            'showSupplierInfo'      => $showSupplierInfo,

            'supplierInfo'          => $roleData['supplierInfo'] ?? null,
            'wholesalePrices'       => $roleData['wholesalePrices'] ?? collect(),
            'retailPrice'           => $roleData['retailPrice'] ?? null,
            'myRetailPrice'         => $roleData['myRetailPrice'] ?? null,
            'profitMargin'          => $roleData['profitMargin'] ?? null,
            'canSetRetailPrice'     => $roleData['canSetRetailPrice'] ?? false,
        ])
        ->header('Cache-Control', 'max-age=1800, public, must-revalidate')
        ->header('Expires', now()->addMinutes(30)->toRfc7231String());
    }








            // ══════════════════════════════════════════════════════════
    //  CATEGORY PRODUCTS - محصولات یک دسته‌بندی
    // ══════════════════════════════════════════════════════════

    /**
     * محصولات یک دسته‌بندی خاص
     * URL: /categories/{slug}
     */
    public function byCategory(Request $request, string $categorySlug): View
    {
        $category = Category::where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        // تمام زیردسته‌ها را جمع‌آوری کن
        $categoryIds = $this->getAllDescendantIds($category);

        // کوئری محصولات
        $query = $this->buildBaseProductQuery()
            ->whereIn('products.category_id', $categoryIds);

        // اعمال فیلترها، جستجو و مرتب‌سازی
        $query = $this->applyPriceFilter($query, $request);
        $query = $this->applySupplierFilter($query, $request);
        $query = $this->applyStockFilter($query, $request);
        $query = $this->applySearch($query, $request);
        $query = $this->applySorting($query, $request);

        $perPage  = $this->resolvePerPage($request);
        $products = $query->paginate($perPage)->withQueryString();

        // زیردسته‌های مستقیم
        $subcategories = Category::where('parent_id', $category->id)
            ->where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('status', ProductStatus::ACTIVE)])
            ->orderBy('sort_order')
            ->get();

        $sidebarData = $this->getIndexSidebarData($request);
        $breadcrumbs = $this->buildCategoryBreadcrumb($category);
        $seoData     = $this->buildCategorySeo($category);

        return view('pages.categories.show', array_merge([
            'category'      => $category,
            'subcategories' => $subcategories,
            'products'      => $products,
            'activeFilters' => $this->getActiveFilters($request),
            'sortBy'        => $request->get('sort', 'latest'),
            'perPage'       => $perPage,
            'searchQuery'   => $request->get('q', ''),
            'breadcrumbs'   => $breadcrumbs,
            'seo'           => $seoData,
        ], $sidebarData));
    }

    // ══════════════════════════════════════════════════════════
    //  SEARCH - صفحه نتایج جستجو
    // ══════════════════════════════════════════════════════════

    /**
     * صفحه نتایج جستجو
     * URL: /search?q={query}
     */
    public function search(Request $request): View
    {
        $searchQuery = trim($request->get('q', ''));

        if (empty($searchQuery)) {
            return redirect()->route('products.index');
        }

        $query = $this->buildBaseProductQuery();
        $query = $this->applyFullTextSearch($query, $searchQuery);
        $query = $this->applyPriceFilter($query, $request);
        $query = $this->applySupplierFilter($query, $request);
        $query = $this->applySorting($query, $request);

        $perPage  = $this->resolvePerPage($request);
        $products = $query->paginate($perPage)->withQueryString();

        $sidebarData = $this->getIndexSidebarData($request);

        return view('pages.products.search', array_merge([
            'products'      => $products,
            'searchQuery'   => $searchQuery,
            'activeFilters' => $this->getActiveFilters($request),
            'sortBy'        => $request->get('sort', 'latest'),
            'perPage'       => $perPage,
            'seo'           => [
                'title'       => "جستجوی «{$searchQuery}» — " . config('app.name'),
                'description' => "نتایج جستجو برای «{$searchQuery}» در فروشگاه",
                'robots'      => 'noindex, follow',
                'canonical'   => url('/search') . '?q=' . urlencode($searchQuery),
            ],
        ], $sidebarData));
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: QUERY BUILDERS
    // ══════════════════════════════════════════════════════════

    /**
     * ساخت کوئری پایه محصولات فعال
     */
    private function buildBaseProductQuery(): Builder
    {
        return Product::query()
            ->select([
                'products.id',
                'products.title',
                'products.slug',
                'products.short_description',
                'products.sku',
                'products.category_id',
                'products.supplier_id',
                'products.view_count',
                'products.is_featured',
                'products.is_active',
                'products.status',
                'products.created_at',
                'products.updated_at',
            ])
            ->where('products.is_active', true)
            ->where('products.status', ProductStatus::ACTIVE)
            ->with([
                'category:id,name,slug,icon,parent_id',

                'supplier:id,company_name,brand_name,logo_path,is_verified',
                'galleries' => function ($q) {
                    $q->select([
                        'id', 'product_id', 'file_path', 'file_type',
                        'alt_text', 'sort_order', 'is_primary',
                        'versions', 'base_name', // 👈 اضافه شد برای getVersionUrl()
                    ])
                        ->where('file_type', 'image')
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->limit(1);
                },
                // ✅ استفاده از ساختار جدید: productSuppliers با prices
                'productSuppliers' => function ($q) {
                    $q->where('is_active', true);
                },
                'productSuppliers.prices' => function ($q) {
                    $q->where('is_active', true)
                        ->where(function ($sub) {
                            $sub->whereNull('valid_until')
                                ->orWhere('valid_until', '>=', now());
                        })
                        ->where(function ($sub) {
                            $sub->whereNull('valid_from')
                                ->orWhere('valid_from', '<=', now());
                        });
                },
                'productSuppliers.supplier:id,company_name,brand_name',
                'retailPrices' => function ($q) {
                    $q->select([
                        'id', 'product_id', 'user_id', 'retail_price', 'is_published',
                    ])
                        ->where('is_published', true)
                        ->orderBy('retail_price', 'asc');
                },
            ]);
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: FILTERS
    // ══════════════════════════════════════════════════════════

    /**
     * اعتبارسنجی پارامترهای ورودی صفحه لیست
     */
    private function validateIndexRequest(Request $request): array
    {
        return $request->validate([
            'q'            => 'nullable|string|max:200',
            'category'     => 'nullable|string|max:100',
            'min_price'    => 'nullable|numeric|min:0',
            'max_price'    => 'nullable|numeric|min:0',
            'supplier_id'   => 'nullable|array',
            'supplier_id.*' => 'integer|exists:suppliers,id',
            'in_stock'     => 'nullable|in:0,1',
            'featured'     => 'nullable|in:0,1',
            'sort'         => 'nullable|string|in:' . implode(',', self::ALLOWED_SORT_OPTIONS),
            'per_page'     => 'nullable|integer|in:' . implode(',', self::ALLOWED_PER_PAGE),
            'page'         => 'nullable|integer|min:1',
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'array'],
            'attributes.*.*' => ['integer', 'exists:attribute_values,id'],
        ]);
    }


    private function applyAttributeFilter(Builder $query, Request $request): Builder
    {
        $selectedAttributes = $request->input('attributes', []);

        if (!is_array($selectedAttributes) || empty($selectedAttributes)) {
            return $query;
        }

        $normalizedAttributes = collect($selectedAttributes)
            ->filter(fn ($value) => is_array($value) && !empty($value))
            ->map(fn ($value) => collect($value)
                ->filter(fn ($item) => filled($item) && is_numeric($item))
                ->map(fn ($item) => (int) $item)
                ->unique()
                ->values()
                ->all()
            )
            ->filter(fn ($value) => !empty($value));

        if ($normalizedAttributes->isEmpty()) {
            return $query;
        }

        foreach ($normalizedAttributes as $attributeId => $valueIds) {
            $query->whereHas('attributeValues', function ($attributeValueQuery) use ($attributeId, $valueIds) {
                $attributeValueQuery
                    ->where('attribute_values.attribute_id', (int) $attributeId)
                    ->whereIn('attribute_values.id', $valueIds);
            });
        }

        return $query;
    }



    /**
     * فیلتر بر اساس دسته‌بندی (شامل تمام زیردسته‌ها)
     */
    private function applyCategoryFilter(Builder $query, Request $request): Builder
    {
        if (!$request->filled('category')) {
            return $query;
        }

        $category = Cache::remember(
            "web:category:slug:{$request->category}",
            self::CACHE_TTL_MEDIUM,
            fn() => Category::where('slug', $request->category)
                ->where('is_active', true)
                ->first()
        );

        if ($category) {
            $categoryIds = $this->getAllDescendantIds($category);
            $query->whereIn('products.category_id', $categoryIds);
        }

        return $query;
    }

    /**
     * فیلتر بر اساس بازه قیمت
     * فقط کاربرانی که به قیمت دسترسی دارند می‌توانند این فیلتر را اعمال کنند.
     * قیمت‌ها در دیتابیس به تومان هستند.
     */
    private function applyPriceFilter(Builder $query, Request $request): Builder
    {
        if (!$request->filled('min_price') && !$request->filled('max_price')) {
            return $query;
        }

        // فقط کاربرانی که به قیمت دسترسی دارند می‌توانند فیلتر کنند
        if (!$this->userCanSeePrice()) {
            return $query;
        }

        $minPrice = $request->filled('min_price') ? (int) $request->min_price : null;
        $maxPrice = $request->filled('max_price') ? (int) $request->max_price : null;

        $query->whereHas('prices', function ($priceQuery) use ($minPrice, $maxPrice) {
            $priceQuery->where('is_active', true)
                ->where(function ($sub) {
                    $sub->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', now());
                });

            if ($minPrice !== null) {
                $priceQuery->where('price', '>=', $minPrice);
            }
            if ($maxPrice !== null) {
                $priceQuery->where('price', '<=', $maxPrice);
            }
        });

        return $query;
    }

    /**
     * آیا کاربر جاری دسترسی به مشاهده قیمت دارد؟
     * 
     * ✅ بازنویسی شده با استفاده از PriceVisibilityService
     */
    private function userCanSeePrice(): bool
    {
        $priceVisibilityService = app(\App\Services\PriceVisibilityService::class);
        return $priceVisibilityService->canFilterByPrice(Auth::user());
    }

    /**
     * فیلتر بر اساس تأمین‌کننده (multi-select)
     * محصول می‌تواند از طریق supplier_id مستقیم یا جدول pivot product_supplier به تأمین‌کننده وصل باشد.
     */
    private function applySupplierFilter(Builder $query, Request $request): Builder
    {
        $supplierIds = $this->resolveSupplierIds($request);

        if (empty($supplierIds)) {
            return $query;
        }

        $query->where(function ($q) use ($supplierIds) {
            $q->whereIn('products.supplier_id', $supplierIds)
              ->orWhereExists(function ($sub) use ($supplierIds) {
                  $sub->select(DB::raw(1))
                      ->from('product_supplier')
                      ->whereColumn('product_supplier.product_id', 'products.id')
                      ->whereIn('product_supplier.supplier_id', $supplierIds)
                      ->where('product_supplier.is_active', true);
              });
        });

        return $query;
    }

    /**
     * خواندن و نرمال‌سازی supplier_id از request (آرایه یا scalar)
     */
    private function resolveSupplierIds(Request $request): array
    {
        $raw = $request->input('supplier_id');

        if (empty($raw)) {
            return [];
        }

        return collect(is_array($raw) ? $raw : [$raw])
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * فیلتر بر اساس وضعیت موجودی
     */
    private function applyStockFilter(Builder $query, Request $request): Builder
    {
        if (!$request->filled('in_stock')) {
            return $query;
        }

        if ($request->in_stock == '1') {
            $query->where('products.status', '!=', ProductStatus::OUT_OF_STOCK)
                ->whereHas('prices', function ($q) {
                    $q->where('is_active', true)
                        ->where(function ($sub) {
                            $sub->whereNull('valid_until')
                                ->orWhere('valid_until', '>=', now());
                        });
                });
        }

        return $query;
    }

    /**
     * فیلتر محصولات ویژه
     */
    private function applyFeaturedFilter(Builder $query, Request $request): Builder
    {
        if ($request->filled('featured') && $request->featured == '1') {
            $query->where('products.is_featured', true);
        }

        return $query;
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: SEARCH
    // ══════════════════════════════════════════════════════════

    /**
     * جستجوی ساده — استفاده در صفحه index
     */
    private function applySearch(Builder $query, Request $request): Builder
    {
        if (!$request->filled('q')) {
            return $query;
        }

        return $this->applyFullTextSearch($query, trim($request->q));
    }

    /**
     * جستجوی پیشرفته چندفیلدی
     */
    private function applyFullTextSearch(Builder $query, string $searchTerm): Builder
    {
        if (empty($searchTerm)) {
            return $query;
        }

        $escapedTerm = str_replace(['%', '_'], ['\%', '\_'], $searchTerm);
        $likeTerm    = "%{$escapedTerm}%";

        $query->where(function (Builder $q) use ($likeTerm, $escapedTerm) {
            $q->where('products.title', 'LIKE', $likeTerm)
                ->orWhere('products.short_description', 'LIKE', $likeTerm)
                ->orWhere('products.sku', 'LIKE', $likeTerm);

            $q->orWhereHas('supplier', function (Builder $supplierQuery) use ($likeTerm) {
                $supplierQuery->where('company_name', 'LIKE', $likeTerm)
                    ->orWhere('brand_name', 'LIKE', $likeTerm);
            });

            $q->orWhereHas('category', function (Builder $categoryQuery) use ($likeTerm) {
                $categoryQuery->where('name', 'LIKE', $likeTerm);
            });
        });

        $query->orderByRaw("
            CASE
                WHEN products.title LIKE ? THEN 1
                WHEN products.sku = ? THEN 2
                WHEN products.short_description LIKE ? THEN 3
                ELSE 4
            END ASC
        ", [$likeTerm, $escapedTerm, $likeTerm]);

        return $query;
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: SORTING
    // ══════════════════════════════════════════════════════════

    /**
     * اعمال مرتب‌سازی بر اساس پارامتر ورودی
     */
    private function applySorting(Builder $query, Request $request): Builder
    {
        $sort = $request->get('sort', 'latest');

        if (!in_array($sort, self::ALLOWED_SORT_OPTIONS)) {
            $sort = 'latest';
        }

        return match ($sort) {
            'latest'      => $query->orderByDesc('products.created_at'),
            'oldest'      => $query->orderBy('products.created_at'),
            'popular',
            'most_viewed' => $query->orderByDesc('products.view_count'),
            'name_asc'    => $query->orderBy('products.title'),
            'name_desc'   => $query->orderByDesc('products.title'),
            'price_low'   => $this->sortByPrice($query, 'asc'),
            'price_high'  => $this->sortByPrice($query, 'desc'),
            default       => $query->orderByDesc('products.created_at'),
        };
    }

    /**
     * مرتب‌سازی بر اساس قیمت با Subquery
     */
    private function sortByPrice(Builder $query, string $direction): Builder
    {
        $userRole = $this->resolveUserRole();

        if ($userRole === 'retailer_premium') {
            $query->addSelect([
                'sort_price' => DB::table('product_prices')
                    ->selectRaw('MIN(price)')
                    ->whereColumn('product_prices.product_id', 'products.id')
                    ->where('product_prices.is_active', true)
                    ->where(function ($sub) {
                        $sub->whereNull('valid_until')
                            ->orWhere('valid_until', '>=', now());
                    })
                    ->limit(1),
            ]);
        } else {
            $query->addSelect([
                'sort_price' => DB::table('retail_prices')
                    ->selectRaw('MIN(retail_price)')
                    ->whereColumn('retail_prices.product_id', 'products.id')
                    ->where('retail_prices.is_published', true)
                    ->limit(1),
            ]);
        }

        $query->orderByRaw("sort_price IS NULL ASC")
            ->orderBy('sort_price', $direction);

        return $query;
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: PRODUCT DETAIL HELPERS
    // ══════════════════════════════════════════════════════════

    /**
     * سورت تامین‌کنندگان بر اساس تعداد بهترین قیمت‌ها
     * 
     * @param \Illuminate\Support\Collection $productSuppliers
     * @param Product $product
     * @return \Illuminate\Support\Collection
     */
    private function sortSuppliersByBestPrices($productSuppliers, $product)
    {
        // محاسبه تعداد قیمت‌های فعال برای هر supplier
        $supplierScores = $productSuppliers->map(function($ps) use ($product) {
            // ✅ tier_enabled از model به صورت array برمی‌گردد (cast شده)
            $tierEnabled = $ps->tier_enabled ?? [];
            $activePrices = 0;
            
            // شمارش قیمت‌های فعال
            foreach ($ps->prices as $price) {
                // بررسی فعال بودن تیراژ
                if (!empty($tierEnabled) && !($tierEnabled[$price->tier_index] ?? true)) {
                    continue; // تیراژ غیرفعال
                }
                
                // شمارش قیمت فعال
                $activePrices++;
            }
            
            return [
                'ps' => $ps,
                'score' => $activePrices,
                'is_primary' => $ps->is_primary ? 1 : 0,
            ];
        });
        
        // سورت بر اساس:
        // 1. is_primary (تامین‌کننده اصلی اول)
        // 2. تعداد قیمت‌های فعال (بیشترین اول)
        // 3. sort_order (ترتیب دستی)
        $sorted = $supplierScores->sortBy([
            ['is_primary', 'desc'],
            ['score', 'desc'],
            ['ps.sort_order', 'asc'],
        ])->pluck('ps');
        
        return $sorted->values();
    }

    /**
     * افزایش شمارنده بازدید با Rate Limiting
     */
    private function incrementViewCount(Product $product, Request $request): void
    {
        $key = "product_view:{$product->id}:" . $request->ip();

        RateLimiter::attempt(
            key: $key,
            maxAttempts: 1,
            callback: function () use ($product) {
                $product->incrementQuietly('view_count');
            },
            decaySeconds: 300
        );
    }

    /**
     * تشخیص نقش کاربر فعلی
     */
    private function resolveUserRole(): string
    {
        $user = Auth::user();

        if (!$user) {
            return 'guest';
        }

        if ($user->hasRole('super_admin')) {
            return 'super_admin';
        }

        if ($user->hasRole('supplier')) {
            return 'supplier';
        }

        if ($user->hasRole('retailer_premium')) {
            return 'retailer_premium';
        }

        if ($user->hasRole('retailer_basic')) {
            return 'retailer_basic';
        }

        return 'end_user';
    }

    /**
     * تعیین داده‌های قابل مشاهده بر اساس نقش کاربر
     * 
     * ✅ بازنویسی شده با استفاده از PriceVisibilityService
     * این متد حالا از سرویس یکپارچه استفاده می‌کند و دیگر hard-coded نیست
     */
    private function resolveVisibleDataByRole(Product $product, string $userRole): array
    {
        // استفاده از Service برای دریافت دسترسی‌ها
        $priceVisibilityService = app(\App\Services\PriceVisibilityService::class);
        $user = Auth::user();

        // ✅ دریافت داده‌های اولیه از Service
        $visibilityData = $priceVisibilityService->getVisibleDataForProduct($product, $user);

        $data = [
            'showSupplierInfo'    => $visibilityData['showSupplierInfo'],
            'showWholesalePrices' => $visibilityData['showWholesalePrice'],
            'showRetailPrice'     => $visibilityData['showRetailPrice'],
            'canSetRetailPrice'   => $visibilityData['canSetRetailPrice'],
            'supplierInfo'        => null,
            'wholesalePrices'     => collect(),
            'retailPrice'         => null,
            'myRetailPrice'       => null,
            'profitMargin'        => null,
        ];

        // ─── قیمت خرده‌فروشی (برای کسانی که دسترسی دارند) ───
        if ($data['showRetailPrice']) {
            $lowestRetailPrice = $product->retailPrices
                ->where('is_published', true)
                ->sortBy('retail_price')
                ->first();

            $data['retailPrice'] = $lowestRetailPrice;
        }

        // ─── اطلاعات تامین‌کننده (اگر دسترسی دارد) ───
        if ($data['showSupplierInfo'] && $product->supplier) {
            // برای retailer_premium فقط اطلاعات عمومی
            if ($userRole === 'retailer_premium') {
                $data['supplierInfo'] = [
                    'company_name' => $product->supplier->company_name ?? null,
                    'brand_name'   => $product->supplier->brand_name ?? null,
                    'logo_path'    => $product->supplier->logo_path ?? null,
                    'is_verified'  => $product->supplier->is_verified ?? false,
                    'phone'        => $product->supplier->phone ?? null,
                    'mobile'       => $product->supplier->mobile ?? null,
                    'email'        => $product->supplier->email ?? null,
                    'website'      => $product->supplier->website ?? null,
                ];
            } else {
                // برای supplier و super_admin اطلاعات کامل
                $data['supplierInfo'] = $product->supplier;
            }
        }

        // ─── قیمت‌های عمده (اگر دسترسی دارد) ───
        if ($data['showWholesalePrices']) {
            $wholesalePrices = collect();
            
            if ($product->relationLoaded('productSuppliers')) {
                foreach ($product->productSuppliers as $ps) {
                    if ($ps->relationLoaded('prices')) {
                        $activePrices = $ps->prices->where('is_active', true);
                        
                        // ✅ فیلتر بر اساس انواع قیمت مجاز (اگر محدودیت وجود دارد)
                        $filteredPrices = $priceVisibilityService->filterPricesByVisibility(
                            $activePrices, 
                            $user
                        );
                        
                        $wholesalePrices = $wholesalePrices->merge($filteredPrices);
                    }
                }
            }
            
            $data['wholesalePrices'] = $wholesalePrices;
        }

        // ─── قیمت خرده‌فروشی خود کاربر (برای کسانی که می‌توانند ثبت کنند) ───
        if ($data['canSetRetailPrice'] && $user) {
            $myPrice = $product->retailPrices
                ->where('user_id', $user->id)
                ->first();

            $data['myRetailPrice'] = $myPrice;

            // ─── محاسبه حاشیه سود (فقط برای retailer_premium) ───
            if ($userRole === 'retailer_premium' && $myPrice && $data['wholesalePrices']->isNotEmpty()) {
                $lowestWholesale = $data['wholesalePrices']->min('price');
                
                if ($lowestWholesale > 0) {
                    $profitAmount     = $myPrice->retail_price - $lowestWholesale;
                    $profitPercentage = round(($profitAmount / $lowestWholesale) * 100, 1);

                    $data['profitMargin'] = [
                        'wholesale_price'   => $lowestWholesale,
                        'retail_price'      => $myPrice->retail_price,
                        'profit_amount'     => $profitAmount,
                        'profit_percentage' => $profitPercentage,
                    ];
                }
            }
        }

        return $data;
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: RELATED & RECENTLY VIEWED
    // ══════════════════════════════════════════════════════════

    /**
     * محصولات مرتبط (هم‌دسته‌بندی)
     */
    private function getRelatedProducts(Product $product, int $limit = 8): Collection
    {
        $cacheKey = "web:product:{$product->id}:related:{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SHORT, function () use ($product, $limit) {
            return Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->where('status', ProductStatus::ACTIVE)
                ->with([
                    'galleries' => fn($q) => $q->where('file_type', 'image')
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->limit(1),
                    'retailPrices' => fn($q) => $q->where('is_published', true)
                        ->orderBy('retail_price')
                        ->limit(1),
                    'category:id,name,slug',
                    // ✅ اضافه شده: eager loading برای قیمت‌های عمده
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
                ])
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        });
    }

    /**
     * ذخیره محصول در لیست بازدیدهای اخیر (Session)
     */
    private function trackRecentlyViewed(int $productId, Request $request): void
    {
        $recentIds = session()->get('recently_viewed', []);

        $recentIds = array_values(array_diff($recentIds, [$productId]));
        array_unshift($recentIds, $productId);

        $recentIds = array_slice($recentIds, 0, 20);

        session()->put('recently_viewed', $recentIds);
    }

    /**
     * دریافت محصولات اخیراً بازدید شده
     */
    private function getRecentlyViewed(int $excludeProductId, Request $request, int $limit = 8): Collection
    {
        $recentIds = session()->get('recently_viewed', []);

        $recentIds = array_values(array_diff($recentIds, [$excludeProductId]));
        $recentIds = array_slice($recentIds, 0, $limit);

        if (empty($recentIds)) {
            return collect();
        }

        return Product::whereIn('id', $recentIds)
            ->where('is_active', true)
            ->where('status', ProductStatus::ACTIVE)
            ->with([
                'galleries' => fn($q) => $q->where('file_type', 'image')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->limit(1),
                'retailPrices' => fn($q) => $q->where('is_published', true)
                    ->orderBy('retail_price')
                    ->limit(1),
                'category:id,name,slug',
                // ✅ اضافه شده: eager loading برای قیمت‌های عمده
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
            ])
            ->get()
            ->sortBy(function ($product) use ($recentIds) {
                return array_search($product->id, $recentIds);
            });
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: SIDEBAR & VIEW DATA
    // ══════════════════════════════════════════════════════════

    /**
     * داده‌های ساید‌بار صفحه لیست (کش شده)
     */
    private function getIndexSidebarData(Request $request): array
    {
        $filterableAttributes = collect();
        $selectedAttributeFilters = $request->input('attributes', []);
        $supplierIds = $this->resolveSupplierIds($request);

        // وقتی supplier انتخاب شده، categories بدون کش و با تعداد وابسته ساخته می‌شود
        if (!empty($supplierIds)) {
            $categoriesTree = $this->buildCategoriesTreeForSuppliers($supplierIds);
        } else {
            $categoriesTree = Cache::remember('categories.tree.active', now()->addHours(6), function () {
                return $this->buildCategoriesTree();
            });
        }

        $priceRange = Cache::remember('products.price_range', now()->addHours(1), function () {
            return [
                'min' => \App\Models\ProductPrice::query()->where('is_active', true)->min('price') ?? 0,
                'max' => \App\Models\ProductPrice::query()->where('is_active', true)->max('price') ?? 0,
            ];
        });

        $canSeeSupplierFilter = in_array($this->resolveUserRole(), ['super_admin', 'supplier', 'retailer_premium']);

        $suppliers = $canSeeSupplierFilter
            ? Supplier::query()
                ->select('id', 'company_name', 'brand_name', 'logo_path', 'is_verified')
                ->where('is_active', true)
                ->selectSub(
                    DB::table('products')
                        ->selectRaw('COUNT(*)')
                        ->where('products.is_active', true)
                        ->where('products.status', \App\Enums\ProductStatus::ACTIVE->value)
                        ->whereNull('products.deleted_at')
                        ->where(function ($q) {
                            $q->whereColumn('products.supplier_id', 'suppliers.id')
                              ->orWhereExists(function ($sub) {
                                  $sub->select(DB::raw(1))
                                      ->from('product_supplier')
                                      ->whereColumn('product_supplier.product_id', 'products.id')
                                      ->whereColumn('product_supplier.supplier_id', 'suppliers.id')
                                      ->where('product_supplier.is_active', true);
                              });
                        }),
                    'products_count'
                )
                ->orderBy('company_name')
                ->get()
            : collect();

        $sortOptions = [
            'latest' => 'جدیدترین',
            'oldest' => 'قدیمی‌ترین',
            'price_asc' => 'ارزان‌ترین',
            'price_desc' => 'گران‌ترین',
            'popular' => 'پربازدیدترین',
        ];

        $perPageOptions = [24, 48, 72, 96];

        if ($request->filled('category')) {
            $category = Category::query()
                ->where('slug', $request->string('category')->toString())
                ->first();

            if ($category) {
                $categoryIds = $category->descendantsAndSelf()->pluck('id');

                $filterableAttributes = Attribute::query()
                    ->where('is_filterable', true)
                    ->whereHas('categories', function ($query) use ($categoryIds) {
                        $query->whereIn('categories.id', $categoryIds);
                    })
                    ->with([
                        'values' => function ($query) use ($categoryIds) {
                            $query->whereHas('products', function ($productQuery) use ($categoryIds) {
                                $productQuery
                                    ->whereIn('products.category_id', $categoryIds)
                                    ->where('products.is_active', true)
                                    ->where('products.status', 'active');
                            })->orderBy('value');
                        },
                    ])
                    ->orderBy('name')
                    ->get()
                    ->filter(fn ($attribute) => $attribute->values->isNotEmpty())
                    ->values();
            }
        }

        return [
            'categories' => $categoriesTree,
            'priceRange' => $priceRange,
            'suppliers'                => $suppliers,
            'canSeeSupplierFilter'     => $canSeeSupplierFilter,
            'sortOptions' => $sortOptions,
            'perPageOptions' => $perPageOptions,
            'filterableAttributes' => $filterableAttributes,
            'selectedAttributeFilters' => $selectedAttributeFilters,
            'canSeePriceFilter' => $this->userCanSeePrice(),
        ];
    }

    /**
     * لیست گزینه‌های مرتب‌سازی
     */
    private function getSortOptions(): array
    {
        return [
            'latest'      => 'جدیدترین',
            'oldest'      => 'قدیمی‌ترین',
            'popular'     => 'محبوب‌ترین',
            'most_viewed' => 'پربازدیدترین',
            'price_low'   => 'ارزان‌ترین',
            'price_high'  => 'گران‌ترین',
            'name_asc'    => 'نام (الف-ی)',
            'name_desc'   => 'نام (ی-الف)',
        ];
    }

    /**
     * ساخت categories tree بدون فیلتر — برای حالت عمومی (کش می‌شود)
     */
    private function buildCategoriesTree(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->withCount(['products as total_products_count' => function ($q) {
                $q->where('products.is_active', true)->where('products.status', 'active');
            }])
            ->with([
                'children' => function ($q) {
                    $q->where('is_active', true)->orderBy('sort_order')
                      ->withCount(['products as total_products_count' => function ($sq) {
                          $sq->where('products.is_active', true)->where('products.status', 'active');
                      }])
                      ->with([
                          'children' => function ($sq) {
                              $sq->where('is_active', true)->orderBy('sort_order')
                                 ->withCount(['products as active_products_count' => function ($ssq) {
                                     $ssq->where('products.is_active', true)->where('products.status', 'active');
                                 }]);
                          }
                      ]);
                }
            ])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * ساخت categories tree وابسته به یک یا چند supplier
     * تعداد محصولات هر دسته فقط از محصولات آن supplierها محاسبه می‌شود.
     */
    private function buildCategoriesTreeForSuppliers(array $supplierIds): \Illuminate\Database\Eloquent\Collection
    {
        $supplierProductsConstraint = function ($q) use ($supplierIds) {
            $q->where('products.is_active', true)
              ->where('products.status', 'active')
              ->where(function ($sub) use ($supplierIds) {
                  $sub->whereIn('products.supplier_id', $supplierIds)
                      ->orWhereExists(function ($ex) use ($supplierIds) {
                          $ex->select(DB::raw(1))
                             ->from('product_supplier')
                             ->whereColumn('product_supplier.product_id', 'products.id')
                             ->whereIn('product_supplier.supplier_id', $supplierIds)
                             ->where('product_supplier.is_active', true);
                      });
              });
        };

        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->withCount(['products as total_products_count' => $supplierProductsConstraint])
            ->with([
                'children' => function ($q) use ($supplierProductsConstraint) {
                    $q->where('is_active', true)->orderBy('sort_order')
                      ->withCount(['products as total_products_count' => $supplierProductsConstraint])
                      ->with([
                          'children' => function ($sq) use ($supplierProductsConstraint) {
                              $sq->where('is_active', true)->orderBy('sort_order')
                                 ->withCount(['products as active_products_count' => $supplierProductsConstraint]);
                          }
                      ]);
                }
            ])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * فیلترهای فعال فعلی (برای نمایش badge)
     */
    private function getActiveFilters(Request $request): array
    {
        $filters = [];

        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            $filters['category'] = [
                'label' => 'دسته‌بندی: ' . ($category->name ?? $request->category),
                'key'   => 'category',
                'value' => $request->category,
            ];
        }

        if ($request->filled('min_price')) {
            $filters['min_price'] = [
                'label' => 'حداقل قیمت: ' . number_format($request->min_price) . ' تومان',
                'key'   => 'min_price',
                'value' => $request->min_price,
            ];
        }

        if ($request->filled('max_price')) {
            $filters['max_price'] = [
                'label' => 'حداکثر قیمت: ' . number_format($request->max_price) . ' تومان',
                'key'   => 'max_price',
                'value' => $request->max_price,
            ];
        }

        if ($request->filled('supplier_id')) {
            $supplierIds = $this->resolveSupplierIds($request);
            if (!empty($supplierIds)) {
                $supplierNames = \App\Models\Supplier::whereIn('id', $supplierIds)
                    ->pluck('company_name', 'id');
                foreach ($supplierIds as $sid) {
                    $filters["supplier_id_{$sid}"] = [
                        'label'      => 'تأمین‌کننده: ' . ($supplierNames[$sid] ?? $sid),
                        'key'        => 'supplier_id',
                        'remove_url' => route('products.index', array_merge(
                            request()->except('page'),
                            ['supplier_id' => array_values(array_filter($supplierIds, fn($id) => $id !== $sid))]
                        )),
                    ];
                }
            }
        }

        if ($request->filled('in_stock') && $request->in_stock == '1') {
            $filters['in_stock'] = [
                'label' => 'فقط موجود',
                'key'   => 'in_stock',
                'value' => '1',
            ];
        }

        if ($request->filled('featured') && $request->featured == '1') {
            $filters['featured'] = [
                'label' => 'فقط ویژه',
                'key'   => 'featured',
                'value' => '1',
            ];
        }

        if ($request->filled('q')) {
            $filters['search'] = [
                'label' => 'جستجو: ' . $request->q,
                'key'   => 'q',
                'value' => $request->q,
            ];
        }

        $selectedAttributes = $request->input('attributes', []);

        if (is_array($selectedAttributes) && !empty($selectedAttributes)) {
            $attributeValueIds = collect($selectedAttributes)
                ->filter(fn ($valueIds) => is_array($valueIds) && !empty($valueIds))
                ->flatMap(fn ($valueIds) => $valueIds)
                ->filter(fn ($valueId) => filled($valueId) && is_numeric($valueId))
                ->map(fn ($valueId) => (int) $valueId)
                ->unique()
                ->values();

            if ($attributeValueIds->isNotEmpty()) {
                $attributeValues = AttributeValue::query()
                    ->with('attribute')
                    ->whereIn('id', $attributeValueIds)
                    ->get()
                    ->keyBy('id');

                foreach ($selectedAttributes as $attributeId => $valueIds) {
                    if (!is_array($valueIds) || empty($valueIds)) {
                        continue;
                    }

                    foreach ($valueIds as $valueId) {
                        if (!filled($valueId) || !is_numeric($valueId)) {
                            continue;
                        }

                        $valueId = (int) $valueId;
                        $attributeValue = $attributeValues->get($valueId);

                        if (!$attributeValue || !$attributeValue->attribute) {
                            continue;
                        }

                        $updatedAttributes = $selectedAttributes;

                        if (isset($updatedAttributes[$attributeId]) && is_array($updatedAttributes[$attributeId])) {
                            $updatedAttributes[$attributeId] = array_values(array_filter(
                                $updatedAttributes[$attributeId],
                                fn ($selectedValueId) => (int) $selectedValueId !== $valueId
                            ));

                            if (empty($updatedAttributes[$attributeId])) {
                                unset($updatedAttributes[$attributeId]);
                            }
                        }

                        $query = $request->except(['page']);
                        $query['attributes'] = $updatedAttributes;

                        if (empty($query['attributes'])) {
                            unset($query['attributes']);
                        }

                        $filters["attribute_{$attributeId}_{$valueId}"] = [
                            'label' => $attributeValue->attribute->name . ': ' . $attributeValue->value,
                            'key' => 'attributes',
                            'value' => $valueId,
                            'remove_url' => route('products.index', $query),
                        ];
                    }
                }
            }
        }

        return $filters;
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: BREADCRUMBS
    // ══════════════════════════════════════════════════════════

    /**
     * ساخت breadcrumb برای صفحه محصول
     */
    private function buildProductBreadcrumb($product): array
    {
        $breadcrumbs = [];

        $breadcrumbs[] = [
            'name' => 'خانه',
            'url'  => route('home'),
        ];

        if ($product->category) {
            $categoryChain = $this->getCategoryAncestors($product->category);

            foreach ($categoryChain as $cat) {
                $breadcrumbs[] = [
                    'name' => $cat->name ?? 'دسته‌بندی',
                    'url'  => route('categories.show', ['category' => $cat->slug ?? 'unknown']),
                ];
            }
        }

        $breadcrumbs[] = [
            'name' => $product->title ?? 'محصول',
            'url'  => null,
        ];

        return $breadcrumbs;
    }

    /**
     * ساخت breadcrumb برای صفحه دسته‌بندی
     */
    // ProductController.php

private function buildCategoryBreadcrumb(Category $category): array
{
    $breadcrumbs = [
        ['name' => 'خانه', 'url' => route('home')],
        ['name' => 'محصولات', 'url' => route('products.index')],
    ];

    $chain = $this->getCategoryChain($category);

    foreach ($chain as $index => $cat) {
        $isLast = ($index === count($chain) - 1);
        $breadcrumbs[] = [
            'name' => $cat->name,
            'url'  => $isLast ? null : route('categories.show', ['category' => $cat->slug]),
        ];
    }

    return $breadcrumbs;
}



    /**
     * بازیابی بازگشتی اجداد دسته‌بندی (از ریشه تا فعلی)
     */
   // ProductController.php

private function eagerLoadParents(Category $category, int $depth =2): void
{
    $relations = [];
    $path = 'parent';

    for ($i = 0; $i < $depth; $i++) {
        $relations[] = $path;
        $path .= '.parent';
    }

    $category->loadMissing($relations);
}

private function getCategoryAncestors(Category $category): array
{
    $this->eagerLoadParents($category);

    $ancestors = [];
    $current   = $category;
    $visited   = [];

    while ($current) {
        if (in_array($current->id, $visited, true)) {
            break;
        }

        $visited[] = $current->id;
        array_unshift($ancestors, $current);

        $current = $current->parent; // بدون کوئری جدید
    }

    return $ancestors;
}

private function getCategoryChain(Category $category): array
{
    $this->eagerLoadParents($category);

    $chain   = [$category];
    $current = $category;

    while ($current->parent) {
        array_unshift($chain, $current->parent);
        $current = $current->parent; // بدون کوئری جدید
    }

    return $chain;
}

    /**
     * تمام شناسه‌های زیردسته‌ها (بازگشتی)
     */
    private function getAllDescendantIds(Category $category): array
    {
        $ids = [$category->id];

        $children = Category::where('parent_id', $category->id)
            ->where('is_active', true)
            ->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $this->getAllDescendantIds($child));
        }

        return $ids;
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: SEO
    // ══════════════════════════════════════════════════════════

    /**
     * SEO برای صفحه محصول با Canonical URL صحیح
     * ✅ پیش‌فرض: متمرکز بر عمده‌فروشی B2B
     */
    private function buildSeoData(Product $product, array $breadcrumbs): array
    {
        $categoryName = $product->category?->name ?? 'محصولات';
        $siteName = config('app.name', 'عمده‌فروشی چن تا؟');

        // ✅ اولویت تصویر SEO: is_seo_image > is_primary > اولین تصویر
        $seoImage = $product->galleries?->where('is_seo_image', true)->where('file_type', 'image')->first()
            ?? $product->galleries?->where('is_primary', true)->where('file_type', 'image')->first()
            ?? $product->galleries?->where('file_type', 'image')->first();

        // ✅ استفاده از تصویر بهینه با fallback
        $imageUrl = null;
        if ($seoImage && method_exists($seoImage, 'getVersionUrl')) {
            $imageUrl = $seoImage->getVersionUrl('md') ?: asset('storage/' . $seoImage->file_path);
        } elseif ($seoImage) {
            $imageUrl = asset('storage/' . $seoImage->file_path);
        } else {
            $imageUrl = asset('images/placeholders/product-default.svg');
        }

        // استفاده از alt_text تصویر SEO (اگر موجود باشد)
        $imageAlt = $seoImage?->alt_text ?: ($product->title . ' - عمده‌فروشی ' . $siteName);

        // ✅ جمع‌آوری قیمت‌های عمده برای SEO
        $allPrices = collect();
        if ($product->relationLoaded('productSuppliers')) {
            foreach ($product->productSuppliers as $ps) {
                if ($ps->relationLoaded('prices')) {
                    $allPrices = $allPrices->merge(
                        $ps->prices->where('is_active', true)->pluck('price')
                    );
                }
            }
        }
        
        // ✅ ساخت متن قیمت عمده برای description
        $priceText = '';
        if ($allPrices->isNotEmpty()) {
            $lowestPrice = $allPrices->min();
            $highestPrice = $allPrices->max();
            
            if ($lowestPrice == $highestPrice) {
                $priceText = ' | قیمت عمده: ' . number_format($lowestPrice) . ' تومان';
            } else {
                $priceText = ' | قیمت عمده از ' . number_format($lowestPrice) . ' تومان';
            }
        } else {
            // fallback: قیمت خرده‌فروشی (با تاکید بر عمده)
            $retailPrice = $product->retailPrices?->where('is_published', true)->sortBy('retail_price')->first();
            if ($retailPrice) {
                $priceText = ' | قیمت از ' . number_format($retailPrice->retail_price) . ' تومان';
            }
        }

        // ✅ توضیحات SEO با تمرکز بر عمده‌فروشی
        if ($product->seo_description) {
            $seoDescription = $product->seo_description;
        } else {
            $baseDescription = strip_tags($product->short_description ?? '');
            if (empty($baseDescription)) {
                // fallback: توضیحات خودکار با تمرکز B2B
                $seoDescription = "خرید عمده {$product->title} با بهترین قیمت از {$siteName}. فروش عمده به صورت کارتن و پالت{$priceText}";
            } else {
                $seoDescription = mb_substr($baseDescription, 0, 100) . $priceText . ' | فروش عمده';
            }
        }

        // ✅ Canonical URL: همیشه به URL بدون پارامتر اشاره می‌کند
        $canonicalUrl = $product->seo_canonical_url ?? route('products.show', ['product' => $product->slug]);

        // ✅ عنوان SEO با تمرکز بر عمده‌فروشی
        $defaultTitle = $product->seo_title 
            ?? "خرید عمده {$product->title} | {$categoryName} | {$siteName}";

        // ✅ کلمات کلیدی با تمرکز B2B
        $defaultKeywords = $product->seo_keywords
            ?? "{$product->title} عمده,خرید عمده {$product->title},{$categoryName} عمده,قیمت عمده {$product->title},فروش کارتنی,فروش عمده";

        return [
            'title'             => $defaultTitle,
            'description'       => mb_substr($seoDescription, 0, 160),
            'keywords'          => $defaultKeywords,
            'canonical_url'     => $canonicalUrl,
            'og_title'          => $product->seo_title ?? "خرید عمده {$product->title}",
            'og_description'    => mb_substr($seoDescription, 0, 200),
            'og_image'          => $imageUrl,
            'og_type'           => 'product',
            'og_url'            => url('/products/' . $product->slug),
            'schema'            => $this->buildProductSchema($product, $breadcrumbs),
            'breadcrumb_schema' => $this->buildBreadcrumbSchema($breadcrumbs),
        ];
    }

    /**
     * ساخت Schema.org برای محصول با قیمت‌های عمده
     * 
     * @param Product $product
     * @param array $breadcrumbs
     * @return array
     */
    private function buildProductSchema(Product $product, array $breadcrumbs): array
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product->title ?? '',
            'description' => strip_tags($product->short_description ?? 'خرید عمده ' . ($product->title ?? '')),
            'sku'         => $product->sku ?? '',
            'url'         => url('/products/' . $product->slug),
            'productID'   => $product->sku ?? $product->id,
        ];

        // ✅ تصویر با اولویت SEO Image (نسخه sm برای سرعت بیشتر)
        $seoImage = $product->galleries?->where('is_seo_image', true)->where('file_type', 'image')->first()
            ?? $product->galleries?->where('is_primary', true)->where('file_type', 'image')->first()
            ?? $product->galleries?->where('file_type', 'image')->first();
            
        if ($seoImage) {
            if (method_exists($seoImage, 'getVersionUrl')) {
                // استفاده از نسخه sm برای سرعت بیشتر در نتایج جستجو
                $schema['image'] = $seoImage->getVersionUrl('sm') ?: $seoImage->getVersionUrl('md') ?: asset('storage/' . $seoImage->file_path);
            } else {
                $schema['image'] = asset('storage/' . $seoImage->file_path);
            }
        }

        if ($product->category) {
            $schema['category'] = $product->category->name ?? '';
        }

        // ✅ برند محصول
        if ($product->supplier) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $product->supplier->brand_name ?? $product->supplier->company_name ?? config('app.name'),
            ];
        }

        // ✅ جمع‌آوری قیمت‌های عمده از productSuppliers
        $allPrices = collect();
        
        // بررسی اینکه آیا relation productSuppliers لود شده
        if ($product->relationLoaded('productSuppliers')) {
            foreach ($product->productSuppliers as $ps) {
                if ($ps->relationLoaded('prices')) {
                    $allPrices = $allPrices->merge(
                        $ps->prices->where('is_active', true)->pluck('price')
                    );
                } elseif ($ps->prices) {
                    $allPrices = $allPrices->merge(
                        $ps->prices->pluck('price')
                    );
                }
            }
        }
        
        // اگر قیمت عمده داریم
        if ($allPrices->isNotEmpty()) {
            $lowestPrice = (float) $allPrices->min();
            $highestPrice = (float) $allPrices->max();
            
            $schema['offers'] = [
                '@type'         => 'AggregateOffer',
                'priceCurrency' => 'IRT', // ✅ تومان ایران (نه ریال)
                'lowPrice'      => $lowestPrice,
                'highPrice'     => $highestPrice,
                'offerCount'    => $allPrices->count(),
                'availability'  => $product->status === \App\Enums\ProductStatus::ACTIVE 
                    ? 'https://schema.org/InStock' 
                    : 'https://schema.org/OutOfStock',
                'url'           => url('/products/' . $product->slug),
                'priceValidUntil' => now()->addDay()->format('Y-m-d'), // ✅ اعتبار 1 روز
                'businessFunction' => 'http://purl.org/goodrelations/v1#Sell',
                'eligibleQuantity' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'unitText' => 'عدد'
                ],
                // ✅ اضافه کردن seller برای هر تأمین‌کننده
                'seller' => $product->productSuppliers->map(function($ps) {
                    return [
                        '@type' => 'Organization',
                        'name' => $ps->supplier->company_name ?? $ps->supplier->brand_name ?? 'تأمین‌کننده',
                    ];
                })->toArray(),
            ];
        } else {
            // fallback: قیمت خرده‌فروشی
            $retailPrice = $product->retailPrices?->where('is_published', true)->sortBy('retail_price')->first();
            if ($retailPrice) {
                $schema['offers'] = [
                    '@type'         => 'Offer',
                    'priceCurrency' => 'IRT', // ✅ تومان ایران (نه ریال)
                    'price'         => (float) $retailPrice->retail_price,
                    'availability'  => 'https://schema.org/InStock',
                    'url'           => url('/products/' . $product->slug),
                    'priceValidUntil' => now()->addDay()->format('Y-m-d'), // ✅ اعتبار 1 روز
                ];
            } else {
                // ✅ اگر هیچ قیمتی نیست، حداقل یک placeholder بذاریم
                $schema['offers'] = [
                    '@type'         => 'AggregateOffer',
                    'priceCurrency' => 'IRT', // ✅ تومان ایران (نه ریال)
                    'lowPrice'      => 0,
                    'highPrice'     => 0,
                    'offerCount'    => 0,
                    'availability'  => 'https://schema.org/OutOfStock',
                    'url'           => url('/products/' . $product->slug),
                ];
            }
        }

        // ✅ اضافه کردن audience برای B2B
        $schema['audience'] = [
            '@type' => 'BusinessAudience',
            'audienceType' => 'خرده‌فروشان و مشاغل'
        ];

        // ✅ اضافه کردن aggregateRating اگر نظرات داریم
        if ($product->reviews_count ?? 0 > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $product->average_rating ?? 0,
                'reviewCount' => $product->reviews_count ?? 0,
            ];
        }

        return $schema;
    }

    /**
     * Schema.org BreadcrumbList JSON-LD
     */
    private function buildBreadcrumbSchema(array $breadcrumbs): array
    {
        $items = [];
        foreach ($breadcrumbs as $index => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => $crumb['name'] ?? '',
                'item'     => $crumb['url'] ?? request()->url(),
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * SEO برای صفحه لیست
     * ✅ پیش‌فرض: متمرکز بر عمده‌فروشی B2B
     */
    private function buildIndexSeo(Request $request): array
    {
        $title = 'فروش عمده محصولات';
        $description = 'خرید عمده محصولات با بهترین قیمت از عمده‌فروشی چن تا؟ — مشاهده و مقایسه قیمت‌های عمده‌فروشی انواع محصولات';

        if ($request->filled('q')) {
            $title = "خرید عمده {$request->q}";
            $description = "قیمت عمده {$request->q} — مقایسه قیمت‌های عمده‌فروشی {$request->q} از چندین تامین‌کننده";
        }

        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $title = "خرید عمده {$category->name}";
                $description = $category->seo_description
                    ?: "قیمت عمده {$category->name} — مشاهده و خرید عمده محصولات {$category->name} با بهترین قیمت";
            }
        }

        return [
            'title'       => $title . ' | ' . config('app.name'),
            'description' => mb_substr($description, 0, 160),
            'keywords'    => $request->get('q', 'خرید عمده, قیمت عمده, فروش عمده, عمده‌فروشی'),
            'canonical'   => $request->fullUrlWithQuery(
                array_diff_key($request->query(), ['page' => null])
            ),
            'robots'      => 'index, follow',
            'og' => [
                'title'       => $title,
                'description' => mb_substr($description, 0, 200),
                'type'        => 'website',
                'url'         => $request->fullUrl(),
                'image'       => asset('images/og-default.jpg'),
            ],
            'schema' => [
                '@context' => 'https://schema.org',
                '@type'    => 'ItemList',
                'name'     => $title,
                'url'      => $request->fullUrl(),
            ],
        ];
    }

    /**
     * SEO برای صفحه دسته‌بندی
     * ✅ پیش‌فرض: متمرکز بر عمده‌فروشی B2B
     */
    private function buildCategorySeo(Category $category): array
    {
        $title       = $category->seo_title ?: "خرید عمده {$category->name}";
        $description = $category->seo_description
            ?: "قیمت عمده {$category->name} — خرید عمده و فروش کارتنی محصولات {$category->name} با بهترین قیمت از عمده‌فروشی چن تا؟";

        return [
            'title'       => $title . ' | ' . config('app.name'),
            'description' => mb_substr($description, 0, 160),
            'keywords'    => $category->seo_keywords ?: "{$category->name} عمده,خرید عمده {$category->name},قیمت عمده {$category->name},فروش کارتنی",
            'canonical'   => route('categories.show', ['category' => $category->slug]),
            'robots'      => 'index, follow',
            'og' => [
                'title'       => $title,
                'description' => mb_substr($description, 0, 200),
                'type'        => 'website',
                'url'         => route('categories.show', ['category' => $category->slug]),
                'image'       => $category->image_path
                    ? asset('storage/' . $category->image_path)
                    : asset('images/og-default.jpg'),
            ],
            'schema' => [
                '@context' => 'https://schema.org',
                '@type'    => 'ItemList',
                'name'     => $title,
                'url'      => route('categories.show', ['category' => $category->slug]),
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE: UTILITIES
    // ══════════════════════════════════════════════════════════

    /**
     * تعیین تعداد آیتم‌ها در هر صفحه
     */
    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', self::DEFAULT_PER_PAGE);

        return in_array($perPage, self::ALLOWED_PER_PAGE)
            ? $perPage
            : self::DEFAULT_PER_PAGE;
    }
}
