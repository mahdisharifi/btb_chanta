<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RetailPrice;
use App\Models\Product;
use App\Models\Category;
use App\Enums\ProductStatus;
use App\Helpers\PriceHelper;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class RetailerController extends Controller
{
    // ══════════════════════════════════════════════════════════
    //  CONSTANTS
    // ══════════════════════════════════════════════════════════

    private const DEFAULT_PER_PAGE = 24;
    private const ALLOWED_PER_PAGE = [12, 24, 48, 96];

    private const CACHE_TTL_SHORT  = 600;
    private const CACHE_TTL_MEDIUM = 1800;

    // ══════════════════════════════════════════════════════════
    //  INDEX - لیست کاسبان (فروشندگان خرده)
    // ══════════════════════════════════════════════════════════

    /**
     * صفحه لیست کاسبان
     * URL: /retailers
     */
    public function index(Request $request): View
    {
        $request->validate([
            'q'        => 'nullable|string|max:200',
            'sort'     => 'nullable|string|in:newest,name_asc,most_products,most_affordable',
            'per_page' => 'nullable|integer|in:' . implode(',', self::ALLOWED_PER_PAGE),
        ]);

        // کاسبانی که حداقل یک قیمت منتشرشده دارند
        $query = User::query()
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['retailer_basic', 'retailer_premium']))
            ->whereHas('retailPrices', fn($q) => $q->where('is_published', true))
            ->withCount([
                'retailPrices' => fn($q) => $q->where('is_published', true),
            ]);

        // ─── جستجو ───
        if ($request->filled('q')) {
            $term = '%' . $request->q . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                    ->orWhere('shop_name', 'LIKE', $term)
                    ->orWhere('city', 'LIKE', $term);
            });
        }

        // ─── مرتب‌سازی ───
        $sort = $request->get('sort', 'newest');
        $query = match ($sort) {
            'newest'          => $query->orderByDesc('created_at'),
            'name_asc'        => $query->orderBy('name'),
            'most_products'   => $query->orderByDesc('retail_prices_count'),
            'most_affordable' => $query->orderBy(
                RetailPrice::selectRaw('AVG(retail_price)')
                    ->whereColumn('user_id', 'users.id')
                    ->where('is_published', true)
            ),
            default           => $query->orderByDesc('created_at'),
        };

        $perPage   = in_array((int)$request->per_page, self::ALLOWED_PER_PAGE) ? (int)$request->per_page : self::DEFAULT_PER_PAGE;
        $retailers = $query->paginate($perPage)->withQueryString();

        // ─── آمار کلی ───
        $stats = Cache::remember('web:retailers:stats', self::CACHE_TTL_MEDIUM, function () {
            return [
                'total_retailers'    => User::whereHas('roles', fn($q) => $q->whereIn('name', ['retailer_basic', 'retailer_premium']))->count(),
                'active_retailers'   => User::whereHas('roles', fn($q) => $q->whereIn('name', ['retailer_basic', 'retailer_premium']))
                    ->whereHas('retailPrices', fn($q) => $q->where('is_published', true))
                    ->count(),
                'total_retail_prices'=> RetailPrice::where('is_published', true)->count(),
                'total_products'     => RetailPrice::where('is_published', true)->distinct('product_id')->count('product_id'),
            ];
        });

        return view('pages.retailers.index', [
            'retailers'   => $retailers,
            'stats'       => $stats,
            'searchQuery' => $request->get('q', ''),
            'sortBy'      => $sort,
            'perPage'     => $perPage,
            'seo'         => [
                'title'         => 'کاسبان — ' . config('app.name'),
                'description'   => 'لیست کاسبان و فروشندگان خرده‌فروشی با قیمت‌های رقابتی',
                'keywords'      => 'کاسبان, خرده‌فروشی, فروشندگان',
                'canonical_url' => route('retailers.index'),
            ],
            'breadcrumbs' => [
                ['name' => 'خانه', 'url' => route('home')],
                ['name' => 'کاسبان', 'url' => null],
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  SHOW - صفحه پروفایل کاسب
    // ══════════════════════════════════════════════════════════

    /**
     * صفحه جزئیات کاسب
     * URL: /retailers/{id}
     */
    public function show(Request $request, int $id): View
    {
        $retailer = User::whereHas('roles', fn($q) => $q->whereIn('name', ['retailer_basic', 'retailer_premium']))
            ->findOrFail($id);

        // ─── محصولات قیمت‌گذاری شده توسط کاسب ───
        $retailPricesQuery = RetailPrice::where('user_id', $retailer->id)
            ->where('is_published', true)
            ->with([
                'product' => function ($q) {
                    $q->where('is_active', true)
                        ->where('status', ProductStatus::ACTIVE)
                        ->with([
                            'category:id,name,slug,icon',
                            'galleries' => fn($gq) => $gq->where('file_type', 'image')
                                ->orderByDesc('is_primary')->orderBy('sort_order')->limit(1),
                            'supplier:id,company_name,brand_name,logo_path,logo_versions,is_verified',
                        ]);
                },
            ]);

        // ─── فیلتر دسته‌بندی ───
        if ($request->filled('category')) {
            $retailPricesQuery->whereHas('product.category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // ─── مرتب‌سازی ───
        $sort = $request->get('sort', 'cheapest');
        $retailPricesQuery = match ($sort) {
            'cheapest'  => $retailPricesQuery->orderBy('retail_price'),
            'expensive' => $retailPricesQuery->orderByDesc('retail_price'),
            'newest'    => $retailPricesQuery->orderByDesc('created_at'),
            default     => $retailPricesQuery->orderBy('retail_price'),
        };

        $perPage      = in_array((int)$request->per_page, self::ALLOWED_PER_PAGE) ? (int)$request->per_page : self::DEFAULT_PER_PAGE;
        $retailPrices = $retailPricesQuery->paginate($perPage)->withQueryString();

        // فیلتر محصولات غیرفعال
        $retailPrices->getCollection()->transform(function ($rp) {
            if (!$rp->product || !$rp->product->is_active) {
                return null;
            }
            return $rp;
        });
        $retailPrices->setCollection($retailPrices->getCollection()->filter());

        // ─── دسته‌بندی‌های کاسب ───
        $retailerCategories = Category::whereHas('products', function ($q) use ($retailer) {
            $q->where('is_active', true)
                ->where('status', ProductStatus::ACTIVE)
                ->whereHas('retailPrices', function ($rq) use ($retailer) {
                    $rq->where('user_id', $retailer->id)->where('is_published', true);
                });
        })
            ->where('is_active', true)
            ->withCount(['products' => function ($q) use ($retailer) {
                $q->where('is_active', true)
                    ->whereHas('retailPrices', fn($rq) => $rq->where('user_id', $retailer->id)->where('is_published', true));
            }])
            ->orderByDesc('products_count')
            ->get();

        // ─── آمار کاسب ───
        $retailerStats = Cache::remember(
            "web:retailer:{$retailer->id}:stats",
            self::CACHE_TTL_SHORT,
            function () use ($retailer) {
                $prices = RetailPrice::where('user_id', $retailer->id)
                    ->where('is_published', true);

                return [
                    'total_products' => (clone $prices)->distinct('product_id')->count('product_id'),
                    'total_prices'   => (clone $prices)->count(),
                    'avg_price'      => (clone $prices)->avg('retail_price'),
                    'min_price'      => (clone $prices)->min('retail_price'),
                    'max_price'      => (clone $prices)->max('retail_price'),
                    'categories'     => Product::where('is_active', true)
                        ->where('status', ProductStatus::ACTIVE)
                        ->whereHas('retailPrices', fn($q) => $q->where('user_id', $retailer->id)->where('is_published', true))
                        ->distinct('category_id')
                        ->count('category_id'),
                ];
            }
        );

        // ─── SEO ───
        $displayName = $retailer->shop_name ?? $retailer->name ?? 'کاسب';
        $seo = [
            'title'         => "فروشگاه {$displayName} — " . config('app.name'),
            'description'   => "محصولات و قیمت‌های {$displayName} — مشاهده و مقایسه قیمت‌ها",
            'keywords'      => $displayName . ', خرده‌فروشی, قیمت محصولات',
            'canonical_url' => route('retailers.show', $retailer->id),
        ];

        // ─── Breadcrumbs ───
        $breadcrumbs = [
            ['name' => 'خانه', 'url' => route('home')],
            ['name' => 'کاسبان', 'url' => route('retailers.index')],
            ['name' => $displayName, 'url' => null],
        ];

        return view('pages.retailers.show', [
            'retailer'           => $retailer,
            'retailPrices'       => $retailPrices,
            'retailerCategories' => $retailerCategories,
            'retailerStats'      => $retailerStats,
            'sortBy'             => $sort,
            'perPage'            => $perPage,
            'seo'                => $seo,
            'breadcrumbs'        => $breadcrumbs,
        ]);
    }
}
