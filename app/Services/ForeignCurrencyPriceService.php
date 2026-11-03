<?php

namespace App\Services;

use App\Helpers\PriceRoundingHelper;
use App\Models\ExchangeRate;
use App\Models\PriceType;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\SupplierExchangeRateSetting;
use App\Models\Supplier;
use App\Models\SupplierTierDiscount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ForeignCurrencyPriceService
{
    public const MAX_TOMAN_PRICE = 999999999999;
    public const CURRENCIES = ['USD', 'CNY', 'AED'];
    public const REFRESH_HOURS = [2, 6, 12, 24];

    public function latestRate(string $currency): ?ExchangeRate
    {
        if (!in_array($currency, self::CURRENCIES, true)) {
            return null;
        }

        return ExchangeRate::query()->find($currency);
    }

    public function calculateToman(float $amount, ExchangeRate|float $rate, ProductSupplier $productSupplier): int
    {
        $rateValue = $rate instanceof ExchangeRate ? (float) $rate->rate_toman : $rate;
        $unroundedPrice = $amount * $rateValue;

        if (!is_finite($unroundedPrice) || $rateValue <= 0 || $unroundedPrice <= 0 || $unroundedPrice > self::MAX_TOMAN_PRICE) {
            throw new InvalidArgumentException('مبلغ تبدیل‌شده خارج از محدودهٔ مجاز قیمت تومانی است.');
        }

        $roundedPrice = round(PriceRoundingHelper::round(
            $unroundedPrice,
            $productSupplier->supplier
        ));

        if (!is_finite($roundedPrice) || $roundedPrice <= 0 || $roundedPrice > self::MAX_TOMAN_PRICE) {
            throw new InvalidArgumentException('قیمت پس از گردکردن خارج از محدودهٔ مجاز است.');
        }

        return (int) $roundedPrice;
    }

    /** Dispatch due supplier/currency cycles; the queue does all work in bounded product chunks. */
    public function processDue(): int
    {
        $dueSettings = SupplierExchangeRateSetting::query()
            ->where('auto_update', true)
            ->whereNotNull('next_update_at')
            ->where('next_update_at', '<=', now())
            ->whereIn('currency', self::CURRENCIES)
            ->whereHas('supplier', function ($query): void {
                if ($query->getQuery()->getConnection()->getDriverName() === 'mysql') {
                    $query->whereRaw(
                        'suppliers.pricing_currency COLLATE utf8mb4_unicode_ci = supplier_exchange_rate_settings.currency COLLATE utf8mb4_unicode_ci'
                    );

                    return;
                }

                $query->whereColumn(
                    'suppliers.pricing_currency',
                    'supplier_exchange_rate_settings.currency'
                );
            });

        if (!$dueSettings->exists()) {
            return 0;
        }

        // One provider request refreshes all supported currencies, regardless of product count.
        try {
            $maxRefreshHours = max(self::REFRESH_HOURS);
            app(\App\Services\ExchangeRates\ExchangeRateRefreshService::class)->refresh(
                $this->maxAgeMinutesForRefreshHours($maxRefreshHours)
            );
        } catch (Throwable $exception) {
            $attemptedAt = now();
            $retryAt = $attemptedAt->copy()->addMinutes(
                max(5, (int) config('pricing.exchange_rates.refresh_minutes', 5))
            );
            $error = mb_substr($exception->getMessage(), 0, 500);

            $dueSettings->orderBy('id')->chunkById(100, function ($settings) use ($attemptedAt, $retryAt, $error): void {
                foreach ($settings as $dueSetting) {
                    DB::transaction(function () use ($dueSetting, $attemptedAt, $retryAt, $error): void {
                        $supplier = Supplier::query()
                            ->lockForUpdate()
                            ->find((int) $dueSetting->supplier_id);
                        $setting = SupplierExchangeRateSetting::query()
                            ->lockForUpdate()
                            ->find($dueSetting->id);

                        if (!$supplier
                            || !$setting
                            || $supplier->pricing_currency !== $setting->currency
                            || !$setting->auto_update
                            || !$setting->next_update_at
                            || $setting->next_update_at->isFuture()) {
                            return;
                        }

                        $setting->forceFill([
                            'last_attempt_at' => $attemptedAt,
                            'last_error' => $error,
                            'next_update_at' => $retryAt,
                        ])->save();
                    }, 3);
                }
            });

            return 0;
        }

        $rates = ExchangeRate::query()
            ->whereIn('currency', self::CURRENCIES)
            ->get()
            ->keyBy('currency');
        $dispatched = 0;

        $dueSettings->orderBy('id')->chunkById(100, function ($settings) use ($rates, &$dispatched): void {
            foreach ($settings as $dueSetting) {
                DB::transaction(function () use ($dueSetting, $rates, &$dispatched): void {
                $supplier = Supplier::query()
                    ->lockForUpdate()
                    ->find((int) $dueSetting->supplier_id);
                $setting = SupplierExchangeRateSetting::query()
                    ->lockForUpdate()
                    ->find($dueSetting->id);

                if (!$setting
                    || !$supplier
                    || $supplier->pricing_currency !== $setting->currency
                    || !$setting->auto_update
                    || !$setting->next_update_at
                    || $setting->next_update_at->isFuture()) {
                    return;
                }

                $hours = (int) $setting->refresh_hours;
                if (!in_array($hours, self::REFRESH_HOURS, true)) {
                    $setting->forceFill([
                        'last_attempt_at' => now(),
                        'last_error' => 'بازهٔ به‌روزرسانی نرخ ارز معتبر نیست.',
                        'next_update_at' => now()->addMinutes(30),
                    ])->save();

                    return;
                }

                $rate = $rates->get($setting->currency);
                if (!$this->isFreshRate($rate, $this->maxAgeMinutesForRefreshHours($hours))) {
                    $attributes = [
                        'last_attempt_at' => now(),
                        'last_error' => $rate?->last_error ?: 'آخرین نرخ از بازهٔ انتخاب‌شده قدیمی‌تر است؛ قیمت‌های ثبت‌شده حفظ شدند.',
                        'next_update_at' => now()->addMinutes(max(5, (int) config('pricing.exchange_rates.refresh_minutes', 5))),
                    ];

                    if ($rate
                        && $rate->source === \App\Services\ExchangeRates\NerkhsanjRateProvider::SOURCE
                        && is_numeric($rate->rate_toman)
                        && (float) $rate->rate_toman > 0) {
                        $attributes += [
                            'rate_toman' => $rate->rate_toman,
                            'source' => $rate->source,
                            'source_market_date' => $rate->source_market_date,
                            'source_market_time' => $rate->source_market_time,
                            'source_updated_at' => $rate->source_updated_at,
                            'fetched_at' => $rate->fetched_at,
                        ];
                    }

                    $setting->forceFill($attributes)->save();

                    return;
                }

                $setting->forceFill([
                    'rate_toman' => $rate->rate_toman,
                    'source' => $rate->source,
                    'source_market_date' => $rate->source_market_date,
                    'source_market_time' => $rate->source_market_time,
                    'source_updated_at' => $rate->source_updated_at,
                    'fetched_at' => $rate->fetched_at,
                    'last_attempt_at' => now(),
                    'last_error' => $rate->last_error,
                    'last_apply_error' => null,
                    'next_update_at' => now()->addHours($hours),
                    'revision' => ((int) $setting->revision) + 1,
                ])->save();

                DB::afterCommit(function () use ($setting, $supplier, &$dispatched): void {
                    \App\Jobs\RebaseSupplierCurrencyPrices::dispatch(
                        (int) $setting->supplier_id,
                        $setting->currency,
                        (int) $supplier->pricing_currency_revision,
                    );
                    \App\Jobs\ApplySupplierCurrencyPrices::dispatch(
                        (int) $setting->supplier_id,
                        $setting->currency,
                        (int) $setting->revision,
                        true,
                        (int) $supplier->tier_pricing_revision,
                    );
                    $dispatched++;
                });
                }, 3);
            }
        });

        return $dispatched;
    }

    public function isUsableSupplierRate(?SupplierExchangeRateSetting $setting): bool
    {
        if (!$setting) {
            return false;
        }

        if (!$setting->auto_update) {
            return is_numeric($setting->manual_rate_toman) && (float) $setting->manual_rate_toman > 0;
        }

        return $this->isFreshSupplierRate($setting);
    }

    public function isFreshSupplierRate(?SupplierExchangeRateSetting $setting, ?int $maxAgeMinutes = null): bool
    {
        if (!$setting) {
            return false;
        }

        $acceptedAgeMinutes = $maxAgeMinutes === null
            ? $this->maxAgeMinutesForRefreshHours((int) $setting->refresh_hours)
            : max(
                max(1, (int) config('pricing.exchange_rates.max_age_minutes', 15)),
                min(24 * 60, max(1, $maxAgeMinutes))
            );
        $cutoff = \Carbon\CarbonImmutable::now('UTC')->subMinutes($acceptedAgeMinutes);

        return is_numeric($setting->rate_toman)
            && (float) $setting->rate_toman > 0
            && $setting->fetched_at !== null
            && $setting->source_updated_at !== null
            && $setting->fetched_at->gte($cutoff)
            && $setting->source_updated_at->gte($cutoff);
    }

    /**
     * Reinterpret a saved Toman base safely when a supplier changes its global
     * currency. The previous numeric foreign amount is never copied to the new
     * currency; instead, derive an equivalent amount from the persisted Toman
     * reference price. If no rate exists, leave the Toman price untouched and
     * require a new foreign amount from the supplier.
     */
    public function rebaseProductCurrency(
        int $productSupplierId,
        int $supplierId,
        string $currency,
        int $expectedCurrencyRevision
    ): bool {
        return DB::transaction(function () use (
            $productSupplierId,
            $supplierId,
            $currency,
            $expectedCurrencyRevision
        ): bool {
            // Keep the lock order consistent with settings changes and product
            // edits: supplier first, then its product row. The currency and
            // revision checked here are therefore the same values fenced for
            // the write below, not an eager-loaded stale snapshot.
            $supplier = Supplier::query()->lockForUpdate()->find($supplierId);
            if (!$supplier
                || $supplier->pricing_currency !== $currency
                || (int) $supplier->pricing_currency_revision !== $expectedCurrencyRevision) {
                return false;
            }

            $productSupplier = ProductSupplier::query()
                ->with('product')
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->find($productSupplierId);

            if (!$productSupplier
                || (int) $productSupplier->supplier_id !== $supplierId
                || $productSupplier->price_currency === $currency) {
                return false;
            }

            $cashTypeId = PriceType::query()->where('code', 'cash')->value('id');
            if (!$cashTypeId) {
                return false;
            }

            $referenceTier = 1;
            $today = now()->startOfDay();
            $referencePrice = ProductPrice::query()
                ->where('product_supplier_id', $productSupplier->id)
                ->where('price_type_id', $cashTypeId)
                ->where('tier_index', $referenceTier)
                ->whereNull('check_period_id')
                ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                ->lockForUpdate()
                ->orderByDesc('updated_at')
                ->first();

            if (!$referencePrice || (float) $referencePrice->price <= 0) {
                return false;
            }

            $setting = $currency === 'TOM'
                ? null
                : SupplierExchangeRateSetting::query()
                    ->where('supplier_id', $supplierId)
                    ->where('currency', $currency)
                    ->lockForUpdate()
                    ->first();
            if ($currency !== 'TOM' && $setting?->auto_update && !$this->isFreshSupplierRate($setting)) {
                return false;
            }

            $rateValue = $setting
                ? (float) ($setting->auto_update ? $setting->rate_toman : $setting->manual_rate_toman)
                : 0.0;
            $newAmount = $rateValue > 0
                ? number_format((float) $referencePrice->price / $rateValue, 4, '.', '')
                : null;

            if ($currency !== 'TOM' && $newAmount === null) {
                return false;
            }

            $productSupplier->forceFill([
                'price_currency' => $currency,
                'foreign_price_amount' => $currency === 'TOM' ? null : $newAmount,
                'foreign_price_rate' => $rateValue > 0 ? $rateValue : null,
                'foreign_price_source' => $setting
                    ? ($setting->auto_update ? $setting->source : 'manual')
                    : null,
                'foreign_price_updated_at' => $newAmount === null ? null : now(),
                'foreign_price_next_update_at' => null,
                'foreign_price_last_attempt_at' => null,
                'foreign_price_last_error' => $currency !== 'TOM' && $newAmount === null
                    ? 'برای حفظ قیمت تومانی، مبلغ ارز پایهٔ جدید را دوباره وارد کنید.'
                    : null,
                'foreign_price_applied_currency' => null,
                'foreign_price_applied_setting_revision' => 0,
                'foreign_price_applied_tier_revision' => null,
                'foreign_price_revision' => ((int) $productSupplier->foreign_price_revision) + 1,
            ])->save();

            return true;
        }, 3);
    }

    public function applyConfiguredRate(
        int $productSupplierId,
        int $supplierId,
        string $currency,
        int $expectedRevision,
        Collection $priceTypes,
        array &$changedProductSupplierIds,
        ?int $expectedTierPricingRevision = null,
    ): bool {
        return $this->applyRate(
            $productSupplierId,
            $supplierId,
            $currency,
            $expectedRevision,
            $priceTypes,
            $changedProductSupplierIds,
            $expectedTierPricingRevision,
        );
    }

    private function applyRate(
        int $productSupplierId,
        int $supplierId,
        string $currency,
        int $expectedRevision,
        Collection $priceTypes,
        array &$changedProductSupplierIds,
        ?int $expectedTierPricingRevision = null,
    ): bool
    {
        return DB::transaction(function () use (
            $productSupplierId,
            $supplierId,
            $currency,
            $expectedRevision,
            $priceTypes,
            &$changedProductSupplierIds,
            $expectedTierPricingRevision,
        ): bool {
            return app(CatalogSnapshotRevisionService::class)->batch(function () use (
                $productSupplierId,
                $supplierId,
                $currency,
                $expectedRevision,
                $priceTypes,
                &$changedProductSupplierIds,
                $expectedTierPricingRevision,
            ): bool {
            // Lock the supplier before product and rate rows, matching all
            // settings writers so each product sees one tier-pricing revision.
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->first();

            /** @var ProductSupplier|null $productSupplier */
            $productSupplier = ProductSupplier::query()
                ->with('product')
                ->where('supplier_id', $supplierId)
                ->lockForUpdate()
                ->find($productSupplierId);

            $setting = SupplierExchangeRateSetting::query()
                ->where('supplier_id', $supplierId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (!$supplier
                || $supplier->pricing_currency !== $currency
                || ($expectedTierPricingRevision !== null
                    && (int) $supplier->tier_pricing_revision !== $expectedTierPricingRevision)
                || !$productSupplier
                || !$setting
                || (int) $setting->revision !== $expectedRevision
                || (int) $productSupplier->supplier_id !== $supplierId
                || $productSupplier->price_currency !== $currency) {
                return false;
            }

            if (!$this->isUsableSupplierRate($setting)) {
                return false;
            }

            $currentTierPricingRevision = (int) $supplier->tier_pricing_revision;
            if ($productSupplier->foreign_price_applied_currency === $currency
                && (int) $productSupplier->foreign_price_applied_setting_revision === $expectedRevision
                && $productSupplier->foreign_price_applied_tier_revision !== null
                && (int) $productSupplier->foreign_price_applied_tier_revision === $currentTierPricingRevision) {
                return false;
            }

            $amount = (float) $productSupplier->foreign_price_amount;

            if ($amount <= 0 || !is_finite($amount)) {
                return false;
            }

            $rateValue = (float) ($setting->auto_update ? $setting->rate_toman : $setting->manual_rate_toman);
            if (!is_finite($rateValue) || $rateValue <= 0) {
                return false;
            }

            $cashType = $priceTypes->get('cash');

            if (!$cashType) {
                $this->deferLockedProduct($productSupplier, 'نوع قیمت نقدی در سیستم پیدا نشد.');
                return false;
            }

            $today = now()->startOfDay();
            $prices = ProductPrice::query()
                ->where('product_supplier_id', $productSupplier->id)
                ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                ->lockForUpdate()
                ->get();

            $oldCashByTier = $prices
                ->where('price_type_id', $cashType->id)
                ->whereNull('check_period_id')
                ->groupBy('tier_index')
                ->map(fn ($rows) => (int) $rows->first()->price)
                ->all();

            $referenceTier = 1;
            $quantityTiers = (array) ($productSupplier->product->quantity_tiers ?? []);

            if (!array_key_exists($referenceTier, $quantityTiers)) {
                $this->deferLockedProduct($productSupplier, 'تیراژ مرجع معتبر برای این محصول پیدا نشد.');
                return false;
            }

            $baseToman = $this->calculateToman($amount, $rateValue, $productSupplier);
            if ($baseToman <= 0) {
                $this->deferLockedProduct($productSupplier, 'مبلغ تبدیل‌شده به تومان معتبر نیست.');
                return false;
            }

            $newCashByTier = [
                $referenceTier => $this->storeCashPrice(
                    $productSupplier,
                    (int) $cashType->id,
                    $referenceTier,
                    $baseToman
                ),
            ];

            $calculateTierPrices = (bool) $productSupplier->auto_calculate_tiers
                || $productSupplier->price_currency !== 'TOM';
            $tierPercentages = $calculateTierPrices
                ? $this->tierPercentageMap($productSupplier, array_map('intval', array_keys($quantityTiers)))
                : [];

            if ($calculateTierPrices) {
                foreach ($quantityTiers as $tierIndex => $_tier) {
                    $tierIndex = (int) $tierIndex;

                    if ($tierIndex === $referenceTier
                        || !(bool) (($productSupplier->tier_enabled ?? [])[$tierIndex] ?? true)) {
                        continue;
                    }

                    $customPercentages = $productSupplier->tier_percentages ?? [];
                    $percentage = array_key_exists($tierIndex, $customPercentages)
                        ? (float) $customPercentages[$tierIndex]
                        : (float) ($tierPercentages[$tierIndex] ?? 0.0);
                    $tierPrice = $this->validatedPrice(PriceRoundingHelper::round(
                        $baseToman * (1 + ($percentage / 100)),
                        $productSupplier->supplier
                    ));

                    $newCashByTier[$tierIndex] = $this->storeCashPrice(
                        $productSupplier,
                        (int) $cashType->id,
                        $tierIndex,
                        $tierPrice
                    );
                }
            }

            $changedRows = [];
            $checkType = $priceTypes->get('check');
            $checkRows = $checkType
                ? $prices->where('price_type_id', $checkType->id)->whereNotNull('check_period_id')
                : collect();
            $checkPercentages = $this->checkPercentageMap($productSupplier, $checkRows);

            foreach ($checkRows as $checkPrice) {
                $tierIndex = (int) $checkPrice->tier_index;
                if (!array_key_exists($tierIndex, $newCashByTier)) {
                    continue;
                }

                $mode = $checkPrice->calculation_mode;
                if ($mode === null) {
                    $mode = $checkPrice->percentage_override !== null
                        ? 'percentage'
                        : $this->inferLegacyCheckMode(
                            $productSupplier,
                            $checkPrice,
                            $oldCashByTier[$tierIndex] ?? null,
                            $checkPercentages[$checkPrice->check_period_id] ?? null
                        );

                    $checkPrice->calculation_mode = $mode;
                    $checkPrice->saveQuietly();
                }

                if (!in_array($mode, ['default', 'percentage'], true)) {
                    continue;
                }

                $percentage = $mode === 'percentage'
                    ? ($checkPrice->percentage_override === null ? null : (float) $checkPrice->percentage_override)
                    : ($checkPrice->percentage_override === null
                        ? ($checkPercentages[$checkPrice->check_period_id] ?? null)
                        : (float) $checkPrice->percentage_override);

                if ($percentage === null) {
                    continue;
                }

                $checkPrice->price = $this->validatedPrice(PriceRoundingHelper::calculateCheckPrice(
                    (float) $newCashByTier[$tierIndex],
                    $percentage,
                    $productSupplier->supplier
                ));
                $checkPrice->saveQuietly();
                $changedRows[] = $checkPrice;
            }

            $productSupplier->forceFill([
                'foreign_price_rate' => $rateValue,
                'foreign_price_source' => $setting->auto_update ? $setting->source : 'manual',
                'foreign_price_applied_currency' => $currency,
                'foreign_price_applied_setting_revision' => $expectedRevision,
                'foreign_price_applied_tier_revision' => $currentTierPricingRevision,
                'foreign_price_updated_at' => now(),
                'foreign_price_next_update_at' => null,
                'foreign_price_last_attempt_at' => now(),
                'foreign_price_last_error' => null,
                'foreign_price_revision' => ((int) $productSupplier->foreign_price_revision) + 1,
            ])->saveQuietly();

            app(ProductPriceRevisionService::class)->increment(
                (int) $productSupplier->product_id,
                (int) $productSupplier->supplier_id,
            );

            DB::afterCommit(function () use ($productSupplier, &$changedProductSupplierIds): void {
                $changedProductSupplierIds[] = (int) $productSupplier->id;
            });

            return true;
            });
        }, 3);
    }

    private function validatedPrice(float|int $price): int
    {
        if (!is_finite((float) $price) || $price <= 0 || $price > self::MAX_TOMAN_PRICE) {
            throw new InvalidArgumentException('قیمت محاسبه‌شده خارج از محدودهٔ مجاز است.');
        }

        $rounded = (int) round($price);
        if ($rounded <= 0 || $rounded > self::MAX_TOMAN_PRICE) {
            throw new InvalidArgumentException('قیمت گرد‌شده خارج از محدودهٔ مجاز است.');
        }

        return $rounded;
    }

    private function storeCashPrice(ProductSupplier $productSupplier, int $priceTypeId, int $tierIndex, int $price): int
    {
        $record = ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $priceTypeId)
            ->where('tier_index', $tierIndex)
            ->whereNull('check_period_id')
            ->lockForUpdate()
            ->first();

        if ($record) {
            if ((int) $record->price !== $price) {
                $record->forceFill(['price' => $price])->saveQuietly();
            }
            return $price;
        }

        $record = new ProductPrice([
            'product_id' => $productSupplier->product_id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $priceTypeId,
            'tier_index' => $tierIndex,
            'check_period_id' => null,
            'price' => $price,
            'is_active' => true,
        ]);
        $record->saveQuietly();

        return $price;
    }

    /** @param array<int, int> $tierIndexes @return array<int, float> */
    private function tierPercentageMap(ProductSupplier $productSupplier, array $tierIndexes): array
    {
        if ($tierIndexes === []) {
            return [];
        }

        $supplierPercentages = SupplierTierDiscount::query()
            ->where('supplier_id', $productSupplier->supplier_id)
            ->whereIn('tier_index', $tierIndexes)
            ->where('is_active', true)
            ->pluck('percentage_change', 'tier_index')
            ->map(fn ($value) => (float) $value)
            ->all();

        $defaultPercentages = [];
        foreach ($tierIndexes as $tierIndex) {
            $defaultPercentages[(int) $tierIndex] = SupplierTierDiscount::defaultPercentage((int) $tierIndex);
        }
        $supplierPercentages = array_replace($defaultPercentages, $supplierPercentages);

        $categoryId = $productSupplier->product->category_id;
        if (!$categoryId) {
            return $supplierPercentages;
        }

        $categoryPercentages = DB::table('category_tier_discount')
            ->where('category_id', $categoryId)
            ->where('supplier_id', $productSupplier->supplier_id)
            ->whereIn('tier_index', $tierIndexes)
            ->where('is_active', true)
            ->pluck('percentage_change', 'tier_index')
            ->map(fn ($value) => (float) $value)
            ->all();

        return array_replace($supplierPercentages, $categoryPercentages);
    }

    /** @return array<int, float> */
    private function checkPercentageMap(ProductSupplier $productSupplier, Collection $checkRows): array
    {
        $periodIds = $checkRows->pluck('check_period_id')->filter()->unique()->map(fn ($id) => (int) $id)->values();
        if ($periodIds->isEmpty()) {
            return [];
        }

        $supplierPercentages = DB::table('supplier_check_period')
            ->where('supplier_id', $productSupplier->supplier_id)
            ->whereIn('check_period_id', $periodIds)
            ->where('is_active', true)
            ->pluck('percentage_increase', 'check_period_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        $categoryId = $productSupplier->product->category_id;
        if (!$categoryId) {
            return $supplierPercentages;
        }

        $categoryPercentages = DB::table('category_check_period')
            ->where('category_id', $categoryId)
            ->where('supplier_id', $productSupplier->supplier_id)
            ->whereIn('check_period_id', $periodIds)
            ->where('is_active', true)
            ->pluck('percentage_increase', 'check_period_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        return array_replace($supplierPercentages, $categoryPercentages);
    }

    private function inferLegacyCheckMode(
        ProductSupplier $productSupplier,
        ProductPrice $checkPrice,
        ?int $oldCashPrice,
        ?float $percentage
    ): string
    {
        if ($oldCashPrice === null || $percentage === null) {
            return 'manual';
        }

        $expected = (int) round(PriceRoundingHelper::calculateCheckPrice(
            (float) $oldCashPrice,
            $percentage,
            $productSupplier->supplier
        ));

        return $expected === (int) $checkPrice->price ? 'default' : 'manual';
    }

    private function deferLockedProduct(ProductSupplier $productSupplier, string $message): void
    {
        $productSupplier->forceFill([
            'foreign_price_last_attempt_at' => now(),
            'foreign_price_last_error' => $message,
        ])->save();
    }

    public function maxAgeMinutesForRefreshHours(int $refreshHours): int
    {
        $configuredMaxAgeMinutes = max(1, (int) config('pricing.exchange_rates.max_age_minutes', 15));

        if (!in_array($refreshHours, self::REFRESH_HOURS, true)) {
            return $configuredMaxAgeMinutes;
        }

        return max($configuredMaxAgeMinutes, min(24 * 60, $refreshHours * 60));
    }

    public function isFreshRate(?ExchangeRate $rate, ?int $maxAgeMinutes = null): bool
    {
        $configuredMaxAgeMinutes = max(1, (int) config('pricing.exchange_rates.max_age_minutes', 15));
        $acceptedAgeMinutes = $maxAgeMinutes === null
            ? $configuredMaxAgeMinutes
            : max($configuredMaxAgeMinutes, min(24 * 60, max(1, $maxAgeMinutes)));
        $cutoff = \Carbon\CarbonImmutable::now('UTC')->subMinutes($acceptedAgeMinutes);

        return $rate !== null
            && is_numeric($rate->rate_toman)
            && (float) $rate->rate_toman > 0
            && $rate->fetched_at !== null
            && $rate->source_updated_at !== null
            && $rate->fetched_at->gte($cutoff)
            && $rate->source_updated_at->gte($cutoff);
    }
}
