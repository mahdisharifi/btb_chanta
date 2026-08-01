<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    private function getSupplier(): Supplier
    {
        return Supplier::where('user_id', auth()->id())->firstOrFail();
    }

    public function index(Request $request): View
    {
        $supplier = $this->getSupplier();

        $query = Product::query()
            ->where('supplier_id', $supplier->id)
            ->with(['category', 'galleries' => fn($q) => $q->where('is_primary', true)]);

        // Search
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('sku', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Sort
        $sortBy = $request->get('sort', 'latest');
        $query = match ($sortBy) {
            'title' => $query->orderBy('title'),
            'views' => $query->orderBy('view_count', 'desc'),
            'oldest' => $query->oldest(),
            default => $query->latest(),
        };

        $products = $query->paginate(16)->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        $stats = [
            'total' => Product::where('supplier_id', $supplier->id)->count(),
            'active' => Product::where('supplier_id', $supplier->id)->where('status', 'active')->count(),
            'pending' => Product::where('supplier_id', $supplier->id)->where('status', 'pending_review')->count(),
            'draft' => Product::where('supplier_id', $supplier->id)->where('status', 'draft')->count(),
        ];

        return view('supplier.products.index', compact('products', 'categories', 'stats', 'sortBy'));
    }

    public function create(): View
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')->with('children')])
            ->orderBy('sort_order')
            ->get();

        return view('supplier.products.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = $this->getSupplier();

        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'full_description' => ['nullable', 'string', 'max:10000'],
            'specifications' => ['nullable', 'json'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'json'],
            'status' => ['required', 'in:draft,pending_review'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
        ], [
            'title.required' => 'عنوان محصول الزامی است.',
            'sku.required' => 'کد SKU الزامی است.',
            'sku.unique' => 'این کد SKU قبلاً ثبت شده.',
            'category_id.required' => 'انتخاب دسته‌بندی الزامی است.',
        ]);

        $validated['supplier_id'] = $supplier->id;
        $validated['is_active'] = false; // Needs admin approval
        $validated['is_featured'] = false;
        $validated['specifications'] = $request->specifications ? json_decode($request->specifications, true) : null;
        $validated['dimensions'] = $request->dimensions ? json_decode($request->dimensions, true) : null;

        $product = Product::create($validated);

        return redirect()->route('supplier.products.show', $product)
            ->with('success', 'محصول با موفقیت ایجاد شد.');
    }

    public function show(Product $product): View
    {
        $supplier = $this->getSupplier();
        abort_unless($product->supplier_id === $supplier->id, 403);

        $product->load([
            'category.parent',
            'galleries' => fn($q) => $q->orderBy('sort_order'),
            'prices' => fn($q) => $q->orderBy('price_type'),
        ]);

        return view('supplier.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $supplier = $this->getSupplier();
        abort_unless($product->supplier_id === $supplier->id, 403);

        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')->with('children')])
            ->orderBy('sort_order')
            ->get();

        return view('supplier.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $supplier = $this->getSupplier();
        abort_unless($product->supplier_id === $supplier->id, 403);

        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', "unique:products,sku,{$product->id}"],
            'short_description' => ['nullable', 'string', 'max:500'],
            'full_description' => ['nullable', 'string', 'max:10000'],
            'specifications' => ['nullable', 'json'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'json'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['specifications'] = $request->specifications ? json_decode($request->specifications, true) : $product->specifications;
        $validated['dimensions'] = $request->dimensions ? json_decode($request->dimensions, true) : $product->dimensions;

        $product->update($validated);

        return redirect()->route('supplier.products.show', $product)
            ->with('success', 'محصول با موفقیت ویرایش شد.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $supplier = $this->getSupplier();
        abort_unless($product->supplier_id === $supplier->id, 403);

        $product->delete();

        return redirect()->route('supplier.products.index')
            ->with('success', "محصول «{$product->title}» حذف شد.");
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $supplier = $this->getSupplier();
        abort_unless($product->supplier_id === $supplier->id, 403);

        // Suppliers can only toggle between draft and pending
        if ($product->status === 'draft') {
            $product->update(['status' => 'pending_review']);
            $message = 'محصول برای بررسی ارسال شد.';
        } else {
            $product->update(['status' => 'draft', 'is_active' => false]);
            $message = 'محصول به پیش‌نویس برگردانده شد.';
        }

        return back()->with('success', $message);
    }

    public function submitForReview(Product $product): RedirectResponse
    {
        $supplier = $this->getSupplier();
        abort_unless($product->supplier_id === $supplier->id, 403);

        $product->update(['status' => 'pending_review']);

        return back()->with('success', 'محصول برای بررسی توسط مدیر ارسال شد.');
    }
}
