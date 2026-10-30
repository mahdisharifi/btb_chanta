<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierExchangeRateSetting;

final class CatalogExchangeRateBasisResolver
{
    private const CURRENCY_LABELS = [
        'USD' => 'دلار',
        'CNY' => 'یوان',
        'AED' => 'درهم',
    ];

    /**
     * Resolve the supplier's configured exchange-rate basis for catalog contact details.
     * In automatic mode only a successful persisted fetch is considered current.
     *
     * @return array{currency:string,currency_label:string,is_auto:bool,rate_value:?string,rate_display:?string,status:?string,last_fetched_at:?string}|null
     */
    public function resolve(Supplier $supplier): ?array
    {
        $currency = strtoupper((string) $supplier->pricing_currency);
        $currencyLabel = self::CURRENCY_LABELS[$currency] ?? null;

        if ($currencyLabel === null) {
            return null;
        }

        $setting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency', $currency)
            ->first();

        $isAuto = (bool) $setting?->auto_update;
        $rate = $isAuto
            ? $setting?->rate_toman
            : $setting?->manual_rate_toman;
        $hasRate = is_numeric($rate) && (float) $rate > 0;

        if ($isAuto && !$setting?->fetched_at) {
            $hasRate = false;
        }

        $lastFetchedAt = null;
        if ($isAuto && $hasRate) {
            $tehranTime = $setting->fetched_at->setTimezone('Asia/Tehran');
            $lastFetchedAt = jdate($tehranTime)->format('Y/m/d H:i');
        }

        return [
            'currency' => $currency,
            'currency_label' => $currencyLabel,
            'is_auto' => $isAuto,
            'rate_value' => $hasRate ? (string) $rate : null,
            'rate_display' => $hasRate ? $this->formatRate($rate) : null,
            'status' => $hasRate
                ? null
                : ($isAuto ? 'هنوز نرخ موفقی دریافت نشده' : 'نرخ دستی تنظیم نشده'),
            'last_fetched_at' => $lastFetchedAt,
        ];
    }

    private function formatRate(int|float|string $rate): string
    {
        $formatted = number_format((float) $rate, 4, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return strtr($formatted, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}
