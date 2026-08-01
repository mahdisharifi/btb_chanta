<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\RetailPrice;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;


class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->where('status', 'active')->count(),
            'pending_products' => Product::where('status', 'pending_review')->count(),
            'total_suppliers' => Supplier::count(),
            'verified_suppliers' => Supplier::where('is_verified', true)->count(),
            'total_categories' => Category::count(),
            'total_users' => User::count(),
            'total_retailers' => User::role(['retailer_premium', 'retailer_basic'])->count(),
            'total_retail_prices' => RetailPrice::where('is_published', true)->count(),
        ];

        // Recent products
        $recentProducts = Product::query()
            ->with(['category', 'supplier'])
            ->latest()
            ->take(10)
            ->get();

        // Recent suppliers
        $recentSuppliers = Supplier::query()
            ->with('user')
            ->latest()
            ->take(5)
            ->get();

        // Pending products for review
        $pendingProducts = Product::query()
            ->where('status', 'pending_review')
            ->with(['category', 'supplier'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentProducts', 'recentSuppliers', 'pendingProducts'));
    }
}
