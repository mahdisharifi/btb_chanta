<div>
    {{-- Notification Toast (Fixed Overlay) --}}
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
            'bg-green-50 border-green-400 dark:bg-green-900/40 dark:border-green-600': type === 'success',
            'bg-red-50 border-red-400 dark:bg-red-900/40 dark:border-red-600': type === 'error',
            'bg-blue-50 border-blue-400 dark:bg-blue-900/40 dark:border-blue-600': type === 'info'
        }" class="flex items-center gap-3 p-4 border-2 rounded-lg shadow-xl">
            <div :class="{
                'text-green-600 dark:text-green-300': type === 'success',
                'text-red-600 dark:text-red-300': type === 'error',
                'text-blue-600 dark:text-blue-300': type === 'info'
            }">
                <svg x-show="type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <svg x-show="type === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <svg x-show="type === 'info'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="flex-1">
                <p :class="{
                    'text-green-900 dark:text-green-50': type === 'success',
                    'text-red-900 dark:text-red-50': type === 'error',
                    'text-blue-900 dark:text-blue-50': type === 'info'
                }" class="text-sm font-bold" x-text="message"></p>
            </div>
            <button @click="show = false" :class="{
                'text-green-700 hover:text-green-900 dark:text-green-300 dark:hover:text-green-100': type === 'success',
                'text-red-700 hover:text-red-900 dark:text-red-300 dark:hover:text-red-100': type === 'error',
                'text-blue-700 hover:text-blue-900 dark:text-blue-300 dark:hover:text-blue-100': type === 'info'
            }" class="transition-colors flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    {{-- ═══════════ Page Header ═══════════ --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">مدیریت ماتریس قیمت</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            محصول: <span class="font-semibold text-gray-600 dark:text-gray-300">{{ $product->title }}</span>
        </p>
        @if(empty($quantityTiers))
            <div class="mt-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5"/>
                    <div>
                        <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-200">تیراژی برای این محصول تعریف نشده است</h3>
                        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                            برای قیمت‌گذاری این محصول، ابتدا باید تیراژها توسط ادمین تعریف شوند.
                        </p>
                    </div>
                </div>
            </div>
        @elseif(count($quantityTiers) < 3)
            <div class="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-x-circle class="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5"/>
                    <div>
                        <h3 class="text-sm font-semibold text-red-800 dark:text-red-200">تعداد تیراژها کافی نیست</h3>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                            این محصول فقط {{ count($quantityTiers) }} تیراژ دارد. طبق قوانین سیستم، هر محصول باید حداقل 3 تیراژ داشته باشد.
                            لطفاً با ادمین تماس بگیرید تا تیراژهای بیشتری اضافه شود.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ═══════════ Main Card Form ═══════════ --}}
    <x-card>
        <form wire:submit="save" x-data="{ 
            warrantyType: @entangle('warrantyType').live,
            hasWarranty: @entangle('hasWarranty').live,
            init() {
                // پاک کردن warrantyValue هنگام تغییر warrantyType
                this.$watch('warrantyType', (newValue, oldValue) => {
                    if (oldValue && newValue !== oldValue) {
                        $wire.warrantyValue = null;
                    }
                });
            }
        }">
            <div class="p-6">

                {{-- ─── Section 1: اطلاعات عرضه ─── --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6 mb-8">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">اطلاعات عرضه</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            شناسه محصول و وضعیت فروش شما برای این کالا.
                        </p>
                    </div>

                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="sku" value="شناسه انحصاری شما (SKU)" />
                            <x-text-input wire:model="sku" id="sku" type="text" class="mt-1 block w-full" placeholder="مثلا: PROD-12345" />
                            <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                        </div>
</content>

                        <div class="sm:col-span-2">
                            <label for="is_active" class="flex items-center cursor-pointer">
                                <x-checkbox wire:model="is_active" id="is_active" />
                                <span class="ms-3 text-sm text-gray-600 dark:text-gray-300">این محصول را برای فروش فعال کن</span>
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-7">
                                در صورت فعال بودن، قیمت‌های شما به مشتریان نمایش داده می‌شود.
                            </p>
                        </div>
                    </div>
                </div>

                @if(!empty($quantityTiers))
                <hr class="my-8 border-gray-200 dark:border-gray-700">

                {{-- ─── Section 1.5: هزینه گارانتی سراسری ─── --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6 mb-8">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <x-heroicon-o-shield-check class="w-5 h-5 text-purple-600"/>
                            تنظیمات گارانتی
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            تعیین نوع، مدت و هزینه گارانتی محصول.
                        </p>
                    </div>

                    <div class="md:col-span-2 space-y-6">
                        {{-- نکته مهم --}}
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <x-heroicon-o-light-bulb class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5"/>
                                <div class="text-sm">
                                    <p class="font-semibold text-amber-800 dark:text-amber-200">💡 توضیح مهم:</p>
                                    <ul class="text-amber-700 dark:text-amber-300 mt-2 space-y-1 list-disc list-inside">
                                        <li><strong>بدون گارانتی:</strong> محصول هیچ گارانتی ندارد</li>
                                        <li><strong>با گارانتی:</strong> محصول گارانتی دارد و به صورت <strong>آپشن اضافی</strong> به مشتری نمایش داده می‌شود</li>
                                        <li><strong>هزینه آپشن:</strong> مشتری می‌تواند محصول را با یا بدون گارانتی خریداری کند</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- آیا محصول با گارانتی است؟ --}}
                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border-2 border-gray-300 dark:border-gray-700">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="hasWarranty" class="rounded" x-model="hasWarranty">
                                <span class="ms-3 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    ✅ این محصول با گارانتی عرضه می‌شود
                                </span>
                            </label>
                        </div>

                        {{-- اطلاعات گارانتی (فقط اگر hasWarranty = true) --}}
                        <div x-show="hasWarranty" x-cloak class="space-y-4 bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                            {{-- ارائه‌دهنده گارانتی --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    ارائه‌دهنده گارانتی:
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyProvider === 'none' ? 'border-gray-400 bg-gray-100 dark:bg-gray-800' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyProvider" value="none" class="text-purple-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">نامشخص</span>
                                    </label>
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyProvider === 'manufacturer' ? 'border-purple-500 bg-purple-100 dark:bg-purple-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyProvider" value="manufacturer" class="text-purple-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">شرکتی</span>
                                    </label>
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyProvider === 'seller' ? 'border-purple-500 bg-purple-100 dark:bg-purple-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyProvider" value="seller" class="text-purple-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">فروشنده</span>
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('warrantyProvider')" class="mt-2" />
                            </div>

                            {{-- نوع پوشش گارانتی --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    نوع پوشش گارانتی:
                                </label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyCoverageType === 'full' ? 'border-purple-500 bg-purple-100 dark:bg-purple-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyCoverageType" value="full" class="text-purple-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">کامل</span>
                                    </label>
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyCoverageType === 'limited' ? 'border-purple-500 bg-purple-100 dark:bg-purple-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyCoverageType" value="limited" class="text-purple-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">محدود</span>
                                    </label>
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyCoverageType === 'parts_only' ? 'border-purple-500 bg-purple-100 dark:bg-purple-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyCoverageType" value="parts_only" class="text-purple-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">فقط قطعات</span>
                                    </label>
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyCoverageType === 'service_only' ? 'border-purple-500 bg-purple-100 dark:bg-purple-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyCoverageType" value="service_only" class="text-purple-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">فقط خدمات</span>
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('warrantyCoverageType')" class="mt-2" />
                            </div>

                            {{-- ✅ نوع گارانتی (تست، مدت‌دار، مادام‌العمر) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    نوع گارانتی:
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyTypeCoverage === 'test' ? 'border-orange-500 bg-orange-100 dark:bg-orange-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyTypeCoverage" value="test" class="text-orange-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">تست</span>
                                    </label>
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyTypeCoverage === 'limited' ? 'border-blue-500 bg-blue-100 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyTypeCoverage" value="limited" class="text-blue-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">مدت‌دار</span>
                                    </label>
                                    <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                                           :class="$wire.warrantyTypeCoverage === 'lifetime' ? 'border-green-500 bg-green-100 dark:bg-green-900/30' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" wire:model="warrantyTypeCoverage" value="lifetime" class="text-green-600">
                                        <span class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-100">مادام‌العمر</span>
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    💡 <strong>تست:</strong> گارانتی آزمایشی کوتاه‌مدت | <strong>مدت‌دار:</strong> گارانتی با زمان مشخص | <strong>مادام‌العمر:</strong> گارانتی بدون محدودیت زمانی
                                </p>
                                <x-input-error :messages="$errors->get('warrantyTypeCoverage')" class="mt-2" />
                            </div>

                            {{-- مدت گارانتی --}}
                            <div x-data="{ warrantyTypeCoverage: @entangle('warrantyTypeCoverage').live }">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    مدت گارانتی (برای تست و مدت‌دار):
                                </label>
                                <div class="flex items-center gap-3">
                                    <input type="number" 
                                           wire:model="warrantyDurationDays" 
                                           placeholder="مثلاً 365" 
                                           min="1" 
                                           max="7300"
                                           :disabled="warrantyTypeCoverage === 'lifetime'"
                                           :class="warrantyTypeCoverage === 'lifetime' ? 'opacity-50 cursor-not-allowed' : ''"
                                           class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md"
                                           onwheel="this.blur()">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">روز</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400" x-show="$wire.warrantyDurationDays && warrantyTypeCoverage !== 'lifetime'">
                                        ≈ <span x-text="Math.round($wire.warrantyDurationDays / 30)"></span> ماه
                                        (<span x-text="($wire.warrantyDurationDays / 365).toFixed(1)"></span> سال)
                                    </span>
                                    <span class="text-xs text-orange-600 dark:text-orange-400 font-medium" x-show="warrantyTypeCoverage === 'lifetime'">
                                        برای مادام‌العمر نیاز به تعیین مدت نیست
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    💡 برای <strong>گارانتی تست</strong> مدت کوتاه (مثلاً 7 روز) و برای <strong>مدت‌دار</strong> مدت بلند (مثلاً 365 روز = 1 سال) تعیین کنید.
                                </p>
                                <x-input-error :messages="$errors->get('warrantyDurationDays')" class="mt-2" />
                            </div>
                        </div>

                        {{-- هزینه گارانتی (فقط اگر hasWarranty = true) --}}
                        <div x-show="hasWarranty" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                💰 هزینه آپشن گارانتی (به عنوان انتخاب اضافی برای مشتری):
                            </label>
                            <div class="space-y-3">
                                {{-- بدون هزینه --}}
                                <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all"
                                       :class="warrantyType === 'none' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-300 dark:border-gray-700 hover:border-gray-400'">
                                    <input type="radio" wire:model.live="warrantyType" value="none" class="mt-1" x-model="warrantyType">
                                    <div class="ms-3 flex-1">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">بدون هزینه اضافه</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">گارانتی بدون هزینه اضافی (قیمت‌ها بدون تغییر)</div>
                                    </div>
                                </label>

                                {{-- درصد ثابت --}}
                                <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all"
                                       :class="warrantyType === 'percentage' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-300 dark:border-gray-700 hover:border-gray-400'">
                                    <input type="radio" wire:model.live="warrantyType" value="percentage" class="mt-1" x-model="warrantyType">
                                    <div class="ms-3 flex-1">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">درصد ثابت برای همه</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">درصدی به تمام قیمت‌های پایه اضافه می‌شود</div>
                                        <template x-if="warrantyType === 'percentage'">
                                            <div class="flex items-center gap-2">
                                                <input type="number" 
                                                       wire:model="warrantyValue" 
                                                       step="0.01" 
                                                       min="0" 
                                                       max="100" 
                                                       placeholder="5"
                                                       class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md text-sm"
                                                       onwheel="this.blur()">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">% اضافه بر قیمت پایه</span>
                                            </div>
                                        </template>
                                        <div x-show="warrantyType === 'percentage' && $wire.warrantyValue" x-cloak class="mt-2 text-xs text-green-600 dark:text-green-400">
                                            💬 مثال: قیمت 100,000 تومان → با گارانتی <span x-text="(100000 * (1 + ($wire.warrantyValue || 0) / 100)).toLocaleString('fa-IR')"></span> تومان
                                        </div>
                                    </div>
                                </label>

                                {{-- مبلغ ثابت --}}
                                <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all"
                                       :class="warrantyType === 'fixed' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-700 hover:border-gray-400'">
                                    <input type="radio" wire:model.live="warrantyType" value="fixed" class="mt-1" x-model="warrantyType">
                                    <div class="ms-3 flex-1">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">مبلغ ثابت برای همه</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">مبلغ مشخصی به تمام قیمت‌ها اضافه می‌شود</div>
                                        <template x-if="warrantyType === 'fixed'">
                                            <div class="flex items-center gap-2">
                                                <input type="number" 
                                                       wire:model="warrantyValue" 
                                                       step="1000" 
                                                       min="0" 
                                                       placeholder="10000"
                                                       class="w-40 px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md text-sm"
                                                       onwheel="this.blur()">
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">تومان به همه قیمت‌ها اضافه شود</span>
                                            </div>
                                        </template>
                                        <div x-show="warrantyType === 'fixed' && $wire.warrantyValue" x-cloak class="mt-2 text-xs text-blue-600 dark:text-blue-400">
                                            💬 مثال: <span x-text="($wire.warrantyValue || 0).toLocaleString('fa-IR')"></span> تومان به تمام قیمت‌ها اضافه می‌شود
                                        </div>
                                    </div>
                                </label>

                                {{-- سفارشی --}}
                                <label class="flex items-start p-4 border-2 rounded-lg cursor-pointer transition-all"
                                       :class="warrantyType === 'custom' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-gray-300 dark:border-gray-700 hover:border-gray-400'">
                                    <input type="radio" wire:model.live="warrantyType" value="custom" class="mt-1" x-model="warrantyType">
                                    <div class="ms-3 flex-1">
                                        <div class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                            سفارشی (پیشرفته)
                                            <span class="text-xs bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 px-2 py-0.5 rounded-full">Advanced</span>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">برای هر تیراژ/نوع قیمت جداگانه در ماتریس زیر تعیین کنید</div>
                                    </div>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('warrantyType')" class="mt-2" />
                            <x-input-error :messages="$errors->get('warrantyValue')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <hr class="my-8 border-gray-200 dark:border-gray-700">

                {{-- ─── Section 1.7: محاسبه خودکار تیراژها ─── --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6 mb-8">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <x-heroicon-o-calculator class="w-5 h-5 text-indigo-600"/>
                            محاسبه خودکار تیراژها
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            تعیین درصد تغییر هر تیراژ نسبت به تیراژ مرجع برای محاسبه خودکار قیمت‌ها.
                        </p>
                    </div>

                    <div class="md:col-span-2 space-y-6">
                        {{-- نکته آموزشی --}}
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <x-heroicon-o-light-bulb class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"/>
                                <div class="text-sm">
                                    <p class="font-semibold text-blue-900 dark:text-blue-100 mb-2">💡 چگونه کار می‌کند؟</p>
                                    <ul class="text-blue-800 dark:text-blue-200 space-y-1.5 list-disc list-inside">
                                        <li><strong>تیراژ مرجع:</strong> کارتن (تیراژ 1) - پایه محاسبات</li>
                                        <li>برای سایر تیراژها <strong>درصد افزایش یا کاهش</strong> تعیین کنید</li>
                                        <li>با تغییر <strong>قیمت نقدی کارتن</strong>، سایر تیراژها <strong>خودکار محاسبه</strong> می‌شوند</li>
                                        <li><strong class="text-red-600 dark:text-red-400">مهم:</strong> قیمت نقدی سایر تیراژها <strong>قفل</strong> می‌شود</li>
                                        <li>قیمت‌های <strong>نسیه</strong> (چک) همیشه قابل ویرایش هستند</li>
                                        <li>می‌توانید محاسبه خودکار را <strong>غیرفعال</strong> کنید و قیمت‌ها را <strong>دستی</strong> وارد کنید</li>
                                    </ul>
                                    <p class="mt-2 text-xs text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 px-3 py-2 rounded">
                                        📌 <strong>توجه:</strong> هر محصول باید حداقل 3 و حداکثر 5 تیراژ داشته باشد (تنظیم شده توسط ادمین)
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- فعال/غیرفعال کردن محاسبه خودکار --}}
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border-2" :class="$wire.autoCalculateTiers ? 'border-green-400 dark:border-green-600' : 'border-gray-300 dark:border-gray-700'">
                            <label class="flex items-center cursor-pointer group">
                                <input type="checkbox" 
                                       wire:model.live="autoCalculateTiers" 
                                       class="w-6 h-6 rounded text-green-600 focus:ring-2 focus:ring-green-500 transition-all">
                                <div class="mr-3 flex-1">
                                    <span class="text-base font-semibold text-gray-900 dark:text-gray-100 group-hover:text-green-600 dark:group-hover:text-green-400 transition-colors">
                                        ✨ فعال‌سازی محاسبه خودکار تیراژها
                                    </span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        وقتی فعال باشد، با تغییر قیمت تیراژ مرجع، سایر تیراژها خودکار محاسبه می‌شوند
                                    </p>
                                </div>
                            </label>
                        </div>

                        {{-- ✅ فعال/غیرفعال تیراژها (بدون محاسبه خودکار) --}}
                        <div x-show="!$wire.autoCalculateTiers" 
                             x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95"
                             x-transition:enter-end="opacity-100 transform scale-100"
                             class="space-y-4 bg-gradient-to-br from-gray-50 to-slate-50 dark:from-gray-900/20 dark:to-slate-900/20 p-5 rounded-lg border border-gray-200 dark:border-gray-700">
                            
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                                <x-heroicon-s-cog class="w-4 h-4 text-gray-600"/>
                                انتخاب تیراژهای فعال:
                            </h4>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">
                                فقط تیراژهای فعال شده در ماتریس قیمت نمایش داده می‌شوند.
                            </p>

                            <div class="grid grid-cols-1 gap-3">
                                @foreach($quantityTiers as $tierIndex => $tier)
                                    <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                                        {{-- Toggle --}}
                                        <div class="flex-shrink-0">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" 
                                                       wire:model.live="tierEnabled.{{ $tierIndex }}"
                                                       class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                            </label>
                                        </div>

                                        {{-- اطلاعات تیراژ --}}
                                        <div class="flex-1">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $tier['name'] ?? 'نامشخص' }}
                                            </span>
                                            @if(isset($tier['min_quantity']))
                                                <span class="text-xs text-gray-500 dark:text-gray-400 block">
                                                    حداقل {{ number_format($tier['min_quantity']) }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Badge وضعیت --}}
                                        <div class="flex-shrink-0">
                                            @if($tierEnabled[$tierIndex] ?? true)
                                                <span class="text-xs bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 px-2 py-1 rounded-full font-medium">فعال</span>
                                            @else
                                                <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-1 rounded-full font-medium">غیرفعال</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- تنظیمات محاسبه خودکار (فقط وقتی فعال است) --}}
                        <div x-show="$wire.autoCalculateTiers" 
                             x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95"
                             x-transition:enter-end="opacity-100 transform scale-100"
                             class="space-y-5 bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 p-5 rounded-lg border border-indigo-200 dark:border-indigo-800">
                            
                            {{-- نمایش تیراژ مرجع (ثابت = کارتن) --}}
                            <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-300 dark:border-green-700 rounded-lg p-4">
                                <div class="flex items-center gap-3">
                                    <x-heroicon-s-bookmark class="w-6 h-6 text-green-600 dark:text-green-400"/>
                                    <div>
                                        <p class="text-sm font-bold text-green-900 dark:text-green-100">
                                            تیراژ مرجع: کارتن (تیراژ 2)
                                        </p>
                                        <p class="text-xs text-green-700 dark:text-green-300 mt-1">
                                            قیمت نقدی این تیراژ پایه محاسبات است و سایر تیراژها بر اساس آن محاسبه می‌شوند.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- درصدهای هر تیراژ --}}
                            <div class="space-y-3">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                                    <x-heroicon-s-adjustments-horizontal class="w-4 h-4 text-purple-600"/>
                                    درصد تغییر نسبت به کارتن (تیراژ مرجع):
                                </h4>
                                
                                {{-- راهنمای تنظیمات --}}
                                <div class="mb-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <x-heroicon-o-light-bulb class="w-5 h-5 text-purple-600 dark:text-purple-400 flex-shrink-0 mt-0.5"/>
                                        <div class="flex-1 text-sm">
                                            <p class="font-semibold text-purple-900 dark:text-purple-100 mb-2">
                                                💡 نحوه استفاده از درصدهای تیراژ
                                            </p>
                                            <div class="text-purple-800 dark:text-purple-200 space-y-1 text-xs">
                                                <p>
                                                    <strong>پیشفرض:</strong> استفاده از درصدهای تعیین شده در تنظیمات (برای همه محصولات)
                                                </p>
                                                <p>
                                                    <strong>درصد اختصاصی:</strong> تعیین درصد متفاوت فقط برای این محصول
                                                </p>
                                            </div>
                                            <div class="mt-3">
                                                <a href="{{ route('supplier.settings.tier-discounts') }}" 
                                                   class="inline-flex items-center gap-2 text-xs font-medium text-purple-700 dark:text-purple-300 hover:text-purple-900 dark:hover:text-purple-100 bg-white dark:bg-purple-900/40 px-3 py-1.5 rounded-md border border-purple-300 dark:border-purple-700 hover:border-purple-400 dark:hover:border-purple-600 transition-colors">
                                                    <x-heroicon-o-cog-6-tooth class="w-4 h-4"/>
                                                    تنظیم درصدهای پیشفرض
                                                    <x-heroicon-o-arrow-left class="w-3 h-3"/>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 gap-3">
                                    {{-- ✅ نمایش کارتن (تیراژ 1) اول --}}
                                    @if(isset($quantityTiers[1]))
                                        @php $tier = $quantityTiers[1]; $tierIndex = 1; @endphp
                                        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border-2 border-green-400 dark:border-green-600">
                                            <div class="flex items-center gap-3">
                                                {{-- عنوان تیراژ --}}
                                                <div class="flex-shrink-0 w-32">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $tier['name'] ?? 'نامشخص' }}
                                                    </span>
                                                    @if(isset($tier['min_quantity']))
                                                        <span class="text-xs text-gray-500 dark:text-gray-400 block">
                                                            حداقل {{ number_format($tier['min_quantity']) }}
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- کارتن = تیراژ مرجع --}}
                                                <div class="flex-1 flex items-center gap-2 px-4 py-2 bg-green-100 dark:bg-green-900/40 rounded-md border border-green-300 dark:border-green-700">
                                                    <x-heroicon-s-bookmark class="w-5 h-5 text-green-600 dark:text-green-400"/>
                                                    <span class="text-sm font-bold text-green-900 dark:text-green-100">
                                                        تیراژ مرجع (0%)
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    {{-- ✅ سپس سایر تیراژها با Radio Buttons --}}
                                    @foreach($quantityTiers as $tierIndex => $tier)
                                        @if($tierIndex != 1)
                                        @php
                                            $mode = $tierPriceMode[$tierIndex] ?? 'default';
                                            
                                            // دریافت درصد مؤثر با اولویت‌بندی
                                            $effectiveData = $this->getEffectiveTierPercentageForUI($tierIndex);
                                            $defaultPercentage = $effectiveData['percentage'];
                                            $percentageSource = $effectiveData['source'];
                                            $sourceLabel = $percentageSource === 'category' ? 'از تنظیمات دسته‌بندی' : 'از تنظیمات عمومی';
                                            $isEnabled = $tierEnabled[$tierIndex] ?? true;
                                        @endphp
                                        <div class="p-4 bg-white dark:bg-gray-800/50 rounded-lg border" 
                                             :class="{ 'border-gray-200 dark:border-gray-700': {{ $isEnabled ? 'true' : 'false' }}, 'border-gray-300 dark:border-gray-600 opacity-60': {{ $isEnabled ? 'false' : 'true' }} }">
                                            {{-- عنوان تیراژ --}}
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="flex items-center gap-3 flex-1">
                                                    {{-- Toggle فعال/غیرفعال --}}
                                                    <div class="flex-shrink-0">
                                                        <label class="relative inline-flex items-center cursor-pointer">
                                                            <input type="checkbox" 
                                                                   wire:model.live="tierEnabled.{{ $tierIndex }}"
                                                                   class="sr-only peer">
                                                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-green-600"></div>
                                                        </label>
                                                    </div>
                                                    
                                                    {{-- اطلاعات تیراژ --}}
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                                {{ $tier['name'] ?? 'نامشخص' }}
                                                            </span>
                                                            @if($isEnabled)
                                                                <span class="text-xs bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 px-2 py-0.5 rounded-full font-medium">فعال</span>
                                                            @else
                                                                <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full font-medium">غیرفعال</span>
                                                            @endif
                                                        </div>
                                                        @if(isset($tier['min_quantity']))
                                                            <span class="text-xs text-gray-500 dark:text-gray-400 block mt-0.5">
                                                                حداقل {{ number_format($tier['min_quantity']) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            {{-- محتوای تیراژ (فقط اگر فعال باشد) --}}
                                            @if($isEnabled)
                                            {{-- Radio Buttons --}}
                                            <div class="space-y-3">
                                                <div class="flex items-center gap-4">
                                                    <label class="flex items-center cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 p-2 rounded transition-colors">
                                                        <input type="radio" 
                                                               wire:model.live="tierPriceMode.{{ $tierIndex }}" 
                                                               value="default"
                                                               class="w-4 h-4 text-blue-600">
                                                        <span class="mr-2 text-xs text-gray-700 dark:text-gray-300">پیشفرض</span>
                                                    </label>
                                                    <label class="flex items-center cursor-pointer hover:bg-orange-50 dark:hover:bg-orange-900/20 p-2 rounded transition-colors">
                                                        <input type="radio" 
                                                               wire:model.live="tierPriceMode.{{ $tierIndex }}" 
                                                               value="percentage"
                                                               class="w-4 h-4 text-orange-600">
                                                        <span class="mr-2 text-xs text-gray-700 dark:text-gray-300">درصد اختصاصی</span>
                                                    </label>
                                                </div>
                                                
                                                {{-- نمایش بر اساس mode --}}
                                                @if($mode === 'default')
                                                    {{-- نمایش درصد پیشفرض --}}
                                                    <div class="bg-blue-100 dark:bg-blue-900/40 rounded-lg p-3 border border-blue-200 dark:border-blue-800">
                                                        <div class="text-xs text-blue-600 dark:text-blue-400 mb-1">درصد پیشفرض:</div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-lg font-bold text-blue-900 dark:text-blue-100">
                                                                {{ number_format($defaultPercentage, 1) }}%
                                                            </span>
                                                            <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-1 rounded">{{ $sourceLabel }}</span>
                                                        </div>
                                                    </div>
                                                @elseif($mode === 'percentage')
                                                    {{-- Input درصد اختصاصی --}}
                                                    <div class="space-y-3">
                                                        <div class="flex items-center gap-2">
                                                            <input type="number"
                                                                   wire:model.live.debounce.1500ms.blur="tierPercentages.{{ $tierIndex }}"
                                                                   placeholder="درصد (مثال: 5)"
                                                                   step="0.01"
                                                                   inputmode="decimal"
                                                                   onwheel="this.blur()"
                                                                   class="flex-1 max-w-xs px-4 py-2 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900 focus:border-orange-500 focus:ring-orange-500 rounded-md">
                                                            <span class="text-xs text-gray-600 dark:text-gray-400">% تغییر</span>
                                                        </div>
                                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                                            <span class="font-medium text-red-600">مثبت:</span> گران‌تر از مرجع | 
                                                            <span class="font-medium text-green-600">منفی:</span> ارزان‌تر از مرجع
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            @else
                                            {{-- پیام غیرفعال --}}
                                            <div class="text-center py-4">
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    این تیراژ غیرفعال است و در محاسبات لحاظ نمی‌شود.
                                                </p>
                                            </div>
                                            @endif
                                        </div>
                                        <x-input-error :messages="$errors->get('tierPercentages.' . $tierIndex)" class="mt-1" />
                                        @endif
                                    @endforeach
                                </div>

                                {{-- نمونه محاسبه --}}
                                <div class="mt-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <x-heroicon-o-sparkles class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5"/>
                                        <div class="text-sm">
                                            <p class="font-semibold text-amber-900 dark:text-amber-100 mb-2">
                                                📊 نمونه محاسبه (فرض: قیمت مرجع = 100,000 تومان):
                                            </p>
                                            <div class="space-y-1 text-amber-800 dark:text-amber-200 text-xs">
                                                @foreach($quantityTiers as $tierIndex => $tier)
                                                    @php
                                                        $isEnabled = $tierEnabled[$tierIndex] ?? true;
                                                        $mode = $tierPriceMode[$tierIndex] ?? 'default';
                                                        $percentage = (float)($tierPercentages[$tierIndex] ?? 0);
                                                        
                                                        if ($mode === 'default') {
                                                            $percentage = $defaultPercentage;
                                                        }
                                                        
                                                        if ($tierIndex == 1) {
                                                            $percentage = 0;
                                                        }
                                                        
                                                        $examplePrice = 100000 * (1 + $percentage / 100);
                                                    @endphp
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-mono">•</span>
                                                        <strong>{{ $tier['name'] }}</strong>: 
                                                        @if(!$isEnabled)
                                                            <span class="text-gray-500 dark:text-gray-500 line-through">(غیرفعال)</span>
                                                        @elseif($tierIndex == 1)
                                                            <span class="text-gray-600 dark:text-gray-400">100,000 تومان (مرجع)</span>
                                                        @elseif($percentage > 0)
                                                            <span class="text-red-600 dark:text-red-400">+{{ number_format($percentage, 1) }}%</span> = 
                                                            <span class="font-semibold">{{ number_format($examplePrice) }} تومان</span>
                                                            <span class="text-red-500">(گران‌تر)</span>
                                                        @elseif($percentage < 0)
                                                            <span class="text-green-600 dark:text-green-400">{{ number_format($percentage, 1) }}%</span> = 
                                                            <span class="font-semibold">{{ number_format($examplePrice) }} تومان</span>
                                                            <span class="text-green-500">(ارزان‌تر)</span>
                                                        @else
                                                            <span class="text-gray-600 dark:text-gray-400">0%</span> = 100,000 تومان
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- دکمه محاسبه --}}
                                <div class="flex items-center justify-between pt-3 border-t border-indigo-200 dark:border-indigo-800">
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        محاسبه خودکار با تغییر قیمت تیراژ مرجع انجام می‌شود
                                    </p>
                                    <button type="button"
                                            wire:click="calculateTierPrices"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                                        <x-heroicon-o-arrow-path class="w-4 h-4"/>
                                        محاسبه مجدد همه تیراژها
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-8 border-gray-200 dark:border-gray-700">

                {{-- ─── Section 2: ماتریس قیمت ─── --}}
                <div>
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <x-heroicon-o-table-cells class="w-5 h-5 text-indigo-600"/>
                            ماتریس قیمت (تیراژ × نوع قیمت)
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            قیمت‌ها را بر اساس تیراژ و نوع پرداخت وارد کنید. برای فعال‌سازی قیمت چک، چک‌باکس مربوطه را علامت بزنید.
                        </p>
                        
                        {{-- راهنمای دوره‌های چکی --}}
                        <div class="mt-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"/>
                                <div class="flex-1 text-sm">
                                    <p class="font-semibold text-blue-900 dark:text-blue-100 mb-2">
                                        📋 مدیریت دوره‌های چکی
                                    </p>
                                    <p class="text-blue-800 dark:text-blue-200 mb-2">
                                        می‌توانید دوره‌های چکی (30، 60، 90 روزه و...) و درصد افزایش هر دوره را در بخش تنظیمات مشخص کنید.
                                    </p>
                                    <a href="{{ route('supplier.settings.check-periods') }}" 
                                       class="inline-flex items-center gap-2 text-xs font-medium text-blue-700 dark:text-blue-300 hover:text-blue-900 dark:hover:text-blue-100 bg-white dark:bg-blue-900/40 px-3 py-1.5 rounded-md border border-blue-300 dark:border-blue-700 hover:border-blue-400 dark:hover:border-blue-600 transition-colors">
                                        <x-heroicon-o-calendar-days class="w-4 h-4"/>
                                        رفتن به تنظیمات دوره‌های چکی
                                        <x-heroicon-o-arrow-left class="w-3 h-3"/>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 📘 Info Box: توضیح تیراژها --}}
                    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border-r-4 border-blue-500 rounded-lg p-5 shadow-sm">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 mt-0.5">
                                <x-heroicon-s-information-circle class="w-6 h-6 text-blue-600 dark:text-blue-400"/>
                            </div>
                            <div class="flex-1 space-y-3">
                                <h4 class="text-sm font-bold text-blue-900 dark:text-blue-100">
                                    💡 تیراژ چیست و چرا اهمیت دارد؟
                                </h4>
                                <p class="text-sm text-blue-800 dark:text-blue-200 leading-relaxed">
                                    تیراژ به <strong>تعداد کل محصول</strong> (نه واحد بسته‌بندی) اشاره دارد که خریدار قصد خرید آن را دارد. 
                                    قیمت هر واحد معمولاً با افزایش تیراژ کاهش می‌یابد.
                                </p>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 space-y-2">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        🎯 نقش تیراژها در این سیستم:
                                    </p>
                                    <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-2 mr-4">
                                        <li class="flex items-start gap-2">
                                            <span class="text-blue-500 mt-0.5">•</span>
                                            <span><strong>تیراژ 1، تیراژ 2، تیراژ 3...</strong> نقاط مرجع برای مقایسه قیمت‌های شما با رقبا هستند</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-blue-500 mt-0.5">•</span>
                                            <span>خریداران می‌توانند قیمت‌ها را در این نقاط با سایر تأمین‌کنندگان مقایسه کنند</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-blue-500 mt-0.5">•</span>
                                            <span><strong class="text-amber-600 dark:text-amber-400">برای تعداد دقیق خارج از این نقاط، خریدار مستقیماً با شما تماس می‌گیرد</strong></span>
                                        </li>
                                    </ul>
                                </div>
                                
                                {{-- مثال عملی برای تأمین‌کننده --}}
                                @if($product->packaging_unit_type && $product->quantity_per_packaging)
                                    <div class="bg-gradient-to-l from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg p-3 border border-green-200 dark:border-green-700">
                                        <p class="text-xs font-semibold text-green-900 dark:text-green-100 mb-2 flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                            </svg>
                                            💡 مثال عملی برای این محصول:
                                        </p>
                                        <div class="bg-white dark:bg-gray-800 rounded p-2.5 space-y-2">
                                            <div class="text-xs text-gray-800 dark:text-gray-200 space-y-1.5">
                                                <div class="flex items-start gap-2">
                                                    <span class="text-green-600 dark:text-green-400 font-bold">1.</span>
                                                    <span>واحد بسته‌بندی: <strong class="text-green-900 dark:text-green-100">{{ $product->packaging_unit_label }}</strong></span>
                                                </div>
                                                <div class="flex items-start gap-2">
                                                    <span class="text-green-600 dark:text-green-400 font-bold">2.</span>
                                                    <span>هر {{ $product->packaging_unit_label }} = <strong class="text-green-900 dark:text-green-100">{{ number_format($product->quantity_per_packaging) }} عدد</strong></span>
                                                </div>
                                                <div class="flex items-start gap-2">
                                                    <span class="text-green-600 dark:text-green-400 font-bold">3.</span>
                                                    <span>اگر خریدار <strong>10 {{ $product->packaging_unit_label }}</strong> بخواهد = <strong class="text-green-900 dark:text-green-100">{{ number_format($product->quantity_per_packaging * 10) }} عدد</strong></span>
                                                </div>
                                                <div class="flex items-start gap-2 pt-1.5 border-t border-green-200 dark:border-green-700">
                                                    <span class="text-amber-600 dark:text-amber-400">→</span>
                                                    <span class="font-medium text-amber-800 dark:text-amber-300">
                                                        شما قیمت را بر اساس <strong class="underline">{{ number_format($product->quantity_per_packaging * 10) }} عدد</strong> وارد می‌کنید (نه 10 {{ $product->packaging_unit_label }})
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                
                                @if($product->packaging_unit_type && $product->quantity_per_packaging)
                                    <div class="bg-gradient-to-l from-purple-50 to-blue-50 dark:from-purple-900/20 dark:to-blue-900/20 rounded-lg p-3 border border-purple-200 dark:border-purple-700">
                                        <p class="text-xs text-purple-900 dark:text-purple-100">
                                            <strong>📦 واحد بسته‌بندی این محصول:</strong> 
                                            {{ $product->packaging_unit_label }} 
                                            (هر {{ $product->packaging_unit_label }} شامل {{ number_format($product->quantity_per_packaging) }} عدد است)
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ماتریس قیمت - Desktop/Tablet: Table، Mobile: Accordion --}}
                    <div class="space-y-8">
                        @php
                            $cashPriceType = $priceTypes->firstWhere('code', 'cash');
                            $checkPriceType = $priceTypes->firstWhere('code', 'check');
                            $warrantyPriceType = $priceTypes->firstWhere('code', 'warranty');
                            
                            // ✅ ترتیب نمایش: کارتن (0) اول، سپس بقیه
                            $orderedTiers = collect($quantityTiers);
                            if (isset($quantityTiers[0])) {
                                $carton = collect([0 => $quantityTiers[0]]);
                                $others = $orderedTiers->except(0);
                                $orderedTiers = $carton->merge($others);
                            }
                        @endphp
                        
                        @foreach($orderedTiers as $tierIndex => $tier)
                            {{-- ✅ فقط تیراژهای فعال را نمایش بده --}}
                            @if($tierEnabled[$tierIndex] ?? true)
                            <x-price-tier-table
                                :tier="$tier"
                                :tierIndex="$tierIndex"
                                :quantityTiers="$quantityTiers"
                                :priceTypes="$priceTypes"
                                :checkPeriods="$checkPeriods"
                                :cashPriceType="$cashPriceType"
                                :checkPriceType="$checkPriceType"
                                :warrantyPriceType="$warrantyPriceType"
                                :hasWarranty="$hasWarranty"
                                :warrantyType="$warrantyType"
                                :checkPeriodEnabled="$checkPeriodEnabled"
                                :checkPriceMode="$checkPriceMode"
                                :checkPercentageOverride="$checkPercentageOverride"
                                :effectivePercentages="$effectivePercentages"
                                :priceMatrix="$priceMatrix">
                                
                                {{-- Desktop Table View - نوع قیمت‌ها در ردیف --}}
                                <div class="overflow-x-auto">
                                    <table class="w-full border-collapse">
                                        {{-- Table Header: عنوان ستون‌ها --}}
                                        <thead>
                                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                                <th class="sticky right-0 z-10 px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100 border-b-2 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 min-w-[180px]">
                                                    نوع قیمت
                                                </th>
                                                <th class="px-6 py-3 text-center text-sm font-semibold text-gray-900 dark:text-gray-100 border-b-2 border-gray-300 dark:border-gray-600">
                                                    قیمت (تومان)
                                                </th>
                                            </tr>
                                        </thead>
                                        
                                        {{-- Table Body: ردیف نقدی --}}
                                        <tbody>
                                            <tr class="hover:bg-green-50 dark:hover:bg-green-900/10 transition-colors bg-green-50/30 dark:bg-green-900/5">
                                                <td class="sticky right-0 z-10 px-4 py-4 border border-gray-200 dark:border-gray-700 bg-green-50 dark:bg-green-900/20 font-medium">
                                                    <div class="flex items-center gap-2">
                                                        <x-heroicon-s-banknotes class="w-5 h-5 text-green-600 dark:text-green-400"/>
                                                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">نقدی</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 border border-gray-200 dark:border-gray-700">
                                                    @php
                                                        // ✅ قیمت نقدی فقط برای تیراژ مرجع قابل ویرایش است (در حالت auto)
                                                        $isCashDisabled = $autoCalculateTiers && $tierIndex !== $referenceTierIndex;
                                                    @endphp
                                                    
                                                    <div class="relative max-w-xs">
                                                        <input type="number"
                                                               wire:model.live.debounce.1500ms.blur="priceMatrix.{{ $tierIndex }}.{{ $cashPriceType->id }}.price"
                                                               placeholder="قیمت نقدی"
                                                               inputmode="numeric"
                                                               onwheel="this.blur()"
                                                               @if($isCashDisabled) disabled readonly @endif
                                                               class="block w-full py-2.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 focus:border-green-500 focus:ring-green-500 rounded-md shadow-sm @if($isCashDisabled) opacity-60 cursor-not-allowed bg-gray-100 dark:bg-gray-800 pr-10 pl-16 @else px-4 pl-16 @endif">
                                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">تومان</span>
                                                        </div>
                                                        @if($isCashDisabled)
                                                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                                <x-heroicon-s-lock-closed class="w-4 h-4 text-gray-400"/>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    @if($isCashDisabled)
                                                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1.5 flex items-center gap-1">
                                                            <x-heroicon-o-information-circle class="w-3.5 h-3.5"/>
                                                            محاسبه خودکار از تیراژ مرجع
                                                        </p>
                                                    @endif
                                                </td>
                                            </tr>
                                            
                                            {{-- ردیف‌های چک --}}
                                            @foreach($checkPeriods as $period)
                                                @php
                                                    $isEnabled = $checkPeriodEnabled[$tierIndex][$period->id] ?? false;
                                                    $mode = $checkPriceMode[$tierIndex][$period->id] ?? 'default';
                                                    $effectivePercentage = $effectivePercentages[$period->id] ?? null;
                                                    $calculatedPrice = $this->calculateCheckPrice($tierIndex, $period->id);
                                                @endphp
                                                
                                                <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors {{ !$isEnabled ? 'opacity-50 bg-gray-50 dark:bg-gray-800/50' : '' }}">
                                                    <td class="sticky right-0 z-10 px-4 py-4 border border-gray-200 dark:border-gray-700 {{ $isEnabled ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-100 dark:bg-gray-800' }}">
                                                        <div class="flex items-center gap-3">
                                                            {{-- ✅ Toggle فعال/غیرفعال --}}
                                                            <label class="relative inline-flex items-center cursor-pointer">
                                                                <input type="checkbox" 
                                                                       wire:model.live="checkPeriodEnabled.{{ $tierIndex }}.{{ $period->id }}"
                                                                       class="sr-only peer">
                                                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                                            </label>
                                                            
                                                            <div class="flex items-center gap-2">
                                                                <x-heroicon-s-document-text class="w-5 h-5 {{ $isEnabled ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400' }}"/>
                                                                <span class="text-sm font-semibold {{ $isEnabled ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500' }}">چک {{ $period->days }} روزه</span>
                                                            </div>
                                                            
                                                            {{-- Tooltip درصد پیش‌فرض --}}
                                                            @if($effectivePercentage && $isEnabled)
                                                                <div x-data="{ showTooltip: false }" class="relative">
                                                                    <button type="button" 
                                                                            @mouseenter="showTooltip = true" 
                                                                            @mouseleave="showTooltip = false"
                                                                            class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                                                        <x-heroicon-o-information-circle class="w-4 h-4"/>
                                                                    </button>
                                                                    <div x-show="showTooltip" 
                                                                         x-cloak
                                                                         class="absolute right-0 bottom-full mb-2 w-64 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg p-3 shadow-lg z-20">
                                                                        <div class="font-semibold mb-2 border-b border-gray-700 pb-1">سلسله مراتب درصد:</div>
                                                                        @if($effectivePercentage['source'] === 'category')
                                                                            <div class="space-y-1">
                                                                                <div class="flex items-center gap-2 text-green-400">
                                                                                    <x-heroicon-s-check-circle class="w-4 h-4"/>
                                                                                    <span>دسته‌بندی: {{ $effectivePercentage['details']['category_name'] ?? 'نامشخص' }}</span>
                                                                                </div>
                                                                                <div class="mr-6 text-green-300 font-bold">
                                                                                    {{ number_format($effectivePercentage['value'], 2) }}%
                                                                                </div>
                                                                            </div>
                                                                        @else
                                                                            <div class="space-y-1">
                                                                                <div class="flex items-center gap-2 text-blue-400">
                                                                                    <x-heroicon-s-arrow-path class="w-4 h-4"/>
                                                                                    <span>پیش‌فرض سیستمی</span>
                                                                                </div>
                                                                                <div class="mr-6 text-blue-300 font-bold">
                                                                                    {{ number_format($effectivePercentage['value'], 2) }}%
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                        <div class="absolute right-4 top-full w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    
                                                    <td class="px-6 py-4 border border-gray-200 dark:border-gray-700">
                                                        <div class="space-y-3">
                                                            {{-- انتخاب Mode --}}
                                                                <div class="flex items-center gap-4">
                                                                    <label class="flex items-center cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 p-2 rounded transition-colors">
                                                                        <input type="radio" 
                                                                               wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                               value="default"
                                                                               {{ !$isEnabled ? 'disabled' : '' }}
                                                                               class="w-4 h-4 text-blue-600 {{ !$isEnabled ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                                        <span class="mr-2 text-xs {{ $isEnabled ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400' }}">پیش‌فرض</span>
                                                                    </label>
                                                                    <label class="flex items-center cursor-pointer hover:bg-orange-50 dark:hover:bg-orange-900/20 p-2 rounded transition-colors">
                                                                        <input type="radio" 
                                                                               wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                               value="percentage"
                                                                               {{ !$isEnabled ? 'disabled' : '' }}
                                                                               class="w-4 h-4 text-orange-600 {{ !$isEnabled ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                                        <span class="mr-2 text-xs {{ $isEnabled ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400' }}">درصد اختصاصی</span>
                                                                    </label>
                                                                    <label class="flex items-center cursor-pointer hover:bg-purple-50 dark:hover:bg-purple-900/20 p-2 rounded transition-colors">
                                                                        <input type="radio" 
                                                                               wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                               value="manual"
                                                                               {{ !$isEnabled ? 'disabled' : '' }}
                                                                               class="w-4 h-4 text-purple-600 {{ !$isEnabled ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                                        <span class="mr-2 text-xs {{ $isEnabled ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400' }}">دستی</span>
                                                                    </label>
                                                                </div>
                                                                
                                                                {{-- Input بر اساس mode --}}
                                                                @if(!$isEnabled)
                                                                    <div class="text-sm text-gray-400 italic">
                                                                        برای تنظیم قیمت، ابتدا این دوره را فعال کنید
                                                                    </div>
                                                                @elseif($mode === 'default')
                                                                    <div class="flex items-center gap-3">
                                                                        <div class="flex-1 text-center p-3 bg-blue-100 dark:bg-blue-900/40 rounded-md border border-blue-200 dark:border-blue-800 max-w-xs">
                                                                            <div class="text-base font-bold text-blue-900 dark:text-blue-100">
                                                                                {{ $calculatedPrice ? number_format($calculatedPrice) : '—' }}
                                                                            </div>
                                                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                                                @if($effectivePercentage)
                                                                                    ({{ number_format($effectivePercentage['value'], 1) }}% افزایش)
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @elseif($mode === 'percentage')
                                                                    <div class="flex items-center gap-3">
                                                                        <div class="flex items-center gap-2">
                                                                            <input type="number"
                                                                                   wire:model.live.debounce.1500ms.blur="checkPercentageOverride.{{ $tierIndex }}.{{ $period->id }}"
                                                                                   placeholder="10"
                                                                                   step="0.01"
                                                                                   min="0"
                                                                                   max="100"
                                                                                   inputmode="decimal"
                                                                                   onwheel="this.blur()"
                                                                                   class="w-24 px-3 py-2 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900 focus:border-orange-500 focus:ring-orange-500 rounded-md">
                                                                            <span class="text-sm text-gray-600 dark:text-gray-400">% افزایش</span>
                                                                        </div>
                                                                        @if($calculatedPrice)
                                                                            <div class="text-sm font-semibold text-orange-600 dark:text-orange-400">
                                                                                = {{ number_format($calculatedPrice) }} تومان
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @elseif($mode === 'manual')
                                                                    <div class="relative max-w-xs">
                                                                        <input type="number"
                                                                               wire:model.defer="priceMatrix.{{ $tierIndex }}.{{ $checkPriceType->id }}.check_periods.{{ $period->id }}.price"
                                                                               placeholder="قیمت دستی"
                                                                               inputmode="numeric"
                                                                               onwheel="this.blur()"
                                                                               class="block w-full px-4 py-2 text-sm border-purple-300 dark:border-purple-600 dark:bg-gray-900 focus:border-purple-500 focus:ring-purple-500 rounded-md">
                                                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                                            <span class="text-xs text-gray-500">تومان</span>
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            
                                            {{-- ردیف گارانتی --}}
                                            @if($hasWarranty)
                                                <tr class="hover:bg-purple-50 dark:hover:bg-purple-900/10 transition-colors bg-purple-50/30 dark:bg-purple-900/5">
                                                    <td class="sticky right-0 z-10 px-4 py-4 border border-gray-200 dark:border-gray-700 bg-purple-50 dark:bg-purple-900/20 font-medium">
                                                        <div class="flex items-center gap-2">
                                                            <x-heroicon-s-shield-check class="w-5 h-5 text-purple-600 dark:text-purple-400"/>
                                                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">گارانتی</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 border border-gray-200 dark:border-gray-700">
                                                        @if($warrantyType === 'custom')
                                                            <div class="relative max-w-xs">
                                                                <input type="number"
                                                                       wire:model.defer="priceMatrix.{{ $tierIndex }}.{{ $warrantyPriceType->id }}.price"
                                                                       placeholder="هزینه گارانتی"
                                                                       inputmode="numeric"
                                                                       onwheel="this.blur()"
                                                                       class="block w-full px-4 py-2.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900 focus:border-purple-500 focus:ring-purple-500 rounded-md">
                                                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                                    <span class="text-xs text-gray-500">تومان</span>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="flex items-center gap-3">
                                                                <div class="px-4 py-2 bg-purple-100 dark:bg-purple-900/40 rounded-md border border-purple-200 dark:border-purple-800">
                                                                    <div class="text-sm font-semibold text-purple-900 dark:text-purple-100">
                                                                        @if($warrantyType === 'none')
                                                                            بدون هزینه اضافه
                                                                        @elseif($warrantyType === 'percentage')
                                                                            {{ $warrantyValue }}% اضافه بر قیمت نقدی
                                                                        @elseif($warrantyType === 'fixed')
                                                                            {{ number_format($warrantyValue) }} تومان اضافه
                                                                        @endif
                                                                    </div>
                                                                    <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">محاسبه خودکار</div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                                
                                {{-- Mobile Accordion View --}}
                                <x-slot name="mobileSlot">
                                    <div class="p-4 space-y-3">
                                        @php
                                            // ✅ قیمت نقدی فقط برای تیراژ مرجع قابل ویرایش است (در حالت auto)
                                            $isCashDisabled = $autoCalculateTiers && $tierIndex !== $referenceTierIndex;
                                        @endphp
                                        
                                        {{-- نقدی --}}
                                        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                            <label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                                                <x-heroicon-s-banknotes class="w-5 h-5 text-green-600 dark:text-green-400"/>
                                                قیمت نقدی
                                                @if($isCashDisabled)
                                                    <span class="text-xs bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300 px-2 py-0.5 rounded-full flex items-center gap-1">
                                                        <x-heroicon-s-lock-closed class="w-3 h-3"/>
                                                        قفل (محاسبه خودکار)
                                                    </span>
                                                @endif
                                            </label>
                                            <input type="number"
                                                   wire:model.live.debounce.1500ms.blur="priceMatrix.{{ $tierIndex }}.{{ $cashPriceType->id }}.price"
                                                   placeholder="قیمت نقدی"
                                                   inputmode="numeric"
                                                   onwheel="this.blur()"
                                                   @if($isCashDisabled) disabled readonly @endif
                                                   class="block w-full px-4 py-3 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded-lg @if($isCashDisabled) opacity-60 cursor-not-allowed bg-gray-100 dark:bg-gray-800 @endif">
                                            @if($isCashDisabled)
                                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-2 flex items-center gap-1">
                                                    <x-heroicon-o-information-circle class="w-3.5 h-3.5"/>
                                                    این قیمت به صورت خودکار از تیراژ مرجع محاسبه می‌شود
                                                </p>
                                            @endif
                                        </div>
                                        
                                        {{-- چک‌ها --}}
                                        @foreach($checkPeriods as $period)
                                            @php
                                                $isEnabled = $checkPeriodEnabled[$tierIndex][$period->id] ?? false;
                                                $mode = $checkPriceMode[$tierIndex][$period->id] ?? 'default';
                                                $effectivePercentage = $effectivePercentages[$period->id] ?? null;
                                                $calculatedPrice = $this->calculateCheckPrice($tierIndex, $period->id);
                                            @endphp
                                            <div class="p-4 rounded-lg border {{ $isEnabled ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' : 'bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700 opacity-60' }}">
                                                <div class="flex items-center justify-between gap-3 mb-3">
                                                    <span class="text-base font-medium {{ $isEnabled ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500' }} flex items-center gap-2">
                                                        <x-heroicon-s-document-text class="w-5 h-5 {{ $isEnabled ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400' }}"/>
                                                        چک {{ $period->days }} روزه
                                                    </span>
                                                    
                                                    {{-- ✅ Toggle فعال/غیرفعال --}}
                                                    <label class="relative inline-flex items-center cursor-pointer">
                                                        <input type="checkbox" 
                                                               wire:model.live="checkPeriodEnabled.{{ $tierIndex }}.{{ $period->id }}"
                                                               class="sr-only peer">
                                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                                    </label>
                                                </div>
                                                
                                                @if($isEnabled)
                                                <div class="space-y-3 mt-3 pt-3 border-t {{ $isEnabled ? 'border-blue-200 dark:border-blue-800' : 'border-gray-200 dark:border-gray-700' }}">
                                                        {{-- Radio Buttons --}}
                                                        <div class="space-y-2">
                                                            <label class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-lg cursor-pointer min-h-[44px]">
                                                                <input type="radio" 
                                                                       wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                       value="default"
                                                                       class="w-5 h-5 text-blue-600">
                                                                <span class="text-sm">پیش‌فرض</span>
                                                            </label>
                                                            <label class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-lg cursor-pointer min-h-[44px]">
                                                                <input type="radio" 
                                                                       wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                       value="percentage"
                                                                       class="w-5 h-5 text-orange-600">
                                                                <span class="text-sm">درصد اختصاصی</span>
                                                            </label>
                                                            <label class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-lg cursor-pointer min-h-[44px]">
                                                                <input type="radio" 
                                                                       wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                       value="manual"
                                                                       class="w-5 h-5 text-purple-600">
                                                                <span class="text-sm">قیمت دستی</span>
                                                            </label>
                                                        </div>
                                                        
                                                        {{-- نمایش اطلاعات بر اساس mode --}}
                                                        @if($mode === 'default')
                                                            {{-- نمایش درصد پیش‌فرض و مبلغ محاسبه شده --}}
                                                            <div class="bg-blue-100 dark:bg-blue-900/40 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                                                                @if($effectivePercentage)
                                                                    <div class="mb-3">
                                                                        <div class="text-xs text-blue-600 dark:text-blue-400 mb-1">درصد پیش‌فرض:</div>
                                                                        <div class="flex items-center gap-2">
                                                                            <span class="text-lg font-bold text-blue-900 dark:text-blue-100">{{ number_format($effectivePercentage['value'], 1) }}%</span>
                                                                            @if($effectivePercentage['source'] === 'category')
                                                                                <span class="text-xs bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 px-2 py-1 rounded">از دسته‌بندی</span>
                                                                            @else
                                                                                <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-1 rounded">سیستمی</span>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                                @if($calculatedPrice)
                                                                    <div>
                                                                        <div class="text-xs text-blue-600 dark:text-blue-400 mb-1">مبلغ محاسبه شده:</div>
                                                                        <div class="text-lg font-bold text-blue-900 dark:text-blue-100">{{ number_format($calculatedPrice) }} تومان</div>
                                                                    </div>
                                                                @else
                                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                                        برای محاسبه، ابتدا قیمت نقدی را وارد کنید
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @elseif($mode === 'percentage')
                                                            {{-- Input درصد اختصاصی --}}
                                                            <div class="space-y-3">
                                                                <input type="number"
                                                                       wire:model.live.debounce.1500ms.blur="checkPercentageOverride.{{ $tierIndex }}.{{ $period->id }}"
                                                                       placeholder="درصد (مثال: 10)"
                                                                       inputmode="decimal"
                                                                       onwheel="this.blur()"
                                                                       class="block w-full px-4 py-3 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded-lg">
                                                                @if($calculatedPrice)
                                                                    <div class="bg-orange-100 dark:bg-orange-900/40 rounded-lg p-3 border border-orange-200 dark:border-orange-800">
                                                                        <div class="text-xs text-orange-600 dark:text-orange-400 mb-1">مبلغ نهایی:</div>
                                                                        <div class="text-base font-bold text-orange-900 dark:text-orange-100">{{ number_format($calculatedPrice) }} تومان</div>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @elseif($mode === 'manual')
                                                            {{-- Input قیمت دستی --}}
                                                            <input type="number"
                                                                   wire:model.defer="priceMatrix.{{ $tierIndex }}.{{ $checkPriceType->id }}.check_periods.{{ $period->id }}.price"
                                                                   placeholder="قیمت"
                                                                   inputmode="numeric"
                                                                   onwheel="this.blur()"
                                                                   class="block w-full px-4 py-3 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded-lg">
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                        
                                        {{-- گارانتی --}}
                                        @if($hasWarranty && $warrantyType === 'custom')
                                            <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                                                <label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                                                    <x-heroicon-s-shield-check class="w-5 h-5 text-purple-600 dark:text-purple-400"/>
                                                    هزینه گارانتی
                                                </label>
                                                <input type="number"
                                                       wire:model.defer="priceMatrix.{{ $tierIndex }}.{{ $warrantyPriceType->id }}.price"
                                                       placeholder="هزینه گارانتی"
                                                       inputmode="numeric"
                                                       onwheel="this.blur()"
                                                       class="block w-full px-4 py-3 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded-lg">
                                            </div>
                                        @endif
                                    </div>
                                </x-slot>
                            </x-price-tier-table>
                            @endif {{-- پایان شرط tierEnabled --}}
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- ─── Form Footer ─── --}}
            <div class="flex items-center justify-end gap-x-4 bg-gray-50 dark:bg-gray-800/50 px-6 py-4 rounded-b-lg border-t border-gray-200 dark:border-gray-700">
                <x-action-message class="me-3" on="saved">
                    <span class="text-green-600 font-semibold">ذخیره شد.</span>
                </x-action-message>

                <a href="{{ route('supplier.products.index') }}" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors">
                    انصراف
                </a>

                <x-primary-button>
                    <x-heroicon-o-check-circle class="w-4 h-4 mr-2"/>
                    ذخیره ماتریس قیمت
                </x-primary-button>
            </div>
        </form>
    </x-card>
</div>
