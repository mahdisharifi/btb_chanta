<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$supplierId = 34;

// تست متد getSupplierStats جدید
echo "Testing getSupplierStats:\n";

$stats = DB::table('products')
    ->join('product_supplier', 'products.id', '=', 'product_supplier.product_id')
    ->where('product_supplier.supplier_id', $supplierId)
    ->where('product_supplier.is_active', true)
    ->where('products.status', 'active')
    ->selectRaw('
        COUNT(DISTINCT products.id) as total_products,
        COUNT(DISTINCT products.category_id) as total_categories,
        COALESCE(SUM(products.view_count), 0) as total_views
    ')
    ->first();

echo "Total Products: {$stats->total_products}\n";
echo "Total Categories: {$stats->total_categories}\n";
echo "Total Views: {$stats->total_views}\n";

// بررسی cache
echo "\nChecking cache:\n";
$cacheKey = "suppliers:stats:{$supplierId}";
if (Cache::has($cacheKey)) {
    echo "Cache EXISTS for key: {$cacheKey}\n";
    $cached = Cache::get($cacheKey);
    echo "Cached total_products: " . ($cached['total_products'] ?? 'N/A') . "\n";
    Cache::forget($cacheKey);
    echo "Cache CLEARED\n";
} else {
    echo "No cache found\n";
}
