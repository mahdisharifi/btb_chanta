<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AdvancedSearchService;
use App\Models\Category;
use App\Models\Attribute;

class AdvancedSearchController extends Controller
{
    protected $searchService;

    public function __construct(AdvancedSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'q', 'category_id', 'attributes', 'supplier_id',
            'price_type', 'min_price', 'max_price',
            'sort_by', 'sort_direction', 'page'
        ]);

        // تبدیل attributes به آرایه اگر رشته است
        if ($request->has('attributes') && is_string($request->attributes)) {
            $filters['attributes'] = json_decode($request->attributes, true) ?? [];
        }

        $products = $this->searchService->searchProducts($filters, auth()->user());
        $categories = Category::active()->get();

        $attributes = collect();
        $suppliers = collect();

        if ($request->filled('category_id')) {
            $options = $this->searchService->getFilterOptions($request->category_id, auth()->user());
            $attributes = $options['attributes'];
            $suppliers = $options['suppliers'];
        }

        $priceTypes = \App\Enums\PriceType::cases();

        return view('pages.advanced-search', compact(
            'products', 'categories', 'attributes', 'suppliers', 'priceTypes', 'filters'
        ));
    }

    public function getFilterOptions(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id'
        ]);

        $options = $this->searchService->getFilterOptions(
            $request->category_id,
            auth()->user()
        );

        return response()->json($options);
    }

    public function getSearchStats(Request $request)
    {
        $stats = $this->searchService->getSearchStats(
            $request->q,
            $request->only(['category_id']),
            auth()->user()
        );

        return response()->json($stats);
    }
}
