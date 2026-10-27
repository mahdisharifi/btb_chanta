<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * ✅ Command برای بررسی سلامت محصولات قبل از Migration
 * 
 * استفاده:
 * php artisan products:check-health
 */
class CheckProductsHealth extends Command
{
    /**
     * دستور Console
     *
     * @var string
     */
    protected $signature = 'products:check-health';

    /**
     * توضیحات دستور
     *
     * @var string
     */
    protected $description = 'بررسی سلامت محصولات قبل از اعمال تیراژبندی خودکار';

    /**
     * اجرای دستور
     */
    public function handle(): int
    {
        $this->info('🔍 شروع بررسی سلامت محصولات...');
        $this->newLine();

        // ✅ آمار کلی
        $this->displayGeneralStats();
        $this->newLine();

        // ✅ بررسی واحد بسته‌بندی
        $this->checkPackagingUnits();
        $this->newLine();

        // ✅ بررسی quantity_per_packaging
        $this->checkQuantityPerPackaging();
        $this->newLine();

        // ✅ بررسی تیراژهای موجود
        $this->checkExistingTiers();
        $this->newLine();

        // ✅ پیش‌بینی نتیجه Migration
        $this->predictMigrationResult();

        return Command::SUCCESS;
    }

    /**
     * نمایش آمار کلی
     */
    protected function displayGeneralStats(): void
    {
        $total = Product::count();
        $withTiers = Product::whereNotNull('quantity_tiers')
            ->whereJsonLength('quantity_tiers', '>', 0)
            ->count();
        $withoutTiers = $total - $withTiers;

        $this->info('═══════════════════════════════════════════════════');
        $this->info('📊 آمار کلی محصولات');
        $this->info('═══════════════════════════════════════════════════');
        $this->line("  • کل محصولات:           {$total}");
        $this->line("  • محصولات با تیراژ:     {$withTiers}");
        $this->line("  • محصولات بدون تیراژ:   {$withoutTiers}");
        $this->info('═══════════════════════════════════════════════════');
    }

    /**
     * بررسی واحد بسته‌بندی
     */
    protected function checkPackagingUnits(): void
    {
        $this->info('📦 بررسی واحد بسته‌بندی (packaging_unit_type)');
        $this->info('═══════════════════════════════════════════════════');

        $withUnit = Product::whereNotNull('packaging_unit_type')->count();
        $withoutUnit = Product::whereNull('packaging_unit_type')->count();

        $this->line("  ✅ محصولات با واحد:     {$withUnit}");
        
        if ($withoutUnit > 0) {
            $this->warn("  ⚠️  محصولات بدون واحد:   {$withoutUnit}");
            
            // نمونه محصولات بدون واحد
            $samples = Product::whereNull('packaging_unit_type')
                ->select('id', 'title')
                ->limit(5)
                ->get();
            
            if ($samples->isNotEmpty()) {
                $this->line("\n  نمونه محصولات بدون واحد:");
                foreach ($samples as $product) {
                    $this->line("    - محصول #{$product->id}: {$product->title}");
                }
            }
            
            $this->newLine();
            $this->warn("  💡 توصیه: این محصولات باید واحد بسته‌بندی داشته باشند:");
            $this->line("     UPDATE products SET packaging_unit_type = 'carton' WHERE packaging_unit_type IS NULL;");
        } else {
            $this->line("  ✅ همه محصولات واحد بسته‌بندی دارند");
        }

        // آمار انواع واحدها
        $this->newLine();
        $unitTypes = Product::selectRaw('packaging_unit_type, COUNT(*) as count')
            ->whereNotNull('packaging_unit_type')
            ->groupBy('packaging_unit_type')
            ->get();

        if ($unitTypes->isNotEmpty()) {
            $this->line("  📊 توزیع انواع واحد:");
            foreach ($unitTypes as $type) {
                $this->line("    - {$type->packaging_unit_type}: {$type->count} محصول");
            }
        }
    }

    /**
     * بررسی تعداد در واحد
     */
    protected function checkQuantityPerPackaging(): void
    {
        $this->info('🔢 بررسی تعداد در واحد (quantity_per_packaging)');
        $this->info('═══════════════════════════════════════════════════');

        $validQty = Product::where('quantity_per_packaging', '>', 0)->count();
        $invalidQty = Product::where(function ($q) {
            $q->whereNull('quantity_per_packaging')
              ->orWhere('quantity_per_packaging', '<=', 0);
        })->count();

        $this->line("  ✅ محصولات با تعداد معتبر:     {$validQty}");
        
        if ($invalidQty > 0) {
            $this->warn("  ⚠️  محصولات با تعداد نامعتبر:   {$invalidQty}");
            
            // نمونه محصولات با تعداد نامعتبر
            $samples = Product::where(function ($q) {
                    $q->whereNull('quantity_per_packaging')
                      ->orWhere('quantity_per_packaging', '<=', 0);
                })
                ->select('id', 'title', 'quantity_per_packaging')
                ->limit(5)
                ->get();
            
            if ($samples->isNotEmpty()) {
                $this->line("\n  نمونه محصولات با تعداد نامعتبر:");
                foreach ($samples as $product) {
                    $qty = $product->quantity_per_packaging ?? 'NULL';
                    $this->line("    - محصول #{$product->id}: {$product->title} (qty: {$qty})");
                }
            }
            
            $this->newLine();
            $this->warn("  💡 توصیه: این محصولات باید تعداد معتبر داشته باشند:");
            $this->line("     UPDATE products SET quantity_per_packaging = 100");
            $this->line("     WHERE quantity_per_packaging IS NULL OR quantity_per_packaging <= 0;");
        } else {
            $this->line("  ✅ همه محصولات تعداد معتبر دارند");
        }

        // محصولات با quantity_per_packaging = 1
        $oneQty = Product::where('quantity_per_packaging', 1)->count();
        if ($oneQty > 0) {
            $this->newLine();
            $this->line("  ℹ️  محصولات با تعداد 1:  {$oneQty}");
            $this->line("     (این محصولات منطق خاصی برای تیراژبندی دارند)");
        }
    }

    /**
     * بررسی تیراژهای موجود
     */
    protected function checkExistingTiers(): void
    {
        $this->info('📈 بررسی تیراژهای موجود');
        $this->info('═══════════════════════════════════════════════════');

        $products = Product::whereNotNull('quantity_tiers')
            ->whereJsonLength('quantity_tiers', '>', 0)
            ->get();

        if ($products->isEmpty()) {
            $this->line("  ℹ️  هیچ محصولی تیراژ ندارد");
            return;
        }

        $this->line("  📊 تعداد محصولات با تیراژ: {$products->count()}");
        $this->newLine();

        // بررسی تعداد تیراژها
        $tierCounts = [];
        $invalidTiers = [];

        foreach ($products as $product) {
            $tiers = $product->quantity_tiers;
            $count = count($tiers);
            
            if (!isset($tierCounts[$count])) {
                $tierCounts[$count] = 0;
            }
            $tierCounts[$count]++;

            // بررسی اعتبار (3-5 تیراژ)
            if ($count < 3 || $count > 5) {
                $invalidTiers[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'count' => $count,
                ];
            }
        }

        $this->line("  توزیع تعداد تیراژها:");
        ksort($tierCounts);
        foreach ($tierCounts as $count => $total) {
            $status = ($count >= 3 && $count <= 5) ? '✅' : '⚠️';
            $this->line("    {$status} {$count} تیراژ: {$total} محصول");
        }

        // محصولات نامعتبر
        if (!empty($invalidTiers)) {
            $this->newLine();
            $this->warn("  ⚠️  محصولات با تعداد تیراژ نامعتبر:");
            foreach (array_slice($invalidTiers, 0, 5) as $item) {
                $this->line("    - محصول #{$item['id']}: {$item['title']} ({$item['count']} تیراژ)");
            }
            
            if (count($invalidTiers) > 5) {
                $remaining = count($invalidTiers) - 5;
                $this->line("    ... و {$remaining} محصول دیگر");
            }
        } else {
            $this->newLine();
            $this->line("  ✅ همه تیراژها معتبر هستند (3-5 تیراژ)");
        }
    }

    /**
     * پیش‌بینی نتیجه Migration
     */
    protected function predictMigrationResult(): void
    {
        $this->info('🔮 پیش‌بینی نتیجه Migration');
        $this->info('═══════════════════════════════════════════════════');

        // محصولات قابل بروزرسانی
        $eligible = Product::where(function ($q) {
                $q->whereNull('quantity_tiers')
                  ->orWhereJsonLength('quantity_tiers', 0);
            })
            ->whereNotNull('packaging_unit_type')
            ->where('quantity_per_packaging', '>', 0)
            ->count();

        // محصولات skip شونده
        $skipNoUnit = Product::where(function ($q) {
                $q->whereNull('quantity_tiers')
                  ->orWhereJsonLength('quantity_tiers', 0);
            })
            ->whereNull('packaging_unit_type')
            ->count();

        $skipInvalidQty = Product::where(function ($q) {
                $q->whereNull('quantity_tiers')
                  ->orWhereJsonLength('quantity_tiers', 0);
            })
            ->where(function ($q) {
                $q->whereNull('quantity_per_packaging')
                  ->orWhere('quantity_per_packaging', '<=', 0);
            })
            ->count();

        $totalSkip = $skipNoUnit + $skipInvalidQty;

        $this->line("  ✅ محصولات قابل بروزرسانی:      {$eligible}");
        $this->line("  ⏭️  محصولات skip می‌شوند:         {$totalSkip}");
        
        if ($totalSkip > 0) {
            $this->line("    - بدون واحد بسته‌بندی:         {$skipNoUnit}");
            $this->line("    - تعداد در واحد نامعتبر:       {$skipInvalidQty}");
        }

        $this->newLine();

        // نتیجه نهایی
        $successRate = $eligible > 0 ? round(($eligible / ($eligible + $totalSkip)) * 100, 1) : 0;
        
        if ($successRate >= 90) {
            $this->info("  🎉 نرخ موفقیت پیش‌بینی شده: {$successRate}%");
            $this->info("  ✅ دیتابیس آماده برای Migration است!");
        } elseif ($successRate >= 70) {
            $this->warn("  ⚠️  نرخ موفقیت پیش‌بینی شده: {$successRate}%");
            $this->warn("  💡 توصیه می‌شود محصولات مشکل‌دار را اصلاح کنید");
        } else {
            $this->error("  ❌ نرخ موفقیت پیش‌بینی شده: {$successRate}%");
            $this->error("  ⚠️  لطفاً قبل از Migration، محصولات را اصلاح کنید!");
        }

        $this->newLine();
        $this->info("  📝 دستور بعدی:");
        if ($totalSkip > 0) {
            $this->line("     1. اصلاح محصولات مشکل‌دار (مطابق توصیه‌های بالا)");
            $this->line("     2. php artisan products:generate-tiers --dry-run");
        } else {
            $this->line("     php artisan products:generate-tiers --dry-run");
        }
    }
}
