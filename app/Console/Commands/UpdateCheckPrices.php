<?php

namespace App\Console\Commands;

use App\Models\CheckPeriod;
use App\Models\ProductSupplier;
use App\Models\PriceType;
use App\Models\Supplier;
use App\Helpers\PriceRoundingHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateCheckPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:update-check
                            {--supplier= : Supplier ID (اختیاری - اگر خالی باشد، همه تأمین‌کنندگان)}
                            {--period= : Check Period ID (اختیاری - اگر خالی باشد، همه دوره‌ها)}
                            {--category= : Category ID (اختیاری - فقط محصولات این دسته‌بندی)}
                            {--dry-run : نمایش تغییرات بدون اعمال آن‌ها}
                            {--force : بروزرسانی بدون تایید}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'بروزرسانی قیمت‌های چک (default/percentage) بر اساس درصدهای جدید';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $supplierId = $this->option('supplier');
        $periodId = $this->option('period');
        $categoryId = $this->option('category');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🔍 در حال بررسی قیمت‌های نیازمند بروزرسانی...');
        $this->newLine();

        // یافتن قیمت‌های نیازمند بروزرسانی
        $query = DB::table('product_supplier_prices as psp')
            ->join('product_suppliers as ps', 'psp.product_supplier_id', '=', 'ps.id')
            ->join('products as p', 'psp.product_id', '=', 'p.id')
            ->join('price_types as pt', 'psp.price_type_id', '=', 'pt.id')
            ->join('check_periods as cp', 'psp.check_period_id', '=', 'cp.id')
            ->whereNotNull('psp.check_period_id')
            ->where('pt.code', 'check')
            ->where('psp.is_active', true);

        // فیلترها
        if ($supplierId) {
            $query->where('ps.supplier_id', $supplierId);
        }
        
        if ($periodId) {
            $query->where('psp.check_period_id', $periodId);
        }
        
        if ($categoryId) {
            $query->where('p.category_id', $categoryId);
        }

        // فقط قیمت‌های default یا percentage (نه manual)
        // default: percentage_override = null
        // percentage: percentage_override != null
        $query->where(function($q) {
            $q->whereNull('psp.percentage_override') // default
              ->orWhereNotNull('psp.percentage_override'); // percentage
        });

        $prices = $query->select(
            'psp.id',
            'psp.product_id',
            'psp.product_supplier_id',
            'psp.tier_index',
            'psp.price_type_id',
            'psp.check_period_id',
            'psp.price as current_price',
            'psp.percentage_override',
            'ps.supplier_id',
            'p.title as product_title',
            'p.category_id',
            'cp.name as period_name',
            'cp.days as period_days'
        )->get();

        if ($prices->isEmpty()) {
            $this->warn('⚠️  هیچ قیمتی برای بروزرسانی یافت نشد.');
            return 0;
        }

        $this->info("📊 تعداد قیمت‌های یافت شده: " . $prices->count());
        $this->newLine();

        // محاسبه قیمت‌های جدید
        $updates = [];
        $cashPriceType = PriceType::where('code', 'cash')->first();
        
        // ✅ Cache suppliers برای rounding
        $suppliers = Supplier::whereIn('id', $prices->pluck('supplier_id')->unique())->get()->keyBy('id');

        foreach ($prices as $priceRecord) {
            // دریافت قیمت نقدی
            $cashPrice = DB::table('product_supplier_prices')
                ->where('product_supplier_id', $priceRecord->product_supplier_id)
                ->where('tier_index', $priceRecord->tier_index)
                ->where('price_type_id', $cashPriceType->id)
                ->whereNull('check_period_id')
                ->value('price');

            if (!$cashPrice || $cashPrice <= 0) {
                continue; // قیمت نقدی معتبر نیست
            }
            
            // ✅ دریافت supplier برای rounding
            $supplier = $suppliers->get($priceRecord->supplier_id);
            if (!$supplier) continue;

            // محاسبه قیمت جدید
            $newPrice = null;
            $mode = null;

            if ($priceRecord->percentage_override !== null) {
                // mode: percentage
                $mode = 'percentage';
                $percentage = (float)$priceRecord->percentage_override;
                
                // ✅ استفاده از Helper برای محاسبه با rounding
                $newPrice = PriceRoundingHelper::calculateCheckPrice($cashPrice, $percentage, $supplier);
            } else {
                // mode: default
                $mode = 'default';
                $percentage = $this->getEffectivePercentage(
                    $priceRecord->supplier_id,
                    $priceRecord->category_id,
                    $priceRecord->check_period_id
                );
                
                if ($percentage !== null) {
                    // ✅ استفاده از Helper برای محاسبه با rounding
                    $newPrice = PriceRoundingHelper::calculateCheckPrice($cashPrice, $percentage, $supplier);
                }
            }

            if ($newPrice === null || $newPrice <= 0) {
                continue; // محاسبه ناموفق
            }

            // مقایسه با قیمت فعلی
            $difference = abs($newPrice - $priceRecord->current_price);
            
            if ($difference > 0.01) { // تفاوت بیش از 0.01 تومان
                $updates[] = [
                    'id' => $priceRecord->id,
                    'product_title' => $priceRecord->product_title,
                    'tier_index' => $priceRecord->tier_index,
                    'period_name' => $priceRecord->period_name,
                    'mode' => $mode,
                    'current_price' => $priceRecord->current_price,
                    'new_price' => round($newPrice, 2),
                    'difference' => round($newPrice - $priceRecord->current_price, 2),
                    'percentage' => $percentage,
                ];
            }
        }

        if (empty($updates)) {
            $this->info('✅ همه قیمت‌ها به‌روز هستند. نیازی به بروزرسانی نیست.');
            return 0;
        }

        // نمایش تغییرات
        $this->info("📋 تغییرات پیشنهادی ({count} قیمت):");
        $this->newLine();

        $this->table(
            ['#', 'محصول', 'تیراژ', 'دوره', 'Mode', 'قیمت فعلی', 'قیمت جدید', 'تفاوت', 'درصد'],
            collect($updates)->take(20)->map(function($update, $index) {
                return [
                    $index + 1,
                    mb_substr($update['product_title'], 0, 30),
                    $update['tier_index'] + 1,
                    $update['period_name'],
                    $update['mode'],
                    number_format($update['current_price']),
                    number_format($update['new_price']),
                    number_format($update['difference']),
                    number_format($update['percentage'], 2) . '%',
                ];
            })->toArray()
        );

        if (count($updates) > 20) {
            $this->warn("... و " . (count($updates) - 20) . " قیمت دیگر");
        }

        $this->newLine();

        // خلاصه
        $totalDifference = array_sum(array_column($updates, 'difference'));
        $this->info("📊 خلاصه:");
        $this->line("  • تعداد کل: " . count($updates) . " قیمت");
        $this->line("  • مجموع تفاوت: " . number_format($totalDifference) . " تومان");
        $this->newLine();

        // Dry run check
        if ($dryRun) {
            $this->warn('⚠️  حالت Dry Run: تغییرات اعمال نشد.');
            return 0;
        }

        // تایید
        if (!$force && !$this->confirm('آیا می‌خواهید این تغییرات را اعمال کنید؟', false)) {
            $this->warn('❌ عملیات لغو شد.');
            return 1;
        }

        // اعمال تغییرات
        $this->info('🔄 در حال بروزرسانی قیمت‌ها...');
        
        $progressBar = $this->output->createProgressBar(count($updates));
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;

        DB::transaction(function() use ($updates, &$successCount, &$errorCount, $progressBar) {
            foreach ($updates as $update) {
                try {
                    DB::table('product_supplier_prices')
                        ->where('id', $update['id'])
                        ->update([
                            'price' => $update['new_price'],
                            'updated_at' => now(),
                        ]);
                    
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    \Log::error("Failed to update price ID {$update['id']}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // نتیجه نهایی
        if ($errorCount === 0) {
            $this->info("✅ بروزرسانی موفق: $successCount قیمت به‌روز شد.");
        } else {
            $this->warn("⚠️  بروزرسانی با خطا: $successCount موفق، $errorCount خطا");
        }

        return 0;
    }

    /**
     * دریافت درصد مؤثر با سلسله مراتب
     */
    private function getEffectivePercentage(int $supplierId, ?int $categoryId, int $periodId): ?float
    {
        // 1. بررسی درصد دسته‌بندی
        if ($categoryId) {
            $categoryPercentage = DB::table('category_check_period')
                ->where('category_id', $categoryId)
                ->where('check_period_id', $periodId)
                ->where('supplier_id', $supplierId)
                ->where('is_active', true)
                ->value('percentage_increase');
            
            if ($categoryPercentage !== null) {
                return (float)$categoryPercentage;
            }
        }
        
        // 2. درصد پیش‌فرض دوره چک
        $defaultPercentage = DB::table('supplier_check_period')
            ->where('supplier_id', $supplierId)
            ->where('check_period_id', $periodId)
            ->where('is_active', true)
            ->value('percentage_increase');
        
        return $defaultPercentage !== null ? (float)$defaultPercentage : null;
    }
}
