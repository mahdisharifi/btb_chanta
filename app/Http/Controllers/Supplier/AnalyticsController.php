<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        $supplier = Supplier::where('user_id', auth()->id())->firstOrFail();

        // Total views
        $totalViews = Product::where('supplier_id', $supplier->id)->sum('view_count');

        // Most viewed products
        $mostViewed = Product::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->with(['category', 'galleries' => fn($q) => $q->where('is_primary', true)])
            ->orderBy('view_count', 'desc')
            ->take(10)
            ->get();

        // Products by status
        $productsByStatus = [
            'active' => Product::where('supplier_id', $supplier->id)->where('status', 'active')->count(),
            'pending' => Product::where('supplier_id', $supplier->id)->where('status', 'pending_review')->count(),
            'draft' => Product::where('supplier_id', $supplier->id)->where('status', 'draft')->count(),
            'inactive' => Product::where('supplier_id', $supplier->id)->where('status', 'inactive')->count(),
        ];

        // Products by category
        $productsByCategory = Product::query()
            ->where('supplier_id', $supplier->id)
            ->selectRaw('category_id, COUNT(*) as count')
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get();

        // Recent activity (last 30 days products)
        $recentProducts = Product::query()
            ->where('supplier_id', $supplier->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return view('supplier.analytics.index', compact(
            'totalViews',
            'mostViewed',
            'productsByStatus',
            'productsByCategory',
            'recentProducts'
        ));
    }
}
