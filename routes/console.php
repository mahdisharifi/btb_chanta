<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\ExpireSubscriptionsJob;


//Schedule::job(new ExpireSubscriptionsJob())->dailyAt('00:05')->name('process-expired-subscriptions');
Schedule::job(new ExpireSubscriptionsJob())->everySecond(60);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
