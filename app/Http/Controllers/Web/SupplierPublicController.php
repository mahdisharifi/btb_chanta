<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Category;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Helpers\PriceHelper;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class SupplierPublicController extends Controller
{
    // ══════════════════════════════════════════════════════════
    //  CONSTANTS
    // ══════════════════════════════════════════════════════════

    private const DEFAULT_PER_PAGE = 24;
    private const ALLOWED_PER_PAGE = [12, 24, 48, 96];
    private const ALLOWED_SORT_OPTIONS = [
        'latest', 'oldest', 'popular', 'price_low', 'price_high',
        'name_asc', 'name_desc', 'most_viewed',
    ];

    private const CACHE_TTL_SHORT  = 600;
    private const CACHE_TTL_MEDIUM = 1800;
    private const CACHE_TTL_LONG   = 3600;

    // ══════════════════════════════════════════════════════════
    //  INDEX - لیست تأمین‌کنندگان
    // ══════════════════════════════════════════════════════════

    /**
     * صفحه لیست تأمین‌کنندگان
     * URL: /suppliers
     */
    public function index(Request $request): View
    {
        $request->validate([
            'q'        => 'nullable|string|max:200',
            'province' => 'nullable|string|max:100',
            'city'     => 'nullable|string|max:100',
            'sort'     => 'nullable|string|in:newest,name_asc,name_desc,most_products,verified_first',
            'per_page' => 'nullable|integer|in:' . implode(',', self::ALLOWED_PER_PAGE),
        ]);

        $query = Supplier::query()
            ->where('is_active', true)
            ->withCount([
                'products' => fn($q) => $q->where('is_active', true)
                    ->where('status', ProductStatus::ACTIVE),
            ]);

        // ─── فیلتر جستجو ───
        if ($request->filled('q')) {
            $term = '%' . $request->q . '%';
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'LIKE', $term)
                    ->orWhere('company_name_en', 'LIKE', $term)
                    ->orWhere('brand_name', 'LIKE', $term)
                    ->orWhere('description', 'LIKE', $term);
            });
        }

        // ─── فیلتر استان/شهر ───
        if ($request->filled('province')) {
            $query->where('province', $request->province);
        }
        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        // ─── مرتب‌سازی ───
        $sort = $request->get('sort', 'newest');
        $query = match ($sort) {
            'newest'        => $query->orderByDesc('created_at'),
            'name_asc'      => $query->orderBy('company_name'),
            'name_desc'     => $query->orderByDesc('company_name'),
            'most_products' => $query->orderByDesc('products_count'),
            'verified_first'=> $query->orderByDesc('is_verified')->orderByDesc('created_at'),
            default         => $query->orderByDesc('created_at'),
        };

        $perPage   = in_array((int)$request->per_page, self::ALLOWED_PER_PAGE) ? (int)$request->per_page : self::DEFAULT_PER_PAGE;
        $suppliers = $query->paginate($perPage)->withQueryString();

        // ─── داده‌های جانبی ───
        $provinces = Cache::remember('web:suppliers:provinces', self::CACHE_TTL_LONG, function () {
            return Supplier::where('is_active', true)
                ->whereNotNull('province')
                ->select('province')
                ->distinct()
                ->orderBy('province')
                ->pluck('province');
        });

        $stats = Cache::remember('web:suppliers:stats', self::CACHE_TTL_MEDIUM, function () {
            return [
                'total'    => Supplier::where('is_active', true)->count(),
                'verified' => Supplier::where('is_active', true)->where('is_verified', true)->count(),
                'cities'   => Supplier::where('is_active', true)->whereNotNull('city')->distinct('city')->count('city'),
            ];
        });

        return view('pages.suppliers.index', [
            'suppliers'   => $suppliers,
            'provinces'   => $provinces,
            'stats'       => $stats,
            'searchQuery' => $request->get('q', ''),
            'sortBy'      => $sort,
            'perPage'     => $perPage,
            'seo'         => [
                'title'       => 'تأمین‌کنندگان — ' . config('app.name'),
                'description' => 'لیست تأمین‌کنندگان تأیید شده با محصولات متنوع و قیمت‌های رقابتی',
                'keywords'    => 'تأمین‌کنندگان, عمده‌فروشی, B2B',
                'canonical_url' => route('suppliers.index'),
            ],
            'breadcrumbs' => [
                ['name' => 'خانه', 'url' => route('home')],
                ['name' => 'تأمین‌کنندگان', 'url' => null],
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  SHOW - صفحه پروفایل تأمین‌کننده
    // ══════════════════════════════════════════════════════════

    /**
     * صفحه جزئیات تأمین‌کننده
     * URL: /suppliers/{slug}
     */
    public function show(Request $request, string $slug): View
    {
        $supplier = Supplier::where('slug', $slug)
            ->where('is_active', true)
            ->withCount([
                'products' => fn($q) => $q->where('is_active', true)
                    ->where('status', ProductStatus::ACTIVE),
            ])
            ->firstOrFail();

        // ─── تشخیص نقش کاربر ───
        $userRole = $this->resolveUserRole();
        $canViewContact = in_array($userRole, ['retailer_premium', 'supplier', 'super_admin']);

        // ─── محصولات تأمین‌کننده ───
        $productsQuery = Product::where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->where('status', ProductStatus::ACTIVE)
            ->with([
                'category:id,name,slug,icon',
                'galleries' => fn($q) => $q->where('file_type', 'image')
                    ->orderByDesc('is_primary')->orderBy('sort_order')->limit(1),
                'retailPrices' => fn($q) => $q->where('is_published', true)
                    ->orderBy('retail_price')->limit(1),
                'prices' => fn($q) => $q->where('is_active', true)
                    ->where(function ($sub) {
                        $sub->whereNull('valid_until')->orWhere('valid_until', '>=', now());
                    })
                    ->orderBy('price')->limit(1),
            ]);

        // ─── فیلتر دسته‌بندی ───
        if ($request->filled('category')) {
            $productsQuery->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // ─── مرتب‌سازی ───
        $sort = $request->get('sort', 'latest');
        $productsQuery = match ($sort) {
            'latest'      => $productsQuery->orderByDesc('created_at'),
            'oldest'      => $productsQuery->orderBy('created_at'),
            'popular'     => $productsQuery->orderByDesc('view_count'),
            'price_low'   => $productsQuery->orderByRaw('(SELECT MIN(retail_price) FROM retail_prices WHERE retail_prices.product_id = products.id AND is_published = 1) ASC'),
            'price_high'  => $productsQuery->orderByRaw('(SELECT MIN(retail_price) FROM retail_prices WHERE retail_prices.product_id = products.id AND is_published = 1) DESC'),
            'name_asc'    => $productsQuery->orderBy('title'),
            default       => $productsQuery->orderByDesc('created_at'),
        };

        $perPage  = in_array((int)$request->per_page, self::ALLOWED_PER_PAGE) ? (int)$request->per_page : self::DEFAULT_PER_PAGE;
        $products = $productsQuery->paginate($perPage)->withQueryString();

        // ─── دسته‌بندی‌های تأمین‌کننده ───
        $supplierCategories = Category::whereHas('products', function ($q) use ($supplier) {
            $q->where('supplier_id', $supplier->id)
                ->where('is_active', true)
                ->where('status', ProductStatus::ACTIVE);
        })
            ->where('is_active', true)
            ->withCount(['products' => function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)
                    ->where('is_active', true)
                    ->where('status', ProductStatus::ACTIVE);
            }])
            ->orderByDesc('products_count')
            ->get();

        // ─── آمار تأمین‌کننده ───
        $supplierStats = Cache::remember(
            "web:supplier:{$supplier->id}:stats",
            self::CACHE_TTL_SHORT,
            function () use ($supplier) {
                $productIds = Product::where('supplier_id', $supplier->id)
                    ->where('is_active', true)
                    ->where('status', ProductStatus::ACTIVE)
                    ->pluck('id');

                return [
                    'total_products'   => $productIds->count(),
                    'total_categories' => Product::where('supplier_id', $supplier->id)
                        ->where('is_active', true)
                        ->distinct('category_id')
                        ->count('category_id'),
                    'total_views'      => Product::where('supplier_id', $supplier->id)
                        ->where('is_active', true)
                        ->sum('view_count'),
                    'avg_price'        => DB::table('retail_prices')
                        ->whereIn('product_id', $productIds)
                        ->where('is_published', true)
                        ->avg('retail_price'),
                    'min_price'        => DB::table('retail_prices')
                        ->whereIn('product_id', $productIds)
                        ->where('is_published', true)
                        ->min('retail_price'),
                    'max_price'        => DB::table('retail_prices')
                        ->whereIn('product_id', $productIds)
                        ->where('is_published', true)
                        ->max('retail_price'),
                    'featured_count'   => Product::where('supplier_id', $supplier->id)
                        ->where('is_active', true)
                        ->where('is_featured', true)
                        ->count(),
                ];
            }
        );

        // ─── SEO ───
        $seo = [
            'title'         => ($supplier->seo_title ?? $supplier->company_name) . ' — ' . config('app.name'),
            'description'   => $supplier->seo_description ?? "محصولات و اطلاعات تأمین‌کننده {$supplier->company_name}",
            'keywords'      => $supplier->seo_keywords ?? $supplier->company_name . ',' . ($supplier->brand_name ?? ''),
            'canonical_url' => route('suppliers.show', $slug),
            'og_title'      => $supplier->company_name,
            'og_description'=> "مشاهده محصولات {$supplier->company_name}",
            'og_image'      => $supplier->logo_path ? asset('storage/' . $supplier->logo_path) : asset('images/og-default.jpg'),
            'og_type'       => 'profile',
            'og_url'        => route('suppliers.show', $slug),
        ];

        // ─── Breadcrumbs ───
        $breadcrumbs = [
            ['name' => 'خانه', 'url' => route('home')],
            ['name' => 'تأمین‌کنندگان', 'url' => route('suppliers.index')],
            ['name' => $supplier->company_name, 'url' => null],
        ];

        return view('pages.suppliers.show', [
            'supplier'           => $supplier,
            'products'           => $products,
            'supplierCategories' => $supplierCategories,
            'supplierStats'      => $supplierStats,
            'userRole'           => $userRole,
            'canViewContact'     => $canViewContact,
            'sortBy'             => $sort,
            'perPage'            => $perPage,
            'searchQuery'        => $request->get('q', ''),
            'seo'                => $seo,
            'breadcrumbs'        => $breadcrumbs,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════

    private function resolveUserRole(): string
    {
        $user = Auth::user();

        if (!$user) return 'guest';
        if ($user->hasRole('super_admin')) return 'super_admin';
        if ($user->hasRole('supplier')) return 'supplier';
        if ($user->hasRole('retailer_premium')) return 'retailer_premium';
        if ($user->hasRole('retailer_basic')) return 'retailer_basic';

        return 'end_user';
    }
}
