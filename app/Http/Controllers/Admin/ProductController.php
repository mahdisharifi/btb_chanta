<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\ProductGallery;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'supplier', 'primaryImage'])
            ->withCount('galleries');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        $products = $query->paginate(20)->withQueryString();

        $categories = Category::active()->orderBy('name')->get();
        $suppliers = Supplier::active()->orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories', 'suppliers'));
    }

    public function create()
    {
        $categories = Category::active()->orderBy('name')->get();
        $suppliers = Supplier::active()->orderBy('name')->get();

        return view('admin.products.create', compact('categories', 'suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'dimensions' => ['nullable', 'array'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'pending', 'approved', 'rejected'])],
            'is_featured' => ['boolean'],
            'main_image' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'gallery.*' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.quantity_from' => ['required', 'integer', 'min:1'],
            'prices.*.quantity_to' => ['nullable', 'integer', 'min:1'],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
        ]);

        try {

            DB::beginTransaction();

            $product = Product::create([
                'supplier_id' => $validated['supplier_id'],
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']),
                'sku' => $validated['sku'],
                'barcode' => $validated['barcode'] ?? null,
                'description' => $validated['description'] ?? null,
                'specifications' => $validated['specifications'] ?? null,
                'dimensions' => $validated['dimensions'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'status' => $validated['status'],
                'is_featured' => $validated['is_featured'] ?? false,
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'meta_keywords' => $validated['meta_keywords'] ?? null,
            ]);

            if ($request->hasFile('main_image')) {

                $path = $request->file('main_image')->store("products/{$product->id}", 'public');

                ProductGallery::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => true,
                    'sort_order' => 0
                ]);
            }

            if ($request->hasFile('gallery')) {

                foreach ($request->file('gallery') as $index => $image) {

                    $path = $image->store("products/{$product->id}", 'public');

                    ProductGallery::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => false,
                        'sort_order' => $index + 1
                    ]);
                }
            }

            foreach ($validated['prices'] as $price) {

                ProductPrice::create([
                    'product_id' => $product->id,
                    'quantity_from' => $price['quantity_from'],
                    'quantity_to' => $price['quantity_to'] ?? null,
                    'price' => $price['price']
                ]);
            }

            DB::commit();

            return redirect()->route('products.index')
                ->with('success', 'محصول با موفقیت ایجاد شد');

        } catch (\Throwable $e) {

            DB::rollBack();

            return back()->withInput()
                ->with('error', 'خطا در ایجاد محصول');
        }
    }

    public function show(Product $product)
    {
        $product->load([
            'category',
            'supplier',
            'galleries',
            'prices',
            'retailPrices'
        ]);

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Category::active()->orderBy('name')->get();
        $suppliers = Supplier::active()->orderBy('name')->get();

        $product->load(['galleries', 'prices']);

        return view('admin.products.edit', compact('product', 'categories', 'suppliers'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($product->id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products')->ignore($product->id)],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'dimensions' => ['nullable', 'array'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'pending', 'approved', 'rejected'])],
            'is_featured' => ['boolean'],
            'main_image' => ['nullable', 'image', 'max:2048'],
            'gallery.*' => ['nullable', 'image', 'max:2048'],
            'remove_gallery_ids' => ['nullable', 'array'],
            'prices' => ['required', 'array'],
            'prices.*.id' => ['nullable', 'exists:product_prices,id'],
            'prices.*.quantity_from' => ['required', 'integer', 'min:1'],
            'prices.*.quantity_to' => ['nullable', 'integer'],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
            'remove_price_ids' => ['nullable', 'array']
        ]);

        try {

            DB::beginTransaction();

            $product->update([
                'supplier_id' => $validated['supplier_id'],
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']),
                'sku' => $validated['sku'],
                'barcode' => $validated['barcode'] ?? null,
                'description' => $validated['description'] ?? null,
                'specifications' => $validated['specifications'] ?? null,
                'dimensions' => $validated['dimensions'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'status' => $validated['status'],
                'is_featured' => $validated['is_featured'] ?? false,
            ]);

            if (!empty($validated['remove_gallery_ids'])) {

                $images = ProductGallery::whereIn('id', $validated['remove_gallery_ids'])->get();

                foreach ($images as $image) {
                    Storage::disk('public')->delete($image->image_path);
                    $image->delete();
                }
            }

            if ($request->hasFile('gallery')) {

                $maxSort = $product->galleries()->max('sort_order') ?? 0;

                foreach ($request->file('gallery') as $index => $image) {

                    $path = $image->store("products/{$product->id}", 'public');

                    ProductGallery::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'sort_order' => $maxSort + $index + 1
                    ]);
                }
            }

            if (!empty($validated['remove_price_ids'])) {
                ProductPrice::whereIn('id', $validated['remove_price_ids'])->delete();
            }

            foreach ($validated['prices'] as $price) {

                if (!empty($price['id'])) {

                    ProductPrice::where('id', $price['id'])->update([
                        'quantity_from' => $price['quantity_from'],
                        'quantity_to' => $price['quantity_to'] ?? null,
                        'price' => $price['price']
                    ]);

                } else {

                    ProductPrice::create([
                        'product_id' => $product->id,
                        'quantity_from' => $price['quantity_from'],
                        'quantity_to' => $price['quantity_to'] ?? null,
                        'price' => $price['price']
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('products.index')
                ->with('success', 'محصول بروزرسانی شد');

        } catch (\Throwable $e) {

            DB::rollBack();

            return back()->withInput()
                ->with('error', 'خطا در بروزرسانی محصول');
        }
    }

    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {

            foreach ($product->galleries as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            $product->delete();
        });

        return redirect()->route('products.index')
            ->with('success', 'محصول حذف شد');
    }

    public function approve(Product $product)
    {
        $this->authorize('approve', $product);

        $product->update(['status' => 'approved']);

        return back()->with('success', 'محصول تایید شد');
    }

    public function reject(Product $product)
    {
        $this->authorize('reject', $product);

        $product->update(['status' => 'rejected']);

        return back()->with('success', 'محصول رد شد');
    }

    public function bulkAction(Request $request)
    {
        $this->authorize('bulkAction', Product::class);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['delete', 'approve', 'reject'])],
            'ids' => ['required', 'array']
        ]);

        $products = Product::whereIn('id', $validated['ids'])->get();

        DB::transaction(function () use ($products, $validated) {

            foreach ($products as $product) {

                if ($validated['action'] == 'delete') {
                    $product->delete();
                }

                if ($validated['action'] == 'approve') {
                    $product->update(['status' => 'approved']);
                }

                if ($validated['action'] == 'reject') {
                    $product->update(['status' => 'rejected']);
                }
            }
        });

        return back()->with('success', 'عملیات با موفقیت انجام شد');
    }
}
