<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->pricedFirst()
            ->where('is_active', true)
            ->where('status', 'active')
            ->with(['category:id,name,slug', 'galleries' => fn($q) => $q->where('is_primary', true)]);

        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('q')) {
            $query->where('title', 'like', "%{$request->q}%");
        }

        $products = $query->latest()->paginate($request->get('per_page', 16));

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active && $product->status === 'active', 404);

        $product->increment('view_count');

        $product->load([
            'category:id,name,slug',
            'galleries' => fn($q) => $q->orderBy('sort_order'),
        ]);

        // Retail prices (public)
        $retailPrices = $product->retailPrices()
            ->where('is_published', true)
            ->get(['id', 'retail_price']);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'retail_prices' => $retailPrices,
            ],
        ]);
    }

    public function retailPrices(Request $request): JsonResponse
    {
        $prices = $request->user()->retailPrices()
            ->with('product:id,title,slug')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $prices,
        ]);
    }

    public function setRetailPrice(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'retail_price' => ['required', 'numeric', 'min:1000'],
            'is_published' => ['boolean'],
        ]);

        $price = $product->retailPrices()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'retail_price' => $validated['retail_price'],
                'is_published' => $validated['is_published'] ?? false,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'قیمت فروش ثبت شد.',
            'data' => $price,
        ]);
    }

    public function myProducts(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'شما تأمین‌کننده ثبت شده‌ای ندارید.'], 403);
        }

        $products = Product::query()
            ->where('supplier_id', $supplier->id)
            ->with(['category:id,name,slug'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        // Simplified for now
        return response()->json([
            'success' => false,
            'message' => 'این قابلیت در نسخه بعدی API فعال خواهد شد.',
        ], 501);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'این قابلیت در نسخه بعدی API فعال خواهد شد.',
        ], 501);
    }
}
