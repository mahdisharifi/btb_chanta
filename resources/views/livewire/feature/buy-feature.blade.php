<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    @php
    $dashboardRoute = auth()->user()->isSupplier() ? route('supplier.dashboard') : route('retailer.dashboard');
    @endphp

    <div class="mb-6 flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <x-heroicon-o-sparkles class="w-7 h-7 text-indigo-500" />
            خرید امکانات ویژه: {{ $feature->name ?? 'نامشخص' }}
        </h1>
        <a href="{{ $dashboardRoute }}" class="text-gray-500 hover:text-indigo-600 transition-colors flex items-center gap-1 text-sm font-medium">
            بازگشت به داشبورد
            <x-heroicon-m-arrow-left class="w-4 h-4" />
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($error)
        <div class="p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-4">
                <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-red-600" />
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">خطا در پردازش</h3>
            <p class="text-gray-600 mb-6">{{ $error }}</p>
            <a href="{{ $dashboardRoute }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                متوجه شدم
            </a>
        </div>
        @else
        <div class="p-6 sm:p-8">
            <div class="prose prose-indigo mb-8 text-gray-600 text-sm leading-relaxed">
                {{ $feature->description ?? 'توضیحاتی برای این امکان ثبت نشده است.' }}
            </div>

            <div class="bg-gray-50 rounded-xl p-6 mb-8 border border-gray-100">
                <h4 class="text-sm font-bold text-gray-800 mb-4 border-b border-gray-200 pb-2">جزئیات محاسبه قیمت</h4>

                <div class="space-y-3">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">اعتبار اشتراک فعلی شما:</span>
                        <span class="font-bold text-gray-800 dir-ltr">{{ jdate($activeSubscription->ends_at)->format('Y/m/d') }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">روزهای باقی‌مانده تا پایان اشتراک:</span>
                        <span class="font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $remainingDays }} روز</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">قیمت پایه (برای {{ $feature->base_duration_days }} روز):</span>
                        <span class="font-bold text-gray-800">{{ number_format($feature->base_price) }} تومان</span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                    <span class="text-base font-bold text-gray-800">مبلغ قابل پرداخت:</span>
                    <span class="text-2xl font-black text-green-600">{{ number_format($calculatedPrice) }} <span class="text-sm font-normal text-gray-500">تومان</span></span>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button wire:click="purchase" wire:loading.attr="disabled" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-xl font-bold text-sm text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all shadow-md hover:shadow-lg disabled:opacity-50">
                    <x-heroicon-o-credit-card class="w-5 h-5 ml-2" />
                    <span wire:loading.remove wire:target="purchase">تایید و پرداخت موقت (تستی)</span>
                    <span wire:loading wire:target="purchase">در حال پردازش...</span>
                </button>
            </div>
        </div>
        @endif
    </div>
</div>
