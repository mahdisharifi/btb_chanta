<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAttributeProductPackaging extends Command
{
    protected $signature = 'attribute:fix-packaging';
    protected $description = 'اصلاح quantity_per_packaging بر اساس جدول attribute_product';

    public function handle()
    {
        $this->info('=== اصلاح quantity_per_packaging بر اساس attribute_product ===');
        $this->newLine();
        
        // پیدا کردن attribute_value_id مربوط به "تعداد در کارتن"
        $attributeId = 2; // ID مشخصه "تعداد در کارتن"
        
        // تمام رکوردهای attribute_product که value دارند
        $attributeProducts = DB::table('attribute_product')
            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
            ->where('attribute_values.attribute_id', $attributeId)
            ->whereNotNull('attribute_product.value')
            ->select('attribute_product.*')
            ->get();
        
        $this->info('تعداد رکوردهای attribute_product: ' . $attributeProducts->count());
        $this->newLine();
        
        $matched = 0;
        $updated = 0;
        $updatedList = [];
        
        foreach ($attributeProducts as $ap) {
            $value = $ap->value;
            
            // 1. حذف فاصله
            $value = str_replace(' ', '', $value);
            
            // 2. حذف "pcs:"
            $value = str_ireplace('pcs:', '', $value);
            
            // 3. تبدیل به عدد
            if (!is_numeric($value)) {
                // اگر هنوز عدد نیست، سعی کن عدد را استخراج کن
                if (preg_match('/(\d+)/', $value, $matches)) {
                    $value = $matches[1];
                } else {
                    continue;
                }
            }
            
            $number = (int) $value;
            
            if ($number <= 0) {
                continue;
            }
            
            // بارگذاری محصول
            $product = Product::find($ap->product_id);
            
            if (!$product) {
                continue;
            }
            
            // بررسی با quantity_per_packaging
            if ($number == $product->quantity_per_packaging) {
                $matched++;
            } else {
                $oldValue = $product->quantity_per_packaging;
                
                // به‌روزرسانی
                $product->quantity_per_packaging = $number;
                $product->save();
                
                $updated++;
                $updatedList[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'old_value' => $oldValue,
                    'new_value' => $number,
                ];
            }
        }
        
        $this->info("✅ تطابق کامل: {$matched} محصول");
        $this->info("🔄 به‌روزرسانی شده: {$updated} محصول");
        
        if (!empty($updatedList)) {
            $this->newLine();
            $this->info('=== لیست محصولات به‌روزرسانی شده ===');
            
            foreach ($updatedList as $item) {
                $this->line("ID {$item['id']}: {$item['title']}");
                $this->line("  مقدار قبلی: {$item['old_value']} → مقدار جدید: {$item['new_value']}");
                $this->newLine();
            }
        }
        
        return Command::SUCCESS;
    }
}
