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
         x-transition
         class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full px-4">
        <div :class="{
            'bg-green-50 border-green-400': type === 'success',
            'bg-red-50 border-red-400': type === 'error',
            'bg-blue-50 border-blue-400': type === 'info',
            'bg-amber-50 border-amber-400': type === 'warning'
        }" class="flex items-center gap-3 p-4 border-2 rounded-lg shadow-xl">
            <div class="flex-1">
                <p class="text-sm font-bold" x-text="message"></p>
            </div>
            <button @click="show = false" class="transition-colors flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">تنظیمات رُند کردن قیمت‌ها</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            نحوه رُند کردن قیمت‌های محاسبه شده (قیمت چک با درصد) را تعیین کنید
        </p>
    </div>

    {{-- Info Box --}}
    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border-r-4 border-blue-500 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-200">
                <p class="font-semibold mb-1">💡 این تنظیم برای چیست؟</p>
                <p class="leading-relaxed">
                    وقتی قیمت چک با درصد محاسبه می‌شود، ممکن است اعشار داشته باشد (مثلاً 12,345.67 تومان).
                    با این تنظیم می‌توانید مشخص کنید که این قیمت چطور رُند شود (مثلاً 12,346 یا 12,350 یا 12,300).
                </p>
            </div>
        </div>
    </div>

    {{-- Main Card --}}
    <x-card>
        <div class="p-6">
            
            {{-- انتخاب نوع Rounding --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    نوع رُند کردن:
                </label>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($roundingTypes as $type => $label)
                        <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all"
                               :class="$wire.price_rounding_type === '{{ $type }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30' : 'border-gray-300 dark:border-gray-700 hover:border-indigo-300'">
                            <input type="radio" 
                                   wire:model.live="price_rounding_type" 
                                   value="{{ $type }}" 
                                   class="text-indigo-600">
                            <span class="mr-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- نمونه‌های Rounding --}}
            <div class="mb-6 bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    پیش‌نمایش نمونه:
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700">
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300">قیمت اصلی</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300">قیمت رُند شده</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300">تفاوت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($roundingSamples as $sample)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 tabular-nums">
                                        {{ number_format($sample['original'], 2) }} تومان
                                    </td>
                                    <td class="px-4 py-2 text-sm font-bold text-indigo-600 dark:text-indigo-400 tabular-nums">
                                        {{ number_format($sample['rounded'], 2) }} تومان
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 tabular-nums">
                                        {{ number_format($sample['rounded'] - $sample['original'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- تعداد قیمت‌های تحت تأثیر --}}
            @if($affectedPricesCount['total'] > 0)
                <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="text-sm text-amber-800 dark:text-amber-200">
                            <p class="font-semibold mb-1">⚠️ تعداد قیمت‌های تحت تأثیر:</p>
                            <ul class="list-disc mr-5 space-y-1">
                                <li>قیمت‌های پیش‌فرض (default): <strong>{{ number_format($affectedPricesCount['default']) }}</strong></li>
                                <li>قیمت‌های با درصد اختصاصی (percentage): <strong>{{ number_format($affectedPricesCount['percentage']) }}</strong></li>
                                <li class="font-bold pt-1 border-t border-amber-300 dark:border-amber-700">جمع کل: {{ number_format($affectedPricesCount['total']) }} قیمت</li>
                            </ul>
                            <p class="mt-2 text-xs">
                                💡 با ذخیره این تنظیمات، تمام این قیمت‌ها بروزرسانی می‌شوند.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- دکمه ذخیره --}}
            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button"
                        wire:click="confirmUpdate"
                        class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                    ذخیره تنظیمات
                </button>
                
                <a href="{{ route('supplier.dashboard') }}" 
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    انصراف
                </a>
            </div>
        </div>
    </x-card>

    {{-- Modal تایید --}}
    @if($showConfirmModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" 
             x-data
             @click.self="$wire.cancelUpdate()">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4 p-6"
                 @click.stop>
                <div class="flex items-start gap-4 mb-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">
                            تایید بروزرسانی قیمت‌ها
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            با ذخیره این تنظیمات، <strong class="text-gray-900 dark:text-gray-100">{{ number_format($affectedPricesCount['total']) }} قیمت</strong> 
                            بر اساس رُند جدید بروزرسانی می‌شوند.
                        </p>
                        <p class="text-sm text-amber-700 dark:text-amber-300 mt-2">
                            ⚠️ این عملیات غیرقابل بازگشت است!
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 justify-end">
                    <button type="button"
                            wire:click="cancelUpdate"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        انصراف
                    </button>
                    <button type="button"
                            wire:click="save"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                        تایید و ذخیره
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
