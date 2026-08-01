<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryApiController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withCount(['products' => fn($q) => $q->where('is_active', true)->where('status', 'active')])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function show(Category $category): JsonResponse
    {
        abort_unless($category->is_active, 404);

        $category->load([
            'children' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'parent',
        ]);

        $products = $category->products()
            ->where('is_active', true)
            ->where('status', 'active')
            ->with(['galleries' => fn($q) => $q->where('is_primary', true)])
            ->latest()
            ->paginate(16);

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $category,
                'products' => $products,
            ],
        ]);
    }
}
