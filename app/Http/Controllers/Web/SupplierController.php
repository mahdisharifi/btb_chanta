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
            ->whereNotNull('company_name');

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
            case 'products':
                $query->withCount('products')->orderByDesc('products_count');
                break;
            case 'name':
                $query->orderBy('company_name');
                break;
            default:
                $query->latest();
        }

        $suppliers = $query->paginate(12);

        return view('pages.suppliers.index', compact('suppliers', 'sortBy'));
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
            ->where('supplier_id', $supplierId)
            ->where('status', 'published')
            ->selectRaw('
                COUNT(*) as total_products,
                COUNT(DISTINCT category_id) as total_categories,
                COALESCE(SUM(view_count), 0) as total_views,
                COALESCE(MIN(min_order_quantity), 1) as min_moq,
                COALESCE(MIN(price), 0) as min_price,
                COALESCE(MAX(price), 0) as max_price
            ')
            ->first();

        $featuredCount = DB::table('products')
            ->where('supplier_id', $supplierId)
            ->where('status', 'published')
            ->where('is_featured', true)
            ->count();

        return [
            'total_products' => $stats->total_products ?? 0,
            'total_categories' => $stats->total_categories ?? 0,
            'total_views' => $stats->total_views ?? 0,
            'featured_count' => $featuredCount,
            'min_price' => $stats->min_price ?? 0,
            'max_price' => $stats->max_price ?? 0,
            'min_moq' => $stats->min_moq ?? 1,
        ];
    }

    /**
     * دسته‌بندی‌های تأمین‌کننده با تعداد محصولات
     */
    private function getSupplierCategories(int $supplierId)
    {
        return Category::whereHas('products', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)
                  ->where('status', 'published');
            })
            ->withCount(['products' => function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)
                  ->where('status', 'published');
            }])
            ->orderBy('products_count', 'desc')
            ->get();
    }

    /**
     * محصولات تأمین‌کننده با فیلتر و مرتب‌سازی
     */
    private function getProducts(int $supplierId, Request $request)
    {
        $query = Product::where('supplier_id', $supplierId)
            ->where('status', 'published')
            ->with(['category', 'images']);

        // فیلتر دسته‌بندی
        if ($categorySlug = $request->get('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $categorySlug));
        }

        // جستجو در محصولات
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // مرتب‌سازی
        $sortBy = $request->get('sort', 'latest');
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderByDesc('view_count');
                break;
            case 'name_asc':
                $query->orderBy('name');
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
