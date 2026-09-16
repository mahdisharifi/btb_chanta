<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Slider;
use App\Enums\ProductStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    // ══════════════════════════════════════════════════════════
    //  CONSTANTS
    // ══════════════════════════════════════════════════════════

    private const CACHE_TTL_SHORT  = 600;    // 10 دقیقه
    private const CACHE_TTL_MEDIUM = 1800;   // 30 دقیقه
    private const CACHE_TTL_LONG   = 3600;   // 1 ساعت

    private const FEATURED_PRODUCTS_LIMIT  = 12;
    private const LATEST_PRODUCTS_LIMIT    = 12;
    private const POPULAR_PRODUCTS_LIMIT   = 8;
    private const TOP_CATEGORIES_LIMIT     = 12;
    private const TOP_SUPPLIERS_LIMIT      = 8;
    private const RECENT_VIEWED_LIMIT      = 8;
    private const MAX_SLIDERS              = 8;

    // ══════════════════════════════════════════════════════════
    //  INDEX — صفحه اصلی
    // ══════════════════════════════════════════════════════════

    public function index(Request $request): View
    {
        // ─── 1. اسلایدرها ───
        $sliders = $this->getActiveSliders();

        // ─── 2. دسته‌بندی‌ها ───
        $topCategories = $this->getTopCategories();

        // ─── 3. محصولات ویژه ───
        $featuredProducts = $this->getFeaturedProducts();

        // ─── 4. جدیدترین ───
        $latestProducts = $this->getLatestProducts();

        // ─── 5. پربازدید ───
        $popularProducts = $this->getPopularProducts();

        // ─── 6. تأمین‌کنندگان برتر ───
        $topSuppliers = $this->getTopSuppliers();

        // ─── 7. آمار ───
        $siteStats = $this->getSiteStats();

        // ─── 8. بازدیدهای اخیر ───
        $recentlyViewed = $this->getRecentlyViewedProducts($request);

        // ─── 9. نقش کاربر ───
        $userRole = $this->resolveUserRole();

        // ─── 10. SEO ───
        $seo = $this->buildHomeSeo();

        return view('pages.home.index', compact(
            'sliders',
            'topCategories',
            'featuredProducts',
            'latestProducts',
            'popularProducts',
            'topSuppliers',
            'siteStats',
            'recentlyViewed',
            'userRole',
            'seo'
        ));
    }

    // ══════════════════════════════════════════════════════════
    //  SLIDER: دریافت اسلایدرهای فعال
    // ══════════════════════════════════════════════════════════

    /**
     * واکشی اسلایدرهای فعال از دیتابیس (کش‌دار)
     *
     * - فقط اسلایدرهای is_active = true
     * - فقط در بازه زمانی starts_at / ends_at
     * - مرتب بر اساس sort_order
     * - حداکثر MAX_SLIDERS عدد
     */
    private function getActiveSliders(): Collection
    {
        return Cache::remember('home:sliders:active', self::CACHE_TTL_MEDIUM, function () {
            // ── بررسی وجود جدول sliders ──
            if (!\Schema::hasTable('sliders')) {
                return $this->getFallbackSliders();
            }

            $sliders = Slider::active()
                ->ordered()
                ->select([
                    'id',
                    'title',
                    'subtitle',
                    'description',
                    'image_path',
                    'mobile_image_path',
                    'link_url',
                    'link_text',
                    'link_target',
                    'bg_color',
                    'text_color',
                    'overlay_color',
                    'text_position',
                    'sort_order',
                ])
                ->limit(self::MAX_SLIDERS)
                ->get();

            // اگر جدول خالی بود → Fallback
            if ($sliders->isEmpty()) {
                return $this->getFallbackSliders();
            }

            return $sliders;
        });
    }

    /**
     * اسلایدرهای پیش‌فرض (وقتی جدول وجود ندارد یا خالی است)
     */
    private function getFallbackSliders(): Collection
    {
        return collect([
            (object) [
                'id'                 => 1,
                'title'              => 'به فروشگاه B2B خوش آمدید',
                'subtitle'           => 'خرید عمده با بهترین قیمت از تأمین‌کنندگان معتبر',
                'description'        => null,
                'image_path'         => null,
                'mobile_image_path'  => null,
                'link_url'           => '/products',
                'link_text'          => 'مشاهده محصولات',
                'link_target'        => '_self',
                'bg_color'           => '#1e40af',
                'text_color'         => '#ffffff',
                'overlay_color'      => 'rgba(0,0,0,0.3)',
                'text_position'      => 'right',
                'image_url'          => asset('images/sliders/default-1.jpg'),
                'mobile_image_url'   => asset('images/sliders/default-1.jpg'),
            ],
            (object) [
                'id'                 => 2,
                'title'              => 'تأمین‌کننده هستید؟',
                'subtitle'           => 'محصولات خود را با هزاران خرده‌فروش به اشتراک بگذارید',
                'description'        => null,
                'image_path'         => null,
                'mobile_image_path'  => null,
                'link_url'           => '/register',
                'link_text'          => 'ثبت‌نام تأمین‌کننده',
                'link_target'        => '_self',
                'bg_color'           => '#059669',
                'text_color'         => '#ffffff',
                'overlay_color'      => 'rgba(0,0,0,0.3)',
                'text_position'      => 'right',
                'image_url'          => asset('images/sliders/default-2.jpg'),
                'mobile_image_url'   => asset('images/sliders/default-2.jpg'),
            ],
            (object) [
                'id'                 => 3,
                'title'              => 'تخفیف‌های ویژه عمده',
                'subtitle'           => 'تا ۴۰٪ تخفیف برای خریدهای بالای ۱۰ میلیون تومان',
                'description'        => null,
                'image_path'         => null,
                'mobile_image_path'  => null,
                'link_url'           => '/products?featured=1',
                'link_text'          => 'مشاهده پیشنهادات',
                'link_target'        => '_self',
                'bg_color'           => '#dc2626',
                'text_color'         => '#ffffff',
                'overlay_color'      => 'rgba(0,0,0,0.4)',
                'text_position'      => 'center',
                'image_url'          => asset('images/sliders/default-3.jpg'),
                'mobile_image_url'   => asset('images/sliders/default-3.jpg'),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════
    //  بقیه متدها (بدون تغییر — از پیاده‌سازی قبلی)
    // ══════════════════════════════════════════════════════════

    private function getTopCategories(): Collection
    {
        return Cache::remember('home:categories:top', self::CACHE_TTL_LONG, function () {
            return Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->select([
                    'id', 'name', 'slug', 'icon', 'image_path',
                    'sort_order', 'description',
                ])
                ->withCount(['products as active_products_count' => function ($q) {
                    $q->where('is_active', true)
                        ->where('status', ProductStatus::ACTIVE);
                }])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(self::TOP_CATEGORIES_LIMIT)
                ->get()
                ->map(function ($cat) {
                    $cat->total_count = $this->getCategoryTotalCount($cat->id);
                    return $cat;
                });
        });
    }

    private function getCategoryTotalCount(int $categoryId): int
    {
        return Cache::remember(
            "home:cat_total:{$categoryId}",
            self::CACHE_TTL_LONG,
            function () use ($categoryId) {
                $allIds = [$categoryId];
                $parentIds = [$categoryId];

                for ($depth = 0; $depth < 5 && !empty($parentIds); $depth++) {
                    $childIds = Category::where('is_active', true)
                        ->whereIn('parent_id', $parentIds)
                        ->pluck('id')
                        ->toArray();

                    if (empty($childIds)) break;

                    $allIds = array_merge($allIds, $childIds);
                    $parentIds = $childIds;
                }

                return Product::where('is_active', true)
                    ->where('status', ProductStatus::ACTIVE)
                    ->whereIn('category_id', array_unique($allIds))
                    ->count();
            }
        );
    }

    private function getFeaturedProducts(): Collection
    {
        return Cache::remember('home:products:featured', self::CACHE_TTL_SHORT, function () {
            return $this->buildHomeProductQuery()
                ->where('products.is_featured', true)
                ->orderByDesc('products.updated_at')
                ->limit(self::FEATURED_PRODUCTS_LIMIT)
                ->get();
        });
    }

    private function getLatestProducts(): Collection
    {
        return Cache::remember('home:products:latest', self::CACHE_TTL_SHORT, function () {
            return $this->buildHomeProductQuery()
                ->orderByDesc('products.created_at')
                ->limit(self::LATEST_PRODUCTS_LIMIT)
                ->get();
        });
    }

    private function getPopularProducts(): Collection
    {
        return Cache::remember('home:products:popular', self::CACHE_TTL_MEDIUM, function () {
            return $this->buildHomeProductQuery()
                ->where('products.view_count', '>', 0)
                ->orderByDesc('products.view_count')
                ->limit(self::POPULAR_PRODUCTS_LIMIT)
                ->get();
        });
    }

    private function buildHomeProductQuery()
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
            ])
            ->where('products.is_active', true)
            ->where('products.status', ProductStatus::ACTIVE)
            ->with([
                'category:id,name,slug',
                'supplier:id,company_name,brand_name,slug,logo_path,is_verified',
                'galleries' => function ($q) {
                    $q->select([
                        'id', 'product_id', 'file_path', 'alt_text',
                        'sort_order', 'is_primary', 'file_type', 'versions', 'base_name',
                    ])
                        ->where('file_type', 'image')
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->limit(1);
                },
                // ✅ استفاده از ساختار جدید: productSuppliers با prices
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
                        'id', 'product_id', 'retail_price', 'is_published',
                    ])
                        ->where('is_published', true)
                        ->orderBy('retail_price', 'asc')
                        ->limit(1);
                },
            ]);
    }

    private function getTopSuppliers(): Collection
    {
        return Cache::remember('home:suppliers:top', self::CACHE_TTL_LONG, function () {
            return Supplier::query()
                ->where('is_active', true)
                ->where('is_verified', true)
                ->select([
                    'id', 'user_id', 'company_name', 'brand_name',
                    'logo_path', 'city', 'province', 'is_verified',
                    'description',
                ])
                ->withCount(['products as active_products_count' => function ($q) {
                    $q->where('products.is_active', true)
                        ->where('products.status', ProductStatus::ACTIVE);
                }])
                ->having('active_products_count', '>', 0)
                ->orderByDesc('active_products_count')
                ->limit(self::TOP_SUPPLIERS_LIMIT)
                ->get();
        });
    }

    private function getSiteStats(): array
    {
        return Cache::remember('home:stats:site', self::CACHE_TTL_LONG, function () {
            return [
                'total_products' => Product::where('is_active', true)
                    ->where('status', ProductStatus::ACTIVE)
                    ->count(),

                'total_categories' => Category::where('is_active', true)->count(),

                'total_suppliers' => Supplier::where('is_active', true)
                    ->where('is_verified', true)
                    ->count(),

                'total_brands' => Supplier::where('is_active', true)
                    ->where('is_verified', true)
                    ->whereNotNull('brand_name')
                    ->distinct('brand_name')
                    ->count('brand_name'),
            ];
        });
    }

    private function getRecentlyViewedProducts(Request $request): Collection
    {
        $recentIds = session()->get('recently_viewed', []);
        $recentIds = array_slice($recentIds, 0, self::RECENT_VIEWED_LIMIT);

        if (empty($recentIds)) {
            return collect();
        }

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
            ])
            ->whereIn('products.id', $recentIds)
            ->where('products.is_active', true)
            ->where('products.status', ProductStatus::ACTIVE)
            ->with([
                'category:id,name,slug',
                'supplier:id,company_name,slug,logo_path,is_verified',
                'galleries' => function ($q) {
                    $q->select([
                        'id', 'product_id', 'file_path', 'alt_text',
                        'sort_order', 'is_primary', 'file_type', 'versions', 'base_name',
                    ])
                        ->where('file_type', 'image')
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->limit(1);
                },
                // ✅ استفاده از ساختار جدید: productSuppliers با prices
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
                        'id', 'product_id', 'retail_price', 'is_published',
                    ])
                        ->where('is_published', true)
                        ->orderBy('retail_price', 'asc')
                        ->limit(1);
                },
            ])
            ->get()
            ->sortBy(function ($product) use ($recentIds) {
                return array_search($product->id, $recentIds);
            })
            ->values();
    }

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

    private function buildHomeSeo(): array
    {
        $siteName    = config('app.name', 'فروشگاه B2B');
        $description = 'بزرگ‌ترین بازار آنلاین B2B — خرید عمده محصولات از تأمین‌کنندگان معتبر با بهترین قیمت';

        return [
            'title'       => $siteName . ' — بازار آنلاین خرید عمده',
            'description' => $description,
            'keywords'    => 'خرید عمده, فروشگاه B2B, تأمین‌کننده, بازار آنلاین, خرده‌فروشی',
            'canonical'   => url('/'),
            'robots'      => 'index, follow',
            'og'          => [
                'title'       => $siteName,
                'description' => $description,
                'type'        => 'website',
                'url'         => url('/'),
                'image'       => asset('images/og-home.jpg'),
                'site_name'   => $siteName,
            ],
            'schema' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'WebSite',
                'name'        => $siteName,
                'url'         => url('/'),
                'description' => $description,
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => url('/products?q={search_term_string}'),
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }
}
