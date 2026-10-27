<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAllProductsPackaging extends Command
{
    protected $signature = 'products:check-all-packaging';
    protected $description = 'بررسی وضعیت بسته‌بندی تمام محصولات';

    public function handle()
    {
        $this->info('=== گزارش کامل وضعیت بسته‌بندی محصولات ===');
        $this->newLine();
        
        // آمار کلی
        $total = Product::count();
        $withSpecs = Product::whereNotNull('specifications')->count();
        $withoutSpecs = Product::whereNull('specifications')->count();
        $withPackaging = Product::where('quantity_per_packaging', '>', 1)->count();
        $defaultPackaging = Product::where('quantity_per_packaging', 1)->count();
        
        $this->info("تعداد کل محصولات: {$total}");
        $this->info("با specifications: {$withSpecs}");
        $this->info("بدون specifications: {$withoutSpecs}");
        $this->info("با quantity_per_packaging > 1: {$withPackaging}");
        $this->info("با quantity_per_packaging = 1 (پیش‌فرض): {$defaultPackaging}");
        $this->newLine();
        
        // محصولات با تعداد در کارتن در specifications
        $this->info('=== محصولات با تطابق کامل ===');
        
        $products = Product::whereNotNull('specifications')
            ->orderBy('id')
            ->get(['id', 'title', 'specifications', 'packaging_unit_type', 'quantity_per_packaging']);
        
        $mismatchCount = 0;
        $matchCount = 0;
        
        foreach ($products as $p) {
            $specs = $p->specifications ?? [];
            $cartonCount = $specs['تعداد در کارتن'] ?? $specs['تعداد در بسته'] ?? null;
            
            if ($cartonCount !== null) {
                // استخراج عدد
                $number = $this->extractNumber($cartonCount);
                
                $isMatch = ($number == $p->quantity_per_packaging);
                
                if (!$isMatch) {
                    $mismatchCount++;
                    $this->error("❌ ID {$p->id}: {$p->title}");
                    $this->error("   Specs تعداد در کارتن: {$cartonCount} (عدد استخراج شده: {$number})");
                    $this->error("   quantity_per_packaging: {$p->quantity_per_packaging}");
                    $this->newLine();
                } else {
                    $matchCount++;
                }
            }
        }
        
        $this->newLine();
        $this->info("✅ تطابق کامل: {$matchCount} محصول");
        $this->error("❌ عدم تطابق: {$mismatchCount} محصول");
        
        $this->newLine();
        $this->info('=== محصولات با quantity_per_packaging = 1 ===');
        $this->info('(این محصولات یا specifications ندارند یا تعداد در کارتن ندارند)');
        
        $defaultProducts = Product::where('quantity_per_packaging', 1)
            ->whereNotNull('specifications')
            ->get(['id', 'title', 'specifications', 'packaging_unit_type', 'quantity_per_packaging']);
        
        $this->info("تعداد محصولات با quantity_per_packaging = 1 و specifications: " . $defaultProducts->count());
        $this->newLine();
        
        $missingMigration = 0;
        
        foreach ($defaultProducts as $p) {
            $specs = $p->specifications ?? [];
            $hasCarton = isset($specs['تعداد در کارتن']) || isset($specs['تعداد در بسته']);
            
            if ($hasCarton) {
                $cartonValue = $specs['تعداد در کارتن'] ?? $specs['تعداد در بسته'] ?? 'N/A';
                $number = $this->extractNumber($cartonValue);
                
                // اگر عدد بزرگتر از 1 است ولی quantity_per_packaging = 1 است، مشکل دارد
                if ($number > 1) {
                    $missingMigration++;
                    
                    $this->error("❌ ID {$p->id}: {$p->title}");
                    $this->error("   تعداد در کارتن در specs: {$cartonValue} (عدد استخراج شده: {$number})");
                    $this->error("   quantity_per_packaging: {$p->quantity_per_packaging}");
                    $this->newLine();
                }
            }
        }
        
        $this->info("تعداد محصولات با مشکل مهاجرت (عدد > 1): {$missingMigration}");
        
        $this->newLine();
        $this->info('=== بررسی محصول 948 ===');
        
        $product948 = Product::find(948);
        if ($product948) {
            $this->info('ID: ' . $product948->id);
            $this->info('Title: ' . $product948->title);
            $this->info('Specifications: ' . json_encode($product948->specifications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->info('Packaging: ' . $product948->packaging_unit_type . '/' . $product948->quantity_per_packaging);
            $this->info('Created at: ' . $product948->created_at);
            $this->info('Updated at: ' . $product948->updated_at);
        } else {
            $this->error('محصول 948 یافت نشد');
        }
        
        $this->newLine();
        $this->info('=== محصولات ایجاد شده در 24 ساعت اخیر ===');
        
        $recentProducts = Product::where('created_at', '>=', now()->subDay())
            ->orderBy('id', 'desc')
            ->get(['id', 'title', 'specifications', 'quantity_per_packaging', 'created_at']);
        
        $this->info('تعداد: ' . $recentProducts->count());
        
        foreach ($recentProducts as $p) {
            $this->line("ID {$p->id}: {$p->title}");
            $this->line("  Qty: {$p->quantity_per_packaging}");
            $this->line("  Created: {$p->created_at}");
        }
        
        return Command::SUCCESS;
    }
    
    private function extractNumber($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }
        
        return 0;
    }
}
