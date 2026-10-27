<?php

use App\Jobs\CleanupProductCompletionMediaJob;
use App\Jobs\ExpireProductCompletionTasksJob;
use App\Jobs\ExpireSubscriptionsJob;
use App\Jobs\RecoverProductCompletionOperationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

// Use the scheduler's PHP binary and consume both configured and legacy default queues.
$queueConnection = (string) config('queue.default');
$workerQueues = implode(',', array_unique(array_filter([
    (string) config("queue.connections.{$queueConnection}.queue", 'default'),
    'default',
])));

Schedule::command('queue:work', [
    '--queue' => $workerQueues,
    '--stop-when-empty',
    '--max-time' => 55,
    '--timeout' => 60,
    '--sleep' => 1,
    '--no-interaction',
])
    ->everyMinute()
    ->name('shared-hosting-queue-worker')
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/queue-worker.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
