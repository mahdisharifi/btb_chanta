<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$product = App\Models\Product::find(940);
if ($product) {
    echo "Product ID: " . $product->id . "\n";
    echo "Product Title: " . $product->title . "\n";
    echo "\n=== quantity_tiers ===\n";
    var_dump($product->quantity_tiers);
    
    echo "\n=== Raw DB value ===\n";
    $raw = DB::table('products')->where('id', 940)->first();
    echo "quantity_tiers column: " . $raw->quantity_tiers ?? 'NULL' . "\n";
} else {
    echo "Product not found\n";
}
