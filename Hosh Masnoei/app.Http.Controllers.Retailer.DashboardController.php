<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RetailPrice;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $stats = [
            'total_priced' => RetailPrice::where('user_id', $user->id)->count(),
            'published_prices' => RetailPrice::where('user_id', $user->id)->where('is_published', true)->count(),
            'draft_prices' => RetailPrice::where('user_id', $user->id)->where('is_published', false)->count(),
            'total_products' => Product::where('is_active', true)->where('status', 'active')->count(),
        ];

        // Recent priced products
        $recentPrices = RetailPrice::query()
            ->where('user_id', $user->id)
            ->with(['product' => fn($q) => $q->with('category')])
            ->latest()
            ->take(10)
            ->get();

        // Latest available products (not yet priced by this retailer)
        $unpricedProducts = Product::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereDoesntHave('retailPrices', fn($q) => $q->where('user_id', $user->id))
            ->with(['category', 'galleries' => fn($q) => $q->where('is_primary', true)])
            ->latest()
            ->take(8)
            ->get();

        $isPremium = $user->hasRole('retailer_premium');

        return view('retailer.dashboard', compact('stats', 'recentPrices', 'unpricedProducts', 'isPremium'));
    }
}
