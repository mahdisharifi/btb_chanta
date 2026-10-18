<?php

namespace App\Livewire\Web;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\Category;
use App\Enums\ProductStatus;
use App\Helpers\PriceHelper;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class SupplierProducts extends Component
{
    use WithPagination;

    // ─── URL Parameters ───
    public string $slug;

    #[Url(as: 'category')]
    public ?string $categorySlug = null;

    #[Url(as: 'q')]
    public string $searchQuery = '';

    #[Url(as: 'min_price')]
    public ?int $minPrice = null;

    #[Url(as: 'max_price')]
    public ?int $maxPrice = null;

    #[Url(as: 'featured')]
    public bool $featured = false;

    #[Url(as: 'in_stock')]
    public bool $inStock = false;

    #[Url(as: 'sort')]
    public string $sortBy = 'latest';

    #[Url(as: 'per_page')]
    public int $perPage = 24;

    // ─── Data Properties ───
    public Supplier $supplier;
    public array $supplierStats = [];
    public $supplierCategories;
    public bool $canViewContact = false;

    // ─── Filter Options ───
    public array $sortOptions = [
        'latest' => 'جدیدترین',
        'oldest' => 'قدیمی‌ترین',
        'popular' => 'محبوب‌ترین',
        'price_low' => 'ارزان‌ترین',
        'price_high' => 'گران‌ترین',
        'name_asc' => 'نام (الف-ی)',
    ];

    public array $perPageOptions = [12, 24, 48, 96];

    // ─── UI State ───
    public bool $showMobileFilter = false;

    // ─── Listeners ───
    protected $listeners = ['category-changed'];

    // ─── Validation Rules ───
    protected function rules(): array
    {
        return [
            'searchQuery' => ['nullable', 'string', 'max:200'],
            'minPrice' => ['nullable', 'integer', 'min:0'],
            'maxPrice' => ['nullable', 'integer', 'min:0'],
            'sortBy' => ['string', 'in:' . implode(',', array_keys($this->sortOptions))],
            'perPage' => ['integer', 'in:' . implode(',', $this->perPageOptions)],
        ];
    }

    // ─── Lifecycle Hooks ───

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->loadSupplier();
        $this->loadSupplierStats();
        $this->loadSupplierCategories();
        $this->checkContactPermission();
    }

    public function updated($property): void
    {
        // Reset pagination when filters change
        if (in_array($property, ['categorySlug', 'searchQuery', 'minPrice', 'maxPrice', 'featured', 'inStock', 'sortBy', 'perPage'])) {
            $this->resetPage();
        }
    }

    // ─── Actions ───

    public function clearFilters(): void
    {
        $this->reset([
            'categorySlug',
            'searchQuery',
            'minPrice',
            'maxPrice',
            'featured',
            'inStock',
        ]);
        $this->sortBy = 'latest';
        $this->resetPage();
    }

    public function applyQuickPriceRange(?int $min, ?int $max): void
    {
        $this->minPrice = $min;
        $this->maxPrice = $max;
        $this->resetPage();
    }

    // ─── Data Loading ───

    protected function loadSupplier(): void
    {
        $this->supplier = Supplier::where('slug', $this->slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    protected function loadSupplierStats(): void
    {
        $cacheKey = "web:supplier:{$this->supplier->id}:stats";

        $this->supplierStats = Cache::remember($cacheKey, 600, function () {
            // آمار محصولات (شامل هر دو مسیر)
            $stats = DB::table('products')
                ->where(function ($q) {
                    $q->where('supplier_id', $this->supplier->id)
                      ->orWhereExists(function ($sub) {
                          $sub->select(DB::raw(1))
                              ->from('product_supplier')
                              ->whereColumn('product_supplier.product_id', 'products.id')
                              ->where('product_supplier.supplier_id', $this->supplier->id)
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

            // بازه قیمت‌ها
            $productIds = DB::table('products')
                ->where(function ($q) {
                    $q->where('supplier_id', $this->supplier->id)
                      ->orWhereExists(function ($sub) {
                          $sub->select(DB::raw(1))
                              ->from('product_supplier')
                              ->whereColumn('product_supplier.product_id', 'products.id')
                              ->where('product_supplier.supplier_id', $this->supplier->id)
                              ->where('product_supplier.is_active', true);
                      });
                })
                ->where('is_active', true)
                ->where('status', ProductStatus::ACTIVE->value)
                ->whereNull('deleted_at')
                ->pluck('id');

            $priceStats = DB::table('product_prices')
                ->join('product_supplier', 'product_prices.product_supplier_id', '=', 'product_supplier.id')
                ->where('product_supplier.supplier_id', $this->supplier->id)
                ->where('product_prices.is_active', true)
                ->whereIn('product_prices.product_id', $productIds)
                ->selectRaw('MIN(product_prices.price) as min_price, MAX(product_prices.price) as max_price, AVG(product_prices.price) as avg_price')
                ->first();

            return [
                'total_products' => (int) ($stats->total_products ?? 0),
                'total_categories' => (int) ($stats->total_categories ?? 0),
                'total_views' => (int) ($stats->total_views ?? 0),
                'featured_count' => (int) ($stats->featured_count ?? 0),
                'min_price' => $priceStats->min_price ?? null,
                'max_price' => $priceStats->max_price ?? null,
                'avg_price' => $priceStats->avg_price ? (int) round($priceStats->avg_price) : null,
            ];
        });
    }

    protected function loadSupplierCategories(): void
    {
        $categoryIdsWithCounts = DB::table('products as p')
            ->select('p.category_id', DB::raw('COUNT(DISTINCT p.id) as products_count'))
            ->where('p.is_active', true)
            ->where('p.status', ProductStatus::ACTIVE->value)
            ->whereNull('p.deleted_at')
            ->where(function ($sub) {
                $sub->where('p.supplier_id', $this->supplier->id)
                    ->orWhereExists(function ($ex) {
                        $ex->select(DB::raw(1))
                           ->from('product_supplier as ps')
                           ->whereColumn('ps.product_id', 'p.id')
                           ->where('ps.supplier_id', $this->supplier->id)
                           ->where('ps.is_active', true);
                    });
            })
            ->groupBy('p.category_id')
            ->pluck('products_count', 'category_id');

        if ($categoryIdsWithCounts->isEmpty()) {
            $this->supplierCategories = collect();
        } else {
            $this->supplierCategories = Category::whereIn('id', $categoryIdsWithCounts->keys())
                ->where('is_active', true)
                ->select('id', 'name', 'slug', 'icon')
                ->get()
                ->map(fn($cat) => tap($cat, fn($c) =>
                    $c->products_count = $categoryIdsWithCounts->get($c->id, 0)
                ))
                ->sortByDesc('products_count')
                ->values();
        }
    }

    protected function checkContactPermission(): void
    {
        $user = auth()->user();

        if (!$user) {
            $this->canViewContact = false;
            return;
        }

        $this->canViewContact = $user->hasRole(['super_admin', 'supplier', 'retailer_premium']);
    }

    // ─── Query Building ───

    protected function buildProductsQuery()
    {
        $query = Product::query()
            ->pricedFirst()
            ->where('is_active', true)
            ->where('status', ProductStatus::ACTIVE)
            ->where(function ($q) {
                $q->where('supplier_id', $this->supplier->id)
                  ->orWhereHas('productSuppliers', function ($sub) {
                      $sub->where('supplier_id', $this->supplier->id)
                           ->where('is_active', true);
                  });
            })
            ->with([
                'category:id,name,slug,icon',
                'galleries' => fn($q) => $q->where('file_type', 'image')
                    ->orderByDesc('is_primary')->orderBy('sort_order')->limit(1),
                'productSuppliers.prices' => fn($q) => $q->where('is_active', true),
                'retailPrices' => fn($q) => $q->where('is_published', true),
            ]);

        // ─── Apply Filters ───

        // Category
        if ($this->categorySlug) {
            $cat = Category::where('slug', $this->categorySlug)
                ->where('is_active', true)
                ->first();
            if ($cat) {
                $catIds = $cat->descendantsAndSelf()->pluck('id');
                $query->whereIn('products.category_id', $catIds);
            }
        }

        // Search
        if ($this->searchQuery) {
            $searchTerm = '%' . $this->searchQuery . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', $searchTerm)
                  ->orWhere('sku', 'LIKE', $searchTerm);
            });
        }

        // Price Range
        if ($this->minPrice || $this->maxPrice) {
            $query->whereHas('productSuppliers.prices', function ($q) {
                $q->where('is_active', true);
                
                if ($this->minPrice) {
                    $q->where('price', '>=', $this->minPrice);
                }
                if ($this->maxPrice) {
                    $q->where('price', '<=', $this->maxPrice);
                }
            });
        }

        // Featured
        if ($this->featured) {
            $query->where('is_featured', true);
        }

        // In Stock
        if ($this->inStock) {
            $query->whereHas('productSuppliers', function ($q) {
                $q->where('supplier_id', $this->supplier->id)
                  ->where('is_active', true)
                  ->whereHas('prices', function ($sub) {
                      $sub->where('is_active', true);
                  });
            });
        }

        // ─── Sorting ───
        $query = match ($this->sortBy) {
            'latest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            'popular' => $query->orderByDesc('view_count'),
            'price_low' => $query->orderByRaw('
                (SELECT MIN(price) FROM product_prices 
                 WHERE product_prices.product_supplier_id IN 
                 (SELECT id FROM product_supplier 
                  WHERE product_supplier.product_id = products.id AND supplier_id = ' . $this->supplier->id . ') 
                 AND is_active = 1) ASC
            '),
            'price_high' => $query->orderByRaw('
                (SELECT MIN(price) FROM product_prices 
                 WHERE product_prices.product_supplier_id IN 
                 (SELECT id FROM product_supplier 
                  WHERE product_supplier.product_id = products.id AND supplier_id = ' . $this->supplier->id . ') 
                 AND is_active = 1) DESC
            '),
            'name_asc' => $query->orderBy('title'),
            default => $query->orderByDesc('created_at'),
        };

        return $query;
    }

    // ─── Computed Properties ───

    public function getProductsProperty()
    {
        return $this->buildProductsQuery()
            ->paginate($this->perPage)
            ->withQueryString();
    }

    public function getBreadcrumbsProperty(): array
    {
        return [
            ['name' => 'خانه', 'url' => route('home')],
            ['name' => 'تأمین‌کنندگان', 'url' => route('suppliers.index')],
            ['name' => $this->supplier->company_name, 'url' => null],
        ];
    }

    public function getSeoProperty(): array
    {
        $canonicalUrl = route('suppliers.show', $this->slug);
        $ogImage = $this->supplier->logoUrl('medium') ?: asset('images/og-default.jpg');

        return [
            'title' => ($this->supplier->seo_title ?? $this->supplier->company_name) . ' — ' . config('app.name'),
            'description' => $this->supplier->seo_description
                ?? "محصولات و اطلاعات تأمین‌کننده {$this->supplier->company_name} — " . config('app.name'),
            'canonical_url' => $canonicalUrl,
            'og_title' => $this->supplier->brand_name ?: $this->supplier->company_name,
            'og_image' => $ogImage,
        ];
    }

    public function getJsonLdProperty(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $this->supplier->company_name,
            'url' => route('suppliers.show', $this->slug),
            'logo' => $this->supplier->logoUrl('medium'),
            'description' => $this->seo['description'],
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $this->supplier->city ?? null,
                'addressRegion' => $this->supplier->province ?? null,
                'addressCountry' => 'IR',
            ]),
        ];
    }

    public function getActiveFiltersCountProperty(): int
    {
        return collect([
            $this->categorySlug,
            $this->searchQuery,
            $this->minPrice,
            $this->maxPrice,
            $this->featured ? true : null,
            $this->inStock ? true : null,
        ])->filter()->count();
    }

    // ─── Render ───

    public function render()
    {
        return view('livewire.web.supplier-products', [
            'products' => $this->products,
            'activeFiltersCount' => $this->activeFiltersCount,
            'breadcrumbs' => $this->breadcrumbs,
            'seo' => $this->seo,
            'jsonLd' => $this->jsonLd,
        ])
        ->title($this->seo['title'])
        ->layoutData([
            'seoTitle' => $this->seo['title'],
            'seoDescription' => $this->seo['description'],
            'canonical' => $this->seo['canonical_url'],
            'ogTitle' => $this->seo['og_title'],
            'ogImage' => $this->seo['og_image'],
            'breadcrumbs' => $this->breadcrumbs,
            'jsonLd' => $this->jsonLd,
        ]);
    }
}
