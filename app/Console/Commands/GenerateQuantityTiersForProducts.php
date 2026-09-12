<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Helpers\QuantityTierValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ✅ Command برای ایجاد تیراژ خودکار برای محصولات بدون تیراژ
 * 
 * استفاده:
 * - مشاهده محصولات بدون تیراژ:       php artisan products:generate-tiers --dry-run
 * - اعمال تیراژ خودکار روی همه:     php artisan products:generate-tiers
 * - فقط محصولات فعال:               php artisan products:generate-tiers --active-only
 */
class GenerateQuantityTiersForProducts extends Command
{
    /**
     * دستور Console
     *
     * @var string
     */
    protected $signature = 'products:generate-tiers 
                            {--dry-run : نمایش محصولات بدون اعمال تغییرات}
                            {--active-only : فقط محصولات فعال}
                            {--supplier= : فقط محصولات یک تامین‌کننده خاص (supplier_id)}';

    /**
     * توضیحات دستور
     *
     * @var string
     */
    protected $description = 'ایجاد تیراژ خودکار (4 تیراژ) برای محصولاتی که تیراژ ندارند';

    /**
     * آمار
     */
    protected int $totalProcessed = 0;
    protected int $totalUpdated = 0;
    protected int $totalSkipped = 0;
    protected int $totalErrors = 0;
    protected array $errorMessages = [];

    /**
     * اجرای دستور
     */
    public function handle(): int
    {
        $this->info('🚀 شروع فرآیند ایجاد تیراژ خودکار...');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $activeOnly = $this->option('active-only');
        $supplierId = $this->option('supplier');

        // ✅ Query محصولات بدون تیراژ
        $query = Product::query()
            ->whereNull('quantity_tiers')
            ->orWhereJsonLength('quantity_tiers', 0);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $products = $query->with('supplier:id,company_name')->get();

        if ($products->isEmpty()) {
            $this->info('✅ همه محصولات دارای تیراژ هستند. نیازی به بروزرسانی نیست.');
            return Command::SUCCESS;
        }

        $this->info("📊 تعداد محصولات بدون تیراژ: " . $products->count());
        $this->newLine();

        if ($isDryRun) {
            $this->warn('⚠️  حالت DRY-RUN فعال است. هیچ تغییری اعمال نمی‌شود.');
            $this->newLine();
        }

        // ✅ پردازش محصولات
        $progressBar = $this->output->createProgressBar($products->count());
        $progressBar->start();

        foreach ($products as $product) {
            $this->totalProcessed++;
            
            try {
                $result = $this->processProduct($product, $isDryRun);
                
                if ($result['status'] === 'updated') {
                    $this->totalUpdated++;
                } elseif ($result['status'] === 'skipped') {
                    $this->totalSkipped++;
                    if (isset($result['reason'])) {
                        $this->errorMessages[] = "محصول #{$product->id}: {$result['reason']}";
                    }
                }
            } catch (\Exception $e) {
                $this->totalErrors++;
                $this->errorMessages[] = "محصول #{$product->id} - {$product->title}: " . $e->getMessage();
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // ✅ نمایش آمار
        $this->displayStatistics($isDryRun);

        return Command::SUCCESS;
    }

    /**
     * پردازش یک محصول
     */
    protected function processProduct(Product $product, bool $isDryRun): array
    {
        // ✅ بررسی واحد بسته‌بندی
        if (!$product->packaging_unit_type || !$product->quantity_per_packaging) {
            return [
                'status' => 'skipped',
                'reason' => 'واحد بسته‌بندی تعریف نشده است',
            ];
        }

        $qtyPerPack = (int)$product->quantity_per_packaging;

        if ($qtyPerPack <= 0) {
            return [
                'status' => 'skipped',
                'reason' => 'تعداد در واحد بسته‌بندی نامعتبر است',
            ];
        }

        // ✅ تولید تیراژ خودکار
        $tiers = $this->generateAutoTiers($qtyPerPack, $product->packaging_unit_type);

        // ✅ Validation تیراژها
        try {
            QuantityTierValidator::validate($tiers);
        } catch (\Exception $e) {
            return [
                'status' => 'skipped',
                'reason' => 'تیراژهای تولید شده نامعتبر هستند: ' . $e->getMessage(),
            ];
        }

        // ✅ اگر dry-run است، فقط گزارش بده
        if ($isDryRun) {
            $this->line("\n✓ محصول #{$product->id} - {$product->title}");
            $this->line("  تیراژها: " . count($tiers) . " عدد");
            foreach ($tiers as $tier) {
                $maxLabel = $tier['max_quantity'] ? $tier['max_quantity'] : '∞';
                $this->line("    - {$tier['name']}: {$tier['min_quantity']} تا {$maxLabel}");
            }
            
            return ['status' => 'updated'];
        }

        // ✅ ذخیره تیراژها در دیتابیس
        DB::transaction(function () use ($product, $tiers) {
            $product->update([
                'quantity_tiers' => $tiers,
            ]);
        });

        return ['status' => 'updated'];
    }

    /**
     * تولید تیراژ خودکار (مشابه منطق Create.php)
     */
    protected function generateAutoTiers(int $qtyPerPack, string $packagingType): array
    {
        $unitLabel = $this->getPackagingUnitLabel($packagingType);

        // ═══════════════════════════════════════════════════════════
        // ✅ ساختار جدید 4 تیراژی
        // ═══════════════════════════════════════════════════════════
        
        // ✅ استثنا: اگر بسته‌بندی (کارتن/مرجع) = 1 عددی باشد
        if ($qtyPerPack === 1) {
            return [
                [
                    'name' => 'زیر کارتن',
                    'min_quantity' => 0,
                    'max_quantity' => 0,
                    'description' => 'غیرفعال (کارتن 1 عددی است)',
                    'is_active' => false,
                ],
                [
                    'name' => 'کارتن',
                    'min_quantity' => 1,
                    'max_quantity' => 4,
                    'description' => "1 تا 4 {$unitLabel}",
                    'is_active' => true,
                ],
                [
                    'name' => 'عمده',
                    'min_quantity' => 5,
                    'max_quantity' => 15,
                    'description' => "5 تا 15 {$unitLabel}",
                    'is_active' => true,
                ],
                [
                    'name' => 'بار',
                    'min_quantity' => 16,
                    'max_quantity' => null,
                    'description' => "16 {$unitLabel} به بالا",
                    'is_active' => true,
                ],
            ];
        }

        // ✅ حالت عادی (quantity_per_packaging >= 2)
        return [
            [
                'name' => 'زیر کارتن',
                'min_quantity' => 1,
                'max_quantity' => $qtyPerPack - 1,
                'description' => "کمتر از یک {$unitLabel} (1 تا " . ($qtyPerPack - 1) . " عدد)",
                'is_active' => true,
            ],
            [
                'name' => 'کارتن',
                'min_quantity' => $qtyPerPack, // max_قبلی + 1 = (qtyPerPack-1) + 1
                'max_quantity' => $qtyPerPack * 4,
                'description' => "1 تا 4 {$unitLabel} ({$qtyPerPack} تا " . ($qtyPerPack * 4) . " عدد)",
                'is_active' => true,
            ],
            [
                'name' => 'عمده',
                'min_quantity' => ($qtyPerPack * 4) + 1, // max_قبلی + 1
                'max_quantity' => $qtyPerPack * 15,
                'description' => "5 تا 15 {$unitLabel} (" . (($qtyPerPack * 4) + 1) . " تا " . ($qtyPerPack * 15) . " عدد)",
                'is_active' => true,
            ],
            [
                'name' => 'بار',
                'min_quantity' => ($qtyPerPack * 15) + 1, // max_قبلی + 1
                'max_quantity' => null,
                'description' => "16 {$unitLabel} به بالا (" . (($qtyPerPack * 15) + 1) . " عدد به بالا)",
                'is_active' => true,
            ],
        ];
    }

    /**
     * برچسب فارسی واحد بسته‌بندی
     */
    protected function getPackagingUnitLabel(string $type): string
    {
        $labels = [
            'carton'  => 'کارتن',
            'jean'    => 'جین',
            'jowab'   => 'جورب',
            'package' => 'بسته',
            'bundle'  => 'شیرینگ',
            'roll'    => 'طاقه',
            'piece'   => 'عدد',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * نمایش آمار نهایی
     */
    protected function displayStatistics(bool $isDryRun): void
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('📊 آمار نهایی:');
        $this->info('═══════════════════════════════════════════════════');
        $this->line("  • تعداد کل پردازش شده:    {$this->totalProcessed}");
        $this->line("  • تعداد بروزرسانی شده:     {$this->totalUpdated}");
        $this->line("  • تعداد Skip شده:          {$this->totalSkipped}");
        $this->line("  • تعداد خطاها:             {$this->totalErrors}");
        $this->info('═══════════════════════════════════════════════════');

        // ✅ نمایش خطاها
        if (!empty($this->errorMessages)) {
            $this->newLine();
            $this->error('❌ خطاها و Skip‌ها:');
            foreach (array_slice($this->errorMessages, 0, 20) as $message) {
                $this->line("  • {$message}");
            }
            
            if (count($this->errorMessages) > 20) {
                $remaining = count($this->errorMessages) - 20;
                $this->line("  ... و {$remaining} خطای دیگر");
            }
        }

        $this->newLine();
        
        if ($isDryRun) {
            $this->warn('⚠️  این یک DRY-RUN بود. برای اعمال تغییرات دستور را بدون --dry-run اجرا کنید:');
            $this->line('   php artisan products:generate-tiers');
        } else {
            $this->info('✅ فرآیند با موفقیت به پایان رسید!');
        }
    }
}
