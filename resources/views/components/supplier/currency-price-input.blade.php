@props([
    'tierIndex',
    'referenceTierIndex',
    'cashPriceTypeId',
    'priceMatrix',
    'autoCalculateTiers',
    'priceCurrency',
    'foreignTierAmounts' => [],
    'foreignPriceRebasePending' => false,
    'foreignPriceAmount',
    'foreignPricePreviewValid',
    'latestExchangeRate',
    'latestExchangeFetchedAt',
    'latestExchangeSource',
    'latestExchangeError',
    'foreignPriceUpdatedAt',
    'foreignPriceLastError',
    'mobile' => false,
])

@php
    $isReferenceTier = (int) $tierIndex === (int) $referenceTierIndex;
    $fieldSuffix = $mobile ? 'mobile' : 'desktop';
    $isLocked = $autoCalculateTiers && !$isReferenceTier;
    $inputClass = $mobile
        ? 'block w-full rounded-lg border-gray-300 px-4 py-3 text-base dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100'
        : 'block w-full rounded-md border-gray-300 px-4 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    $tomanPrice = $priceMatrix[$tierIndex][$cashPriceTypeId]['price'] ?? '';
    $tomanPriceErrorKey = "priceMatrix.{$tierIndex}.{$cashPriceTypeId}.price";
    $tomanPriceErrorId = "toman-price-error-{$fieldSuffix}-{$tierIndex}";
    $currencyLabel = match ($priceCurrency) {
        'USD' => 'دلار',
        'CNY' => 'یوان',
        'AED' => 'درهم',
        default => 'تومان',
    };
    $isDerivedToman = $priceCurrency !== 'TOM' && !$isReferenceTier && $autoCalculateTiers;
@endphp

@if($isReferenceTier)
    <div class="space-y-2" data-currency-price-control>
        <div class="rounded-md bg-white/70 px-3 py-2 text-xs text-gray-600 dark:bg-gray-900/50 dark:text-gray-300"
             aria-label="ارز پایهٔ همهٔ محصولات">
            ارز پایهٔ همهٔ محصولات: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $currencyLabel }}</span>
            <a href="{{ route('supplier.settings.tier-discounts') }}" class="mr-1 text-purple-700 underline dark:text-purple-300">تغییر در تنظیمات تیراژ نقدی</a>
        </div>
        @error('priceCurrency') <p id="price-currency-error-{{ $fieldSuffix }}" class="text-xs text-red-600" role="alert">{{ $message }}</p> @enderror

        @if($priceCurrency === 'TOM')
            <div class="relative">
                <input type="text"
                       wire:ignore
                       id="toman-price-{{ $fieldSuffix }}-{{ $tierIndex }}"
                       x-data="formattedPriceInput($wire, 'priceMatrix.{{ $tierIndex }}.{{ $cashPriceTypeId }}.price', true)"
                       x-effect="refresh(state)"
                       x-on:input="input($event)"
                       x-on:blur="sync()"
                       value="{{ $tomanPrice }}"
                       aria-label="قیمت نقدی تیراژ مرجع به تومان"
                       aria-invalid="@error($tomanPriceErrorKey) true @else false @enderror"
                       aria-describedby="{{ $tomanPriceErrorId }}"
                       placeholder="قیمت نقدی"
                       inputmode="numeric"
                       onwheel="this.blur()"
                       class="{{ $inputClass }} pl-16">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-gray-500 dark:text-gray-400">تومان</span>
            </div>
            @error($tomanPriceErrorKey)
                <p id="{{ $tomanPriceErrorId }}" class="text-xs text-red-600" role="alert">{{ $message }}</p>
            @else
                <span id="{{ $tomanPriceErrorId }}" class="sr-only"></span>
            @enderror
        @else
            @if($foreignPriceRebasePending)
                <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200" role="status">
                    ارز پایهٔ تأمین‌کننده تغییر کرده است. مبلغ قبلی با حفظ قیمت تومانیِ ذخیره‌شده در حال هم‌ارزسازی است؛ تا پایان پردازش، مقدار قبلی به‌عنوان ارز جدید نمایش داده نمی‌شود.
                </div>
            @endif
            <label for="foreign-price-amount-{{ $fieldSuffix }}" class="sr-only">
                قیمت نقدی ({{ $currencyLabel }})
            </label>
            <div class="relative">
                <input type="text"
                       id="foreign-price-amount-{{ $fieldSuffix }}"
                       wire:model.live.debounce.700ms="foreignPriceAmount"
                       inputmode="decimal"
                       autocomplete="off"
                       aria-label="قیمت نقدی ({{ $currencyLabel }})"
                       aria-invalid="@error('foreignPriceAmount') true @else false @enderror"
                       aria-describedby="foreign-price-amount-help-{{ $fieldSuffix }} foreign-price-amount-error-{{ $fieldSuffix }}"
                       placeholder="مثلاً 5 یا 5.12"
                       class="{{ $inputClass }} pl-16">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $priceCurrency }}</span>
            </div>

            <div class="rounded-lg bg-white/70 px-3 py-2 text-xs leading-5 text-gray-600 dark:bg-gray-900/50 dark:text-gray-300" aria-live="polite">
                @if($latestExchangeRate)
                    <div>۱ {{ $priceCurrency }} = {{ \App\Helpers\PriceHelper::formatNumber((float) $latestExchangeRate) }} تومان</div>
                    @if($latestExchangeFetchedAt)
                        <div>آخرین دریافت: {{ $latestExchangeFetchedAt }}</div>
                    @endif
                    @if($latestExchangeSource)
                        <div>منبع: {{ $latestExchangeSource === 'manual' ? 'نرخ دستی تامین‌کننده' : 'نرخ‌سنج' }}</div>
                    @endif
                    <div class="font-semibold text-gray-800 dark:text-gray-100">
                        قیمت تبدیل‌شده:
                        @if($foreignPricePreviewValid && $foreignPriceAmount !== null && $foreignPriceAmount !== '' && $tomanPrice !== '')
                            {{ \App\Helpers\PriceHelper::formatNumber((float) $tomanPrice) }} تومان
                        @else
                            مبلغ ارز را وارد کنید
                        @endif
                    </div>
                    @if($latestExchangeError)
                        <div class="text-amber-700 dark:text-amber-300">آخرین نرخ تازه نیست یا دریافت جدید ناموفق بوده است؛ نرخ و قیمت ثبت‌شدهٔ قبلی حفظ می‌شوند.</div>
                    @endif
                @else
                    <div class="text-amber-700 dark:text-amber-300">نرخ این ارز هنوز تنظیم نشده؛ تا ثبت نرخ دستی یا دریافت نرخ خودکار، قیمت تومانی قبلی حفظ می‌شود.</div>
                    <a href="{{ route('supplier.settings.tier-discounts') }}" class="mt-1 inline-flex min-h-[36px] items-center text-purple-700 underline dark:text-purple-300">تنظیم نرخ ارز</a>
                @endif
                @if($foreignPriceLastError)
                    <div class="mt-1 border-t border-amber-200 pt-1 text-amber-700 dark:border-amber-800 dark:text-amber-300">
                        قیمت ارزی به‌روز نشده است؛ آخرین قیمت تومانی معتبر حفظ شده است.
                        @if($foreignPriceUpdatedAt) آخرین به‌روزرسانی قیمت: {{ $foreignPriceUpdatedAt }} تهران. @endif
                    </div>
                @endif
            </div>
        @endif

        <span id="foreign-price-amount-help-{{ $fieldSuffix }}" class="sr-only">مبلغ خرید هر واحد از کالا را به ارز انتخاب‌شده وارد کنید.</span>
        @error('foreignPriceAmount') <p id="foreign-price-amount-error-{{ $fieldSuffix }}" class="text-xs text-red-600" role="alert">{{ $message }}</p> @else <span id="foreign-price-amount-error-{{ $fieldSuffix }}" class="sr-only"></span> @enderror
    </div>
@else
    @if($priceCurrency !== 'TOM' && !$autoCalculateTiers)
        <div class="space-y-2">
            <label for="foreign-tier-amount-{{ $fieldSuffix }}-{{ $tierIndex }}" class="sr-only">
                قیمت نقدی تیراژ {{ $tierIndex + 1 }} به {{ $priceCurrency }}
            </label>
            <div class="relative">
                <input type="text"
                       id="foreign-tier-amount-{{ $fieldSuffix }}-{{ $tierIndex }}"
                       wire:model.live.debounce.700ms="foreignTierAmounts.{{ $tierIndex }}"
                       inputmode="decimal"
                       autocomplete="off"
                       aria-label="قیمت نقدی تیراژ {{ $tierIndex + 1 }} به {{ $priceCurrency }}"
                       aria-invalid="@error('foreignTierAmounts.' . $tierIndex) true @else false @enderror"
                       aria-describedby="foreign-tier-amount-help-{{ $fieldSuffix }}-{{ $tierIndex }} foreign-tier-amount-error-{{ $fieldSuffix }}-{{ $tierIndex }}"
                       placeholder="مبلغ به {{ $priceCurrency }}"
                       class="{{ $inputClass }} pl-16">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $priceCurrency }}</span>
            </div>
            @error('foreignTierAmounts.' . $tierIndex)
                <p id="foreign-tier-amount-error-{{ $fieldSuffix }}-{{ $tierIndex }}" class="text-xs text-red-600" role="alert">{{ $message }}</p>
            @else
                <span id="foreign-tier-amount-error-{{ $fieldSuffix }}-{{ $tierIndex }}" class="sr-only"></span>
            @enderror
            <div id="foreign-tier-amount-help-{{ $fieldSuffix }}-{{ $tierIndex }}" class="text-xs leading-5 text-gray-600 dark:text-gray-400" aria-live="polite">
                قیمت تبدیل‌شده به تومان:
                <strong class="text-gray-800 dark:text-gray-100">{{ is_numeric($tomanPrice) ? \App\Helpers\PriceHelper::formatNumber((float) $tomanPrice) . ' تومان' : 'مبلغ ارز را وارد کنید' }}</strong>
            </div>
        </div>
    @else
        <div class="relative">
            <input type="text"
                   wire:ignore
                   x-data="formattedPriceInput($wire, 'priceMatrix.{{ $tierIndex }}.{{ $cashPriceTypeId }}.price', true)"
                   x-effect="refresh(state)"
                   x-on:input="input($event)"
                   x-on:blur="sync()"
                   value="{{ $tomanPrice }}"
                   aria-label="{{ $isDerivedToman ? 'قیمت نقدی محاسبه‌شدهٔ تیراژ ' . ($tierIndex + 1) . ' به تومان' : 'قیمت نقدی تیراژ ' . ($tierIndex + 1) . ' به تومان' }}"
                   placeholder="{{ $isDerivedToman ? 'قیمت محاسبه‌شده به تومان' : 'قیمت نقدی' }}"
                   inputmode="numeric"
                   onwheel="this.blur()"
                   @if($isLocked) readonly @endif
                   x-bind:readonly="$wire.autoCalculateTiers && {{ $tierIndex }} !== $wire.referenceTierIndex"
                   x-bind:class="($wire.autoCalculateTiers && {{ $tierIndex }} !== $wire.referenceTierIndex) ? 'opacity-60 cursor-not-allowed bg-gray-100 dark:bg-gray-800' : ''"
                   class="{{ $inputClass }}">
            @if($isLocked)
                <p class="mt-2 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-information-circle class="h-3.5 w-3.5"/>
                    {{ $isDerivedToman ? 'این مبلغ به تومان و بر اساس درصد تیراژ محاسبه می‌شود.' : 'این قیمت از تیراژ مرجع محاسبه می‌شود.' }}
                </p>
            @endif
            @if($isDerivedToman)
                <span class="sr-only">واحد این مبلغ تومان است.</span>
            @endif
        </div>
    @endif
@endif
