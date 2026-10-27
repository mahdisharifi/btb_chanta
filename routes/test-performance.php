<?php

/**
 * Route موقت برای تست Performance
 * URL: /test/suppliers-performance
 * 
 * پس از تست، این فایل را حذف کنید
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use App\Enums\ProductStatus;

Route::get('/test/suppliers-performance', function () {
    DB::enableQueryLog();

    $startTime = microtime(true);
    $memoryBefore = memory_get_usage(true);

    // ═══════════════════════════════════════
    //  همان Query که در Controller است
    // ═══════════════════════════════════════

    $suppliers = Supplier::query()
        ->where('is_active', true)
        ->withCount([
            'products as products_count' => fn($q) => $q
                ->where('products.is_active', true)
                ->where('products.status', ProductStatus::ACTIVE),
        ])
        ->orderByDesc('created_at')
        ->paginate(24);

    $provinces = cache()->remember('web:suppliers:provinces', 3600, function () {
        return Supplier::where('is_active', true)
            ->whereNotNull('province')
            ->select('province')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');
    });

    $stats = cache()->remember('web:suppliers:stats', 1800, function () {
        return [
            'total'    => Supplier::where('is_active', true)->count(),
            'verified' => Supplier::where('is_active', true)->where('is_verified', true)->count(),
            'cities'   => Supplier::where('is_active', true)->whereNotNull('city')->distinct('city')->count('city'),
        ];
    });

    $endTime = microtime(true);
    $memoryAfter = memory_get_usage(true);

    // ═══════════════════════════════════════
    //  گزارش Performance
    // ═══════════════════════════════════════

    $queries = DB::getQueryLog();
    $queryCount = count($queries);
    $responseTime = ($endTime - $startTime) * 1000; // ms
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

    $totalQueryTime = array_sum(array_column($queries, 'time'));

    $html = '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-5xl mx-auto">
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">📊 Performance Report: Supplier Index</h1>
            
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                    <div class="text-sm text-blue-600 font-medium mb-1">Total Queries</div>
                    <div class="text-3xl font-bold text-blue-800">' . $queryCount . '</div>
                    <div class="text-xs text-blue-500 mt-1">' . number_format($totalQueryTime, 2) . 'ms</div>
                </div>
                
                <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                    <div class="text-sm text-green-600 font-medium mb-1">Response Time</div>
                    <div class="text-3xl font-bold text-green-800">' . number_format($responseTime, 2) . 'ms</div>
                    <div class="text-xs text-green-500 mt-1">' . ($responseTime < 500 ? '✓ خوب' : '✗ نیاز به بهینه‌سازی') . '</div>
                </div>
                
                <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                    <div class="text-sm text-purple-600 font-medium mb-1">Memory Usage</div>
                    <div class="text-3xl font-bold text-purple-800">' . number_format($memoryUsed, 2) . 'MB</div>
                    <div class="text-xs text-purple-500 mt-1">' . ($memoryUsed < 10 ? '✓ خوب' : '✗ زیاد') . '</div>
                </div>
                
                <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
                    <div class="text-sm text-orange-600 font-medium mb-1">Results</div>
                    <div class="text-3xl font-bold text-orange-800">' . $suppliers->count() . '</div>
                    <div class="text-xs text-orange-500 mt-1">از ' . $suppliers->total() . ' تأمین‌کننده</div>
                </div>
            </div>
            
            <!-- Status -->
            <div class="mb-6">';
    
    if ($queryCount <= 10 && $responseTime < 500 && $memoryUsed < 10) {
        $html .= '<div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-lg">
                    <strong class="font-bold">✓ عالی!</strong>
                    <span class="block sm:inline">Performance در حد مطلوب است.</span>
                  </div>';
    } else {
        $html .= '<div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded-lg">
                    <strong class="font-bold">⚠ هشدار!</strong>
                    <span class="block sm:inline">برخی معیارها نیاز به بهینه‌سازی دارند.</span>
                  </div>';
    }
    
    $html .= '</div>
            
            <!-- Query Details -->
            <div>
                <h2 class="text-xl font-bold text-gray-800 mb-4">Query Details</h2>
                <div class="space-y-3">';
    
    foreach ($queries as $i => $query) {
        $time = number_format($query['time'], 2);
        $timeClass = $query['time'] > 50 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
        
        $html .= '<div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-bold text-gray-600">Query #' . ($i + 1) . '</span>
                        <span class="text-xs font-mono px-2 py-1 rounded ' . $timeClass . '">' . $time . 'ms</span>
                    </div>
                    <pre class="text-xs bg-gray-800 text-green-400 p-3 rounded overflow-x-auto font-mono">' . htmlspecialchars($query['query']) . '</pre>';
        
        if (!empty($query['bindings'])) {
            $html .= '<div class="text-xs text-gray-500 mt-2">Bindings: ' . htmlspecialchars(json_encode($query['bindings'])) . '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>
            </div>
        </div>
        
        <!-- Cache Info -->
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">🗄️ Cache Information</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Provinces Count:</span>
                    <span class="font-bold">' . $provinces->count() . '</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Suppliers:</span>
                    <span class="font-bold">' . number_format($stats['total']) . '</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Verified Suppliers:</span>
                    <span class="font-bold">' . number_format($stats['verified']) . '</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Cities:</span>
                    <span class="font-bold">' . number_format($stats['cities']) . '</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';

    return response($html);
})->middleware('web');
