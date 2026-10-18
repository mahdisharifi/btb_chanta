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
            ->where('type', 'supplier') // ✅ فقط تأمین‌کنندگان
            ->addSelect([
                'total_products_count' => Product::selectRaw('COUNT(DISTINCT products.id)')
                    ->where('products.is_active', true)
                    ->where('products.status', ProductStatus::ACTIVE)
                    ->where(function ($q) {
                        $q->whereColumn('products.supplier_id', 'suppliers.id')
                          ->orWhereIn('products.id', function ($sub) {
                              $sub->select('product_supplier.product_id')
                                  ->from('product_supplier')
                                  ->whereColumn('product_supplier.supplier_id', 'suppliers.id')
                                  ->where('product_supplier.is_active', true);
                          });
                    }),
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
            'most_products' => $query->orderByDesc('total_products_count'),
            'verified_first'=> $query->orderByDesc('is_verified')->orderByDesc('created_at'),
            default         => $query->orderByDesc('created_at'),
        };

        $perPage   = in_array((int)$request->per_page, self::ALLOWED_PER_PAGE) ? (int)$request->per_page : self::DEFAULT_PER_PAGE;
        $suppliers = $query->paginate($perPage)->withQueryString();

        // ─── داده‌های جانبی ───
        $provinces = Cache::remember('web:suppliers:provinces', self::CACHE_TTL_LONG, function () {
            return Supplier::where('is_active', true)
                ->where('type', 'supplier') // ✅ فقط تأمین‌کنندگان
                ->whereNotNull('province')
                ->select('province')
                ->distinct()
                ->orderBy('province')
                ->pluck('province');
        });

        // ─── شهرها بر اساس استان انتخاب شده ───
        $cities = collect();
        if ($request->filled('province')) {
            $cities = Cache::remember(
                'web:suppliers:cities:' . $request->province, 
                self::CACHE_TTL_LONG, 
                function () use ($request) {
                    return Supplier::where('is_active', true)
                        ->where('type', 'supplier') // ✅ فقط تأمین‌کنندگان
                        ->where('province', $request->province)
                        ->whereNotNull('city')
                        ->select('city')
                        ->distinct()
                        ->orderBy('city')
                        ->pluck('city');
                }
            );
        }

        $stats = Cache::remember('web:suppliers:stats', self::CACHE_TTL_MEDIUM, function () {
            $totalProducts = Product::where('is_active', true)
                ->where('status', ProductStatus::ACTIVE)
                ->distinct('id')
                ->count('id');
            
            return [
                'total'          => Supplier::where('is_active', true)
                    ->where('type', 'supplier') // ✅ فقط تأمین‌کنندگان
                    ->count(),
                'verified'       => Supplier::where('is_active', true)
                    ->where('type', 'supplier') // ✅ فقط تأمین‌کنندگان
                    ->where('is_verified', true)
                    ->count(),
                'cities'         => Supplier::where('is_active', true)
                    ->where('type', 'supplier') // ✅ فقط تأمین‌کنندگان
                    ->whereNotNull('city')
                    ->distinct('city')
                    ->count('city'),
                'total_products' => $totalProducts,
            ];
        });

        return view('pages.suppliers.index', [
            'suppliers'   => $suppliers,
            'provinces'   => $provinces,
            'cities'      => $cities,
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
            ->pricedFirst()
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
                // بارگذاری تامین‌کننده و قیمت‌های این supplier
                'productSuppliers' => fn($q) => $q->where('supplier_id', $supplier->id)
                    ->with([
                        'prices' => fn($priceQ) => $priceQ->where('is_active', true)
                            ->where(function ($sub) {
                                $sub->whereNull('valid_until')->orWhere('valid_until', '>=', now());
                            })
                            ->orderBy('price')
                    ]),
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

        // ─── فیلتر جستجو ───
        if ($request->filled('q')) {
            $searchTerm = '%' . $request->q . '%';
            $productsQuery->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', $searchTerm)
                  ->orWhere('sku', 'LIKE', $searchTerm);
            });
        }

        // ─── فیلتر قیمت ───
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $minPrice = $request->get('min_price');
            $maxPrice = $request->get('max_price');
            
            $productsQuery->whereHas('productSuppliers.prices', function ($q) use ($minPrice, $maxPrice, $supplier) {
                $q->where('is_active', true)
                  ->where('product_supplier_id', function ($sub) use ($supplier) {
                      $sub->select('id')
                          ->from('product_supplier')
                          ->whereColumn('product_supplier_id', 'product_supplier.id')
                          ->where('supplier_id', $supplier->id);
                  });
                
                if ($minPrice) {
                    $q->where('price', '>=', (float) $minPrice);
                }
                if ($maxPrice) {
                    $q->where('price', '<=', (float) $maxPrice);
                }
            });
        }

        // ─── فیلتر وضعیت ───
        if ($request->featured == '1') {
            $productsQuery->where('is_featured', true);
        }
        if ($request->in_stock == '1') {
            $productsQuery->whereHas('productSuppliers', function ($q) use ($supplier) {
                $q->where('supplier_id', $supplier->id)
                  ->where('is_active', true)
                  ->whereHas('prices', function ($sub) {
                      $sub->where('is_active', true);
                  });
            });
        }

        // ─── مرتب‌سازی ───
        $sort = $request->get('sort', 'latest');
        $productsQuery = match ($sort) {
            'latest'      => $productsQuery->orderByDesc('created_at'),
            'oldest'      => $productsQuery->orderBy('created_at'),
            'popular'     => $productsQuery->orderByDesc('view_count'),
            'price_low'   => $productsQuery->orderByRaw('(SELECT MIN(price) FROM product_prices WHERE product_prices.product_supplier_id IN (SELECT id FROM product_supplier WHERE product_supplier.product_id = products.id AND supplier_id = ' . $supplier->id . ') AND is_active = 1) ASC'),
            'price_high'  => $productsQuery->orderByRaw('(SELECT MIN(price) FROM product_prices WHERE product_prices.product_supplier_id IN (SELECT id FROM product_supplier WHERE product_supplier.product_id = products.id AND supplier_id = ' . $supplier->id . ') AND is_active = 1) DESC'),
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
                // ✅ اصلاح شده: شامل هر دو مسیر supplier_id مستقیم + pivot
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
                    ->where('status', ProductStatus::ACTIVE->value)
                    ->whereNull('deleted_at')
                    ->selectRaw("
                        COUNT(DISTINCT id) as total_products,
                        COUNT(DISTINCT category_id) as total_categories,
                        SUM(view_count) as total_views,
                        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_count
                    ")
                    ->first();

                // ✅ دریافت ID محصولات یکتا برای محاسبه قیمت
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
                    ->where('status', ProductStatus::ACTIVE->value)
                    ->whereNull('deleted_at')
                    ->pluck('id');

                // ✅ محاسبه قیمت از جدول product_supplier.prices
                $priceStats = DB::table('product_prices')
                    ->join('product_supplier', 'product_prices.product_supplier_id', '=', 'product_supplier.id')
                    ->where('product_supplier.supplier_id', $supplier->id)
                    ->where('product_prices.is_active', true)
                    ->whereIn('product_prices.product_id', $productIds)
                    ->selectRaw('MIN(product_prices.price) as min_price, MAX(product_prices.price) as max_price, AVG(product_prices.price) as avg_price')
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
        $ogImage      = $supplier->logoUrl('medium') ?: asset('images/og-default.jpg');

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
            'sortOptions'        => [
                'latest'      => 'جدیدترین',
                'oldest'      => 'قدیمی‌ترین',
                'popular'     => 'محبوب‌ترین',
                'price_low'   => 'ارزان‌ترین',
                'price_high'  => 'گران‌ترین',
                'name_asc'    => 'نام (الف-ی)',
            ],
            'perPage'            => $perPage,
            'perPageOptions'     => self::ALLOWED_PER_PAGE,
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
