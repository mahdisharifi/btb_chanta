<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use App\Enums\PriceType;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PriceController extends Controller
{
    private function getSupplier(): Supplier
    {
        return Supplier::where('user_id', auth()->id())->firstOrFail();
    }

    public function index(): View
    {
        $supplier = $this->getSupplier();

        $products = Product::query()
            ->where('supplier_id', $supplier->id)
            ->with(['category', 'prices'])
            ->withCount('prices')
            ->latest()
            ->paginate(20);

        return view('supplier.prices.index', compact('products'));
    }

    public function show(Product $product): View
    {
        $supplier = $this->getSupplier();
        abort_unless($product->supplier_id === $supplier->id, 403);

        $product->load([
            'prices' => fn($q) => $q->orderBy('price_type'),
            'category',
        ]);

        $priceTypes = PriceType::cases();

        return view('supplier.prices.show', compact('product', 'priceTypes'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $supplier = $this->getSupplier();
        abort_unless($product->supplier_id === $supplier->id, 403);

        $validated = $request->validate([
            'prices' => ['required', 'array'],
            'prices.*.price_type' => ['required', 'in:' . implode(',', PriceType::values())],
            'prices.*.price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'prices.*.min_quantity' => ['nullable', 'integer', 'min:1'],
            'prices.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'prices.*.warranty_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'prices.*.check_months' => ['nullable', 'integer', 'min:1', 'max:12'],
            'prices.*.description' => ['nullable', 'string', 'max:255'],
            'prices.*.is_active' => ['boolean'],
            'prices.*.valid_from' => ['nullable', 'date'],
            'prices.*.valid_until' => ['nullable', 'date', 'after:prices.*.valid_from'],
        ], [
            'prices.*.price.required' => 'قیمت الزامی است.',
            'prices.*.price.numeric' => 'قیمت باید عددی باشد.',
            'prices.*.price.min' => 'قیمت نمی‌تواند منفی باشد.',
        ]);

        // Delete existing prices
        $product->prices()->delete();

        // Create new prices
        foreach ($validated['prices'] as $priceData) {
            $priceData['is_active'] = $priceData['is_active'] ?? true;
            $product->prices()->create($priceData);
        }

        return back()->with('success', 'قیمت‌های محصول با موفقیت به‌روزرسانی شد.');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $supplier = $this->getSupplier();

        $validated = $request->validate([
            'updates' => ['required', 'array'],
            'updates.*.product_id' => ['required', 'exists:products,id'],
            'updates.*.price_type' => ['required', 'in:' . implode(',', PriceType::values())],
            'updates.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($validated['updates'] as $update) {
            $product = Product::find($update['product_id']);

            if ($product && $product->supplier_id === $supplier->id) {
                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $update['product_id'],
                        'price_type' => $update['price_type'],
                    ],
                    [
                        'price' => $update['price'],
                        'is_active' => true,
                    ]
                );
            }
        }

        return back()->with('success', 'قیمت‌ها با موفقیت به‌روزرسانی شد.');
    }
}
