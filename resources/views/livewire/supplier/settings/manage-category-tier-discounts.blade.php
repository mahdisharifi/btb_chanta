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
         x-transition
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
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
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
            تنظیمات درصد اختصاصی تیراژ برای دسته‌بندی
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            تنظیم درصد اختصاصی برای دسته‌بندی‌های خاص که از درصد پیشفرض تامین‌کننده متفاوت است
        </p>
    </div>

    {{-- راهنما --}}
    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-200">
                <p class="font-semibold mb-2">سلسله مراتب اعمال درصد:</p>
                <ol class="list-decimal list-inside space-y-1 mr-4">
                    <li><strong>اولویت اول:</strong> درصد اختصاصی دسته‌بندی (تنظیمات این صفحه)</li>
                    <li><strong>اولویت دوم:</strong> درصد پیشفرض تامین‌کننده</li>
                </ol>
            </div>
        </div>
    </div>

    {{-- فرم افزودن دسته‌بندی جدید --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            افزودن دسته‌بندی جدید
        </h2>

        <form wire:submit.prevent="addCategory">
            {{-- انتخاب دسته‌بندی --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    دسته‌بندی
                </label>
                <select wire:model="selectedCategoryId" 
                        class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- انتخاب کنید --</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('selectedCategoryId')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- درصدهای 4 تیراژ --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                @foreach(range(0, 3) as $tierIndex)
                @php
                    $tierName = \App\Models\SupplierTierDiscount::TIER_NAMES[$tierIndex] ?? "تیراژ {$tierIndex}";
                    $defaultPercentage = $defaultTiers[$tierIndex]->percentage_change ?? 0;
                @endphp
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ $tierName }}
                        @if($tierIndex === 1)
                            <span class="text-xs text-gray-500">(مرجع)</span>
                        @endif
                    </label>
                    <div class="relative">
                        <input type="number" 
                               step="0.01"
                               wire:model="tierPercentages.{{ $tierIndex }}"
                               placeholder="{{ number_format($defaultPercentage, 2) }}"
                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-sm">%</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">پیشفرض: {{ number_format($defaultPercentage, 2) }}%</p>
                    @error("tierPercentages.{$tierIndex}")
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                @endforeach
            </div>

            <div class="flex items-center justify-end">
                <button type="submit"
                        class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors font-medium">
                    افزودن دسته‌بندی
                </button>
            </div>
        </form>
    </div>

    {{-- لیست دسته‌بندی‌های تنظیم شده --}}
    @if(count($categoryTierSettings) > 0)
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            دسته‌بندی‌های تنظیم شده
        </h2>

        <div class="space-y-4">
            @foreach($categoryTierSettings as $categorySetting)
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ $categorySetting['category_name'] }}
                    </h3>
                    <div class="flex items-center gap-2">
                        @if($editingCategoryId === $categorySetting['category_id'])
                            <button type="button"
                                    wire:click="saveCategoryEdit"
                                    class="text-sm text-green-600 hover:text-green-800 font-medium">
                                ذخیره
                            </button>
                            <button type="button"
                                    wire:click="cancelCategoryEdit"
                                    class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                                انصراف
                            </button>
                        @else
                            <button type="button"
                                    wire:click="startEditCategory({{ $categorySetting['category_id'] }})"
                                    class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                ویرایش
                            </button>
                            <button type="button"
                                    wire:click="removeCategory({{ $categorySetting['category_id'] }})"
                                    class="text-sm text-red-600 hover:text-red-800 font-medium">
                                حذف
                            </button>
                        @endif
                    </div>
                </div>

                @if($editingCategoryId === $categorySetting['category_id'])
                {{-- حالت ویرایش --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @foreach(range(0, 3) as $tierIndex)
                    @php
                        $tierName = \App\Models\SupplierTierDiscount::TIER_NAMES[$tierIndex] ?? "تیراژ {$tierIndex}";
                    @endphp
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            {{ $tierName }}
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   step="0.01"
                                   wire:model="tierPercentages.{{ $tierIndex }}"
                                   class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm">
                            <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs">%</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                {{-- حالت نمایش --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($categorySetting['tiers'] as $tier)
                    <div class="bg-gray-50 dark:bg-gray-900 rounded p-2">
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ $tier['tier_name'] }}</p>
                        <p class="text-sm font-semibold @if($tier['percentage'] > 0) text-red-600 @elseif($tier['percentage'] < 0) text-green-600 @else text-gray-900 dark:text-gray-100 @endif">
                            {{ number_format($tier['percentage'], 2) }}%
                        </p>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
