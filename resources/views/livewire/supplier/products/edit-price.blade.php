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
                    </div>

                    {{-- ماتریس قیمت - Desktop/Tablet: Table، Mobile: Accordion --}}
                    <div class="space-y-8">
                        @php
                            $cashPriceType = $priceTypes->firstWhere('code', 'cash');
                            $checkPriceType = $priceTypes->firstWhere('code', 'check');
                            $warrantyPriceType = $priceTypes->firstWhere('code', 'warranty');
                        @endphp
                        
                        @foreach($quantityTiers as $tierIndex => $tier)
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
                                                    <div class="relative max-w-xs">
                                                        <input type="number"
                                                               wire:model.defer="priceMatrix.{{ $tierIndex }}.{{ $cashPriceType->id }}.price"
                                                               placeholder="قیمت نقدی"
                                                               inputmode="numeric"
                                                               onwheel="this.blur()"
                                                               class="block w-full px-4 py-2.5 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 focus:border-green-500 focus:ring-green-500 rounded-md shadow-sm">
                                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">تومان</span>
                                                        </div>
                                                    </div>
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
                                                
                                                <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors @if(!$isEnabled) opacity-60 @endif">
                                                    <td class="sticky right-0 z-10 px-4 py-4 border border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/20">
                                                        <div class="flex items-center gap-3">
                                                            {{-- Checkbox فعال/غیرفعال --}}
                                                            <label class="flex items-center cursor-pointer">
                                                                <input type="checkbox" 
                                                                       wire:model.live="checkPeriodEnabled.{{ $tierIndex }}.{{ $period->id }}"
                                                                       class="w-5 h-5 text-blue-600 dark:text-blue-500 rounded focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                                            </label>
                                                            
                                                            <div class="flex items-center gap-2">
                                                                <x-heroicon-s-document-text class="w-5 h-5 text-blue-600 dark:text-blue-400"/>
                                                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">چک {{ $period->days }} روزه</span>
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
                                                        @if($isEnabled)
                                                            <div class="space-y-3">
                                                                {{-- انتخاب Mode --}}
                                                                <div class="flex items-center gap-4">
                                                                    <label class="flex items-center cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 p-2 rounded transition-colors">
                                                                        <input type="radio" 
                                                                               wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                               value="default"
                                                                               class="w-4 h-4 text-blue-600">
                                                                        <span class="mr-2 text-xs text-gray-700 dark:text-gray-300">پیش‌فرض</span>
                                                                    </label>
                                                                    <label class="flex items-center cursor-pointer hover:bg-orange-50 dark:hover:bg-orange-900/20 p-2 rounded transition-colors">
                                                                        <input type="radio" 
                                                                               wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                               value="percentage"
                                                                               class="w-4 h-4 text-orange-600">
                                                                        <span class="mr-2 text-xs text-gray-700 dark:text-gray-300">درصد اختصاصی</span>
                                                                    </label>
                                                                    <label class="flex items-center cursor-pointer hover:bg-purple-50 dark:hover:bg-purple-900/20 p-2 rounded transition-colors">
                                                                        <input type="radio" 
                                                                               wire:model.live="checkPriceMode.{{ $tierIndex }}.{{ $period->id }}" 
                                                                               value="manual"
                                                                               class="w-4 h-4 text-purple-600">
                                                                        <span class="mr-2 text-xs text-gray-700 dark:text-gray-300">دستی</span>
                                                                    </label>
                                                                </div>
                                                                
                                                                {{-- Input بر اساس mode --}}
                                                                @if($mode === 'default')
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
                                                                                   wire:model.live.debounce.500ms="checkPercentageOverride.{{ $tierIndex }}.{{ $period->id }}"
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
                                                        @else
                                                            <div class="text-center py-3 text-xs text-gray-400 dark:text-gray-600">
                                                                <x-heroicon-o-lock-closed class="w-5 h-5 mx-auto mb-1 opacity-50"/>
                                                                <div>برای فعال‌سازی، چک‌باکس را علامت بزنید</div>
                                                            </div>
                                                        @endif
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
                                        {{-- نقدی --}}
                                        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                            <label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                                                <x-heroicon-s-banknotes class="w-5 h-5 text-green-600 dark:text-green-400"/>
                                                قیمت نقدی
                                            </label>
                                            <input type="number"
                                                   wire:model.defer="priceMatrix.{{ $tierIndex }}.{{ $cashPriceType->id }}.price"
                                                   placeholder="قیمت نقدی"
                                                   inputmode="numeric"
                                                   onwheel="this.blur()"
                                                   class="block w-full px-4 py-3 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded-lg">
                                        </div>
                                        
                                        {{-- چک‌ها --}}
                                        @foreach($checkPeriods as $period)
                                            @php
                                                $isEnabled = $checkPeriodEnabled[$tierIndex][$period->id] ?? false;
                                                $mode = $checkPriceMode[$tierIndex][$period->id] ?? 'default';
                                                $effectivePercentage = $effectivePercentages[$period->id] ?? null;
                                                $calculatedPrice = $this->calculateCheckPrice($tierIndex, $period->id);
                                            @endphp
                                            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                                <label class="flex items-center gap-3 mb-3 min-h-[44px] cursor-pointer">
                                                    <input type="checkbox" 
                                                           wire:model.live="checkPeriodEnabled.{{ $tierIndex }}.{{ $period->id }}"
                                                           class="w-6 h-6 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                                                    <span class="text-base font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                        <x-heroicon-s-document-text class="w-5 h-5 text-blue-600 dark:text-blue-400"/>
                                                        چک {{ $period->days }} روزه
                                                    </span>
                                                </label>
                                                @if($isEnabled)
                                                    <div class="space-y-3 mt-3 pt-3 border-t border-blue-200 dark:border-blue-800">
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
                                                                       wire:model.live.debounce.500ms="checkPercentageOverride.{{ $tierIndex }}.{{ $period->id }}"
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
