<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAttributeProduct948 extends Command
{
    protected $signature = 'attribute:check-product-948';
    protected $description = 'بررسی جدول attribute_product برای محصول 948';

    public function handle()
    {
        $this->info('=== بررسی جدول attribute_product برای محصول 948 ===');
        $this->newLine();
        
        // بررسی attribute_product
        $attributeProducts = DB::table('attribute_product')
            ->where('product_id', 948)
            ->get();
        
        $this->info('تعداد attributes: ' . $attributeProducts->count());
        $this->newLine();
        
        foreach ($attributeProducts as $ap) {
            $columns = get_object_vars($ap);
            
            $this->line('Columns: ' . implode(', ', array_keys($columns)));
            
            foreach ($columns as $key => $value) {
                $this->line("{$key}: {$value}");
            }
            $this->newLine();
        }
        
        // بررسی محصول
        $product = Product::find(948);
        if ($product) {
            $this->info('=== اطلاعات محصول 948 ===');
            $this->info('Title: ' . $product->title);
            $this->info('quantity_per_packaging: ' . $product->quantity_per_packaging);
            $this->info('packaging_unit_type: ' . $product->packaging_unit_type);
        }
        
        return Command::SUCCESS;
    }
}
