<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierExchangeRateSetting;
use Illuminate\Support\Collection;

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
        if (!isset(self::CURRENCY_LABELS[$currency])) {
            return null;
        }

        $setting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency', $currency)
            ->first();

        return $this->formatResolvedBasis($supplier, $currency, $setting);
    }

    /**
     * Resolve several suppliers with one settings query, for all-branch catalog
     * screens where calling resolve() in a loop would create an N+1 query.
     *
     * @param iterable<Supplier> $suppliers
     * @return Collection<int, array|null>
     */
    public function resolveMany(iterable $suppliers): Collection
    {
        $suppliers = collect($suppliers)->values();
        $eligible = $suppliers->filter(static function (Supplier $supplier): bool {
            return isset(self::CURRENCY_LABELS[strtoupper((string) $supplier->pricing_currency)]);
        });

        $settingsByKey = collect();
        if ($eligible->isNotEmpty()) {
            $settingsByKey = SupplierExchangeRateSetting::query()
                ->whereIn('supplier_id', $eligible->pluck('id')->map(static fn ($id): int => (int) $id)->unique())
                ->whereIn('currency', $eligible->map(static fn (Supplier $supplier): string => strtoupper((string) $supplier->pricing_currency))->unique())
                ->get()
                ->keyBy(static fn (SupplierExchangeRateSetting $setting): string =>
                    (int) $setting->supplier_id . ':' . strtoupper((string) $setting->currency)
                );
        }

        return $suppliers->mapWithKeys(function (Supplier $supplier) use ($settingsByKey): array {
            $currency = strtoupper((string) $supplier->pricing_currency);
            $setting = $settingsByKey->get((int) $supplier->id . ':' . $currency);

            return [(int) $supplier->id => $this->formatResolvedBasis($supplier, $currency, $setting)];
        });
    }

    private function formatResolvedBasis(
        Supplier $supplier,
        string $currency,
        ?SupplierExchangeRateSetting $setting,
    ): ?array {
        $currencyLabel = self::CURRENCY_LABELS[$currency] ?? null;
        if ($currencyLabel === null) {
            return null;
        }

        $isAuto = (bool) $setting?->auto_update;
        $rate = $isAuto
            ? $setting?->rate_toman
            : $setting?->manual_rate_toman;
        $hasRate = $isAuto
            ? app(ForeignCurrencyPriceService::class)->isFreshSupplierRate($setting)
            : is_numeric($rate) && (float) $rate > 0;

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
                : ($isAuto ? 'نرخ خودکار معتبر در بازهٔ انتخاب‌شده موجود نیست' : 'نرخ دستی تنظیم نشده'),
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
