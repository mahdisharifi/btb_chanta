<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchApiController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'عبارت جستجو باید حداقل ۲ کاراکتر باشد.',
            ]);
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('short_description', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            })
            ->with(['category:id,name,slug', 'galleries' => fn($q) => $q->where('is_primary', true)])
            ->latest()
            ->paginate($request->get('per_page', 16));

        return response()->json([
            'success' => true,
            'data' => $products,
            'query' => $query,
        ]);
    }
}
