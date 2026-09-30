<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierProfileController extends Controller
{
    /**
     * نمایش پروفایل عمومی تأمین‌کننده
     */
    public function show(Request $request, string $slug)
    {
        $supplier = Supplier::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // بررسی دسترسی به اطلاعات تماس
        $canViewContact = $this->canViewContact($request);

        // آمار تأمین‌کننده
        $supplierStats = $this->getSupplierStats($supplier->id);

        // دسته‌بندی‌های تأمین‌کننده
        $supplierCategories = $this->getSupplierCategories($supplier->id);

        // محصولات
        $products = $this->getProducts($supplier->id, $request);

        // مرتب‌سازی
        $sortBy = $request->get('sort', 'latest');

        // Breadcrumbs
        $breadcrumbs = [
            ['name' => 'خانه', 'url' => route('home')],
            ['name' => 'تأمین‌کنندگان', 'url' => route('suppliers.index')],
            ['name' => $supplier->company_name, 'url' => null],
        ];

        // JSON-LD برای سئو
        $jsonLd = $this->generateJsonLd($supplier);

        return view('pages.suppliers.show', compact(
            'supplier',
            'canViewContact',
            'supplierStats',
            'supplierCategories',
            'products',
            'sortBy',
            'breadcrumbs',
            'jsonLd'
        ));
    }

    /**
     * لیست تأمین‌کنندگان
     */
    public function index(Request $request)
    {
        $query = Supplier::where('is_active', true)
            ->whereNotNull('company_name')
            ->withCount(['products as products_count' => function ($q) {
                $q->wherePivot('is_active', true)
                  ->where('products.status', 'active');
            }]);

        // جستجو
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('brand_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فیلتر استان
        if ($province = $request->get('province')) {
            $query->where('province', $province);
        }

        // مرتب‌سازی
        $sortBy = $request->get('sort', 'newest');
        switch ($sortBy) {
            case 'most_products':
                $query->orderByDesc('products_count');
                break;
            case 'name_asc':
                $query->orderBy('company_name');
                break;
            case 'verified_first':
                $query->orderByDesc('is_verified')->latest();
                break;
            default:
                $query->latest();
        }

        $suppliers = $query->paginate(12)->withQueryString();

        // آمار کلی
        $stats = [
            'total' => Supplier::where('is_active', true)->count(),
            'verified' => Supplier::where('is_active', true)->where('is_verified', true)->count(),
            'cities' => Supplier::where('is_active', true)->distinct('city')->count('city'),
        ];

        // لیست استان‌ها
        $provinces = Supplier::where('is_active', true)
            ->whereNotNull('province')
            ->distinct()
            ->pluck('province')
            ->sort()
            ->values();

        $searchQuery = $request->get('q', '');

        return view('pages.suppliers.index', compact('suppliers', 'sortBy', 'stats', 'provinces', 'searchQuery'));
    }

    /**
     * بررسی دسترسی به اطلاعات تماس
     */
    private function canViewContact(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        // ادمین همیشه دسترسی دارد
        if ($user->role === 'admin') {
            return true;
        }

        // خرده‌فروش با اشتراک فعال
        if ($user->role === 'retailer') {
            return $user->hasActiveSubscription();
        }

        return false;
    }

    /**
     * آمار تأمین‌کننده
     */
    private function getSupplierStats(int $supplierId): array
    {
        $stats = DB::table('products')
            ->join('product_supplier', 'products.id', '=', 'product_supplier.product_id')
            ->where('product_supplier.supplier_id', $supplierId)
            ->where('product_supplier.is_active', true)
            ->where('products.status', 'active')
            ->selectRaw('
                COUNT(DISTINCT products.id) as total_products,
                COUNT(DISTINCT products.category_id) as total_categories,
                COALESCE(SUM(products.view_count), 0) as total_views
            ')
            ->first();

        $featuredCount = DB::table('products')
            ->join('product_supplier', 'products.id', '=', 'product_supplier.product_id')
            ->where('product_supplier.supplier_id', $supplierId)
            ->where('product_supplier.is_active', true)
            ->where('products.status', 'active')
            ->where('products.is_featured', true)
            ->count();

        // محاسبه بازه قیمت از quantity_tiers
        $priceRange = DB::table('quantity_tiers')
            ->join('products', 'quantity_tiers.product_id', '=', 'products.id')
            ->join('product_supplier', 'products.id', '=', 'product_supplier.product_id')
            ->where('product_supplier.supplier_id', $supplierId)
            ->where('product_supplier.is_active', true)
            ->where('products.status', 'active')
            ->where('quantity_tiers.is_active', true)
            ->selectRaw('
                COALESCE(MIN(quantity_tiers.price), 0) as min_price,
                COALESCE(MAX(quantity_tiers.price), 0) as max_price
            ')
            ->first();

        return [
            'total_products' => $stats->total_products ?? 0,
            'total_categories' => $stats->total_categories ?? 0,
            'total_views' => $stats->total_views ?? 0,
            'featured_count' => $featuredCount,
            'min_price' => $priceRange->min_price ?? 0,
            'max_price' => $priceRange->max_price ?? 0,
        ];
    }

    /**
     * دسته‌بندی‌های تأمین‌کننده با تعداد محصولات
     */
    private function getSupplierCategories(int $supplierId)
    {
        return Category::whereHas('products', function ($q) use ($supplierId) {
                $q->whereHas('suppliers', function ($sq) use ($supplierId) {
                    $sq->where('suppliers.id', $supplierId)
                       ->wherePivot('is_active', true);
                })
                ->where('products.status', 'active');
            })
            ->withCount(['products' => function ($q) use ($supplierId) {
                $q->whereHas('suppliers', function ($sq) use ($supplierId) {
                    $sq->where('suppliers.id', $supplierId)
                       ->wherePivot('is_active', true);
                })
                ->where('products.status', 'active');
            }])
            ->orderBy('products_count', 'desc')
            ->get();
    }

    /**
     * محصولات تأمین‌کننده با فیلتر و مرتب‌سازی
     */
    private function getProducts(int $supplierId, Request $request)
    {
        $query = Product::whereHas('suppliers', function ($q) use ($supplierId) {
                $q->where('suppliers.id', $supplierId)
                  ->wherePivot('is_active', true);
            })
            ->where('status', 'active')
            ->with(['category', 'images', 'quantityTiers' => function($q) {
                $q->where('is_active', true)->orderBy('min_quantity');
            }]);

        // فیلتر دسته‌بندی
        if ($categorySlug = $request->get('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $categorySlug));
        }

        // جستجو در محصولات
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // فیلتر قیمت (از تیراژها)
        if ($minPrice = $request->get('min_price')) {
            $query->whereHas('quantityTiers', function($q) use ($minPrice) {
                $q->where('price', '>=', (int)$minPrice)
                  ->where('is_active', true);
            });
        }
        if ($maxPrice = $request->get('max_price')) {
            $query->whereHas('quantityTiers', function($q) use ($maxPrice) {
                $q->where('price', '<=', (int)$maxPrice)
                  ->where('is_active', true);
            });
        }

        // مرتب‌سازی
        $sortBy = $request->get('sort', 'latest');
        switch ($sortBy) {
            case 'price_low':
                $query->join('quantity_tiers', 'products.id', '=', 'quantity_tiers.product_id')
                    ->where('quantity_tiers.is_active', true)
                    ->select('products.*', DB::raw('MIN(quantity_tiers.price) as min_price'))
                    ->groupBy('products.id')
                    ->orderBy('min_price', 'asc');
                break;
            case 'price_high':
                $query->join('quantity_tiers', 'products.id', '=', 'quantity_tiers.product_id')
                    ->where('quantity_tiers.is_active', true)
                    ->select('products.*', DB::raw('MAX(quantity_tiers.price) as max_price'))
                    ->groupBy('products.id')
                    ->orderBy('max_price', 'desc');
                break;
            case 'popular':
                $query->orderByDesc('view_count');
                break;
            case 'name_asc':
                $query->orderBy('title');
                break;
            default:
                $query->latest();
        }

        return $query->paginate($request->get('per_page', 12))
            ->appends($request->query());
    }

    /**
     * JSON-LD برای سئو
     */
    private function generateJsonLd(Supplier $supplier): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $supplier->company_name,
            'alternateName' => $supplier->brand_name,
            'description' => $supplier->description,
            'url' => route('suppliers.show', $supplier->slug),
            'logo' => $supplier->logo_path ? asset('storage/' . $supplier->logo_path) : null,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $supplier->city,
                'addressRegion' => $supplier->province,
                'postalCode' => $supplier->postal_code,
                'addressCountry' => 'IR',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => $supplier->phone,
                'contactType' => 'sales',
            ],
        ];
    }
}
