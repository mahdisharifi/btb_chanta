<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class FixPackagingMigration extends Command
{
    protected $signature = 'products:fix-packaging';
    protected $description = 'اصلاح مهاجرت بسته‌بندی برای محصولات با فرمت pcs:number';

    public function handle()
    {
        $this->info('=== اصلاح مهاجرت بسته‌بندی ===');
        $this->newLine();
        
        $products = Product::whereNotNull('specifications')
            ->where('quantity_per_packaging', 1)
            ->get(['id', 'title', 'specifications', 'packaging_unit_type', 'quantity_per_packaging']);
        
        $this->info("تعداد محصولات با quantity_per_packaging = 1: " . $products->count());
        $this->newLine();
        
        $fixed = 0;
        
        foreach ($products as $p) {
            $specs = $p->specifications ?? [];
            $cartonValue = $specs['تعداد در کارتن'] ?? $specs['تعداد در بسته'] ?? null;
            
            if ($cartonValue && str_contains($cartonValue, 'pcs:')) {
                // استخراج عدد از "pcs:number"
                $number = $this->extractPcsNumber($cartonValue);
                
                if ($number > 1) {
                    $p->quantity_per_packaging = $number;
                    $p->save();
                    
                    $fixed++;
                    $this->line("✓ ID {$p->id}: {$p->title}");
                    $this->line("  تعداد در کارتن: {$cartonValue} → {$number}");
                }
            }
        }
        
        $this->newLine();
        $this->info("تعداد محصولات اصلاح شده: {$fixed}");
        
        return Command::SUCCESS;
    }
    
    private function extractPcsNumber(string $value): int
    {
        // فرمت: "pcs:20" یا "pcs:200"
        if (preg_match('/pcs:(\d+)/i', $value, $matches)) {
            return (int) $matches[1];
        }
        
        return 0;
    }
}
