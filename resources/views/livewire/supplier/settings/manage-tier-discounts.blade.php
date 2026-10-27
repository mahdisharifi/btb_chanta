<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
            }" class="text-sm font-medium flex-1" x-text="message"></p>
            <button @click="show = false" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            تنظیمات درصد تیراژ نقدی
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            مدیریت درصد افزایش/کاهش قیمت نقدی هر تیراژ نسبت به تیراژ مرجع (کارتن)
        </p>
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
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                ⚙️ تنظیمات پیشفرض تیراژ
            </h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                برای همه محصولات اعمال می‌شود
            </span>
        </div>

        @if(count($tierSettings) > 0)
        <form wire:submit.prevent="save">
            <div class="space-y-4">
                @foreach($tierSettings as $index => $tier)
                <div class="border-b border-gray-200 dark:border-gray-700 pb-4 last:border-b-0 last:pb-0">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        {{-- نام تیراژ --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $tier['name'] }}
                                @if($tier['tier_index'] === 1)
                                    <span class="text-xs text-gray-500">(مرجع)</span>
                                @endif
                            </label>
                        </div>

                        {{-- درصد تغییر --}}
                        <div>
                            <div class="relative">
                                <input type="number" 
                                       step="0.01"
                                       wire:model="tierSettings.{{ $index }}.percentage_change"
                                       @if($tier['tier_index'] === 1) readonly @endif
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @if($tier['tier_index'] === 1) bg-gray-100 cursor-not-allowed @endif"
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
                            مثال: <span class="font-semibold @if($percentage > 0) text-red-600 @elseif($percentage < 0) text-green-600 @endif">{{ number_format($calculatedPrice, 0) }} ریال</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    @if($affectedPricesCount > 0)
                        <span class="font-semibold text-blue-600 dark:text-blue-400">{{ number_format($affectedPricesCount) }}</span> قیمت نقدی فعال
                    @else
                        هیچ قیمت فعالی وجود ندارد
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if($hasChanges)
                        <span class="text-xs text-amber-600 dark:text-amber-400">تغییرات ذخیره نشده</span>
                    @endif
                    
                    <button type="button"
                            wire:click="resetToDefaults"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm">
                        پیشفرض
                    </button>
                    <button type="submit"
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

    {{-- بخش 2: تنظیمات اختصاصی دسته‌بندی --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
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
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">دسته‌بندی</label>
                    <select wire:model="selectedCategoryId"
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
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
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
                            <div>
                                <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1">
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
                                wire:click="saveCategoryEdit"
                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            ذخیره
                        </button>
                    </div>
                </div>
                @else
                {{-- حالت نمایش --}}
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $setting['category_name'] }}</div>
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
                                wire:click="startEditCategory({{ $setting['category_id'] }})"
                                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 text-sm">
                            ویرایش
                        </button>
                        <button type="button"
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
        <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
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
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
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
                            wire:click="cancelUpdate"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 text-sm">
                        انصراف
                    </button>
                    <button type="button"
                            wire:click="updatePrices"
                            class="px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg">
                        بله، بروزرسانی کن
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
