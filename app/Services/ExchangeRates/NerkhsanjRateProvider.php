<?php

namespace App\Services\ExchangeRates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class NerkhsanjRateProvider
{
    public const SOURCE = 'nerkhsanj';

    /** @return array{rates: array<string, float>, market_date: ?string, market_time: ?string} */
    public function fetch(): array
    {
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->withHeaders([
                'User-Agent' => config('app.name') . '/exchange-rate-fetcher',
                'Cache-Control' => 'no-cache',
            ])
            ->withoutVerifying() // Skip SSL verification for local development
            ->get(config('pricing.exchange_rates.nerkhsanj_url'));

        $payload = $response->throw()->json();
        $sourceRates = is_array($payload) ? ($payload['rates'] ?? null) : null;

        if (!is_array($sourceRates)) {
            throw new RuntimeException('نرخ‌سنج پاسخ JSON قابل‌استفاده برنگرداند.');
        }

        $keyMap = [
            'USD' => 'dollar',
            'CNY' => 'yuan',
            'AED' => 'aed',
        ];
        $rates = [];

        foreach ($keyMap as $currency => $sourceKey) {
            $value = $sourceRates[$sourceKey] ?? null;

            if (is_string($value)) {
                $value = str_replace([',', '٬', ' '], '', $value);
            }

            if (!is_numeric($value) || (float) $value <= 0) {
                throw new RuntimeException("نرخ معتبر {$currency} در پاسخ نرخ‌سنج وجود ندارد.");
            }

            $rates[$currency] = (float) $value;
        }

        $marketDate = isset($payload['date']) ? trim((string) $payload['date']) : '';
        $marketTime = isset($payload['time']) ? trim((string) $payload['time']) : '';

        return [
            'rates' => $rates,
            'market_date' => $marketDate !== '' ? Str::limit($marketDate, 32, '') : null,
            'market_time' => $marketTime !== '' ? Str::limit($marketTime, 16, '') : null,
        ];
    }
}
