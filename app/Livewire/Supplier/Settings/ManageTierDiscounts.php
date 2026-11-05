<?php

namespace App\Livewire\Supplier\Settings;

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\ProductSupplier;
use App\Helpers\PriceHelper;
use App\Jobs\ApplySupplierCurrencyPrices;
use App\Jobs\RebaseSupplierCurrencyPrices;
use App\Jobs\RefreshDueForeignCurrencyPrices;
use App\Models\SupplierTierDiscount;
use App\Models\SupplierExchangeRateSetting;
use App\Models\Supplier;
use App\Services\ExchangeRates\ExchangeRateRefreshService;
use App\Services\ExchangeRates\NerkhsanjRateProvider;
use App\Services\ForeignCurrencyPriceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.supplier')]
class ManageTierDiscounts extends Component
{
    public $tierSettings = [];
    public $affectedPricesCount = 0;
    public $showUpdateConfirm = false;
    public $hasChanges = false;
    #[Locked]
    public int $supplierTierDiscountRevision = 1;
    public array $currencySettings = [];
    public bool $currencySettingsChanged = false;
    public string $pricingCurrency = 'TOM';
    #[Locked]
    public int $pricingCurrencyRevision = 1;
    #[Locked]
    public array $currencySettingRevisions = [];

    public const CURRENCIES = [
        'USD' => 'دلار آمریکا',
        'CNY' => 'یوان چین',
        'AED' => 'درهم امارات',
    ];
    
    // برای تنظیمات دسته‌بندی
    public $categories = [];
    public $selectedCategoryId = null;
    public $categoryTierPercentages = []; // [tier_index => percentage]
    public $categoryTierSettings = []; // دسته‌بندی‌های تنظیم شده
    #[Locked]
    public $editingCategoryId = null;
    #[Locked]
    public ?string $categoryEditSignature = null;
    
    // نام‌های پیشفرض تیراژها
    public const DEFAULT_TIER_NAMES = [
        0 => 'زیر کارتن',
        1 => 'کارتن (مرجع)',
        2 => 'عمده',
        3 => 'بار',
    ];
    
    public function mount()
    {
        $this->loadTierSettings();
        $this->loadCurrencySettings();
        $this->loadCategories();
        $this->loadCategoryTierSettings();
        $this->calculateAffectedPrices();
    }

    private function authorizedSupplierId(): int
    {
        $user = Auth::user();
        abort_unless(
            $user && $user->hasAnyRole(['supplier', 'regular_supplier', 'admin', 'super_admin']),
            403,
            'شما مجاز به تغییر تنظیمات تامین‌کننده نیستید.'
        );

        $supplier = $user->currentSupplier();

        abort_unless(
            $supplier && $supplier->is_active && $supplier->is_verified,
            403,
            'حساب تامین‌کننده فعال و تاییدشده نیست.'
        );

        if ($user->hasAnyRole(['supplier', 'regular_supplier'])) {
            abort_unless(
                $user->isAccountApproved()
                    && $supplier->verification_status === 'approved',
                403,
                'حساب تامین‌کننده فعال و تاییدشده نیست.'
            );
        }

        return (int) $supplier->id;
    }

    private function loadCurrencySettings(): void
    {
        $supplierId = $this->authorizedSupplierId();
        $supplier = Supplier::query()->findOrFail($supplierId);
        $this->pricingCurrency = (string) ($supplier->pricing_currency ?? 'TOM');
        $this->pricingCurrencyRevision = (int) ($supplier->pricing_currency_revision ?? 1);
        $this->currencySettings = [];

        foreach (self::CURRENCIES as $currency => $_label) {
            $snapshot = ExchangeRate::query()->find($currency);
            $initialRate = app(ForeignCurrencyPriceService::class)->isFreshRate($snapshot)
                ? $snapshot?->rate_toman
                : null;
            $setting = SupplierExchangeRateSetting::query()->firstOrCreate(
                ['supplier_id' => $supplierId, 'currency' => $currency],
                [
                    'rate_toman' => $initialRate,
                    'manual_rate_toman' => $initialRate,
                    'source' => 'manual',
                    'revision' => 1,
                ]
            );
            $savedQuoteIsDisplayable = $setting->source === NerkhsanjRateProvider::SOURCE
                && is_numeric($setting->rate_toman)
                && (float) $setting->rate_toman > 0;
            $cachedQuoteIsDisplayable = $snapshot
                && $snapshot->source === NerkhsanjRateProvider::SOURCE
                && is_numeric($snapshot->rate_toman)
                && (float) $snapshot->rate_toman > 0;
            $displaySnapshot = $savedQuoteIsDisplayable
                ? $setting
                : ($cachedQuoteIsDisplayable ? $snapshot : null);

            $this->currencySettings[$currency] = [
                'manual_rate_toman' => $setting->manual_rate_toman === null
                    ? ''
                    : $this->formatRateForInput($setting->manual_rate_toman),
                'auto_update' => (bool) $setting->auto_update,
                'refresh_hours' => $setting->refresh_hours ?? 6,
                'rate_toman' => $displaySnapshot?->rate_toman === null
                    ? null
                    : $this->formatRateForInput($displaySnapshot->rate_toman),
                'source' => $displaySnapshot?->source ?? $setting->source,
                'source_market_at' => trim(implode(' ', array_filter([
                    $displaySnapshot?->source_market_date,
                    $displaySnapshot?->source_market_time,
                ]))) ?: null,
                'fetched_at' => $displaySnapshot?->fetched_at
                    ? jdate($displaySnapshot->fetched_at->timezone('Asia/Tehran'))->format('Y/m/d H:i')
                    : null,
                'last_error' => $snapshot?->last_error ?: $setting->last_error,
                'last_apply_error' => $setting->last_apply_error,
            ];
            $this->currencySettingRevisions[$currency] = (int) $setting->revision;
        }

        $this->currencySettingsChanged = false;
    }

    private function formatRateForInput(mixed $rate): ?string
    {
        if (!is_numeric($rate)) {
            return null;
        }

        $value = (string) $rate;
        if (!str_contains($value, '.')) {
            return $value;
        }

        [$integer, $fraction] = explode('.', $value, 2);
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $integer : $integer . '.' . $fraction;
    }

    private function setCurrencyRatePreview(string $currency, ExchangeRate|SupplierExchangeRateSetting $snapshot): void
    {
        $this->currencySettings[$currency]['rate_toman'] = $this->formatRateForInput($snapshot->rate_toman);
        $this->currencySettings[$currency]['source'] = $snapshot->source;
        $this->currencySettings[$currency]['source_market_at'] = trim(implode(' ', array_filter([
            $snapshot->source_market_date,
            $snapshot->source_market_time,
        ]))) ?: null;
        $this->currencySettings[$currency]['fetched_at'] = $snapshot->fetched_at
            ? jdate($snapshot->fetched_at->timezone('Asia/Tehran'))->format('Y/m/d H:i')
            : null;
    }

    /** Fetch a display-only quote preview; the policy remains unsaved until the save button is used. */
    public function toggleAutomaticCurrencyRate(string $currency, mixed $enabled): void
    {
        $supplierId = $this->authorizedSupplierId();
        abort_unless(array_key_exists($currency, self::CURRENCIES), 404);

        $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        $this->currencySettings[$currency]['auto_update'] = $enabled;

        if (!$enabled) {
            $this->currencySettingsChanged = true;
            $this->dispatch('notify', message: 'گزینه خاموش شد؛ برای ثبت تغییر، تنظیمات را ذخیره کنید.', type: 'info');

            return;
        }

        if ($this->pricingCurrency !== $currency) {
            $this->currencySettings[$currency]['auto_update'] = false;
            $this->currencySettingsChanged = true;
            $this->dispatch('notify', message: 'برای دریافت نرخ، ابتدا همین ارز را به‌عنوان ارز پایه انتخاب کنید.', type: 'warning');

            return;
        }

        $refreshHours = (int) ($this->currencySettings[$currency]['refresh_hours'] ?? 6);
        if (!in_array($refreshHours, ForeignCurrencyPriceService::REFRESH_HOURS, true)) {
            $this->currencySettings[$currency]['auto_update'] = false;
            $this->currencySettingsChanged = true;
            $this->dispatch('notify', message: 'بازهٔ به‌روزرسانی نرخ معتبر نیست.', type: 'error');

            return;
        }

        if (($this->currencySettings[$currency]['source'] ?? 'manual') === 'manual') {
            $this->currencySettings[$currency]['rate_toman'] = null;
            $this->currencySettings[$currency]['source_market_at'] = null;
            $this->currencySettings[$currency]['fetched_at'] = null;
        }
        $this->currencySettings[$currency]['last_error'] = null;
        $this->currencySettingsChanged = true;
        $rateService = app(ForeignCurrencyPriceService::class);
        $maxAgeMinutes = $rateService->maxAgeMinutesForRefreshHours($refreshHours);
        $savedSetting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $supplierId)
            ->where('currency', $currency)
            ->first();
        $savedQuoteIsDisplayable = $savedSetting
            && $savedSetting->source === NerkhsanjRateProvider::SOURCE
            && is_numeric($savedSetting->rate_toman)
            && (float) $savedSetting->rate_toman > 0;
        $savedQuoteIsFresh = $savedQuoteIsDisplayable
            && $rateService->isFreshSupplierRate($savedSetting, $maxAgeMinutes);

        if ($savedQuoteIsDisplayable) {
            $this->setCurrencyRatePreview($currency, $savedSetting);
        }

        try {
            app(ExchangeRateRefreshService::class)->refresh($maxAgeMinutes);
            $rate = ExchangeRate::query()->find($currency);
            $cachedQuoteIsDisplayable = $rate
                && $rate->source === NerkhsanjRateProvider::SOURCE
                && is_numeric($rate->rate_toman)
                && (float) $rate->rate_toman > 0;
            $cachedQuoteIsFresh = $rateService->isFreshRate($rate, $maxAgeMinutes);

            if ($cachedQuoteIsFresh || (!$savedQuoteIsFresh && $cachedQuoteIsDisplayable)) {
                $this->setCurrencyRatePreview($currency, $rate);
            }

            $hasFreshQuote = $cachedQuoteIsFresh || $savedQuoteIsFresh;
            if (!$hasFreshQuote) {
                $error = $rate?->last_error ?: 'آخرین نرخ معتبر از بازهٔ انتخاب‌شده قدیمی‌تر است.';
                $this->currencySettings[$currency]['last_error'] = $error;
                $hasRate = is_numeric($this->currencySettings[$currency]['rate_toman'] ?? null)
                    && (float) $this->currencySettings[$currency]['rate_toman'] > 0;
                $message = $hasRate
                    ? 'دریافت نرخ تازه ناموفق بود؛ آخرین نرخ موجود همراه زمان دریافتش نمایش داده شد. ' . $error
                    : 'نرخ خودکار در دسترس نیست: ' . $error;
                $this->dispatch('notify', message: $message, type: 'warning');

                return;
            }

            $refreshError = $rate?->last_error;
            if (!$refreshError && !$cachedQuoteIsFresh && $savedQuoteIsFresh) {
                $refreshError = 'نرخ تازه‌تری از منبع دریافت نشد؛ آخرین نرخ معتبر ذخیره‌شده حفظ شد.';
            }
            if (!$refreshError && !$rate) {
                $refreshError = $savedSetting?->last_error;
            }
            if ($refreshError || !$rate) {
                $warning = $refreshError ?: 'نرخ تازه دریافت نشد؛ آخرین نرخ معتبر حفظ شده است.';
                $this->currencySettings[$currency]['last_error'] = $warning;
                $this->dispatch(
                    'notify',
                    message: 'دریافت تازه ناموفق بود؛ آخرین نرخ موجود همراه زمان دریافتش نمایش داده شد. ' . $warning,
                    type: 'warning'
                );

                return;
            }

            $this->currencySettings[$currency]['last_error'] = null;
            $formattedRate = PriceHelper::formatNumber((float) $this->currencySettings[$currency]['rate_toman']);
            $this->dispatch('notify', message: "نرخ معتبر {$currency} در کادر نمایش داده شد: {$formattedRate} تومان. برای ثبت دریافت خودکار، تنظیمات را ذخیره کنید.", type: 'success');
        } catch (Throwable $exception) {
            $error = mb_substr($exception->getMessage(), 0, 500);
            $rate = ExchangeRate::query()->find($currency);
            $cachedQuoteIsDisplayable = $rate
                && $rate->source === NerkhsanjRateProvider::SOURCE
                && is_numeric($rate->rate_toman)
                && (float) $rate->rate_toman > 0;

            if (!$savedQuoteIsFresh && $cachedQuoteIsDisplayable) {
                $this->setCurrencyRatePreview($currency, $rate);
            }

            $this->currencySettings[$currency]['last_error'] = $error;
            $hasRate = is_numeric($this->currencySettings[$currency]['rate_toman'] ?? null)
                && (float) $this->currencySettings[$currency]['rate_toman'] > 0;
            $message = $hasRate
                ? 'دریافت نرخ تازه ناموفق بود؛ آخرین نرخ موجود همراه زمان دریافتش نمایش داده شد. ' . $error
                : 'دریافت نرخ ناموفق بود: ' . $error;
            $this->dispatch('notify', message: $message, type: 'warning');
        }
    }

    public function saveCurrencySettings(): void
    {
        $supplierId = $this->authorizedSupplierId();
        $rules = [
            'pricingCurrency' => ['required', 'string', 'in:TOM,USD,CNY,AED'],
        ];
        $messages = [];

        foreach (self::CURRENCIES as $currency => $label) {
            $rules["currencySettings.{$currency}.manual_rate_toman"] = [
                'nullable',
                'numeric',
                'decimal:0,4',
                'gt:0',
                'max:9999999999999999',
            ];
            $rules["currencySettings.{$currency}.auto_update"] = ['required', 'boolean'];
            $rules["currencySettings.{$currency}.refresh_hours"] = [
                'nullable',
                'integer',
                'in:2,6,12,24',
                function ($attribute, $value, $fail) use ($currency): void {
                    $autoUpdate = filter_var(
                        $this->currencySettings[$currency]['auto_update'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    );

                    if ($autoUpdate && $value === null) {
                        $fail('بازهٔ به‌روزرسانی خودکار را انتخاب کنید.');
                    }
                },
            ];
            $messages["currencySettings.{$currency}.manual_rate_toman.gt"] = "نرخ {$label} باید بزرگ‌تر از صفر باشد.";
        }

        $this->validate($rules, $messages);

        $exchangeRates = ExchangeRate::query()
            ->whereIn('currency', array_keys(self::CURRENCIES))
            ->get()
            ->keyBy('currency');
        $foreignCurrencyPriceService = app(ForeignCurrencyPriceService::class);

        $refreshRatesNow = false;
        $autoUpdateConfigured = false;
        $automaticRateApplications = [];
        $manualRateApplications = [];
        $currencyChanged = false;
        $currencyRevision = 1;
        $staleCurrencySettings = false;
        $staleRateSettings = false;
        DB::transaction(function () use ($supplierId, $exchangeRates, $foreignCurrencyPriceService, &$refreshRatesNow, &$autoUpdateConfigured, &$automaticRateApplications, &$manualRateApplications, &$currencyChanged, &$currencyRevision, &$staleCurrencySettings, &$staleRateSettings): void {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
            if ((int) $supplier->pricing_currency_revision !== $this->pricingCurrencyRevision) {
                $staleCurrencySettings = true;
                return;
            }

            $currencyChanged = $supplier->pricing_currency !== $this->pricingCurrency;
            $currencyRevision = $currencyChanged
                ? ((int) $supplier->pricing_currency_revision) + 1
                : (int) $supplier->pricing_currency_revision;

            // Compare every submitted rate-policy snapshot before writing any
            // of them. This prevents one stale tab from restoring an old manual
            // rate or auto-refresh interval after another tab has saved.
            $lockedSettings = [];
            foreach (self::CURRENCIES as $currency => $_label) {
                $setting = SupplierExchangeRateSetting::query()
                    ->where('supplier_id', $supplierId)
                    ->where('currency', $currency)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedSettings[$currency] = $setting;

                if ((int) $setting->revision !== (int) ($this->currencySettingRevisions[$currency] ?? 0)) {
                    $staleRateSettings = true;
                }
            }

            if ($staleRateSettings) {
                return;
            }

            // Mutate the global currency only after both the currency and rate
            // snapshots have passed their optimistic concurrency checks.
            if ($currencyChanged) {
                $supplier->forceFill([
                    'pricing_currency' => $this->pricingCurrency,
                    'pricing_currency_revision' => $currencyRevision,
                ])->save();
            }

            foreach (self::CURRENCIES as $currency => $_label) {
                $input = $this->currencySettings[$currency];
                $setting = $lockedSettings[$currency];
                $autoUpdate = (bool) $input['auto_update'];
                $manualRate = $input['manual_rate_toman'] === '' || $input['manual_rate_toman'] === null
                    ? ($autoUpdate ? $setting->manual_rate_toman : null)
                    : (float) $input['manual_rate_toman'];

                if ($autoUpdate) {
                    $autoUpdateConfigured = true;
                    $quote = $exchangeRates->get($currency);
                    $maxAgeMinutes = $foreignCurrencyPriceService->maxAgeMinutesForRefreshHours((int) $input['refresh_hours']);
                    $hasFreshCachedQuote = $foreignCurrencyPriceService->isFreshRate($quote, $maxAgeMinutes);
                    $hasFreshSavedQuote = $setting->source === NerkhsanjRateProvider::SOURCE
                        && $foreignCurrencyPriceService->isFreshSupplierRate($setting, $maxAgeMinutes);
                    $hasFreshQuote = $hasFreshCachedQuote || $hasFreshSavedQuote;
                    $cachedQuoteIsDisplayable = $quote
                        && $quote->source === NerkhsanjRateProvider::SOURCE
                        && is_numeric($quote->rate_toman)
                        && (float) $quote->rate_toman > 0;
                    $savedQuoteIsDisplayable = $setting->source === NerkhsanjRateProvider::SOURCE
                        && is_numeric($setting->rate_toman)
                        && (float) $setting->rate_toman > 0;
                    $useSavedQuote = $savedQuoteIsDisplayable
                        && (!$cachedQuoteIsDisplayable || (!$hasFreshCachedQuote && $hasFreshSavedQuote));
                    $quoteToSave = $useSavedQuote ? $setting : $quote;
                    $hasDisplayableQuote = $useSavedQuote ? $savedQuoteIsDisplayable : $cachedQuoteIsDisplayable;
                    $quoteError = $hasFreshCachedQuote
                        ? $quote?->last_error
                        : ($quote?->last_error ?: $setting->last_error);
                    if (!$quoteError && !$hasFreshCachedQuote && $hasFreshSavedQuote) {
                        $quoteError = 'دریافت تازه ناموفق بود؛ آخرین نرخ معتبر ذخیره‌شده حفظ شد.';
                    }
                    if (!$quoteError && !$hasFreshQuote) {
                        $quoteError = 'آخرین نرخ معتبر از بازهٔ انتخاب‌شده قدیمی‌تر است؛ قیمت‌های ثبت‌شده حفظ شدند.';
                    }
                    $freshQuoteCanBeApplied = $hasFreshQuote
                        && !$quoteError
                        && $hasDisplayableQuote
                        && $quoteToSave !== null;
                    $effectiveRateChanged = $freshQuoteCanBeApplied
                        && $foreignCurrencyPriceService->hasRateChanged($setting->rate_toman, $quoteToSave->rate_toman);
                    $autoModeChanged = !(bool) $setting->auto_update;
                    $nextRevision = (int) $setting->revision + (int) ($effectiveRateChanged || $autoModeChanged);
                    $attributes = [
                        'manual_rate_toman' => $manualRate,
                        'auto_update' => true,
                        'refresh_hours' => (int) $input['refresh_hours'],
                        'next_update_at' => $hasFreshQuote && !$quoteError
                            ? now()->addHours((int) $input['refresh_hours'])
                            : now(),
                        'last_error' => $quoteError,
                        'revision' => $nextRevision,
                        ...($effectiveRateChanged ? ['last_apply_error' => null] : []),
                    ];

                    if ($freshQuoteCanBeApplied) {
                        $attributes += [
                            'rate_toman' => $quoteToSave->rate_toman,
                            'source' => $quoteToSave->source,
                            'source_market_date' => $quoteToSave->source_market_date,
                            'source_market_time' => $quoteToSave->source_market_time,
                            'source_updated_at' => $quoteToSave->source_updated_at,
                            'fetched_at' => $quoteToSave->fetched_at,
                            'last_attempt_at' => $quoteToSave->last_attempt_at ?? now(),
                        ];
                    } elseif (($setting->source ?? 'manual') === 'manual') {
                        $attributes += [
                            'rate_toman' => null,
                            'source_market_date' => null,
                            'source_market_time' => null,
                            'source_updated_at' => null,
                            'fetched_at' => null,
                        ];
                    }

                    if ($currency === $this->pricingCurrency) {
                        if ($effectiveRateChanged) {
                            $automaticRateApplications[] = [
                                'currency' => $currency,
                                'setting_revision' => $nextRevision,
                                'tier_pricing_revision' => (int) $supplier->tier_pricing_revision,
                            ];
                        }

                        if (!$hasFreshQuote || $quoteError) {
                            $refreshRatesNow = true;
                        }
                    }

                    $setting->fill($attributes)->save();
                    continue;
                }

                $manualRateChanged = $foreignCurrencyPriceService->hasRateChanged(
                    $setting->rate_toman,
                    $manualRate,
                );
                $manualModeChanged = (bool) $setting->auto_update;
                $nextRevision = (int) $setting->revision
                    + (int) ($manualRateChanged || $manualModeChanged);
                $setting->fill([
                    'rate_toman' => $manualRate,
                    'manual_rate_toman' => $manualRate,
                    'auto_update' => false,
                    'refresh_hours' => null,
                    'next_update_at' => null,
                    'source' => 'manual',
                    'source_market_date' => null,
                    'source_market_time' => null,
                    'source_updated_at' => null,
                    'fetched_at' => null,
                    'last_attempt_at' => null,
                    'last_error' => null,
                    'last_apply_error' => null,
                    'revision' => $nextRevision,
                ])->save();

                if ($currency === $this->pricingCurrency
                    && is_numeric($manualRate)
                    && (float) $manualRate > 0) {
                    $manualRateApplications[] = [
                        'currency' => $currency,
                        'setting_revision' => $nextRevision,
                        'tier_pricing_revision' => (int) $supplier->tier_pricing_revision,
                    ];
                }
            }

            DB::afterCommit(function () use ($supplierId, $currencyRevision, $automaticRateApplications, $manualRateApplications): void {
                RebaseSupplierCurrencyPrices::dispatch(
                    $supplierId,
                    $this->pricingCurrency,
                    $currencyRevision
                );

                foreach ($automaticRateApplications as $application) {
                    ApplySupplierCurrencyPrices::dispatch(
                        $supplierId,
                        $application['currency'],
                        $application['setting_revision'],
                        true,
                        $application['tier_pricing_revision'],
                    );
                }

                foreach ($manualRateApplications as $application) {
                    ApplySupplierCurrencyPrices::dispatch(
                        $supplierId,
                        $application['currency'],
                        $application['setting_revision'],
                        false,
                        $application['tier_pricing_revision'],
                    );
                }
            });
        });

        if ($staleCurrencySettings) {
            $this->loadCurrencySettings();
            $this->addError('pricingCurrency', 'تنظیم ارز در تب دیگری تغییر کرده است؛ مقدار تازه بارگذاری شد. تغییرات را دوباره بررسی و ذخیره کنید.');
            $this->dispatch('notify', message: 'تنظیم ارز در تب دیگری تغییر کرده بود؛ این فرم به‌روز شد و ذخیره انجام نشد.', type: 'warning');
            return;
        }

        if ($staleRateSettings) {
            $this->loadCurrencySettings();
            $this->addError('currencySettings', 'تنظیم نرخ ارز در تب دیگری تغییر کرده است؛ مقادیر تازه بارگذاری شد. تغییرات را دوباره بررسی و ذخیره کنید.');
            $this->dispatch('notify', message: 'تنظیم نرخ ارز در تب دیگری تغییر کرده بود؛ این فرم به‌روز شد و ذخیره انجام نشد.', type: 'warning');
            return;
        }

        $this->currencySettingsChanged = false;
        $this->loadCurrencySettings();

        if ($refreshRatesNow) {
            RefreshDueForeignCurrencyPrices::dispatch();
            $message = $currencyChanged
                ? 'ارز پایه ذخیره شد؛ هماهنگ‌سازی امن قیمت‌های قبلی و دریافت نرخ جدید در صف قرار گرفت.'
                : 'تنظیمات ذخیره شد؛ دریافت نرخ و به‌روزرسانی خودکار در صف قرار گرفت.';
        } elseif ($automaticRateApplications !== []) {
            $message = $currencyChanged
                ? 'ارز پایه ذخیره شد؛ نرخ دریافت‌شده و محاسبهٔ قیمت محصولات در صف قرار گرفت.'
                : 'تنظیمات ذخیره شد؛ نرخ دریافت‌شده و محاسبهٔ قیمت محصولات در صف قرار گرفت.';
        } elseif ($manualRateApplications !== []) {
            $message = 'نرخ دستی ذخیره شد و محاسبهٔ قیمت محصولات با نرخ جدید در صف قرار گرفت.';
        } elseif ($autoUpdateConfigured) {
            $message = 'تنظیمات خودکار ذخیره شد؛ نرخ در نوبت به‌روزرسانی بعدی دریافت می‌شود.';
        } else {
            $message = $currencyChanged
                ? 'ارز پایه ذخیره شد؛ هماهنگ‌سازی امن قیمت‌های قبلی در صف قرار گرفت.'
                : 'تنظیمات نرخ دستی ذخیره شد؛ برای محاسبهٔ دوبارهٔ قیمت کالاها، دکمهٔ اعمال روی همهٔ محصولات را بزنید.';
        }

        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function applyCurrencyRatesToProducts(bool $notify = true): string
    {
        if ($this->hasChanges || $this->currencySettingsChanged) {
            if ($notify) {
                $this->dispatch('notify', message: 'ابتدا تنظیمات درصد و نرخ ارز را ذخیره کنید.', type: 'warning');
            }
            return 'settings_unsaved';
        }

        $supplierId = $this->authorizedSupplierId();
        $service = app(\App\Services\ForeignCurrencyPriceService::class);
        $selectedCurrency = Supplier::query()->whereKey($supplierId)->value('pricing_currency') ?? 'TOM';
        if ($selectedCurrency === 'TOM') {
            if ($notify) {
                $this->dispatch('notify', message: 'ارز پایهٔ تأمین‌کننده تومان است؛ برای محاسبهٔ نرخ ارزی، ابتدا ارز پایه را انتخاب کنید.', type: 'info');
            }
            return 'toman';
        }

        $hasPricedProducts = ProductSupplier::query()
            ->where('supplier_id', $supplierId)
            ->where('price_currency', $selectedCurrency)
            ->whereNotNull('foreign_price_amount')
            ->where('foreign_price_amount', '>', 0)
            ->exists();
        if (!$hasPricedProducts) {
            if ($notify) {
                $this->dispatch('notify', message: 'هنوز محصول قیمت‌گذاری‌شده‌ای با ارز پایهٔ انتخاب‌شده وجود ندارد.', type: 'info');
            }
            return 'no_products';
        }

        $setting = DB::transaction(function () use ($supplierId, $selectedCurrency, $service): ?SupplierExchangeRateSetting {
            $setting = SupplierExchangeRateSetting::query()
                ->where('supplier_id', $supplierId)
                ->where('currency', $selectedCurrency)
                ->lockForUpdate()
                ->first();

            $hasRate = $setting && ($setting->auto_update
                ? $service->isFreshSupplierRate($setting)
                : (float) ($setting->manual_rate_toman ?? 0) > 0);
            if (!$hasRate) {
                return null;
            }

            // A new generation lets the supplier re-run this same rate safely;
            // queued chunks from an older run become stale and are ignored.
            $setting->forceFill([
                'revision' => ((int) $setting->revision) + 1,
                'last_apply_error' => null,
            ])->save();

            return $setting;
        });

        if (!$setting) {
            if ($notify) {
                $this->dispatch('notify', message: 'برای ارز پایه نرخ معتبر در دسترس نیست؛ آخرین قیمت محصولات حفظ شد.', type: 'error');
            }
            return 'rate_unavailable';
        }

        $this->loadCurrencySettings();

        \App\Jobs\ApplySupplierCurrencyPrices::dispatch(
            $supplierId,
            $selectedCurrency,
            (int) $setting->revision,
            false,
            (int) Supplier::query()->whereKey($supplierId)->value('tier_pricing_revision'),
        );
        if ($notify) {
            $this->dispatch('notify', message: 'محاسبهٔ همهٔ محصولات با ارز پایهٔ ' . $selectedCurrency . ' در صف قرار گرفت؛ قیمت‌های نقدی و وابسته پس از پردازش از پایگاه‌داده نمایش داده می‌شوند.', type: 'success');
        }

        return 'queued';
    }
    
    public function loadTierSettings()
    {
        $supplierId = Auth::user()->currentSupplier()->id;
        [$revision, $existing] = DB::transaction(function () use ($supplierId): array {
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();
            $discounts = SupplierTierDiscount::where('supplier_id', $supplierId)
                ->where('is_active', true)
                ->get()
                ->keyBy('tier_index');

            return [(int) $supplier->supplier_tier_discount_revision, $discounts];
        });
        $this->supplierTierDiscountRevision = $revision;
        
        // ایجاد آرایه تنظیمات برای 4 تیراژ
        $this->tierSettings = [];
        for ($i = 0; $i <= 3; $i++) {
            if (isset($existing[$i])) {
                $this->tierSettings[$i] = [
                    'tier_index' => $i,
                    'name' => self::DEFAULT_TIER_NAMES[$i],
                    'percentage_change' => (float)$existing[$i]->percentage_change,
                    'label' => $existing[$i]->label,
                    'exists' => true,
                ];
            } else {
                $this->tierSettings[$i] = [
                    'tier_index' => $i,
                    'name' => self::DEFAULT_TIER_NAMES[$i],
                    'percentage_change' => $this->getDefaultPercentage($i),
                    'label' => null,
                    'exists' => false,
                ];
            }
        }
    }
    
    /**
     * درصد پیشفرض برای هر تیراژ:
     * - تیراژ 0 (زیر کارتن): +5% (معمولاً گران‌تر از کارتن)
     * - تیراژ 1 (کارتن): 0% (مرجع)
     * - تیراژ 2 (عمده): -5% (ارزان‌تر از کارتن)
     * - تیراژ 3 (بار): -10% (ارزان‌ترین)
     */
    private function getDefaultPercentage(int $tierIndex): float
    {
        return match($tierIndex) {
            0 => 5.0,   // زیر کارتن
            1 => 0.0,   // کارتن (مرجع)
            2 => -5.0,  // عمده
            3 => -10.0, // بار
            default => 0.0,
        };
    }
    
    public function rules()
    {
        $rules = [];
        
        foreach ($this->tierSettings as $index => $tier) {
            $rules["tierSettings.{$index}.percentage_change"] = [
                'required',
                'numeric',
                'min:-100',
                'max:100',
            ];
            
            $rules["tierSettings.{$index}.label"] = [
                'nullable',
                'string',
                'max:50',
                'regex:/^[\x{0600}-\x{06FF}\s\d]+$/u'
            ];
        }
        
        return $rules;
    }
    
    public function messages()
    {
        $messages = [];
        
        foreach ($this->tierSettings as $index => $tier) {
            $tierName = $tier['name'];
            
            $messages["tierSettings.{$index}.percentage_change.required"] = "درصد تغییر برای {$tierName} الزامی است.";
            $messages["tierSettings.{$index}.percentage_change.numeric"] = "درصد تغییر باید یک عدد باشد.";
            $messages["tierSettings.{$index}.percentage_change.min"] = "درصد تغییر نمی‌تواند کمتر از -100 باشد.";
            $messages["tierSettings.{$index}.percentage_change.max"] = "درصد تغییر نمی‌تواند بیشتر از 100 باشد.";
            
            $messages["tierSettings.{$index}.label.string"] = "عنوان اختصاصی باید متن باشد.";
            $messages["tierSettings.{$index}.label.max"] = "عنوان اختصاصی نباید بیشتر از 50 کاراکتر باشد.";
            $messages["tierSettings.{$index}.label.regex"] = "عنوان اختصاصی فقط می‌تواند شامل حروف فارسی، اعداد و فاصله باشد.";
        }
        
        return $messages;
    }
    
    public function save()
    {
        $this->validate();
        
        $supplierId = Auth::user()->currentSupplier()->id;
        
        $staleSettings = false;
        DB::transaction(function () use ($supplierId, &$staleSettings) {
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();
            if ((int) $supplier->supplier_tier_discount_revision !== $this->supplierTierDiscountRevision) {
                $staleSettings = true;
                return;
            }

            foreach ($this->tierSettings as $index => $tier) {
                $tierIndex = (int)$tier['tier_index'];
                $percentage = (float)$tier['percentage_change'];
                $label = $this->sanitizeInput($tier['label']);
                
                SupplierTierDiscount::updateOrCreate(
                    [
                        'supplier_id' => $supplierId,
                        'tier_index' => $tierIndex,
                    ],
                    [
                        'percentage_change' => $percentage,
                        'label' => $label,
                        'is_active' => true,
                    ]
                );
            }

            $supplier->increment('tier_pricing_revision');
            $supplier->increment('supplier_tier_discount_revision');
        });

        if ($staleSettings) {
            $this->loadTierSettings();
            $this->hasChanges = false;
            $this->calculateAffectedPrices();
            $this->dispatch('notify', message: 'درصدهای تیراژ در تب دیگری تغییر کرده؛ مقدار تازه بارگذاری شد. دوباره بررسی و ذخیره کنید.', type: 'warning');
            return;
        }
        
        $this->dispatch('notify',
            message: '✅ تنظیمات درصد تیراژ با موفقیت ذخیره شد.',
            type: 'success'
        );
        
        $this->hasChanges = false;
        $this->loadTierSettings();
        $this->calculateAffectedPrices();
    }
    
    public function resetToDefaults()
    {
        foreach ($this->tierSettings as $index => $tier) {
            $this->tierSettings[$index]['percentage_change'] = $this->getDefaultPercentage($index);
            $this->tierSettings[$index]['label'] = null;
        }
        
        $this->hasChanges = true;
        
        $this->dispatch('notify',
            message: 'تنظیمات به حالت پیشفرض بازگشت. برای اعمال، ذخیره کنید.',
            type: 'info'
        );
    }
    
    /**
     * محاسبه تعداد قیمت‌های تحت تأثیر
     */
    public function calculateAffectedPrices()
    {
        $supplierId = Auth::user()->currentSupplier()->id;
        
        // شمارش قیمت‌های نقدی که با mode='default' هستند
        $this->affectedPricesCount = DB::table('product_prices as pp')
            ->join('product_supplier as ps', 'pp.product_supplier_id', '=', 'ps.id')
            ->where('ps.supplier_id', $supplierId)
            ->where('pp.price_type_id', function($query) {
                $query->select('id')
                    ->from('price_types')
                    ->where('code', 'cash')
                    ->limit(1);
            })
            ->where('pp.is_active', true)
            ->count();
    }
    
    /**
     * نمایش تأییدیه بروزرسانی
     */
    public function confirmUpdatePrices()
    {
        if ($this->hasChanges || $this->currencySettingsChanged) {
            $this->dispatch('notify',
                message: 'ابتدا تنظیمات درصد و ارز را ذخیره کنید.',
                type: 'warning'
            );
            return;
        }
        
        $this->calculateAffectedPrices();
        $this->showUpdateConfirm = true;
    }
    
    /**
     * بروزرسانی قیمت‌های تیراژ
     */
    public function updatePrices()
    {
        try {
            if ($this->hasChanges || $this->currencySettingsChanged) {
                $this->dispatch('notify', message: 'ابتدا تنظیمات درصد و ارز را ذخیره کنید.', type: 'warning');
                return;
            }

            $supplierId = Auth::user()->currentSupplier()->id;

            $supplier = Supplier::query()->findOrFail($supplierId);
            $tomanApplyQueued = $supplier->pricing_currency === 'TOM';
            if ($tomanApplyQueued) {
                \App\Jobs\ApplySupplierTomanTierPrices::dispatch(
                    $supplierId,
                    (int) $supplier->pricing_currency_revision,
                    (int) $supplier->tier_pricing_revision,
                );
            }

            // FX products use the selected reference tier and the current supplier rate.
            // Combine both outcomes so a failed/declined FX apply cannot be hidden
            // by a later generic success toast.
            $currencyApplyStatus = $this->applyCurrencyRatesToProducts(notify: false);
            $type = 'success';
            if ($tomanApplyQueued) {
                $message = 'بازمحاسبهٔ قیمت تیراژ همهٔ محصولات تومانی در صف قرار گرفت.';
            } elseif ($currencyApplyStatus === 'queued') {
                $message = 'محاسبهٔ قیمت‌های همهٔ محصولات ارزی در صف قرار گرفت.';
            } elseif ($currencyApplyStatus === 'rate_unavailable') {
                $message = 'نرخ ارز معتبر در دسترس نبود؛ آخرین قیمت ذخیره‌شده حفظ شد.';
                $type = 'warning';
            } elseif ($currencyApplyStatus === 'no_products') {
                $message = 'محصولی با مبلغ ارزی برای محاسبه پیدا نشد.';
            } else {
                $message = 'تنظیمات ذخیره نشده بود؛ ابتدا تنظیمات درصد و نرخ را ذخیره کنید.';
                $type = 'warning';
            }

            $this->dispatch('notify', message: $message, type: $type);

            $this->showUpdateConfirm = false;
            $this->calculateAffectedPrices();
            
        } catch (\Exception $e) {
            \Log::error('Error updating tier prices: ' . $e->getMessage());
            $this->dispatch('notify',
                message: 'خطا در بروزرسانی قیمت‌ها: ' . $e->getMessage(),
                type: 'error'
            );
            $this->showUpdateConfirm = false;
        }
    }
    
    /**
     * لغو modal
     */
    public function cancelUpdate()
    {
        $this->showUpdateConfirm = false;
    }
    
    /**
     * بارگذاری دسته‌بندی‌ها
     */
    public function loadCategories()
    {
        // فقط دسته‌های برگ (بدون فرزند)
        $this->categories = Category::active()
            ->whereDoesntHave('children')
            ->ordered()
            ->get()
            ->map(function($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->full_path,
                ];
            })
            ->pluck('name', 'id')
            ->toArray();
    }
    
    /**
     * بارگذاری تنظیمات دسته‌بندی
     */
    public function loadCategoryTierSettings()
    {
        $supplierId = Auth::user()->currentSupplier()->id;
        
        // دریافت تمام تنظیمات
        $records = DB::table('category_tier_discount as ctd')
            ->join('categories as c', 'ctd.category_id', '=', 'c.id')
            ->where('ctd.supplier_id', $supplierId)
            ->where('ctd.is_active', true)
            ->select([
                'c.id as category_id',
                'c.name as category_name',
                'ctd.tier_index',
                'ctd.percentage_change',
            ])
            ->orderBy('c.name')
            ->orderBy('ctd.tier_index')
            ->get();
        
        // گروه‌بندی بر اساس دسته‌بندی
        $grouped = [];
        foreach ($records as $record) {
            $categoryId = $record->category_id;
            
            if (!isset($grouped[$categoryId])) {
                // دریافت مسیر کامل دسته‌بندی
                $category = Category::find($categoryId);
                
                $grouped[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $category ? $category->full_path : $record->category_name,
                    'tiers' => [],
                ];
            }
            
            // فقط تیراژهای غیر از مرجع (tier_index != 1)
            if ($record->tier_index != 1) {
                $grouped[$categoryId]['tiers'][] = [
                    'tier_index' => (int)$record->tier_index,
                    'tier_name' => self::DEFAULT_TIER_NAMES[$record->tier_index] ?? "تیراژ {$record->tier_index}",
                    'percentage' => (float)$record->percentage_change,
                ];
            }
        }
        
        $this->categoryTierSettings = array_values($grouped);
    }
    
    /**
     * افزودن دسته‌بندی
     */
    public function addCategory()
    {
        $this->validate([
            'selectedCategoryId' => ['required', 'exists:categories,id'],
            'categoryTierPercentages' => ['required', 'array', 'min:1'],
            'categoryTierPercentages.*' => ['required', 'numeric', 'min:-100', 'max:100'],
        ], [
            'selectedCategoryId.required' => 'لطفاً یک دسته‌بندی انتخاب کنید.',
            'selectedCategoryId.exists' => 'دسته‌بندی انتخاب شده معتبر نیست.',
            'categoryTierPercentages.required' => 'لطفاً حداقل یک درصد تعیین کنید.',
            'categoryTierPercentages.*.numeric' => 'درصد باید یک عدد باشد.',
            'categoryTierPercentages.*.min' => 'درصد نمی‌تواند کمتر از -100 باشد.',
            'categoryTierPercentages.*.max' => 'درصد نمی‌تواند بیشتر از 100 باشد.',
        ]);
        
        $supplierId = Auth::user()->currentSupplier()->id;
        $categoryId = (int)$this->selectedCategoryId;
        
        // بررسی: آیا این دسته‌بندی قبلاً اضافه شده؟
        $existing = DB::table('category_tier_discount')
            ->where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->exists();
        
        if ($existing) {
            $this->dispatch('notify',
                message: 'این دسته‌بندی قبلاً اضافه شده است. می‌توانید آن را ویرایش کنید.',
                type: 'warning'
            );
            return;
        }
        
        // افزودن رکوردها (فقط برای تیراژهای غیر از مرجع)
        $categoryAlreadyExists = DB::transaction(function () use ($supplierId, $categoryId): bool {
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();

            if (DB::table('category_tier_discount')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->exists()) {
                return true;
            }

            foreach ($this->categoryTierPercentages as $tierIndex => $percentage) {
                // تیراژ مرجع (1) را skip کن
                if ($tierIndex == 1) {
                    continue;
                }
                
                if ($percentage !== null && $percentage !== '') {
                    DB::table('category_tier_discount')->insert([
                        'supplier_id' => $supplierId,
                        'category_id' => $categoryId,
                        'tier_index' => (int)$tierIndex,
                        'percentage_change' => (float)$percentage,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $supplier->increment('tier_pricing_revision');
            return false;
        });

        if ($categoryAlreadyExists) {
            $this->dispatch('notify',
                message: 'این دسته‌بندی قبلاً اضافه شده است. می‌توانید آن را ویرایش کنید.',
                type: 'warning'
            );
            return;
        }

        $this->reset(['selectedCategoryId', 'categoryTierPercentages']);
        $this->loadCategoryTierSettings();
        $this->calculateAffectedPrices();
        
        $this->dispatch('notify',
            message: '✅ درصدهای دسته‌بندی با موفقیت تنظیم شد.',
            type: 'success'
        );
    }
    
    /**
     * شروع ویرایش دسته‌بندی
     */
    public function startEditCategory($categoryId)
    {
        $supplierId = Auth::user()->currentSupplier()->id;

        $rows = DB::transaction(function () use ($supplierId, $categoryId) {
            Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();

            return DB::table('category_tier_discount')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->orderBy('tier_index')
                ->get(['tier_index', 'percentage_change', 'is_active']);
        });

        $this->editingCategoryId = $categoryId;
        $this->categoryEditSignature = $this->categoryEditSignatureFromRows((int) $categoryId, $rows);
        $this->categoryTierPercentages = [];

        foreach ($rows as $row) {
            // تیراژ مرجع را skip کن
            if ((bool) $row->is_active && $row->tier_index != 1) {
                $this->categoryTierPercentages[$row->tier_index] = (float)$row->percentage_change;
            }
        }
    }
    
    /**
     * ذخیره ویرایش دسته‌بندی
     */
    public function saveCategoryEdit()
    {
        $this->validate([
            'categoryTierPercentages' => ['required', 'array', 'min:1'],
            'categoryTierPercentages.*' => ['required', 'numeric', 'min:-100', 'max:100'],
        ]);
        
        $supplierId = Auth::user()->currentSupplier()->id;
        $categoryId = $this->editingCategoryId;

        if ($categoryId === null || $this->categoryEditSignature === null) {
            $this->dispatch('notify', message: 'فرم ویرایش دسته‌بندی منقضی شده است؛ دوباره بازش کنید.', type: 'warning');
            return;
        }

        $expectedSignature = $this->categoryEditSignature;
        $saved = DB::transaction(function () use ($supplierId, $categoryId, $expectedSignature): bool {
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();

            if (!hash_equals($expectedSignature, $this->currentCategoryEditSignature($supplierId, (int) $categoryId))) {
                return false;
            }

            // حذف تنظیمات قبلی
            DB::table('category_tier_discount')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->delete();
            
            // افزودن تنظیمات جدید (فقط برای تیراژهای غیر از مرجع)
            foreach ($this->categoryTierPercentages as $tierIndex => $percentage) {
                if ($tierIndex == 1) {
                    continue; // تیراژ مرجع را skip کن
                }
                
                if ($percentage !== null && $percentage !== '') {
                    DB::table('category_tier_discount')->insert([
                        'supplier_id' => $supplierId,
                        'category_id' => $categoryId,
                        'tier_index' => (int)$tierIndex,
                        'percentage_change' => (float)$percentage,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $supplier->increment('tier_pricing_revision');
            return true;
        });

        if (!$saved) {
            $this->cancelCategoryEdit();
            $this->loadCategoryTierSettings();
            $this->dispatch('notify', message: 'این تنظیمات در تب دیگری تغییر کرده؛ مقدار تازه بارگذاری شد. دوباره بررسی و ویرایش کنید.', type: 'warning');
            return;
        }

        $this->cancelCategoryEdit();
        $this->loadCategoryTierSettings();
        $this->calculateAffectedPrices();
        
        $this->dispatch('notify',
            message: '✅ درصدهای دسته‌بندی بروزرسانی شد.',
            type: 'success'
        );
    }
    
    /**
     * لغو ویرایش دسته‌بندی
     */
    public function cancelCategoryEdit()
    {
        $this->reset(['editingCategoryId', 'categoryEditSignature', 'categoryTierPercentages']);
    }

    private function currentCategoryEditSignature(int $supplierId, int $categoryId): string
    {
        $rows = DB::table('category_tier_discount')
            ->where('supplier_id', $supplierId)
            ->where('category_id', $categoryId)
            ->orderBy('tier_index')
            ->get(['tier_index', 'percentage_change', 'is_active']);

        return $this->categoryEditSignatureFromRows($categoryId, $rows);
    }

    private function categoryEditSignatureFromRows(int $categoryId, $rows): string
    {
        $normalizedRows = $rows
            ->map(fn ($row) => [(int) $row->tier_index, (float) $row->percentage_change, (bool) $row->is_active])
            ->all();

        return hash('sha256', json_encode([$categoryId, $normalizedRows], JSON_THROW_ON_ERROR));
    }
    
    /**
     * حذف دسته‌بندی
     */
    public function removeCategory($categoryId)
    {
        $supplierId = Auth::user()->currentSupplier()->id;

        DB::transaction(function () use ($supplierId, $categoryId): void {
            $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();

            DB::table('category_tier_discount')
                ->where('supplier_id', $supplierId)
                ->where('category_id', $categoryId)
                ->delete();
            $supplier->increment('tier_pricing_revision');
        });
        
        $this->loadCategoryTierSettings();
        $this->calculateAffectedPrices();
        
        $this->dispatch('notify',
            message: '✅ تنظیمات دسته‌بندی حذف شد.',
            type: 'success'
        );
    }
    
    /**
     * Hook برای تشخیص تغییرات
     */
    public function updated($property)
    {
        if (str_starts_with($property, 'tierSettings')) {
            $this->hasChanges = true;
        }

        if (str_starts_with($property, 'currencySettings')) {
            $this->currencySettingsChanged = true;
        }

        if ($property === 'pricingCurrency') {
            $this->currencySettingsChanged = true;
        }
    }
    
    /**
     * Sanitize user input
     */
    private function sanitizeInput(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public function render()
    {
        // دریافت درصدهای پیشفرض تامین‌کننده (برای نمایش در فرم دسته‌بندی)
        $supplierId = Auth::user()->currentSupplier()->id;
        $defaultTiers = SupplierTierDiscount::getAllForSupplier($supplierId)
            ->mapWithKeys(function($tier) {
                return [$tier->tier_index => $tier];
            })
            ->toArray();
        
        return view('livewire.supplier.settings.manage-tier-discounts', [
            'defaultTiers' => $defaultTiers,
        ]);
    }
}
