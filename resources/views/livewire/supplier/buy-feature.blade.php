<div class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        <div class="bg-indigo-50 border-b border-indigo-100 p-6 flex items-center gap-4">
            <div class="p-3 bg-indigo-500 rounded-xl text-white">
                <x-heroicon-o-sparkles class="w-8 h-8" />
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-800">فعال‌سازی امکان ویژه: {{ $feature->name }}</h2>
                <p class="text-sm text-gray-500 mt-1">ارتقای امکانات پنل همگام با اعتبار حساب کاربری شما</p>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            @if($error)
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-3">
                <x-heroicon-o-exclamation-circle class="w-6 h-6" />
                <span class="font-medium">{{ $error }}</span>
            </div>
            <div class="mt-6">
                @php
                $dashboardRoute = auth()->user()->isSupplier() ? route('supplier.dashboard') : route('retailer.dashboard');
                @endphp

                <a href="{{ $dashboardRoute }}" class="text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                    بازگشت به داشبورد
                </a>
            </div>
            @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- اطلاعات اشتراک و محاسبه -->
                <div class="space-y-6">
                    <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                        <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <x-heroicon-o-information-circle class="w-5 h-5 text-indigo-500" />
                            نحوه محاسبه هزینه
                        </h3>

                        <ul class="space-y-3 text-sm text-gray-600">
                            <li class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span>قیمت پایه امکان ({{ $feature->base_duration_days }} روزه):</span>
                                <span class="font-bold text-gray-800">{{ number_format($feature->base_price) }} تومان</span>
                            </li>
                            <li class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span>تاریخ پایان اشتراک فعلی شما:</span>
                                <span class="font-bold text-gray-800 dir-ltr">{{ jdate($activeSubscription->ends_at)->format('Y/m/d') }}</span>
                            </li>
                            <li class="flex justify-between items-center border-b border-gray-200 pb-2">
                                <span>روزهای باقیمانده از اشتراک شما:</span>
                                <span class="font-bold text-indigo-600">{{ $remainingDays }} روز</span>
                            </li>
                        </ul>

                        <div class="mt-4 p-4 bg-indigo-50 rounded-lg border border-indigo-100">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-indigo-900">هزینه قابل پرداخت شما:</span>
                                <span class="text-2xl font-black text-indigo-600">{{ number_format($calculatedPrice) }} <span class="text-sm font-normal">تومان</span></span>
                            </div>
                            <p class="text-xs text-indigo-600/70 mt-2 text-justify">
                                سیستم به صورت خودکار هزینه را فقط برای {{ $remainingDays }} روز باقیمانده از اشتراک اصلی شما محاسبه کرده است. با این کار، تاریخ انقضای این امکان با تاریخ انقضای حساب شما یکسان خواهد شد.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- بخش توضیحات و دکمه پرداخت -->
                <div class="flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">ویژگی‌های {{ $feature->name }}</h3>
                        <p class="text-sm text-gray-600 leading-relaxed mb-6">
                            {{ $feature->description ?? 'با فعال‌سازی این ویژگی به امکانات پیشرفته‌ای دسترسی خواهید داشت که به رشد تجارت شما کمک می‌کند.' }}
                        </p>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                                فعال‌سازی آنی پس از پرداخت
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-700">
                                <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                                همگام‌سازی زمان با اشتراک فعلی
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button wire:click="purchase" wire:loading.attr="disabled" class="w-full flex justify-center items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-md hover:shadow-lg focus:ring-4 focus:ring-indigo-200">
                            <x-heroicon-o-credit-card class="w-6 h-6" />
                            <span wire:loading.remove>پرداخت و فعال‌سازی</span>
                            <span wire:loading>در حال پردازش...</span>
                        </button>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
