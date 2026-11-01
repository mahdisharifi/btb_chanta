<?php

namespace App\Jobs;

use App\Services\ExchangeRates\ExchangeRateRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefreshExchangeRates implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    public int $uniqueFor = 240;

    public function uniqueId(): string
    {
        return 'refresh-exchange-rates';
    }

    public function backoff(): array
    {
        return [30, 90];
    }

    public function handle(ExchangeRateRefreshService $service): void
    {
        $service->refresh();
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
