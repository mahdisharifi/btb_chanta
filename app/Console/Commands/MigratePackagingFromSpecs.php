<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePackagingFromSpecs extends Command
{
    protected $signature = 'products:migrate-packaging';
    protected $description = 'مهاجرت تعداد در کارتن/بسته از specifications و attribute_product به فیلدهای packaging';

    public function handle()
    {
        $this->info('شروع مهاجرت داده‌های بسته‌بندی...');
        $this->newLine();
        
        $migratedFromSpecs = 0;
        $migratedFromAttributes = 0;
        $skipped = 0;
        
        // ============================================================
        // بخش اول: مهاجرت از specifications
        // ============================================================
        
        $this->info('=== مرحله 1: مهاجرت از specifications ===');
        
        $patterns = [
            'تعداد در کارتن',
            'تعداد در بسته',
            'تعداد در شیرینگ',
            'تعداد در جین',
            'تعداد در طاقه',
        ];
        
        $products = Product::whereNotNull('specifications')->get();
        
        $this->info("تعداد کل محصولات با specifications: {$products->count()}");
        
        foreach ($products as $product) {
            $specs = $product->specifications;
            if (!is_array($specs)) {
                $skipped++;
                continue;
            }
            
            $found = false;
            foreach ($patterns as $pattern) {
                if (isset($specs[$pattern])) {
                    $value = $specs[$pattern];
                    
                    // استخراج عدد از مقدار
                    $number = $this->extractNumber($value);
                    
                    if ($number > 0) {
                        // تعیین نوع واحد بسته‌بندی
                        $unitType = $this->determineUnitType($pattern);
                        
                        // به‌روزرسانی محصول
                        $product->packaging_unit_type = $unitType;
                        $product->quantity_per_packaging = $number;
                        $product->save();
                        
                        $this->line("✓ Product ID {$product->id}: {$product->title} -> {$number} در {$unitType}");
                        $migratedFromSpecs++;
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) {
                $skipped++;
            }
        }
        
        $this->newLine();
        $this->info("مهاجرت از specifications تکمیل شد: {$migratedFromSpecs} محصول");
        
        // ============================================================
        // بخش دوم: مهاجرت از attribute_product
        // ============================================================
        
        $this->newLine();
        $this->info('=== مرحله 2: مهاجرت از attribute_product ===');
        
        // پیدا کردن attribute_value_id مربوط به "تعداد در کارتن"
        $attributeId = 2; // ID مشخصه "تعداد در کارتن"
        
        // تمام رکوردهای attribute_product که value دارند
        $attributeProducts = DB::table('attribute_product')
            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
            ->where('attribute_values.attribute_id', $attributeId)
            ->whereNotNull('attribute_product.value')
            ->select('attribute_product.*')
            ->get();
        
        $this->info("تعداد رکوردهای attribute_product: {$attributeProducts->count()}");
        
        $matched = 0;
        $updated = 0;
        
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
                $migratedFromAttributes++;
                
                $this->line("✓ Product ID {$product->id}: {$product->title} -> {$oldValue} به {$number}");
            }
        }
        
        $this->newLine();
        $this->info("مهاجرت از attribute_product تکمیل شد:");
        $this->info("- تطابق کامل: {$matched}");
        $this->info("- به‌روزرسانی شده: {$updated}");
        
        // ============================================================
        // گزارش نهایی
        // ============================================================
        
        $this->newLine();
        $this->info('=== گزارش نهایی ===');
        $this->info("- مهاجرت از specifications: {$migratedFromSpecs}");
        $this->info("- مهاجرت از attribute_product: {$migratedFromAttributes}");
        $this->info("- بدون تغییر: {$skipped}");
        $this->info("- مجموع: " . ($migratedFromSpecs + $migratedFromAttributes));
        
        return Command::SUCCESS;
    }
    
    private function extractNumber($value): int
    {
        // اگر عدد است
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        // حذف فاصله و pcs:
        $value = str_replace(' ', '', $value);
        $value = str_ireplace('pcs:', '', $value);
        
        // استخراج عدد از متن (مثلاً: "12 عدد" یا "24")
        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }
        
        return 0;
    }
    
    private function determineUnitType(string $pattern): string
    {
        if (str_contains($pattern, 'کارتن')) {
            return 'carton';
        } elseif (str_contains($pattern, 'بسته')) {
            return 'package';
        } elseif (str_contains($pattern, 'شیرینگ')) {
            return 'bundle';
        } elseif (str_contains($pattern, 'جین')) {
            return 'jean';
        } elseif (str_contains($pattern, 'طاقه')) {
            return 'roll';
        }
        
        return 'piece';
    }
}
