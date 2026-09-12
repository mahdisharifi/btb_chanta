<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Command برای اصلاح min_quantity تیراژها
 * 
 * قانون: min_quantity هر تیراژ = max_quantity تیراژ قبلی + 1 (به جز تیراژ اول)
 */
class FixTierMinQuantities extends Command
{
    protected $signature = 'tiers:fix-min-quantities 
                            {--dry-run : نمایش تغییرات بدون اعمال}
                            {--product= : فقط یک محصول خاص (ID)}';

    protected $description = 'اصلاح min_quantity تیراژها بر اساس قانون: min = max_قبلی + 1';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $productId = $this->option('product');

        $this->info('🔧 شروع اصلاح min_quantity تیراژها...');
        $this->newLine();

        // دریافت محصولات
        $query = Product::whereNotNull('quantity_tiers');
        
        if ($productId) {
            $query->where('id', $productId);
        }
        
        $products = $query->get();
        
        if ($products->isEmpty()) {
            $this->warn('❌ هیچ محصولی یافت نشد.');
            return self::FAILURE;
        }

        $this->info("📦 تعداد محصولات: {$products->count()}");
        $this->newLine();

        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($products as $product) {
            try {
                $tiers = $product->quantity_tiers;
                
                if (empty($tiers) || !is_array($tiers)) {
                    $this->warn("⏭️  محصول #{$product->id}: تیراژ ندارد - رد شد");
                    $skipped++;
                    continue;
                }

                // مرتب‌سازی تیراژها بر اساس min_quantity
                $sortedTiers = collect($tiers)
                    ->filter(function($tier) {
                        // فیلتر تیراژهای غیرفعال (min=0, max=0)
                        $min = (int)($tier['min_quantity'] ?? 0);
                        $max = isset($tier['max_quantity']) && $tier['max_quantity'] !== null ? (int)$tier['max_quantity'] : null;
                        return !($min === 0 && $max === 0);
                    })
                    ->sortBy('min_quantity')
                    ->values();

                if ($sortedTiers->isEmpty()) {
                    $this->warn("⏭️  محصول #{$product->id}: فقط تیراژ غیرفعال دارد - رد شد");
                    $skipped++;
                    continue;
                }

                $needsUpdate = false;
                $newTiers = collect($tiers)->map(function($tier) {
                    return $tier; // کپی اولیه
                })->toArray();

                // پیدا کردن index واقعی هر تیراژ در آرایه اصلی
                foreach ($sortedTiers as $i => $sortedTier) {
                    if ($i === 0) {
                        // تیراژ اول را نمی‌توانیم تغییر دهیم
                        continue;
                    }

                    $prevTier = $sortedTiers[$i - 1];
                    $prevMax = isset($prevTier['max_quantity']) && $prevTier['max_quantity'] !== null 
                        ? (int)$prevTier['max_quantity'] 
                        : null;

                    if ($prevMax === null) {
                        continue; // اگر تیراژ قبلی open-ended است، نمی‌توانیم محاسبه کنیم
                    }

                    $expectedMin = $prevMax + 1;
                    $currentMin = (int)$sortedTier['min_quantity'];

                    if ($currentMin !== $expectedMin) {
                        $needsUpdate = true;

                        // پیدا کردن index واقعی در آرایه اصلی
                        $originalIndex = array_search($sortedTier, $tiers, true);
                        
                        if ($originalIndex !== false) {
                            $newTiers[$originalIndex]['min_quantity'] = $expectedMin;
                            
                            if (!$isDryRun) {
                                $this->line("  ✏️  تیراژ '{$sortedTier['name']}': {$currentMin} → {$expectedMin}");
                            }
                        }
                    }
                }

                if (!$needsUpdate) {
                    $this->info("✅ محصول #{$product->id} ({$product->title}): تیراژها صحیح هستند");
                    $skipped++;
                    continue;
                }

                if ($isDryRun) {
                    $this->warn("🔍 [DRY RUN] محصول #{$product->id} ({$product->title}): نیاز به اصلاح دارد");
                    $this->line("  📋 تیراژهای فعلی:");
                    foreach ($sortedTiers as $tier) {
                        $this->line("    - {$tier['name']}: {$tier['min_quantity']} تا " . ($tier['max_quantity'] ?? '∞'));
                    }
                } else {
                    DB::transaction(function () use ($product, $newTiers) {
                        $product->update(['quantity_tiers' => $newTiers]);
                    });

                    $this->info("✅ محصول #{$product->id} ({$product->title}): اصلاح شد");
                    $fixed++;
                }

            } catch (\Exception $e) {
                $this->error("❌ محصول #{$product->id}: خطا - " . $e->getMessage());
                $errors++;
            }
        }

        // خلاصه
        $this->newLine();
        $this->info('📊 خلاصه:');
        $this->table(
            ['وضعیت', 'تعداد'],
            [
                ['اصلاح شده', $isDryRun ? 0 : $fixed],
                ['نیاز به اصلاح (dry-run)', $isDryRun ? ($products->count() - $skipped - $errors) : 0],
                ['صحیح / رد شده', $skipped],
                ['خطا', $errors],
            ]
        );

        if ($isDryRun) {
            $this->newLine();
            $this->warn('⚠️  این یک اجرای آزمایشی بود. برای اعمال تغییرات، بدون --dry-run اجرا کنید.');
        }

        return self::SUCCESS;
    }
}
