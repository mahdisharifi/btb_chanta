<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// جستجوی محصول با گارانتی
$pricesWithWarranty = \App\Models\ProductPrice::whereNotNull('warranty_type')
    ->orWhere('warranty_price', '>', 0)
    ->limit(10)
    ->get();

if ($pricesWithWarranty->count() > 0) {
    echo "Found " . $pricesWithWarranty->count() . " prices with warranty:\n\n";
    
    foreach ($pricesWithWarranty as $price) {
        echo "Price ID: " . $price->id . "\n";
        echo "  Product Supplier ID: " . $price->product_supplier_id . "\n";
        echo "  Warranty Type: " . ($price->warranty_type ?? 'NULL') . "\n";
        echo "  Warranty Duration: " . ($price->warranty_duration ?? 'NULL') . "\n";
        echo "  Warranty Price: " . ($price->warranty_price ?? 'NULL') . "\n\n";
    }
} else {
    echo "No prices with warranty found in database!\n";
    echo "Total prices in database: " . \App\Models\ProductPrice::count() . "\n";
}

