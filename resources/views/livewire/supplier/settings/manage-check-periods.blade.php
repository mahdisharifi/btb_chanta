<div>
    {{-- Notification Toast --}}
    <div x-data="{ show: false, message: '', type: 'success', linkUrl: null, linkText: null }" 
         @notify.window="
            show = true; 
            message = $event.detail.message; 
            type = $event.detail.type || 'success';
            linkUrl = null;
            linkText = null;
            setTimeout(() => show = false, 5000)
         "
         @notify-with-link.window="
            show = true; 
            message = $event.detail.message; 
            type = $event.detail.type || 'success';
            linkUrl = $event.detail.linkUrl;
            linkText = $event.detail.linkText || 'مشاهده';
            setTimeout(() => show = false, 15000);
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
            <div class="flex-1">
                <p :class="{
                    'text-green-900': type === 'success',
                    'text-red-900': type === 'error',
                    'text-yellow-900': type === 'warning',
                    'text-blue-900': type === 'info'
                }" class="text-sm font-bold" x-text="message"></p>
                
                <a x-show="linkUrl" :href="linkUrl" 
                   class="text-xs underline mt-1.5 inline-block font-bold hover:opacity-80"
                   :class="{
                       'text-green-700': type === 'success',
                       'text-red-700': type === 'error',
                       'text-yellow-700': type === 'warning',
                       'text-blue-700': type === 'info'
                   }"
                   x-text="linkText">
                </a>
            </div>
            <button @click="show = false" class="text-gray-500 hover:text-gray-700 transition-colors flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    {{-- ═══════════ Page Header ═══════════ --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">مدیریت دوره‌های پرداخت چکی</h1>
        <p class="text-sm text-gray-600 mt-1">
            تعیین دوره‌های چک و درصد افزایش قیمت نسبت به نقدی برای هر دوره
        </p>
    </div>

    {{-- ═══════════ راهنمای کامل ═══════════ --}}
    <div class="mb-6 bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-xl p-5 shadow-sm" x-data="{ expanded: false }">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3 flex-1">
                <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <x-heroicon-o-light-bulb class="w-6 h-6 text-purple-600"/>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-purple-900 text-base mb-2">📚 راهنمای دوره‌های پرداخت چکی</h3>
                    <div class="text-sm text-purple-800 space-y-2" :class="{ 'line-clamp-2': !expanded }">
                        <p><strong>🔹 دوره‌های پیش‌فرض سیستم:</strong> این دوره‌ها برای همه تامین‌کنندگان تعریف شده‌اند. شما می‌توانید آن‌ها را فعال کرده و درصد افزایش قیمت خود را تعیین کنید.</p>
                        
                        <p><strong>🔹 درصد افزایش قیمت:</strong> این درصد روی قیمت نقدی <span class="font-bold text-purple-900">هر تیراژ</span> اعمال می‌شود. برای مثال:</p>
                        <div class="bg-white/70 border border-purple-200 rounded-lg p-3 my-2">
                            <p class="font-mono text-xs">• قیمت نقدی تیراژ 1: <strong>100,000 تومان</strong></p>
                            <p class="font-mono text-xs">• درصد افزایش چک 30 روزه: <strong>5%</strong></p>
                            <p class="font-mono text-xs">• قیمت چک 30 روزه تیراژ 1: <strong>105,000 تومان</strong> (100,000 × 1.05)</p>
                        </div>
                        
                        <p><strong>🔹 درصد صفر:</strong> اگر درصد را صفر قرار دهید، قیمت چکی برابر با نقدی خواهد بود.</p>
                        
                        <p><strong>🔹 محاسبه خودکار:</strong> پس از تعیین درصد، قیمت‌های چکی در قیمت‌گذاری محصولات به صورت خودکار محاسبه می‌شود.</p>
                    </div>
                </div>
            </div>
            <button @click="expanded = !expanded" class="flex-shrink-0 text-purple-600 hover:text-purple-800 transition-colors text-sm font-medium">
                <span x-text="expanded ? 'بستن' : 'بیشتر'"></span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ═══════════ ستون چپ: فرم افزودن ═══════════ --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-4">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <x-heroicon-o-plus-circle class="w-6 h-6"/>
                        افزودن دوره جدید
                    </h2>
                </div>

                <form wire:submit="save" class="p-6 space-y-5">
                    <div>
                        <label for="days" class="block text-sm font-bold text-gray-700 mb-2">
                            تعداد روز چک <span class="text-red-500">*</span>
                        </label>
                        <input wire:model.live="days" id="days" type="number" min="1" max="365" onwheel="this.blur()"
                            class="block w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base"
                            placeholder="مثال: 45" />
                        <x-input-error :messages="$errors->get('days')" class="mt-2" />
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-bold text-gray-700 mb-2">
                            نام دوره <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="name" id="name" type="text"
                            class="block w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="مثال: چک 45 روزه" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="percentage_increase" class="block text-sm font-bold text-gray-700 mb-2">
                            درصد افزایش قیمت <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input wire:model.live="percentage_increase" id="percentage_increase" type="number" min="0" max="100" step="0.01" onwheel="this.blur()"
                                class="block w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-12 text-base"
                                placeholder="5.5" />
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-500 font-bold">
                                %
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('percentage_increase')" class="mt-2" />
                        
                        @if($percentage_increase == 0)
                            <div class="mt-2 flex items-start gap-2 text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg p-2">
                                <x-heroicon-s-exclamation-triangle class="w-4 h-4 flex-shrink-0 mt-0.5"/>
                                <span>⚠️ درصد صفر: قیمت چکی برابر با نقدی خواهد بود</span>
                            </div>
                        @elseif($percentage_increase && $percentage_increase > 0)
                            <p class="text-xs text-green-600 mt-1.5 flex items-center gap-1">
                                <x-heroicon-s-calculator class="w-3.5 h-3.5"/>
                                مثال: قیمت 100,000 تومان → {{ number_format(100000 * (1 + $percentage_increase / 100)) }} تومان
                            </p>
                        @endif
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition-all duration-200 flex items-center justify-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5"/>
                            افزودن دوره
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ═══════════ ستون راست: لیست‌ها ═══════════ --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- دوره‌های پیش‌فرض سیستم --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-yellow-400 to-orange-500 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <x-heroicon-s-star class="w-6 h-6"/>
                        دوره‌های پیش‌فرض سیستم
                    </h2>
                    <span class="text-xs bg-white/30 text-white px-3 py-1 rounded-full font-bold">
                        {{ count($globalPeriods) }} دوره
                    </span>
                </div>

                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-5 flex items-start gap-2">
                        <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"/>
                        <span>این دوره‌ها به صورت پیش‌فرض <strong>غیرفعال</strong> هستند. برای استفاده، درصد افزایش را تعیین کرده و دوره را فعال کنید.</span>
                    </p>

                    @if(empty($globalPeriods))
                        <div class="text-center py-12 text-gray-400">
                            <x-heroicon-o-inbox class="w-16 h-16 mx-auto mb-3 opacity-30"/>
                            <p class="text-sm font-medium">هیچ دوره پیش‌فرضی تعریف نشده است</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($globalPeriods as $period)
                                <div class="p-4 border-2 rounded-xl transition-all duration-200 {{ $period['is_enabled'] ? 'bg-green-50 border-green-300' : 'bg-gray-50 border-gray-200' }}">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h3 class="font-bold text-gray-800">{{ $period['display_name'] }}</h3>
                                                <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-xs font-bold rounded-full">
                                                    {{ $period['days'] }} روز
                                                </span>
                                                @if($period['is_enabled'])
                                                    <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs font-bold rounded-full flex items-center gap-1">
                                                        <x-heroicon-s-check-circle class="w-3 h-3"/>
                                                        فعال
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 bg-gray-200 text-gray-600 text-xs font-bold rounded-full">
                                                        غیرفعال
                                                    </span>
                                                @endif
                                            </div>
                                            
                                            <div class="flex items-center gap-3">
                                                <label class="text-xs font-medium text-gray-600">درصد افزایش:</label>
                                                <div class="relative">
                                                    <input 
                                                        type="number" 
                                                        min="0" 
                                                        max="100" 
                                                        step="0.01"
                                                        value="{{ $period['supplier_percentage'] }}"
                                                        onwheel="this.blur()"
                                                        wire:change="updateGlobalPercentage({{ $period['id'] }}, $event.target.value)"
                                                        class="w-24 px-3 py-1.5 pr-8 border border-gray-300 rounded-lg text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                        placeholder="0"
                                                    />
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs font-bold">%</span>
                                                </div>
                                                
                                                @if($period['supplier_percentage'] > 0)
                                                    <span class="text-xs text-green-600 flex items-center gap-1">
                                                        <x-heroicon-s-arrow-trending-up class="w-3.5 h-3.5"/>
                                                        100k → {{ number_format(100000 * (1 + $period['supplier_percentage'] / 100)) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <button 
                                            wire:click="toggleGlobalPeriod({{ $period['id'] }}, {{ $period['supplier_percentage'] }})"
                                            class="flex-shrink-0 px-4 py-2 rounded-lg font-medium text-sm transition-all {{ $period['is_enabled'] ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200' }}">
                                            {{ $period['is_enabled'] ? 'غیرفعال کردن' : 'فعال کردن' }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- دوره‌های اختصاصی تامین‌کننده --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <x-heroicon-s-cog-6-tooth class="w-6 h-6"/>
                        دوره‌های اختصاصی من
                    </h2>
                    <span class="text-xs bg-white/30 text-white px-3 py-1 rounded-full font-bold">
                        {{ count($supplierPeriods) }} دوره
                    </span>
                </div>

                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-5">
                        دوره‌هایی که خودتان تعریف کرده‌اید و فقط برای شما قابل استفاده است.
                    </p>

                    @if(empty($supplierPeriods))
                        <div class="text-center py-12 text-gray-400">
                            <x-heroicon-o-document-plus class="w-20 h-20 mx-auto mb-4 opacity-30"/>
                            <p class="text-base font-bold mb-1">هنوز دوره اختصاصی ایجاد نکرده‌اید</p>
                            <p class="text-sm">از فرم سمت چپ برای افزودن استفاده کنید</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($supplierPeriods as $period)
                                @if($editingInline === $period['id'])
                                    {{-- فرم ویرایش inline --}}
                                    <div class="p-4 bg-white border-2 border-indigo-400 rounded-xl shadow-md">
                                        <h4 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                                            <x-heroicon-o-pencil-square class="w-5 h-5 text-indigo-600"/>
                                            ویرایش دوره
                                        </h4>
                                        
                                        <div class="space-y-4 mb-4">
                                            {{-- نام دوره --}}
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 mb-1">نام دوره</label>
                                                <input 
                                                    wire:model="inlineName" 
                                                    type="text"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    placeholder="مثال: چک 45 روزه"
                                                />
                                                <x-input-error :messages="$errors->get('inlineName')" class="mt-1" />
                                            </div>
                                            
                                            {{-- تعداد روز (فقط نمایش) --}}
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 mb-1">تعداد روز</label>
                                                <div class="flex items-center gap-2">
                                                    <input 
                                                        value="{{ $period['days'] }}"
                                                        type="text"
                                                        disabled
                                                        class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 cursor-not-allowed"
                                                    />
                                                    <span class="px-3 py-2 bg-blue-100 text-blue-800 text-xs font-bold rounded-lg whitespace-nowrap">
                                                        {{ $period['days'] }} روز
                                                    </span>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1">💡 تعداد روز قابل تغییر نیست</p>
                                            </div>
                                            
                                            {{-- درصد افزایش --}}
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 mb-1">درصد افزایش قیمت <span class="text-red-500">*</span></label>
                                                <div class="relative">
                                                    <input 
                                                        wire:model.live="inlinePercentage" 
                                                        type="number" 
                                                        min="0" 
                                                        max="100"
                                                        step="0.01"
                                                        onwheel="this.blur()"
                                                        class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                        placeholder="5.5"
                                                    />
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-bold">%</span>
                                                </div>
                                                <x-input-error :messages="$errors->get('inlinePercentage')" class="mt-1" />
                                                
                                                @if($inlinePercentage > 0)
                                                    <p class="text-xs text-green-600 mt-1 flex items-center gap-1">
                                                        <x-heroicon-s-calculator class="w-3.5 h-3.5"/>
                                                        مثال: 100,000 تومان → {{ number_format(100000 * (1 + $inlinePercentage / 100)) }} تومان
                                                    </p>
                                                @elseif($inlinePercentage == 0)
                                                    <p class="text-xs text-yellow-600 mt-1 flex items-center gap-1">
                                                        <x-heroicon-s-exclamation-triangle class="w-3.5 h-3.5"/>
                                                        قیمت چک برابر با نقدی خواهد بود
                                                    </p>
                                                @endif
                                            </div>
                                            
                                            {{-- وضعیت فعال --}}
                                            <div class="flex items-center gap-2">
                                                <input 
                                                    wire:model="inlineIsActive" 
                                                    type="checkbox" 
                                                    id="inline-is-active-{{ $period['id'] }}"
                                                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                />
                                                <label for="inline-is-active-{{ $period['id'] }}" class="text-sm font-medium text-gray-700">
                                                    دوره فعال است
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 pt-3 border-t border-gray-200">
                                            <button 
                                                wire:click="saveInlineEdit"
                                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                                                <x-heroicon-o-check class="w-4 h-4"/>
                                                ذخیره
                                            </button>
                                            <button 
                                                wire:click="cancelInlineEdit"
                                                class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg transition-colors">
                                                لغو
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    {{-- نمایش عادی --}}
                                    <div class="p-4 bg-indigo-50 border-2 border-indigo-200 rounded-xl">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-3 mb-1">
                                                    <h3 class="font-bold text-gray-800">{{ $period['name'] }}</h3>
                                                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 text-xs font-bold rounded-full">
                                                        {{ $period['days'] }} روز
                                                    </span>
                                                    @if($period['is_active'])
                                                        <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-bold rounded-full flex items-center gap-1">
                                                            <x-heroicon-s-check-circle class="w-3 h-3"/>
                                                            فعال
                                                        </span>
                                                    @else
                                                        <span class="px-2 py-0.5 bg-gray-200 text-gray-600 text-xs font-bold rounded-full">
                                                            غیرفعال
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                {{-- دکمه ویرایش --}}
                                                <button 
                                                    wire:click="startInlineEdit({{ $period['id'] }})"
                                                    class="p-2 text-indigo-600 hover:bg-indigo-100 rounded-lg transition-colors"
                                                    title="ویرایش">
                                                    <x-heroicon-o-pencil class="w-5 h-5"/>
                                                </button>
                                                
                                                {{-- دکمه فعال/غیرفعال --}}
                                                <button 
                                                    wire:click="toggleSupplierPeriod({{ $period['id'] }})"
                                                    class="px-3 py-1.5 rounded-lg font-medium text-xs transition-all {{ $period['is_active'] ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-green-100 text-green-700 hover:bg-green-200' }}"
                                                    title="{{ $period['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' }}">
                                                    {{ $period['is_active'] ? 'غیرفعال' : 'فعال کردن' }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
