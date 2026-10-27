<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ✅ تست مهاجرت داده‌های بسته‌بندی
 * 
 * این کامند بررسی می‌کند که:
 * 1. چند محصول با quantity_per_packaging = 1 وجود دارد
 * 2. آیا داده‌های صحیح در attribute_product موجود است
 * 3. اگر مهاجرت انجام شود، چه تعداد محصول بروزرسانی می‌شوند
 * 
 * استفاده:
 * php artisan products:test-migration
 */
class TestPackagingMigration extends Command
{
    protected $signature = 'products:test-migration';
    protected $description = 'تست مهاجرت داده‌های بسته‌بندی قبل از اجرا';

    public function handle(): int
    {
        $this->info('🧪 تست مهاجرت داده‌های بسته‌بندی...');
        $this->newLine();

        // ═══════════════════════════════════════════════════════════
        // ✅ مرحله 1: بررسی وضعیت فعلی
        // ═══════════════════════════════════════════════════════════
        
        $this->info('=== مرحله 1: بررسی وضعیت فعلی ===');
        
        $totalProducts = DB::table('products')->count();
        $withQty1 = DB::table('products')->where('quantity_per_packaging', 1)->count();
        $withQtyGt1 = DB::table('products')->where('quantity_per_packaging', '>', 1)->count();
        $withQtyNull = DB::table('products')->whereNull('quantity_per_packaging')->count();
        
        $this->info("  • تعداد کل محصولات: {$totalProducts}");
        $this->info("  • با quantity_per_packaging = 1: {$withQty1}");
        $this->info("  • با quantity_per_packaging > 1: {$withQtyGt1}");
        $this->info("  • با quantity_per_packaging = NULL: {$withQtyNull}");
        $this->newLine();

        // ═══════════════════════════════════════════════════════════
        // ✅ مرحله 2: بررسی داده‌های attribute_product
        // ═══════════════════════════════════════════════════════════
        
        $this->info('=== مرحله 2: بررسی داده‌های attribute_product ===');
        
        // attribute_id = 2 برای "تعداد در کارتن"
        $attributeProducts = DB::table('attribute_product')
            ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
            ->where('attribute_values.attribute_id', 2)
            ->whereNotNull('attribute_product.value')
            ->select('attribute_product.product_id', 'attribute_product.value')
            ->get();
        
        $this->info("  • تعداد رکوردهای attribute_product (تعداد در کارتن): {$attributeProducts->count()}");
        
        // بررسی چند نمونه
        $this->newLine();
        $this->info('  📋 نمونه داده‌ها (10 رکورد اول):');
        
        $samples = $attributeProducts->take(10);
        foreach ($samples as $sample) {
            $product = DB::table('products')->where('id', $sample->product_id)->first(['id', 'title', 'quantity_per_packaging']);
            
            $value = $sample->value;
            $extracted = $this->extractNumber($value);
            
            $status = '';
            if ($product) {
                if ($product->quantity_per_packaging == $extracted) {
                    $status = '✅ تطابق دارد';
                } elseif ($product->quantity_per_packaging == 1 && $extracted > 1) {
                    $status = "⚠️ نیاز به بروزرسانی (1 → {$extracted})";
                } else {
                    $status = "🔄 تفاوت دارد ({$product->quantity_per_packaging} ≠ {$extracted})";
                }
            }
            
            $this->line("    • Product #{$sample->product_id}: value='{$value}' → extracted={$extracted} {$status}");
        }
        
        $this->newLine();

        // ═══════════════════════════════════════════════════════════
        // ✅ مرحله 3: شبیه‌سازی مهاجرت
        // ═══════════════════════════════════════════════════════════
        
        $this->info('=== مرحله 3: شبیه‌سازی مهاجرت ===');
        
        $willBeUpdated = 0;
        $willBeSkipped = 0;
        $alreadyCorrect = 0;
        
        foreach ($attributeProducts as $ap) {
            $product = DB::table('products')->where('id', $ap->product_id)->first(['quantity_per_packaging']);
            
            if (!$product) {
                $willBeSkipped++;
                continue;
            }
            
            $extracted = $this->extractNumber($ap->value);
            
            if ($extracted <= 0) {
                $willBeSkipped++;
                continue;
            }
            
            if ($product->quantity_per_packaging == $extracted) {
                $alreadyCorrect++;
            } else {
                $willBeUpdated++;
            }
        }
        
        $this->info("  • تعدادی که بروزرسانی می‌شوند: {$willBeUpdated}");
        $this->info("  • تعدادی که قبلاً صحیح هستند: {$alreadyCorrect}");
        $this->info("  • تعدادی که skip می‌شوند: {$willBeSkipped}");
        $this->newLine();

        // ═══════════════════════════════════════════════════════════
        // ✅ مرحله 4: بررسی محصولات خاص (988 و 989)
        // ═══════════════════════════════════════════════════════════
        
        $this->info('=== مرحله 4: بررسی محصولات خاص (988 و 989) ===');
        
        $specificProducts = [988, 989];
        
        foreach ($specificProducts as $productId) {
            $product = DB::table('products')->where('id', $productId)->first(['id', 'title', 'packaging_unit_type', 'quantity_per_packaging', 'quantity_tiers']);
            
            if (!$product) {
                $this->error("  ❌ محصول #{$productId} یافت نشد!");
                continue;
            }
            
            $ap = DB::table('attribute_product')
                ->join('attribute_values', 'attribute_product.attribute_value_id', '=', 'attribute_values.id')
                ->where('attribute_values.attribute_id', 2)
                ->where('attribute_product.product_id', $productId)
                ->whereNotNull('attribute_product.value')
                ->select('attribute_product.value')
                ->first();
            
            $extracted = $ap ? $this->extractNumber($ap->value) : null;
            
            $this->info("  📦 محصول #{$productId}: {$product->title}");
            $this->line("     • packaging_unit_type: " . ($product->packaging_unit_type ?? 'NULL'));
            $this->line("     • quantity_per_packaging: " . ($product->quantity_per_packaging ?? 'NULL'));
            $this->line("     • attribute_product.value: " . ($ap->value ?? 'NULL'));
            $this->line("     • عدد استخراج شده: " . ($extracted ?? 'NULL'));
            $this->line("     • quantity_tiers: " . ($product->quantity_tiers ? substr($product->quantity_tiers, 0, 100) . '...' : 'NULL'));
            
            if ($extracted && $product->quantity_per_packaging != $extracted) {
                $this->warn("     ⚠️ نیاز به بروزرسانی: {$product->quantity_per_packaging} → {$extracted}");
            } else {
                $this->info("     ✅ داده‌ها صحیح هستند");
            }
        }
        
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════');
        $this->info('✅ تست تکمیل شد!');
        $this->info('═══════════════════════════════════════════════════');
        
        return Command::SUCCESS;
    }
    
    /**
     * استخراج عدد از value
     */
    protected function extractNumber(?string $value): ?int
    {
        if (!$value) {
            return null;
        }
        
        // حذف فاصله
        $value = str_replace(' ', '', $value);
        
        // حذف "pcs:"
        $value = str_ireplace('pcs:', '', $value);
        
        // استخراج عدد (قبل از اعشار اگر وجود دارد)
        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
}
