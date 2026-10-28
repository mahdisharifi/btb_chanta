<?php

namespace App\Jobs;

use App\Services\ForeignCurrencyPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshDueForeignCurrencyPrices implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 50;

    public int $uniqueFor = 120;

    public function uniqueId(): string
    {
        return 'refresh-due-foreign-currency-prices';
    }

    public function handle(ForeignCurrencyPriceService $service): void
    {
        $service->processDue();
    }
}
