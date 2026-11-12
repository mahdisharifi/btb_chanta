<div data-tier-discounts-guide
     data-product-tour-welcome-title="راهنمای تنظیمات تیراژ و نرخ ارز"
     data-product-tour-welcome-description="در این صفحه درصدهای پیش‌فرض تیراژ، ارز پایه و نرخ محصولات، و استثناهای هر دسته‌بندی را تنظیم می‌کنید. راهنما چیزی را ذخیره یا اعمال نمی‌کند؛ هر ذخیره یا باز‌محاسبه فقط با اقدام خودتان انجام می‌شود."
     data-product-tour-welcome-hint="با «مرحله بعد» جلو بروید. برای خروج، ضربدر یا «بستن» را بزنید."
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    {{-- Notification Toast --}}
    <div x-data="{ show: false, message: '', type: 'success' }" 
         @notify.window="
            show = true; 
            message = $event.detail.message;
            type = $event.detail.type || 'success';
            setTimeout(() => show = false, 5000)
         "
         x-show="show"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full px-4">
        <div :class="{
            'bg-green-50 border-green-400': type === 'success',
            'bg-red-50 border-red-400': type === 'error',
            'bg-yellow-50 border-yellow-400': type === 'warning',
            'bg-blue-50 border-blue-400': type === 'info'
        }" class="flex items-center gap-3 p-4 border-2 rounded-lg shadow-xl">
            <div :class="{
                'text-green-600': type === 'success',
                'text-red-600': type === 'error',
                'text-yellow-600': type === 'warning',
                'text-blue-600': type === 'info'
            }">
                <svg x-show="type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <svg x-show="type === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <svg x-show="type === 'warning'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <svg x-show="type === 'info'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p :class="{
                'text-green-900': type === 'success',
                'text-red-900': type === 'error',
                'text-yellow-900': type === 'warning',
                'text-blue-900': type === 'info'
            }" class="text-sm font-medium flex-1" role="status" aria-live="polite" aria-atomic="true" x-text="message"></p>
            <button @click="show = false" aria-label="بستن اعلان" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
        <h1 data-product-tour-welcome class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            تنظیمات درصد تیراژ نقدی
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            مدیریت درصد افزایش/کاهش قیمت نقدی هر تیراژ نسبت به تیراژ مرجع (کارتن)
        </p>
        </div>
        <x-interactive-guide-launcher />
    </div>

    {{-- راهنما --}}
    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-200">
                <p class="font-semibold mb-2">نحوه عملکرد:</p>
                <ul class="list-disc list-inside space-y-1 mr-4">
                    <li><strong>تیراژ مرجع (کارتن):</strong> درصد همیشه 0% است</li>
                    <li><strong>درصد مثبت (+):</strong> قیمت گران‌تر از مرجع (مثلاً زیر کارتن: +5%)</li>
                    <li><strong>درصد منفی (-):</strong> قیمت ارزان‌تر از مرجع (مثلاً عمده: -5%)</li>
                    <li><strong>اولویت:</strong> درصدهای اختصاصی دسته‌بندی روی درصدهای پیشفرض اولویت دارند</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- بخش 1: تنظیمات پیشفرض --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <h2 data-product-tour-step="default-settings-intro"
                data-product-tour-order="10"
                data-product-tour-title="درصدهای پیش‌فرض تیراژ"
                data-product-tour-description="این مقادیر برای همهٔ محصولات مبنا هستند. درصد مثبت قیمت را نسبت به کارتن بالاتر می‌برد و درصد منفی آن را پایین‌تر می‌آورد."
                class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                ⚙️ تنظیمات پیشفرض تیراژ
            </h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                برای همه محصولات اعمال می‌شود
            </span>
        </div>

        <p class="mb-4 text-sm leading-6 text-gray-500 dark:text-gray-400">در شروع فقط کارتن فعال است. فعال‌کردن تیراژ در این بخش، امکان انتخاب آن را در قیمت‌گذاری باز می‌کند؛ فروش آن برای هر کالای بدون تنظیم قبلی، خاموش است و باید جداگانه فعال شود. تیراژ غیرفعال این بخش در قیمت‌گذاری و نمایش قیمت‌ها پنهان می‌شود. قیمت و انتخاب قبلی هر کالا حفظ می‌شود. برای ثبت تغییر وضعیت، «ذخیره» را بزنید.</p>
        @if(count($tierSettings) > 0)
        <form wire:submit.prevent="save">
            <div class="space-y-4">
                @foreach($tierSettings as $index => $tier)
                <div data-product-tour-step="default-tier-{{ $tier['tier_index'] }}"
                     data-product-tour-order="{{ 20 + $tier['tier_index'] }}"
                     data-product-tour-title="{{ $tier['name'] }}"
                     data-product-tour-description="{{ $tier['tier_index'] === 1 ? 'کارتن تیراژ مرجع است و همیشه با درصد صفر می‌ماند؛ امکان ویرایش این فیلد وجود ندارد.' : 'درصد این تیراژ نسبت به قیمت کارتن محاسبه می‌شود. عدد مثبت قیمت را بیشتر و عدد منفی آن را کمتر می‌کند؛ مثال قیمت کنار فیلد اثر مقدار را نشان می‌دهد. مقدار مجاز از منفی ۱۰۰ تا مثبت ۱۰۰ درصد است.' }}"
                     data-product-tour-target="input"
                     data-product-tour-label=".tier-guide-label"
                     class="rounded-xl border-2 p-4 transition-all {{ ($tier['is_active'] ?? true) ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/20' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30' }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_7rem_minmax(0,1fr)_auto] items-center">
                        {{-- نام تیراژ --}}
                        <div>
                            <label class="tier-guide-label block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $tier['name'] }}
                                @if($tier['tier_index'] === 1)
                                    <span class="text-xs text-gray-500">(مرجع)</span>
                                @endif
                            </label>
                            @if((int) $tier['tier_index'] !== 1)
                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ ($tier['is_active'] ?? true) ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">{{ ($tier['is_active'] ?? true) ? 'فعال' : 'غیرفعال' }}</span>
                                </div>
                                @error("tierSettings.{$index}.is_active")
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            @else
                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-800">همیشه فعال</span>
                            @endif
                        </div>

                        {{-- درصد تغییر --}}
                        <div>
                            <div class="relative">
                                <input aria-label="درصد {{ $tier['name'] }} نسبت به مرجع" class="tier-guide-input min-h-11 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @if($tier['tier_index'] === 1) bg-gray-100 cursor-not-allowed @endif" type="number"
                                       step="0.01"
                                       wire:model="tierSettings.{{ $index }}.percentage_change"
                                       @if($tier['tier_index'] === 1) readonly @endif
                                       placeholder="0.00">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">%</span>
                            </div>
                            @error("tierSettings.{$index}.percentage_change")
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- قیمت نمونه --}}
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @php
                                $referencePrice = 100000;
                                $percentage = (float)($tier['percentage_change'] ?? 0);
                                $calculatedPrice = $referencePrice * (1 + ($percentage / 100));
                            @endphp
                            مثال: <span class="font-semibold @if($percentage > 0) text-red-600 @elseif($percentage < 0) text-green-600 @endif">{{ number_format($calculatedPrice, 0) }} تومان</span>
                        </div>
                        @if((int) $tier['tier_index'] !== 1)
                            <button type="button" wire:click="$toggle('tierSettings.{{ $index }}.is_active')"
                                wire:loading.attr="disabled" aria-pressed="{{ ($tier['is_active'] ?? true) ? 'true' : 'false' }}"
                                aria-label="{{ ($tier['is_active'] ?? true) ? 'غیرفعال کردن' : 'فعال کردن' }} {{ $tier['name'] }}"
                                class="min-h-11 shrink-0 rounded-lg px-4 py-2 text-sm font-medium transition-all disabled:opacity-60 {{ ($tier['is_active'] ?? true) ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200' }}">{{ ($tier['is_active'] ?? true) ? 'غیرفعال کردن' : 'فعال کردن' }}</button>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    @if($affectedPricesCount > 0)
                        <span class="font-semibold text-blue-600 dark:text-blue-400">{{ number_format($affectedPricesCount) }}</span> قیمت نقدی فعال
                    @else
                        هیچ قیمت فعالی وجود ندارد
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if($hasChanges)
                        <span class="text-xs text-amber-600 dark:text-amber-400">تغییرات ذخیره نشده</span>
                    @endif
                    
                    <button type="button"
                            data-product-tour-step="reset-default-tiers"
                            data-product-tour-order="28"
                            data-product-tour-title="بازنشانی درصدها"
                            data-product-tour-description="درصدها و نام‌ها به پیش‌فرض برمی‌گردند و فقط کارتن فعال می‌ماند. برای ثبت بازنشانی، «ذخیره» را بزنید."
                            wire:click="resetToDefaults"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm">
                        پیشفرض
                    </button>
                    <button type="submit"
                            data-product-tour-step="save-default-tiers"
                            data-product-tour-order="29"
                            data-product-tour-title="ذخیرهٔ درصدهای عمومی"
                            data-product-tour-description="این کار قانون عمومی تیراژ را ذخیره می‌کند. برای باز‌محاسبهٔ قیمت‌های نقدی موجود، پس از ذخیره دکمهٔ جداگانهٔ به‌روزرسانی را بزنید و تأیید کنید."
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors font-medium text-sm">
                        ذخیره
                    </button>
                </div>
            </div>
        </form>
        
        {{-- بروزرسانی قیمت‌ها --}}
        @if($affectedPricesCount > 0 && !$hasChanges)
        <div class="mt-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span class="text-xs text-green-700 dark:text-green-300">با تغییر درصدها، می‌توانید قیمت‌های موجود را بروز کنید</span>
                </div>
                <button type="button"
                        data-product-tour-step="update-existing-tier-prices"
                        data-product-tour-order="30"
                        data-product-tour-title="بازمحاسبهٔ قیمت‌های موجود"
                        data-product-tour-description="این گزینه پس از ذخیرهٔ درصدها ظاهر می‌شود. با انتخاب آن، پنجرهٔ تأیید باز می‌شود و فقط با تأیید خودتان باز‌محاسبه در صف قرار می‌گیرد؛ عملیات قابل بازگشت نیست."
                        wire:click="confirmUpdatePrices"
                        class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded transition-colors">
                    بروزرسانی قیمت‌ها
                </button>
            </div>
        </div>
        @endif
        @else
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            در حال بارگذاری...
        </div>
        @endif
    </div>

    {{-- نرخ ارز و سیاست به‌روزرسانی در سطح تامین‌کننده --}}
    <section class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 sm:p-6 mb-6" aria-labelledby="currency-settings-title">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between mb-5">
            <div>
                <h2 id="currency-settings-title"
                    data-product-tour-step="currency-settings-intro"
                    data-product-tour-order="40"
                    data-product-tour-title="ارز و قیمت محصولات"
                    data-product-tour-description="ارز پایه برای قیمت نقدی همهٔ محصولات تأمین‌کننده استفاده می‌شود. تومان نیازی به نرخ تبدیل ندارد؛ انتخاب دلار، یوان یا درهم نرخ هر واحد را به تومان مشخص می‌کند."
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    💱 ارز پایه و نرخ محصولات
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    ارز پایه را یک‌بار برای همهٔ محصولات انتخاب کنید؛ سپس مبلغ نقدی هر محصول با همین ارز وارد و به تومان تبدیل می‌شود.
                </p>
            </div>
            @if($currencySettingsChanged)
                <span class="text-xs text-amber-600 dark:text-amber-400" role="status">تغییرات نرخ ارز ذخیره نشده است</span>
            @endif
        </div>

        <div data-product-tour-step="tier-currency-base"
             data-product-tour-order="41"
             data-product-tour-title="انتخاب ارز پایه"
             data-product-tour-description="یکی از تومان، دلار، یوان یا درهم را برای قیمت‌گذاری همهٔ محصولات انتخاب کنید. با انتخاب ارز ارزی، راهنما اول فیلد اختیاری نرخ دستی را نشان می‌دهد، بعد روش دریافت خودکار و دورهٔ به‌روزرسانی را توضیح می‌دهد. تغییرات تا زدن دکمهٔ ذخیره ثبت نمی‌شوند."
             data-product-tour-hint="با ذخیرهٔ تغییر ارز، سامانه قیمت‌های قبلی را از روی معادل تومانی هماهنگ می‌کند. اگر نرخ لازم در دسترس نباشد، مبلغ تومانی حفظ می‌شود و مبلغ ارزی قبلی ممکن است تا ورود نرخ معتبر دوباره نمایش داده نشود."
             data-product-tour-target="#supplier-pricing-currency"
             data-product-tour-label=".tier-guide-currency-label"
             class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
            <label for="supplier-pricing-currency" class="tier-guide-currency-label mb-2 block text-sm font-semibold text-gray-900 dark:text-gray-100">ارز قیمت‌گذاری همهٔ محصولات</label>
            <select id="supplier-pricing-currency"
                    wire:model.live="pricingCurrency"
                    aria-describedby="supplier-pricing-currency-help"
                    aria-invalid="@error('pricingCurrency') true @else false @enderror"
                    class="w-full rounded-md border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 sm:max-w-md focus:border-indigo-500 focus:ring-indigo-500">
                <option value="TOM">تومان (TOM)</option>
                @foreach(\App\Livewire\Supplier\Settings\ManageTierDiscounts::CURRENCIES as $currency => $currencyLabel)
                    <option value="{{ $currency }}">{{ $currencyLabel }} ({{ $currency }})</option>
                @endforeach
            </select>
            <p id="supplier-pricing-currency-help" class="mt-2 text-xs leading-5 text-gray-600 dark:text-gray-300">
                با تغییر ارز پایه، مبلغ‌های ارزی قبلی به‌صورت گروهی و بر اساس قیمت تومانی ذخیره‌شده هم‌ارزسازی می‌شوند؛ اگر نرخ در دسترس نباشد مبلغ قبلی پنهان می‌ماند تا دوباره وارد شود و قیمت تومانی قبلی حفظ می‌شود.
            </p>
            @error('pricingCurrency') <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p> @enderror
        </div>

        @if($pricingCurrency === 'TOM')
            <div class="mb-4 rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                ارز پایه تومان است؛ برای این حالت نرخ تبدیل لازم نیست.
            </div>
        @endif

        @if($pricingCurrency !== 'TOM')
        <div class="grid grid-cols-1 gap-4">
            @foreach(\App\Livewire\Supplier\Settings\ManageTierDiscounts::CURRENCIES as $currency => $currencyLabel)
                @if($pricingCurrency === $currency)
                <div wire:key="supplier-currency-setting-{{ $currency }}" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4 space-y-3">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $currencyLabel }} <span class="text-sm text-gray-500">({{ $currency }})</span></h3>

                    <div data-product-tour-step="currency-rate-input"
                         data-product-tour-order="42"
                         data-product-tour-title="نرخ هر واحد به تومان"
                         data-product-tour-description="{{ $currencySettings[$currency]['auto_update'] ? 'در حالت خودکار این کادر فقط آخرین نرخ معتبر را نمایش می‌دهد و قابل ویرایش نیست. برای نرخ دستی، تیک دریافت خودکار را خاموش کنید.' : 'وارد کردن نرخ دستی اختیاری است. اگر دستی قیمت‌گذاری می‌کنید، نرخ هر یک واحد از این ارز را به تومان وارد کنید؛ اگر نرخ را نمی‌دانید، فیلد را خالی بگذارید و در گام بعد دریافت خودکار را روشن کنید.' }}"
                         data-product-tour-hint="{{ $currencySettings[$currency]['auto_update'] ? 'نرخ دستی لازم نیست؛ نرخ فعلی از سامانه دریافت شده است.' : 'این فیلد اختیاری است؛ می‌توانید نرخ دلخواه را وارد کنید یا خالی بگذارید.' }}"
                         data-product-tour-target="input"
                         data-product-tour-label=".tier-guide-currency-rate-label">
                        <label for="manual-currency-rate-{{ $currency }}" class="tier-guide-currency-rate-label block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $currencySettings[$currency]['auto_update'] ? 'آخرین نرخ موجود هر واحد به تومان' : 'نرخ دستی هر واحد به تومان' }}
                        </label>
                        <input id="manual-currency-rate-{{ $currency }}"
                               wire:key="currency-rate-input-{{ $currency }}-{{ $currencySettings[$currency]['auto_update'] ? 'automatic' : 'manual' }}"
                               type="number"
                               min="0.0001"
                               step="0.0001"
                               inputmode="decimal"
                               @if($currencySettings[$currency]['auto_update'])
                                   value="{{ $currencySettings[$currency]['rate_toman'] ?? '' }}"
                                   readonly
                                   aria-readonly="true"
                               @else
                               wire:model="currencySettings.{{ $currency }}.manual_rate_toman"
                               @endif
                               aria-describedby="currency-rate-help-{{ $currency }}"
                               aria-invalid="@error('currencySettings.' . $currency . '.manual_rate_toman') true @else false @enderror"
                               class="w-full rounded-md border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm read-only:bg-gray-50 read-only:text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                        <p id="currency-rate-help-{{ $currency }}" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if($currencySettings[$currency]['auto_update'])
                                آخرین نرخ موجود و زمان دریافتش نمایش داده می‌شود. اگر زمان انتشار در منبع موجود نباشد یا دریافت تازه ناموفق باشد هشدار می‌بینید؛ نرخ بدون زمان معتبر یا خارج از بازهٔ انتخابی روی محصولات اعمال نمی‌شود.
                            @else
                                نمونهٔ نرخ دستی هر واحد {{ $currencyLabel }} به تومان؛ برای ارزهای استفاده‌نشده می‌توانید این فیلد را خالی بگذارید.
                            @endif
                        </p>
                        @error('currencySettings.' . $currency . '.manual_rate_toman')
                            <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <label for="currency-auto-{{ $currency }}"
                           data-product-tour-step="currency-auto-toggle"
                           data-product-tour-order="43"
                           data-product-tour-title="انتخاب روش دریافت نرخ"
                           data-product-tour-description="فیلد نرخ دستی اختیاری است. برای دیدن روش خودکار، این تیک را روشن کنید؛ سامانه نرخ را دریافت می‌کند، کادر نرخ فقط‌خواندنی می‌شود و دورهٔ به‌روزرسانی نمایش داده می‌شود. تغییرات را در پایان با «ذخیرهٔ تنظیمات ارز» ثبت کنید."
                           data-product-tour-hint="{{ $currencySettings[$currency]['auto_update'] ? 'دریافت خودکار از قبل روشن است؛ مرحلهٔ بعد دورهٔ به‌روزرسانی را نشان می‌دهد.' : 'برای ادامهٔ این آموزش، تیک دریافت خودکار را روشن کنید؛ بعد از دریافت نرخ، دورهٔ به‌روزرسانی را انتخاب می‌کنیم.' }}"
                           class="flex min-h-[44px] items-center gap-2 rounded-md border border-purple-200 bg-purple-50 px-3 py-2 text-sm text-purple-900 dark:border-purple-800 dark:bg-purple-900/20 dark:text-purple-100">
                        <input id="currency-auto-{{ $currency }}"
                               type="checkbox"
                               wire:change="toggleAutomaticCurrencyRate('{{ $currency }}', $event.target.checked)"
                               wire:loading.attr="disabled"
                               wire:target="toggleAutomaticCurrencyRate"
                               @checked($currencySettings[$currency]['auto_update'])
                               class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <span>دریافت خودکار نرخ</span>
                    </label>
                    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                        با روشن‌کردن تیک، نرخ در کادر بالا دریافت و نمایش داده می‌شود؛ سپس برای ثبت تنظیمات، دکمهٔ ذخیره را بزنید.
                    </p>
                    <p wire:loading wire:target="toggleAutomaticCurrencyRate" class="text-xs text-purple-700 dark:text-purple-300" role="status">
                        در حال دریافت نرخ…
                    </p>

                    @if($currencySettings[$currency]['auto_update'])
                        <div data-product-tour-step="currency-refresh-interval"
                             data-product-tour-order="44"
                             data-product-tour-title="فاصلهٔ دریافت نرخ خودکار"
                             data-product-tour-description="یکی از دوره‌های ۲، ۶، ۱۲ یا ۲۴ ساعت را برای دریافت خودکار انتخاب کنید. این انتخاب همراه ارز پایه با دکمهٔ «ذخیرهٔ تنظیمات ارز» ثبت می‌شود."
                             data-product-tour-target="select"
                             data-product-tour-label="label">
                            <label for="currency-interval-{{ $currency }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">بازهٔ به‌روزرسانی</label>
                            <select id="currency-interval-{{ $currency }}"
                                    wire:model="currencySettings.{{ $currency }}.refresh_hours"
                                    aria-invalid="@error('currencySettings.' . $currency . '.refresh_hours') true @else false @enderror"
                                    class="w-full rounded-md border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="2">هر ۲ ساعت</option>
                                <option value="6">هر ۶ ساعت</option>
                                <option value="12">هر ۱۲ ساعت</option>
                                <option value="24">هر ۲۴ ساعت</option>
                            </select>
                            @error('currencySettings.' . $currency . '.refresh_hours')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @if($currencySettings[$currency]['auto_update'])
                        <div data-product-tour-step="currency-rate-status"
                             data-product-tour-order="45"
                             data-product-tour-title="وضعیت نرخ دریافتی"
                             data-product-tour-description="نرخ، زمان ثبت در منبع و زمان دریافت در سامانه را اینجا ببینید. اگر نرخ تازه و معتبر در دسترس نباشد هشدار نمایش داده می‌شود و قیمت ذخیره‌شدهٔ محصول حفظ می‌شود."
                             class="rounded-md bg-white px-3 py-2 text-xs leading-5 text-gray-600 dark:bg-gray-800 dark:text-gray-300" aria-live="polite">
                            @if($currencySettings[$currency]['rate_toman'])
                                <div>آخرین نرخ موجود: {{ \App\Helpers\PriceHelper::formatNumber((float) $currencySettings[$currency]['rate_toman']) }} تومان</div>
                                @if($currencySettings[$currency]['source_market_at'])
                                    <div>زمان ثبت‌شده در منبع: {{ $currencySettings[$currency]['source_market_at'] }} تهران</div>
                                @endif
                                @if($currencySettings[$currency]['fetched_at'])
                                    <div>زمان دریافت این نرخ از سامانه: {{ $currencySettings[$currency]['fetched_at'] }} تهران</div>
                                @endif
                            @else
                                <div class="text-amber-700 dark:text-amber-300">هنوز نرخ خودکاری دریافت نشده است.</div>
                            @endif
                            @if($currencySettings[$currency]['last_error'])
                                <div class="mt-1 text-amber-700 dark:text-amber-300">{{ $currencySettings[$currency]['last_error'] }}</div>
                            @endif
                            @if($currencySettings[$currency]['last_apply_error'])
                                <div class="mt-1 border-t border-red-200 pt-1 text-red-700 dark:border-red-800 dark:text-red-300" role="alert">{{ $currencySettings[$currency]['last_apply_error'] }}</div>
                            @endif
                        </div>
                    @else
                        <p class="rounded-md bg-white px-3 py-2 text-xs leading-5 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            @if((float) ($currencySettings[$currency]['manual_rate_toman'] ?? 0) > 0)
                                محصولات با همین نرخ دستی محاسبه می‌شوند.
                            @else
                                نرخ دستی ثبت نشده؛ محصولات این ارز هنگام اعمال گروهی بدون تغییر می‌مانند.
                            @endif
                        </p>
                        @if($currencySettings[$currency]['last_error'])
                            <p class="rounded-md bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200" role="status">
                                {{ $currencySettings[$currency]['last_error'] }}
                            </p>
                        @endif
                        @if($currencySettings[$currency]['last_apply_error'])
                            <p class="rounded-md bg-red-50 px-3 py-2 text-xs leading-5 text-red-800 dark:bg-red-900/20 dark:text-red-200" role="alert">
                                {{ $currencySettings[$currency]['last_apply_error'] }}
                            </p>
                        @endif
                    @endif
                </div>
                @endif
            @endforeach
        </div>
        @endif

        <div class="mt-5 flex flex-col gap-3 border-t border-gray-200 dark:border-gray-700 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs leading-5 text-gray-600 dark:text-gray-400">
                با ذخیرهٔ نرخ دستی، قیمت محصولات به‌طور خودکار در صف محاسبه قرار می‌گیرد. در حالت خودکار، فقط پس از دریافت نرخ تازه و معتبرِ متفاوت، قیمت‌ها به‌روز می‌شوند و تا آن زمان آخرین قیمت ذخیره‌شده حفظ خواهد شد. دکمهٔ اعمال برای اجرای دوباره یا تلاش مجدد است.
            </p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="button"
                        data-product-tour-step="apply-currency-rates"
                        data-product-tour-order="47"
                        data-product-tour-title="اعمال یا تلاش دوبارهٔ نرخ"
                        data-product-tour-description="این دکمه محاسبهٔ قیمت محصولات با نرخ ذخیره‌شده را در صف می‌گذارد؛ برای تنظیمات ذخیره‌نشده غیرفعال است. دریافت خودکار نرخ تازه نیز طبق زمان‌بندی خودش اعمال می‌شود."
                        wire:click="applyCurrencyRatesToProducts"
                        wire:loading.attr="disabled"
                        wire:target="applyCurrencyRatesToProducts"
                        @disabled($currencySettingsChanged || $hasChanges)
                        class="min-h-[44px] rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="applyCurrencyRatesToProducts">اعمال نرخ روی همهٔ محصولات</span>
                    <span wire:loading wire:target="applyCurrencyRatesToProducts">در حال قرار دادن در صف…</span>
                </button>
                <button type="button"
                        data-product-tour-step="save-currency-settings"
                        data-product-tour-order="46"
                        data-product-tour-title="ذخیرهٔ ارز و نرخ"
                        data-product-tour-description="ذخیره، ارز پایه و روش نرخ‌گذاری را ثبت می‌کند. با ذخیرهٔ نرخ دستی، محاسبهٔ قیمت محصولات در صف قرار می‌گیرد؛ در حالت خودکار نرخ تازهٔ معتبر و متفاوت باعث به‌روزرسانی می‌شود. تغییر ارز پایه نیز هماهنگ‌سازی قیمت‌های قبلی را در صف می‌گذارد."
                        wire:click="saveCurrencySettings"
                        wire:loading.attr="disabled"
                        wire:target="saveCurrencySettings"
                        class="min-h-[44px] rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="saveCurrencySettings">ذخیرهٔ تنظیمات ارز</span>
                    <span wire:loading wire:target="saveCurrencySettings">در حال ذخیره…</span>
                </button>
            </div>
        </div>
    </section>

    {{-- بخش 2: تنظیمات اختصاصی دسته‌بندی --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 data-product-tour-step="category-overrides-intro"
                data-product-tour-order="60"
                data-product-tour-title="درصدهای اختصاصی دسته‌بندی"
                data-product-tour-description="برای هر دسته می‌توانید درصدهای تیراژ جدا تعیین کنید. هر درصد اختصاصی فقط جای درصد عمومی همان تیراژ را در همان دسته می‌گیرد؛ تیراژهای بدون مقدار اختصاصی از تنظیم عمومی پیروی می‌کنند."
                class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                📂 تنظیمات اختصاصی دسته‌بندی
            </h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                روی دسته‌بندی خاص اعمال می‌شود
            </span>
        </div>

        {{-- فرم افزودن دسته‌بندی جدید --}}
        @if(!$editingCategoryId)
        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">افزودن دسته‌بندی جدید</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div data-product-tour-step="tier-category-select"
                     data-product-tour-order="61"
                     data-product-tour-title="انتخاب دسته‌بندی"
                     data-product-tour-description="دستهٔ نهایی محصول را انتخاب کنید. درصدهایی که در این فرم ثبت می‌کنید فقط روی همین دسته اثر دارند؛ اگر دسته‌ای تنظیم نداشته باشد، درصدهای عمومی استفاده می‌شوند."
                     data-product-tour-target="select"
                     data-product-tour-label="label">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">دسته‌بندی</label>
                    <select data-tier-guide-category-select wire:model="selectedCategoryId"
                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">انتخاب کنید...</option>
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                
                {{-- درصدهای تیراژ (غیر از مرجع) --}}
                <div class="grid grid-cols-3 gap-2">
                    @foreach([0, 2, 3] as $tierIndex)
                        <div data-product-tour-step="category-tier-{{ $tierIndex }}"
                             data-product-tour-order="{{ 62 + $tierIndex }}"
                             data-product-tour-title="{{ App\Livewire\Supplier\Settings\ManageTierDiscounts::DEFAULT_TIER_NAMES[$tierIndex] }} برای این دسته"
                             data-product-tour-description="این عدد فقط درصد عمومی همین تیراژ را برای دستهٔ انتخاب‌شده جایگزین می‌کند. مقدار داخل placeholder صرفاً درصد عمومی فعلی است؛ فیلد خالی به همان مقدار عمومی برمی‌گردد. درصد مجاز از منفی ۱۰۰ تا مثبت ۱۰۰ است."
                             data-product-tour-target="input"
                             data-product-tour-label=".tier-guide-category-label">
                            <label class="tier-guide-category-label block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ App\Livewire\Supplier\Settings\ManageTierDiscounts::DEFAULT_TIER_NAMES[$tierIndex] }}
                            </label>
                            <div class="relative">
                                <input type="number"
                                       step="0.01"
                                       wire:model="categoryTierPercentages.{{ $tierIndex }}"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                       placeholder="@isset($defaultTiers[$tierIndex]) {{ $defaultTiers[$tierIndex]['percentage_change'] }} @else 0 @endif">
                                <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs">%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            
            <div class="mt-3 flex justify-end">
                <button type="button"
                        data-product-tour-step="add-category-override"
                        data-product-tour-order="66"
                        data-product-tour-title="ثبت درصدهای این دسته"
                        data-product-tour-description="برای افزودن، یک دسته‌بندی و دست‌کم یک درصد معتبر وارد کنید. فیلدهای خالی ثبت نمی‌شوند و برای همان تیراژ از درصد عمومی استفاده خواهد شد؛ اگر این دسته قبلاً تنظیم شده، از «ویرایش» استفاده کنید."
                        wire:click="addCategory"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
                    افزودن
                </button>
            </div>
        </div>
        @endif

        {{-- لیست دسته‌بندی‌های تنظیم شده --}}
        @if(count($categoryTierSettings) > 0)
        <div class="space-y-3">
            @foreach($categoryTierSettings as $setting)
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                @if($editingCategoryId == $setting['category_id'])
                {{-- حالت ویرایش --}}
                <div class="space-y-3">
                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $setting['category_name'] }}</div>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach([0, 2, 3] as $tierIndex)
                            <div data-product-tour-step="edit-category-tier-{{ $setting['category_id'] }}-{{ $tierIndex }}"
                                 data-product-tour-order="{{ 80 + $tierIndex }}"
                                 data-product-tour-title="ویرایش {{ App\Livewire\Supplier\Settings\ManageTierDiscounts::DEFAULT_TIER_NAMES[$tierIndex] }}"
                                 data-product-tour-description="عدد این فیلد درصد اختصاصی همین دسته برای این تیراژ است. برای نگه‌داشتن استثنا، مقدار معتبر را ویرایش کنید؛ خانه‌های خالی که پیش‌تر استثنا نداشته‌اند همچنان از درصد عمومی پیروی می‌کنند."
                                 data-product-tour-target="input"
                                 data-product-tour-label=".tier-guide-edit-category-label">
                                <label class="tier-guide-edit-category-label block text-xs text-gray-600 dark:text-gray-400 mb-1">
                                    {{ App\Livewire\Supplier\Settings\ManageTierDiscounts::DEFAULT_TIER_NAMES[$tierIndex] }}
                                </label>
                                <div class="relative">
                                    <input type="number"
                                           step="0.01"
                                           wire:model="categoryTierPercentages.{{ $tierIndex }}"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs">%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button"
                                wire:click="cancelCategoryEdit"
                                class="px-3 py-1 text-gray-600 dark:text-gray-400 text-sm">
                            انصراف
                        </button>
                        <button type="button"
                                data-product-tour-step="save-category-override-{{ $setting['category_id'] }}"
                                data-product-tour-order="84"
                                data-product-tour-title="ذخیرهٔ استثنای دسته"
                                data-product-tour-description="مقادیر ویرایش‌شده را برای این دسته ثبت می‌کند. برای بازگشت کامل دسته به درصدهای عمومی، از دکمهٔ حذف تنظیمات آن دسته استفاده کنید."
                                wire:click="saveCategoryEdit"
                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            ذخیره
                        </button>
                    </div>
                </div>
                @else
                {{-- حالت نمایش --}}
                <div @if($loop->first)
                         data-product-tour-step="existing-category-override"
                         data-product-tour-order="67"
                         data-product-tour-title="ویرایش درصدهای ثبت‌شده"
                         data-product-tour-description="هر ردیف نشان می‌دهد کدام تیراژ برای دسته استثنا دارد. با «ویرایش» مقادیر موجود را تغییر دهید؛ فقط درصدهای ثبت‌شده جای مقدار عمومی همان تیراژ را می‌گیرند."
                         data-product-tour-target=".tier-guide-category-edit"
                         data-product-tour-label=".tier-guide-category-name"
                     @endif
                     class="flex items-center justify-between">
                    <div>
                        <div class="tier-guide-category-name font-medium text-gray-900 dark:text-gray-100">{{ $setting['category_name'] }}</div>
                        <div class="flex gap-3 mt-1">
                            @foreach($setting['tiers'] as $tier)
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $tier['tier_name'] }}: 
                                    <span class="@if($tier['percentage'] > 0) text-red-600 @elseif($tier['percentage'] < 0) text-green-600 @endif">
                                        {{ $tier['percentage'] > 0 ? '+' : '' }}{{ $tier['percentage'] }}%
                                    </span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button"
                                class="tier-guide-category-edit text-blue-600 dark:text-blue-400 hover:text-blue-800 text-sm"
                                wire:click="startEditCategory({{ $setting['category_id'] }})">
                            ویرایش
                        </button>
                        <button type="button"
                                @if($loop->first)
                                    data-product-tour-step="remove-category-override"
                                    data-product-tour-order="68"
                                    data-product-tour-title="حذف استثنای دسته‌بندی"
                                    data-product-tour-description="این عمل پس از تأیید، همهٔ درصدهای اختصاصی همین دسته را حذف می‌کند؛ قیمت‌گذاری آن دسته دوباره از درصدهای عمومی پیروی می‌کند. راهنما حذف را اجرا نمی‌کند."
                                @endif
                                wire:click="removeCategory({{ $setting['category_id'] }})"
                                class="text-red-600 dark:text-red-400 hover:text-red-800 text-sm"
                                onclick="return confirm('آیا مطمئن هستید؟')">
                            حذف
                        </button>
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @else
        <div data-product-tour-step="empty-category-overrides"
             data-product-tour-order="67"
             data-product-tour-title="تنظیمات دسته‌بندی ثبت‌شده"
             data-product-tour-description="پس از انتخاب دسته و ثبت دست‌کم یک درصد اختصاصی، ردیف آن اینجا ظاهر می‌شود و می‌توانید درصدهای ثبت‌شده را ویرایش یا همهٔ استثناهای همان دسته را حذف کنید."
             class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
            هیچ دسته‌بندی با تنظیمات اختصاصی وجود ندارد
        </div>
        @endif
    </div>

    {{-- Modal تأیید بروزرسانی --}}
    <div x-data="{ show: @entangle('showUpdateConfirm') }" 
         x-show="show" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="show = false"></div>
        
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 data-product-tour-step="update-prices-confirmation"
                            data-product-tour-order="31"
                            data-product-tour-title="تأیید باز‌محاسبه"
                            data-product-tour-description="این پنجره تعداد قیمت‌های تحت‌تأثیر را نشان می‌دهد و هشدار می‌دهد که باز‌محاسبه قابل بازگشت نیست. فقط در صورت اطمینان، خودتان ادامه دهید."
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                            بروزرسانی قیمت‌های نقدی
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            این عملیات <span class="font-bold text-blue-600 dark:text-blue-400">{{ number_format($affectedPricesCount) }}</span> قیمت نقدی را بر اساس درصدهای جدید محاسبه می‌کند.
                        </p>
                        <p class="text-xs text-amber-600 dark:text-amber-400">
                            ⚠️ این عملیات قابل بازگشت نیست.
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center justify-end gap-3 mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <button type="button"
                            data-product-tour-step="cancel-tier-price-update"
                            data-product-tour-order="32"
                            data-product-tour-title="انصراف از باز‌محاسبه"
                            data-product-tour-description="بستن این تأیید قیمت‌های فعلی را تغییر نمی‌دهد."
                            wire:click="cancelUpdate"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 text-sm">
                        انصراف
                    </button>
                    <button type="button"
                            data-product-tour-step="confirm-tier-price-update"
                            data-product-tour-order="33"
                            data-product-tour-title="اجرای باز‌محاسبه"
                            data-product-tour-description="با انتخاب این دکمه، قیمت‌ها برای پردازش در صف قرار می‌گیرند و عملیات قابل بازگشت نیست. راهنما این کار را به‌جای شما انجام نمی‌دهد."
                            wire:click="updatePrices"
                            class="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg">
                        بله، بروزرسانی کن
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
