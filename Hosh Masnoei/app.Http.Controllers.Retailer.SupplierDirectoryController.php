<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        // This route is already protected by role:retailer_premium middleware
        $query = Supplier::query()
            ->where('is_active', true)
            ->where('is_verified', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('status', 'active')]);

        // Search
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('company_name', 'like', "%{$searchTerm}%")
                  ->orWhere('brand_name', 'like', "%{$searchTerm}%")
                  ->orWhere('city', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        // Sort
        $sortBy = $request->get('sort', 'name');
        $query = match ($sortBy) {
            'products' => $query->orderBy('products_count', 'desc'),
            'newest' => $query->latest(),
            default => $query->orderBy('company_name', 'asc'),
        };

        $suppliers = $query->paginate(12)->withQueryString();

        // Cities for filter
        $cities = Supplier::query()
            ->where('is_active', true)
            ->where('is_verified', true)
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->sort()
            ->values();

        return view('retailer.suppliers.index', compact('suppliers', 'cities', 'sortBy'));
    }

    public function show(Supplier $supplier): View
    {
        abort_unless($supplier->is_active && $supplier->is_verified, 404);

        $supplier->load([
            'products' => function ($query) {
                $query->where('is_active', true)
                    ->where('status', 'active')
                    ->with([
                        'category',
                        'galleries' => fn($q) => $q->where('is_primary', true),
                        'prices' => fn($q) => $q->where('is_active', true),
                    ])
                    ->latest();
            },
        ]);

        $supplier->loadCount(['products' => fn($q) => $q->where('is_active', true)->where('status', 'active')]);

        return view('retailer.suppliers.show', compact('supplier'));
    }
}
