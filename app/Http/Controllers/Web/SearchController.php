<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->get('q', '');
        $products = collect();
        $categories = collect();

        if (strlen($query) >= 2) {
            // Search products
            $products = Product::query()
                ->where('is_active', true)
                ->where('status', 'active')
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('short_description', 'like', "%{$query}%")
                      ->orWhere('sku', 'like', "%{$query}%");
                })
                ->with(['category', 'supplier', 'galleries' => fn($q) => $q->where('is_primary', true)])
                ->latest()
                ->paginate(16)
                ->withQueryString();

            // Search categories
            $categories = Category::query()
                ->where('is_active', true)
                ->where('name', 'like', "%{$query}%")
                ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('status', 'active')])
                ->take(5)
                ->get();
        }

        return view('pages.search', compact('query', 'products', 'categories'));
    }
}
