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

// اجرای Queue Worker برای هاست اشتراکی
Schedule::exec('php artisan queue:work --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->runInBackground();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
