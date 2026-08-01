<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $supplier = Supplier::where('user_id', $user->id)->firstOrFail();

        $stats = [
            'total_products' => Product::where('supplier_id', $supplier->id)->count(),
            'active_products' => Product::where('supplier_id', $supplier->id)->where('is_active', true)->where('status', 'active')->count(),
            'pending_products' => Product::where('supplier_id', $supplier->id)->where('status', 'pending_review')->count(),
            'draft_products' => Product::where('supplier_id', $supplier->id)->where('status', 'draft')->count(),
            'total_views' => Product::where('supplier_id', $supplier->id)->sum('view_count'),
        ];

        $recentProducts = Product::query()
            ->where('supplier_id', $supplier->id)
            ->with(['category', 'galleries' => fn($q) => $q->where('is_primary', true)])
            ->latest()
            ->take(10)
            ->get();

        return view('supplier.dashboard', compact('stats', 'recentProducts', 'supplier'));
    }
}
