<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckProduct948 extends Command
{
    protected $signature = 'product:check-948';
    protected $description = 'بررسی محصول 948';

    public function handle()
    {
        $product = Product::find(948);
        
        if (!$product) {
            $this->error('محصول 948 یافت نشد');
            return Command::FAILURE;
        }
        
        $this->info('=== محصول 948 ===');
        $this->info('ID: ' . $product->id);
        $this->info('Title: ' . $product->title);
        $this->info('Specifications: ' . json_encode($product->specifications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info('Packaging: ' . $product->packaging_unit_type . '/' . $product->quantity_per_packaging);
        $this->info('Tiers: ' . json_encode($product->quantity_tiers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info('Created at: ' . $product->created_at);
        $this->info('Updated at: ' . $product->updated_at);
        
        $this->newLine();
        $this->info('=== بررسی مستقیم از دیتابیس ===');
        
        $rawData = DB::table('products')->where('id', 948)->first();
        $this->info('Raw specifications: ' . $rawData->specifications);
        $this->info('Raw quantity_per_packaging: ' . $rawData->quantity_per_packaging);
        $this->info('Raw packaging_unit_type: ' . $rawData->packaging_unit_type);
        
        return Command::SUCCESS;
    }
}
