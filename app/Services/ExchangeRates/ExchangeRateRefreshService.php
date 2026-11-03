<?php

namespace App\Services\ExchangeRates;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExchangeRateRefreshService
{
    public function __construct(private readonly NerkhsanjRateProvider $provider)
    {
    }

    public function refresh(?int $maxAgeMinutes = null): void
    {
        $configuredMaxAgeMinutes = max(1, (int) config('pricing.exchange_rates.max_age_minutes', 15));
        $maxAgeMinutes = $maxAgeMinutes === null
            ? $configuredMaxAgeMinutes
            : max($configuredMaxAgeMinutes, min(24 * 60, max(1, $maxAgeMinutes)));
        $lock = Cache::lock('exchange-rates.refresh', 30);

        if (!$lock->get()) {
            Log::info('Exchange rate refresh skipped because another refresh is active.');
            return;
        }

        try {
            $this->refreshUnderLock($maxAgeMinutes);
        } finally {
            $lock->release();
        }
    }

    private function refreshUnderLock(int $maxAgeMinutes): void
    {
        $attemptedAt = Carbon::now('UTC');
        $snapshot = null;
        $fetchedAt = null;

        try {
            $snapshot = $this->provider->fetch();
            $fetchedAt = Carbon::now('UTC');
            $sourceUpdatedAt = $this->parseSourceUpdatedAt($snapshot);
            if ($sourceUpdatedAt->lt($fetchedAt->copy()->subMinutes($maxAgeMinutes))) {
                throw new \RuntimeException('نرخ‌سنج پاسخ قدیمی برگرداند؛ آخرین نرخ معتبر حفظ شد.');
            }

            if ($sourceUpdatedAt->gt($fetchedAt->copy()->addMinutes(2))) {
                throw new \RuntimeException('زمان نرخ‌سنج از زمان سرور جلوتر است؛ آخرین نرخ معتبر حفظ شد.');
            }

            DB::transaction(function () use ($snapshot, $attemptedAt, $fetchedAt, $sourceUpdatedAt): void {
                foreach ($snapshot['rates'] as $currency => $rate) {
                    $exchangeRate = ExchangeRate::query()->lockForUpdate()->find($currency);

                    if ($exchangeRate?->source_updated_at?->gt($sourceUpdatedAt)) {
                        continue;
                    }

                    if ($exchangeRate
                        && $exchangeRate->last_error === null
                        && $exchangeRate->last_attempt_at?->gt($attemptedAt)
                        && $exchangeRate->source_updated_at?->gte($sourceUpdatedAt)) {
                        continue;
                    }

                    $attributes = [
                        'rate_toman' => $rate,
                        'source' => NerkhsanjRateProvider::SOURCE,
                        'source_market_date' => $snapshot['market_date'],
                        'source_market_time' => $snapshot['market_time'],
                        'source_updated_at' => $sourceUpdatedAt,
                        'fetched_at' => $fetchedAt,
                        'last_attempt_at' => $attemptedAt,
                        'last_error' => null,
                    ];

                    if ($exchangeRate) {
                        $exchangeRate->fill($attributes)->save();
                    } else {
                        ExchangeRate::query()->create(['currency' => $currency, ...$attributes]);
                    }
                }
            });
        } catch (Throwable $exception) {
            $hasRatesWithoutSourceTime = is_array($snapshot)
                && is_array($snapshot['rates'] ?? null)
                && (empty($snapshot['market_date']) || empty($snapshot['market_time']));

            DB::transaction(function () use ($attemptedAt, $exception, $snapshot, $fetchedAt, $hasRatesWithoutSourceTime): void {
                $rates = ExchangeRate::query()
                    ->whereIn('currency', ['USD', 'CNY', 'AED'])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('currency');

                foreach (['USD', 'CNY', 'AED'] as $currency) {
                    $rate = $rates->get($currency);
                    $candidateRate = $snapshot['rates'][$currency] ?? null;
                    $canStoreDisplayOnlyCandidate = $hasRatesWithoutSourceTime
                        && is_numeric($candidateRate)
                        && (float) $candidateRate > 0
                        && (!$rate || $rate->source_updated_at === null);

                    if ($rate?->last_attempt_at?->gt($attemptedAt)) {
                        continue;
                    }

                    $attributes = [
                        'last_attempt_at' => $attemptedAt,
                        'last_error' => mb_substr($exception->getMessage(), 0, 500),
                        'updated_at' => $attemptedAt,
                    ];

                    if ($canStoreDisplayOnlyCandidate) {
                        $attributes += [
                            'rate_toman' => $candidateRate,
                            'source' => NerkhsanjRateProvider::SOURCE,
                            'source_market_date' => $snapshot['market_date'],
                            'source_market_time' => $snapshot['market_time'],
                            'source_updated_at' => null,
                            'fetched_at' => $fetchedAt ?? $attemptedAt,
                        ];
                    }

                    if ($rate) {
                        $rate->forceFill($attributes)->save();
                    } elseif ($canStoreDisplayOnlyCandidate) {
                        ExchangeRate::query()->create([
                            'currency' => $currency,
                            ...$attributes,
                        ]);
                    }
                }
            });

            Log::warning('Exchange rate refresh failed; preserving the last good snapshot.', [
                'source' => NerkhsanjRateProvider::SOURCE,
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 250),
            ]);

            throw $exception;
        }
    }

    /** @param array{market_date: ?string, market_time: ?string} $snapshot */
    private function parseSourceUpdatedAt(array $snapshot): Carbon
    {
        if (!$snapshot['market_date'] || !$snapshot['market_time']) {
            throw new \RuntimeException('زمان انتشار نرخ در پاسخ نرخ‌سنج موجود نیست.');
        }

        try {
            return \Morilog\Jalali\Jalalian::fromFormat(
                'Y/m/d H:i',
                $snapshot['market_date'] . ' ' . $snapshot['market_time'],
                new \DateTimeZone('Asia/Tehran')
            )->toCarbon()->utc();
        } catch (Throwable $exception) {
            throw new \RuntimeException('زمان انتشار نرخ در پاسخ نرخ‌سنج معتبر نیست.', previous: $exception);
        }
    }
}
