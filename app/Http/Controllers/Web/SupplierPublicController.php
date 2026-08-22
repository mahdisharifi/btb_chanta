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
                'products as products_count' => fn($q) => $q
                    ->where('products.is_active', true)
                    ->where('products.status', ProductStatus::ACTIVE),
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
            // layout variables
            'seoTitle'       => 'تأمین‌کنندگان — ' . config('app.name'),
            'seoDescription' => 'لیست تأمین‌کنندگان تأیید شده با محصولات متنوع و قیمت‌های رقابتی در بازار B2B ایران',
            'seoKeywords'    => 'تأمین‌کنندگان, عمده‌فروشی, B2B, بازار تجاری',
            'canonical'      => route('suppliers.index'),
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
                'products as products_count' => fn($q) => $q
                    ->where('products.is_active', true)
                    ->where('products.status', ProductStatus::ACTIVE),
            ])
            ->firstOrFail();

        // ─── تشخیص نقش کاربر ───
        $userRole = $this->resolveUserRole();
        $canViewContact = in_array($userRole, ['retailer_premium', 'supplier', 'super_admin']);

        // ─── محصولات تأمین‌کننده (هر دو مسیر: supplier_id مستقیم + pivot) ───
        $productsQuery = Product::query()
            ->where('is_active', true)
            ->where('status', ProductStatus::ACTIVE)
            ->where(function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)
                  ->orWhereExists(function ($sub) use ($supplier) {
                      $sub->select(DB::raw(1))
                          ->from('product_supplier')
                          ->whereColumn('product_supplier.product_id', 'products.id')
                          ->where('product_supplier.supplier_id', $supplier->id)
                          ->where('product_supplier.is_active', true);
                  });
            })
            ->with([
                'category:id,name,slug,icon',
                'galleries' => fn($q) => $q->where('file_type', 'image')
                    ->orderByDesc('is_primary')->orderBy('sort_order')->limit(1),
                'retailPrices' => fn($q) => $q->where('is_published', true)
                    ->orderBy('retail_price')->limit(1),
                // بارگذاری قیمت‌های این supplier
                'prices' => fn($q) => $q->where('is_active', true)
                    ->bySupplier($supplier->id)
                    ->where(function ($sub) {
                        $sub->whereNull('valid_until')->orWhere('valid_until', '>=', now());
                    })
                    ->orderBy('price'),
            ]);

        // ─── فیلتر دسته‌بندی ───
        if ($request->filled('category')) {
            $cat = Cache::remember(
                "web:category:slug:{$request->category}",
                self::CACHE_TTL_MEDIUM,
                fn() => Category::where('slug', $request->category)->where('is_active', true)->first()
            );
            if ($cat) {
                $catIds = $cat->descendantsAndSelf()->pluck('id');
                $productsQuery->whereIn('products.category_id', $catIds);
            }
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

        // ─── دسته‌بندی‌های تأمین‌کننده (هر دو مسیر) ───
        // گرفتن category IDs که حداقل یک محصول از این supplier دارند
        $categoryIdsWithCounts = DB::table('products as p')
            ->select('p.category_id', DB::raw('COUNT(DISTINCT p.id) as products_count'))
            ->where('p.is_active', true)
            ->where('p.status', ProductStatus::ACTIVE->value)
            ->whereNull('p.deleted_at')
            ->where(function ($sub) use ($supplier) {
                $sub->where('p.supplier_id', $supplier->id)
                    ->orWhereExists(function ($ex) use ($supplier) {
                        $ex->select(DB::raw(1))
                           ->from('product_supplier as ps')
                           ->whereColumn('ps.product_id', 'p.id')
                           ->where('ps.supplier_id', $supplier->id)
                           ->where('ps.is_active', true);
                    });
            })
            ->groupBy('p.category_id')
            ->pluck('products_count', 'category_id');

        if ($categoryIdsWithCounts->isEmpty()) {
            $supplierCategories = collect();
        } else {
            $supplierCategories = Category::whereIn('id', $categoryIdsWithCounts->keys())
                ->where('is_active', true)
                ->select('id', 'name', 'slug', 'icon')
                ->get()
                ->map(fn($cat) => tap($cat, fn($c) =>
                    $c->products_count = $categoryIdsWithCounts->get($c->id, 0)
                ))
                ->sortByDesc('products_count')
                ->values();
        }

        // ─── آمار تأمین‌کننده ───
        $supplierStats = Cache::remember(
            "web:supplier:{$supplier->id}:stats",
            self::CACHE_TTL_SHORT,
            function () use ($supplier) {
                // یک کوئری برای آمار محصولات
                $stats = DB::table('products')
                    ->where(function ($q) use ($supplier) {
                        $q->where('supplier_id', $supplier->id)
                          ->orWhereExists(function ($sub) use ($supplier) {
                              $sub->select(DB::raw(1))
                                  ->from('product_supplier')
                                  ->whereColumn('product_supplier.product_id', 'products.id')
                                  ->where('product_supplier.supplier_id', $supplier->id)
                                  ->where('product_supplier.is_active', true);
                          });
                    })
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->selectRaw("
                        COUNT(DISTINCT id) as total_products,
                        COUNT(DISTINCT category_id) as total_categories,
                        SUM(view_count) as total_views,
                        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_count
                    ")
                    ->first();

                $productIds = DB::table('products')
                    ->where(function ($q) use ($supplier) {
                        $q->where('supplier_id', $supplier->id)
                          ->orWhereExists(function ($sub) use ($supplier) {
                              $sub->select(DB::raw(1))
                                  ->from('product_supplier')
                                  ->whereColumn('product_supplier.product_id', 'products.id')
                                  ->where('product_supplier.supplier_id', $supplier->id)
                                  ->where('product_supplier.is_active', true);
                          });
                    })
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->pluck('id');

                $priceStats = DB::table('product_prices')
                    ->whereIn('product_id', $productIds)
                    ->where('is_active', true)
                    ->selectRaw('MIN(price) as min_price, MAX(price) as max_price, AVG(price) as avg_price')
                    ->first();

                return [
                    'total_products'   => (int) ($stats->total_products ?? 0),
                    'total_categories' => (int) ($stats->total_categories ?? 0),
                    'total_views'      => (int) ($stats->total_views ?? 0),
                    'featured_count'   => (int) ($stats->featured_count ?? 0),
                    'min_price'        => $priceStats->min_price ?? null,
                    'max_price'        => $priceStats->max_price ?? null,
                    'avg_price'        => $priceStats->avg_price ? (int) round($priceStats->avg_price) : null,
                ];
            }
        );

        // ─── SEO — منطبق با layout variables + JSON-LD ───
        $canonicalUrl = route('suppliers.show', $slug);
        $ogImage      = $supplier->logo_path
            ? asset('storage/' . $supplier->logo_path)
            : asset('images/og-default.jpg');

        $seo = [
            'title'          => ($supplier->seo_title ?? $supplier->company_name) . ' — ' . config('app.name'),
            'description'    => $supplier->seo_description
                ?? "محصولات و اطلاعات تأمین‌کننده {$supplier->company_name} — " . config('app.name'),
            'keywords'       => $supplier->seo_keywords
                ?? implode(', ', array_filter([$supplier->company_name, $supplier->brand_name])),
            'canonical_url'  => $canonicalUrl,
            'og_title'       => $supplier->brand_name ?: $supplier->company_name,
            'og_description' => "مشاهده محصولات و پروفایل {$supplier->company_name} در " . config('app.name'),
            'og_image'       => $ogImage,
            'og_type'        => 'profile',
            'og_url'         => $canonicalUrl,
            'robots'         => 'index, follow',
        ];

        // JSON-LD structured data برای Google (Organization schema)
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $supplier->company_name,
            'url'      => $canonicalUrl,
            'logo'     => $ogImage,
            'description' => $seo['description'],
            'address'  => array_filter([
                '@type'           => 'PostalAddress',
                'addressLocality' => $supplier->city ?? null,
                'addressRegion'   => $supplier->province ?? null,
                'addressCountry'  => 'IR',
            ]),
        ];

        // ─── Breadcrumbs ───
        $breadcrumbs = [
            ['name' => 'خانه', 'url' => route('home')],
            ['name' => 'تأمین‌کنندگان', 'url' => route('suppliers.index')],
            ['name' => $supplier->company_name, 'url' => null],
        ];

        // ─── شبکه‌های اجتماعی ───
        $socialNetworks = $supplier->social_networks ?? [];

        return view('pages.suppliers.show', [
            'supplier'           => $supplier,
            'products'           => $products,
            'supplierCategories' => $supplierCategories,
            'supplierStats'      => $supplierStats,
            'userRole'           => $userRole,
            'canViewContact'     => $canViewContact,
            'socialNetworks'     => $socialNetworks,
            'sortBy'             => $sort,
            'perPage'            => $perPage,
            'searchQuery'        => $request->get('q', ''),
            'seo'                => $seo,
            'jsonLd'             => $jsonLd,
            'breadcrumbs'        => $breadcrumbs,
            // variables منطبق با layout
            'seoTitle'           => $seo['title'],
            'seoDescription'     => $seo['description'],
            'seoKeywords'        => $seo['keywords'],
            'canonical'          => $seo['canonical_url'],
            'ogTitle'            => $seo['og_title'],
            'ogDescription'      => $seo['og_description'],
            'ogImage'            => $seo['og_image'],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  SHOW BY ID — backward compat
    // ══════════════════════════════════════════════════════════

    /**
     * redirect از route قدیمی (/suppliers/profile/{id}) به route جدید (/suppliers/{slug})
     */
    public function showById(Request $request, Supplier $supplier): \Illuminate\Http\RedirectResponse
    {
        if (!$supplier->slug) {
            abort(404);
        }

        return redirect()->route('suppliers.show', $supplier->slug, 301);
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
