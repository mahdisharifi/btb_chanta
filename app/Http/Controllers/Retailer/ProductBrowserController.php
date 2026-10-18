<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductBrowserController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isPremium = $user->hasRole('retailer_premium');

        $query = Product::query()
            ->pricedFirst()
            ->where('is_active', true)
            ->where('status', 'active')
            ->with(['category', 'galleries' => fn($q) => $q->where('is_primary', true)]);

        // Load supplier info only for premium
        if ($isPremium) {
            $query->with('supplier');
        }

        // Load user's retail prices
        $query->with(['retailPrices' => fn($q) => $q->where('user_id', $user->id)]);

        // Filter by category
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $categoryIds = $category->descendants()->pluck('id')->push($category->id);
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Filter by pricing status
        if ($request->filled('pricing')) {
            if ($request->pricing === 'priced') {
                $query->whereHas('retailPrices', fn($q) => $q->where('user_id', $user->id));
            } elseif ($request->pricing === 'unpriced') {
                $query->whereDoesntHave('retailPrices', fn($q) => $q->where('user_id', $user->id));
            }
        }

        // Search
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('short_description', 'like', "%{$searchTerm}%")
                  ->orWhere('sku', 'like', "%{$searchTerm}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort', 'latest');
        $query = match ($sortBy) {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('title', 'asc'),
            'most_viewed' => $query->orderBy('view_count', 'desc'),
            default => $query->latest(),
        };

        $products = $query->paginate(16)->withQueryString();

        // Categories for sidebar
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('status', 'active')])
            ->orderBy('sort_order')
            ->get();

        return view('retailer.products.index', compact('products', 'categories', 'sortBy', 'isPremium'));
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->status === 'active', 404);

        $user = auth()->user();
        $isPremium = $user->hasRole('retailer_premium');

        // Load relationships
        $product->load([
            'category.parent',
            'galleries' => fn($q) => $q->orderBy('sort_order'),
            'retailPrices' => fn($q) => $q->where('user_id', $user->id),
        ]);

        // Load wholesale prices ONLY for premium
        if ($isPremium) {
            $product->load([
                'supplier',
                'prices' => fn($q) => $q->where('is_active', true),
            ]);
        }

        // Related products
        $relatedProducts = Product::query()
            ->pricedFirst()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->with(['galleries' => fn($q) => $q->where('is_primary', true)])
            ->inRandomOrder()
            ->take(4)
            ->get();

        // My retail price
        $myRetailPrice = $product->retailPrices->first();

        return view('retailer.products.show', compact(
            'product',
            'isPremium',
            'relatedProducts',
            'myRetailPrice'
        ));
    }
}
