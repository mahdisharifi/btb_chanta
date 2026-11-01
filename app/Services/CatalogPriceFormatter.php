<?php

namespace App\Services;

final class CatalogPriceFormatter
{
    private const CURRENCY_LABELS = [
        'USD' => 'دلار',
        'CNY' => 'یوان',
        'AED' => 'درهم',
    ];

    public function format(
        int|float|string|null $priceToman,
        ?string $displayCurrency,
        ?string $appliedCurrency = null,
        int|float|string|null $appliedRate = null,
        bool $currencySnapshotValid = true,
    ): ?string {
        if (!is_numeric($priceToman) || (float) $priceToman <= 0) {
            return null;
        }

        if ($displayCurrency === 'TOM') {
            return number_format((float) $priceToman, 0, '.', ',');
        }

        if (!$currencySnapshotValid
            || !isset(self::CURRENCY_LABELS[$displayCurrency])
            || $appliedCurrency !== $displayCurrency
            || !is_numeric($appliedRate)
            || (float) $appliedRate <= 0) {
            return null;
        }

        $amount = number_format((float) $priceToman / (float) $appliedRate, 2, '.', ',');
        $amount = rtrim(rtrim($amount, '0'), '.');

        return $amount . ' ' . self::CURRENCY_LABELS[$displayCurrency];
    }

    public function label(?string $currency): ?string
    {
        return $currency === 'TOM'
            ? 'تومان'
            : (self::CURRENCY_LABELS[$currency] ?? null);
    }

    public function isSupportedForeignCurrency(?string $currency): bool
    {
        return isset(self::CURRENCY_LABELS[$currency]);
    }
}
