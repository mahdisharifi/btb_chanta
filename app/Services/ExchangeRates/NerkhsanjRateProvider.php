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
            ->connectTimeout(2)
            ->timeout(5)
            ->withHeaders([
                'User-Agent' => config('app.name') . '/exchange-rate-fetcher',
                'Cache-Control' => 'no-cache',
            ])
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

        return [
            'rates' => $rates,
            'market_date' => isset($payload['date']) ? Str::limit((string) $payload['date'], 32, '') : null,
            'market_time' => isset($payload['time']) ? Str::limit((string) $payload['time'], 16, '') : null,
        ];
    }
}
