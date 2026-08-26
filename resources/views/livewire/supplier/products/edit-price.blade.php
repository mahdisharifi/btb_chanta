<div>
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
        <form wire:submit="save">
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

                {{-- ─── Section 2: ماتریس قیمت ─── --}}
                <div>
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <x-heroicon-o-table-cells class="w-5 h-5 text-indigo-600"/>
                            ماتریس قیمت (تیراژ × نوع قیمت)
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            قیمت‌ها را بر اساس تیراژ و نوع پرداخت وارد کنید. خانه‌های خالی ذخیره نمی‌شوند.
                        </p>
                    </div>

                    {{-- ماتریس قیمت --}}
                    <div class="overflow-x-auto">
                        @foreach($quantityTiers as $tierIndex => $tier)
                            <div class="mb-8 bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                {{-- عنوان تیراژ --}}
                                <div class="mb-4 pb-3 border-b border-gray-200 dark:border-gray-700">
                                    <h4 class="text-base font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                        <x-heroicon-s-cube class="w-5 h-5 text-indigo-600"/>
                                        {{ $tier['name'] }}
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        محدوده: 
                                        @if(isset($tier['min_quantity']))
                                            از {{ number_format($tier['min_quantity']) }}
                                        @endif
                                        @if(isset($tier['max_quantity']))
                                            تا {{ number_format($tier['max_quantity']) }} عدد
                                        @else
                                            به بالا
                                        @endif
                                    </p>
                                </div>

                                {{-- قیمت‌های این تیراژ --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($priceTypes as $priceType)
                                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                            <div class="flex items-center justify-between mb-3">
                                                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    {{ $priceType->name }}
                                                </h5>
                                                @if($priceType->code === 'cash')
                                                    <x-heroicon-s-banknotes class="w-5 h-5 text-green-600"/>
                                                @elseif($priceType->code === 'check')
                                                    <x-heroicon-s-document-text class="w-5 h-5 text-blue-600"/>
                                                @elseif($priceType->code === 'warranty')
                                                    <x-heroicon-s-shield-check class="w-5 h-5 text-purple-600"/>
                                                @endif
                                            </div>

                                            @if($priceType->code === 'check')
                                                {{-- قیمت‌های چکی با دوره‌های مختلف --}}
                                                <div class="space-y-3">
                                                    @foreach($checkPeriods as $period)
                                                        <div wire:key="tier-{{ $tierIndex }}-type-{{ $priceType->id }}-period-{{ $period->id }}">
                                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                                {{ $period->name }} ({{ $period->days }} روز)
                                                            </label>
                                                            <div class="relative">
                                                                <input type="number"
                                                                       wire:model="priceMatrix.{{ $tierIndex }}.{{ $priceType->id }}.check_periods.{{ $period->id }}.price"
                                                                       placeholder="0"
                                                                       onwheel="this.blur()"
                                                                       class="block w-full pe-16 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                                                <div class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3">
                                                                    <span class="text-gray-500 text-xs">تومان</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                {{-- قیمت نقدی یا گارانتی --}}
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                        {{ $priceType->code === 'warranty' ? 'قیمت گارانتی' : 'قیمت' }}
                                                    </label>
                                                    <div class="relative">
                                                        <input type="number"
                                                               wire:model="priceMatrix.{{ $tierIndex }}.{{ $priceType->id }}.price"
                                                               placeholder="0"
                                                               onwheel="this.blur()"
                                                               class="block w-full pe-16 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                                        <div class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3">
                                                            <span class="text-gray-500 text-xs">تومان</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- فیلدهای گارانتی --}}
                                                @if($priceType->code === 'warranty')
                                                    @php
                                                        $warrantyTypePath = "priceMatrix.{$tierIndex}.{$priceType->id}.warranty_type";
                                                    @endphp
                                                    <div class="mt-3 space-y-2" 
                                                         x-data="{ 
                                                             warrantyType: @entangle($warrantyTypePath).live 
                                                         }">
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">نوع گارانتی</label>
                                                            <select x-model="warrantyType"
                                                                    wire:model.live="priceMatrix.{{ $tierIndex }}.{{ $priceType->id }}.warranty_type"
                                                                    class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-xs">
                                                                <option value="">انتخاب کنید</option>
                                                                <option value="test">تست</option>
                                                                <option value="limited">مدت‌دار</option>
                                                                <option value="lifetime">مادام‌العمر</option>
                                                            </select>
                                                        </div>
                                                        <div x-show="warrantyType === 'limited'" x-cloak>
                                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">مدت گارانتی (روز)</label>
                                                            <input type="number"
                                                                   wire:model="priceMatrix.{{ $tierIndex }}.{{ $priceType->id }}.warranty_duration"
                                                                   placeholder="365"
                                                                   onwheel="this.blur()"
                                                                   class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-xs">
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
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
