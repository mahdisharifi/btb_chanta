<?php

use App\Jobs\CleanupProductCompletionMediaJob;
use App\Jobs\ExpireProductCompletionTasksJob;
use App\Jobs\ExpireSubscriptionsJob;
use App\Jobs\RecoverProductCompletionOperationsJob;
use App\Jobs\RefreshDueForeignCurrencyPrices;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

// اجرای Job انقضای اشتراک‌ها
Schedule::job(new ExpireSubscriptionsJob())->dailyAt('00:05')->name('process-expired-subscriptions');

Schedule::job(new ExpireProductCompletionTasksJob())
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('expire-product-completion-tasks');

Schedule::job(new CleanupProductCompletionMediaJob())
    ->dailyAt('01:15')
    ->withoutOverlapping()
    ->name('cleanup-product-completion-media');

Schedule::job(new RecoverProductCompletionOperationsJob())
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('recover-product-completion-operations');

// Due supplier/currency settings refresh one shared quote and enqueue bounded product chunks.
Schedule::job(new RefreshDueForeignCurrencyPrices())
    ->everyMinute()
    ->withoutOverlapping()
    ->name('refresh-due-foreign-currency-prices');

Schedule::call(function (): void {
    $disk = Storage::disk('local');
    $directory = 'catalog-pdf-downloads';
    if (!$disk->exists($directory)) {
        return;
    }

    // Keep completed exports long enough for slow clients; bound abandoned-file retention.
    $expiredBefore = now()->subHours(2)->getTimestamp();
    foreach ($disk->files($directory) as $temporaryFile) {
        if ($disk->lastModified($temporaryFile) < $expiredBefore) {
            $disk->delete($temporaryFile);
        }
    }
})
    ->everyFifteenMinutes()
    ->name('cleanup-catalog-pdf-downloads')
    ->withoutOverlapping();

// Use the scheduler's PHP binary and consume both configured and legacy default queues.
$queueConnection = (string) config('queue.default');
$queueWorkerConnection = $queueConnection === 'sync' ? 'database' : $queueConnection;
$workerQueues = implode(',', array_unique(array_filter([
    (string) config("queue.connections.{$queueWorkerConnection}.queue", 'default'),
    'default',
])));

Schedule::command('queue:work', [
    $queueWorkerConnection,
    '--queue' => $workerQueues,
    '--stop-when-empty',
    '--max-time' => 55,
    '--timeout' => 60,
    '--sleep' => 1,
    '--no-interaction',
])
    ->everyMinute()
    ->name('shared-hosting-queue-worker')
    ->when(fn () => getenv('APP_SERVE_QUEUE_RUNNING') !== '1')
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/queue-worker.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('prices:refresh-exchange-rates', function (ExchangeRateRefreshService $service) {
    $service->refresh();
    $this->info('Latest USD, CNY and AED rates have been stored.');
})->purpose('Fetch current USD, CNY and AED rates from the configured direct provider');
