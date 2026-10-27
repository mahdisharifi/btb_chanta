<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAttribute2 extends Command
{
    protected $signature = 'attribute:check-2';
    protected $description = 'بررسی و اصلاح مشخصه تعداد در کارتن (ID 2)';

    public function handle()
    {
        $this->info('=== بررسی مشخصه ID 2 ===');
        
        // بررسی اینکه attribute ID 2 چیست
        $attribute = DB::table('attributes')->where('id', 2)->first();
        
        if (!$attribute) {
            $this->error('مشخصه با ID 2 یافت نشد');
            return Command::FAILURE;
        }
        
        $this->info('Attribute Name: ' . $attribute->name);
        $this->newLine();
        
        // محصولات دارای این attribute در specifications
        $products = Product::whereNotNull('specifications')
            ->get(['id', 'title', 'specifications', 'quantity_per_packaging']);
        
        $this->info('تعداد کل محصولات با specifications: ' . $products->count());
        $this->newLine();
        
        $matched = 0;
        $updated = 0;
        $noAttribute = 0;
        $updatedList = [];
        
        foreach ($products as $product) {
            $specs = $product->specifications ?? [];
            
            // بررسی وجود "تعداد در کارتن" در specifications
            if (!isset($specs['تعداد در کارتن'])) {
                $noAttribute++;
                continue;
            }
            
            $value = $specs['تعداد در کارتن'];
            
            // 1. حذف فاصله
            $value = str_replace(' ', '', $value);
            
            // 2. حذف "pcs:"
            $value = str_replace('pcs:', '', $value);
            $value = str_replace('PCS:', '', $value);
            $value = str_replace('Pcs:', '', $value);
            
            // 3. تبدیل به عدد
            $number = is_numeric($value) ? (int) $value : 0;
            
            if ($number <= 0) {
                continue;
            }
            
            // بررسی با quantity_per_packaging
            if ($number == $product->quantity_per_packaging) {
                $matched++;
            } else {
                // به‌روزرسانی
                $product->quantity_per_packaging = $number;
                $product->save();
                
                $updated++;
                $updatedList[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'old_value' => $product->quantity_per_packaging,
                    'new_value' => $number,
                ];
            }
        }
        
        $this->info("✅ تطابق کامل: {$matched} محصول");
        $this->info("🔄 به‌روزرسانی شده: {$updated} محصول");
        $this->info("⚠️ بدون مشخصه: {$noAttribute} محصول");
        
        if (!empty($updatedList)) {
            $this->newLine();
            $this->info('=== لیست محصولات به‌روزرسانی شده ===');
            
            foreach ($updatedList as $item) {
                $this->line("ID {$item['id']}: {$item['title']}");
                $this->line("  مقدار قبلی: {$item['old_value']} → مقدار جدید: {$item['new_value']}");
            }
        }
        
        return Command::SUCCESS;
    }
}
