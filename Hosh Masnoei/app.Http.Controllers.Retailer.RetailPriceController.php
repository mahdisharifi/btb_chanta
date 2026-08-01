<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RetailPrice;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RetailPriceController extends Controller
{
    public function index(Request $request): View
    {
        $retailPrices = RetailPrice::query()
            ->where('user_id', auth()->id())
            ->with(['product' => function ($query) {
                $query->with(['category', 'galleries' => fn($q) => $q->where('is_primary', true)]);
            }])
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->status === 'published') {
                    $query->where('is_published', true);
                } elseif ($request->status === 'draft') {
                    $query->where('is_published', false);
                }
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => RetailPrice::where('user_id', auth()->id())->count(),
            'published' => RetailPrice::where('user_id', auth()->id())->where('is_published', true)->count(),
            'draft' => RetailPrice::where('user_id', auth()->id())->where('is_published', false)->count(),
        ];

        return view('retailer.retail-prices.index', compact('retailPrices', 'stats'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('setRetailPrice', $product);

        $validated = $request->validate([
            'retail_price' => ['required', 'numeric', 'min:1000', 'max:999999999999'],
            'is_published' => ['boolean'],
        ], [
            'retail_price.required' => 'قیمت فروش الزامی است.',
            'retail_price.numeric' => 'قیمت باید عدد باشد.',
            'retail_price.min' => 'حداقل قیمت ۱,۰۰۰ تومان است.',
            'retail_price.max' => 'قیمت وارد شده بیش از حد مجاز است.',
        ]);

        // Check if user already has a retail price for this product
        $existingPrice = RetailPrice::where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->first();

        if ($existingPrice) {
            $existingPrice->update([
                'retail_price' => $validated['retail_price'],
                'is_published' => $validated['is_published'] ?? false,
            ]);

            return back()->with('success', 'قیمت فروش با موفقیت به‌روزرسانی شد.');
        }

        RetailPrice::create([
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'retail_price' => $validated['retail_price'],
            'is_published' => $validated['is_published'] ?? false,
        ]);

        return back()->with('success', 'قیمت فروش با موفقیت ثبت شد.');
    }

    public function update(Request $request, RetailPrice $retailPrice): RedirectResponse
    {
        // Ensure user owns this retail price
        abort_unless($retailPrice->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'retail_price' => ['required', 'numeric', 'min:1000', 'max:999999999999'],
            'is_published' => ['boolean'],
        ], [
            'retail_price.required' => 'قیمت فروش الزامی است.',
            'retail_price.numeric' => 'قیمت باید عدد باشد.',
            'retail_price.min' => 'حداقل قیمت ۱,۰۰۰ تومان است.',
        ]);

        $retailPrice->update([
            'retail_price' => $validated['retail_price'],
            'is_published' => $validated['is_published'] ?? $retailPrice->is_published,
        ]);

        return back()->with('success', 'قیمت فروش با موفقیت ویرایش شد.');
    }

    public function destroy(RetailPrice $retailPrice): RedirectResponse
    {
        abort_unless($retailPrice->user_id === auth()->id(), 403);

        $retailPrice->delete();

        return back()->with('success', 'قیمت فروش حذف شد.');
    }

    public function togglePublish(RetailPrice $retailPrice): RedirectResponse
    {
        abort_unless($retailPrice->user_id === auth()->id(), 403);

        $retailPrice->update([
            'is_published' => !$retailPrice->is_published,
        ]);

        $status = $retailPrice->is_published ? 'منتشر' : 'پیش‌نویس';

        return back()->with('success', "وضعیت قیمت به «{$status}» تغییر یافت.");
    }
}
