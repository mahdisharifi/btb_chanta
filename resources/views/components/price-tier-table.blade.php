@props([
    'tier',
    'tierIndex',
    'quantityTiers',
    'priceTypes',
    'checkPeriods',
    'cashPriceType',
    'checkPriceType',
    'warrantyPriceType',
    'hasWarranty',
    'warrantyType',
    'checkPeriodEnabled',
    'checkPriceMode',
    'checkPercentageOverride',
    'effectivePercentages',
    'priceMatrix',
])

{{-- Tier Card Container --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    
    {{-- Header: عنوان تیراژ + دکمه Copy --}}
    <div class="bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-gray-800 dark:to-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h4 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-s-cube class="w-5 h-5 text-indigo-600 dark:text-indigo-400"/>
                        {{ $tier['name'] }}
                    </h4>
                    
                    {{-- Tooltip توضیحی --}}
                    <div x-data="{ showTooltip: false }" class="relative">
                        <button type="button" 
                                @mouseenter="showTooltip = true" 
                                @mouseleave="showTooltip = false"
                                class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                            <x-heroicon-o-information-circle class="w-5 h-5"/>
                        </button>
                        <div x-show="showTooltip" 
                             x-cloak
                             class="absolute right-0 top-full mt-2 w-72 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg p-4 shadow-xl z-20">
                            <div class="space-y-2">
                                <p class="font-semibold text-indigo-300">این تیراژ برای چیست؟</p>
                                <p class="leading-relaxed">
                                    این یک <strong>نقطه مرجع</strong> است که خریداران برای مقایسه قیمت شما با سایر تأمین‌کنندگان استفاده می‌کنند.
                                </p>
                                <div class="bg-amber-900/40 border border-amber-700 rounded px-2 py-1.5 mt-2">
                                    <p class="text-amber-200 text-xs">
                                        ⚠️ برای تعداد دقیق، خریدار با شما تماس خواهد گرفت.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Range نمایش با Badge Style --}}
                <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-200">
                        📊 محدوده تعداد:
                        @if(isset($tier['min_quantity']) && isset($tier['max_quantity']))
                            <span class="mr-1 font-bold">{{ number_format($tier['min_quantity']) }}</span> تا 
                            <span class="mr-1 font-bold">{{ number_format($tier['max_quantity']) }}</span> عدد
                        @elseif(isset($tier['min_quantity']))
                            از <span class="mr-1 font-bold">{{ number_format($tier['min_quantity']) }}</span> عدد به بالا
                        @elseif(isset($tier['max_quantity']))
                            تا <span class="mr-1 font-bold">{{ number_format($tier['max_quantity']) }}</span> عدد
                        @endif
                    </span>
                </div>
            </div>
            
            {{-- دکمه Copy Settings --}}
            @if(count($quantityTiers) > 1)
                <div x-data="{ 
                    showMenu: false,
                    sourceTier: {{ $tierIndex }},
                    copyToAll() {
                        $wire.copyTierSettings(this.sourceTier, []);
                        this.showMenu = false;
                    },
                    copyToTier(targetTier) {
                        $wire.copyTierSettings(this.sourceTier, [targetTier]);
                        this.showMenu = false;
                    }
                }" 
                @click.away="showMenu = false"
                class="relative">
                    <button type="button" 
                            @click="showMenu = !showMenu"
                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-700 dark:text-indigo-300 bg-white dark:bg-gray-800 hover:bg-indigo-50 dark:hover:bg-gray-700 rounded-lg transition-colors border border-indigo-200 dark:border-indigo-800 shadow-sm">
                        <x-heroicon-o-document-duplicate class="w-4 h-4"/>
                        <span>کپی تنظیمات</span>
                        <x-heroicon-s-chevron-down class="w-3 h-3"/>
                    </button>
                    
                    {{-- Dropdown Menu --}}
                    <div x-show="showMenu" 
                         x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute left-0 top-full mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-10">
                        <div class="p-2">
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 mb-1">
                                کپی به:
                            </div>
                            
                            {{-- گزینه: همه تیراژها --}}
                            <button type="button"
                                    @click="copyToAll()"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-md transition-colors">
                                <x-heroicon-o-clipboard-document-check class="w-4 h-4 text-indigo-600 dark:text-indigo-400"/>
                                <span class="font-medium">همه تیراژها</span>
                            </button>
                            
                            <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>
                            
                            {{-- گزینه‌های تک تیراژ --}}
                            @foreach($quantityTiers as $targetIndex => $targetTier)
                                @if($targetIndex !== $tierIndex)
                                    <button type="button"
                                            @click="copyToTier({{ $targetIndex }})"
                                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors text-right">
                                        <x-heroicon-o-arrow-left class="w-3 h-3 text-gray-500"/>
                                        <span>{{ $targetTier['name'] }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- جدول قیمت‌ها - Desktop/Tablet (نمایش برای md و بالاتر) --}}
    <div class="hidden md:block">
        {{ $slot }}
    </div>

    {{-- Accordion برای Mobile (نمایش برای کمتر از md) --}}
    <div class="md:hidden">
        {{ $mobileSlot ?? '' }}
    </div>
</div>
