<?php

namespace App\Listeners;

use App\Events\CheckPercentageChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class UpdateAffectedCheckPrices implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 60;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CheckPercentageChanged $event): void
    {
        Log::info('🔄 CheckPercentageChanged Event Received', [
            'supplier_id' => $event->supplierId,
            'category_id' => $event->categoryId,
            'period_id' => $event->checkPeriodId,
            'change_type' => $event->changeType,
            'old_percentage' => $event->oldPercentage,
            'new_percentage' => $event->newPercentage,
        ]);

        // ساخت آرگومان‌های command
        $arguments = [
            '--supplier' => $event->supplierId,
            '--force' => true, // بدون تایید
        ];

        if ($event->checkPeriodId) {
            $arguments['--period'] = $event->checkPeriodId;
        }

        if ($event->categoryId) {
            $arguments['--category'] = $event->categoryId;
        }

        try {
            // اجرای command
            $exitCode = Artisan::call('prices:update-check', $arguments);

            if ($exitCode === 0) {
                Log::info('✅ Check prices updated successfully', [
                    'supplier_id' => $event->supplierId,
                    'category_id' => $event->categoryId,
                    'period_id' => $event->checkPeriodId,
                ]);
            } else {
                Log::warning('⚠️ Check prices update completed with warnings', [
                    'exit_code' => $exitCode,
                    'supplier_id' => $event->supplierId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Failed to update check prices', [
                'supplier_id' => $event->supplierId,
                'category_id' => $event->categoryId,
                'period_id' => $event->checkPeriodId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw برای retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(CheckPercentageChanged $event, \Throwable $exception): void
    {
        Log::error('❌ UpdateAffectedCheckPrices Listener Failed After All Retries', [
            'supplier_id' => $event->supplierId,
            'category_id' => $event->categoryId,
            'period_id' => $event->checkPeriodId,
            'error' => $exception->getMessage(),
        ]);

        // TODO: ارسال notification به ادمین
    }
}
