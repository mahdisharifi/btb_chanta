<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ExpireSubscriptionsJob;

// اجرای Job انقضای اشتراک‌ها
Schedule::job(new ExpireSubscriptionsJob())->dailyAt('00:05')->name('process-expired-subscriptions');

// اجرای Queue Worker برای هاست اشتراکی
Schedule::exec('php artisan queue:work --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->runInBackground();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
