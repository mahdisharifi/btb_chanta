@props(['productSupplier', 'product', 'hasMultipleSuppliers' => false, 'overallLowestPrice' => null, 'allProductSuppliers' => null])

@php
use App\Helpers\PriceHelper;

$ps = $productSupplier;

// اگر allProductSuppliers پاس نشده، فقط خود این تامین‌کننده را در نظر بگیریم
$allSuppliers = $allProductSuppliers ?? collect([$ps]);

// جمع‌آوری تمام قیمت‌های نقدی و چکی (بدون گارانتی) - فقط فعال‌ها
$basePrices = $ps->prices->filter(function($price) {
    $priceTypeCode = $price->priceType ? $price->priceType->code : 'cash';
    return $priceTypeCode !== 'warranty' && $price->is_active === true;
});

// جمع‌آوری قیمت‌های گارانتی سفارشی (اگر وجود داشته باشد)
$customWarrantyPrices = $ps->prices->filter(function($price) {
    $code = $price->priceType ? $price->priceType->code : null;
    return $code === 'warranty';
});

if ($basePrices->isEmpty()) {
    echo '<div class="px-5 py-6 text-center text-gray-400 text-sm">قیمتی برای این تأمین‌کننده ثبت نشده است</div>';
    return;
}

// محاسبه قیمت‌های با گارانتی
$hasWarranty = $ps->has_warranty ?? false;
$warrantyType = $ps->warranty_type ?? 'none';
$warrantyValue = $ps->warranty_value;

// بررسی اینکه آیا واقعاً گارانتی داریم یا نه
// نکته 1: warranty_type = 'none' یعنی گارانتی رایگان (بدون هزینه اضافه) و معتبر است!
// نکته 2: warranty_value = 0 هم معتبر است (گارانتی رایگان)
$hasWarrantyWithCost = $hasWarranty 
    && ($warrantyType === 'none' || $warrantyType === 'custom' || $warrantyValue !== null);

// استخراج تیراژها
$tiers = $basePrices->pluck('tier_index')->unique()->sort()->values();

// ✅ فیلتر تیراژهای فعال
$tierEnabled = $ps->tier_enabled ?? [];
if (!empty($tierEnabled)) {
    $tiers = $tiers->filter(function($tier) use ($tierEnabled) {
        return $tierEnabled[$tier] ?? true;
    })->values();
}

// دریافت نام تیراژها
$tierNames = [];
$quantityTiers = $product->quantity_tiers ? $product->quantity_tiers : [];
foreach ($tiers as $tierIndex) {
    $tierNames[$tierIndex] = isset($quantityTiers[$tierIndex]['name']) ? $quantityTiers[$tierIndex]['name'] : "تیراژ {$tierIndex}";
}

// استخراج انواع فروش (نقدی و چکی)
$priceTypes = $basePrices
    ->map(function($price) {
        $code = $price->priceType ? $price->priceType->code : 'cash';
        $name = $price->priceType ? $price->priceType->name : 'نقدی';
        $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
        $checkPeriodDays = $price->checkPeriod ? $price->checkPeriod->days : 0;
        $checkPeriodName = $price->checkPeriod ? $price->checkPeriod->name : '';
        
        $key = "{$code}_{$checkPeriodId}";
        $sortKey = $code === 'cash' ? 0 : ($code === 'check' ? $checkPeriodDays : 999);
        
        return [
            'key' => $key,
            'code' => $code,
            'name' => $name,
            'check_period_name' => $checkPeriodName,
            'sort_key' => $sortKey,
            'display_label' => $checkPeriodName ? "{$name} ({$checkPeriodName})" : $name,
        ];
    })
    ->unique('key')
    ->sortBy('sort_key')
    ->values();

// ساخت ماتریس با قیمت‌های پایه
$matrix = [];
foreach ($priceTypes as $priceType) {
    foreach ($tiers as $tier) {
        // ✅ فیلتر: فقط قیمت‌های تیراژهای فعال
        if (!empty($tierEnabled) && !($tierEnabled[$tier] ?? true)) {
            continue; // تیراژ غیرفعال → skip
        }
        
        $cellPrice = $basePrices->first(function($price) use ($priceType, $tier) {
            $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
            $priceTypeCode = $price->priceType ? $price->priceType->code : 'cash';
            $itemKey = "{$priceTypeCode}_{$checkPeriodId}";
            return $itemKey === $priceType['key'] && $price->tier_index == $tier;
        });
        
        if ($cellPrice) {
            // محاسبه اینکه آیا این قیمت در این سلول بهترین است یا نه
            $isLowest = false;
            
            if ($hasMultipleSuppliers) {
                // پیدا کردن تمام قیمت‌ها برای این سلول از همه تامین‌کنندگان
                $allPricesInCell = $allSuppliers->flatMap(function($supplier) use ($priceType, $tier) {
                    return $supplier->prices->filter(function($price) use ($priceType, $tier) {
                        $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
                        $priceTypeCode = $price->priceType ? $price->priceType->code : 'cash';
                        $itemKey = "{$priceTypeCode}_{$checkPeriodId}";
                        $code = $price->priceType ? $price->priceType->code : 'cash';
                        return $code !== 'warranty' && $itemKey === $priceType['key'] && $price->tier_index == $tier;
                    });
                });
                
                // اگر فقط یک تامین‌کننده برای این سلول قیمت دارد → سبز
                if ($allPricesInCell->count() == 1) {
                    $isLowest = true;
                } else {
                    // پیدا کردن کمترین قیمت در این سلول
                    $lowestPriceInCell = $allPricesInCell->min('price');
                    
                    // آیا قیمت فعلی برابر با کمترین قیمت است؟
                    $isLowest = $cellPrice->price == $lowestPriceInCell;
                }
            } else {
                // اگر کلاً فقط یک تامین‌کننده است → همه قیمت‌ها سبز
                $isLowest = true;
            }
            
            // محاسبه قیمت با گارانتی (برای استفاده در Alpine.js)
            $warrantyPrice = null;
            $warrantyCost = null;
            
            if ($hasWarrantyWithCost) {
                if ($warrantyType === 'custom') {
                    // قیمت سفارشی از دیتابیس
                    // برای custom، ما به دنبال قیمت گارانتی مطابق با همان priceType و tier هستیم
                    $customPrice = $customWarrantyPrices->first(function($price) use ($priceType, $tier) {
                        // اگر قیمت گارانتی با check_period مشخصی ثبت شده باشد، باید مطابقت داشته باشد
                        $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
                        $priceTypeCode = $price->priceType ? $price->priceType->code : 'warranty';
                        
                        // برای custom، معمولاً فقط tier مهم است (نه نوع فروش)
                        // اما اگر کاربر برای هر check_period قیمت متفاوتی وارد کرده، باید در نظر بگیریم
                        return $price->tier_index == $tier;
                    });
                    
                    if ($customPrice) {
                        $warrantyPrice = $cellPrice->price + $customPrice->price;
                        $warrantyCost = $customPrice->price;
                    }
                } else {
                    // محاسبه با متد calculatePriceWithWarranty
                    $warrantyPrice = $ps->calculatePriceWithWarranty($cellPrice->price);
                    
                    // نکته مهم: حتی اگر گارانتی رایگان باشد (warranty_value=0)، باید نمایش داده شود
                    if ($warrantyPrice !== null) {
                        $warrantyCost = $warrantyPrice - $cellPrice->price;
                    }
                }
            }
            
            $matrix[$priceType['key']][$tier] = [
                'price' => $cellPrice,
                'base_amount' => $cellPrice->price,
                'warranty_amount' => $warrantyPrice,
                'warranty_cost' => $warrantyCost,
                'is_lowest' => $isLowest,
            ];
        }
    }
}

// ساخت anchor ID برای لینک از ماتریس بالا
$supplierId = $ps->supplier->id;
@endphp

{{-- Wrapper با Alpine.js برای مدیریت state --}}
<div @if($hasWarrantyWithCost) x-data="{ showWithWarranty: false }" @endif>

{{-- Toggle برای نمایش قیمت با گارانتی - با بهبود Accessibility --}}
@if($hasWarrantyWithCost)
<div class="mb-4 flex flex-wrap items-center justify-between gap-2 sm:gap-4 px-3 sm:px-4 py-2.5 sm:py-3 bg-gradient-to-l from-purple-50 to-white border border-purple-200 rounded-lg">
    <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center flex-shrink-0 shadow-md">
            <svg class="w-4 h-4 sm:w-6 sm:h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-xs sm:text-base font-bold text-purple-900 truncate">نمایش قیمت با گارانتی</div>
            <div class="text-[10px] sm:text-sm text-purple-600 truncate">
                @switch($warrantyType)
                    @case('percentage')
                        هزینه: {{ $warrantyValue }}%
                        @break
                    @case('fixed')
                        هزینه: {{ number_format($warrantyValue) }} تومان
                        @break
                    @case('custom')
                        هزینه: سفارشی
                        @break
                @endswitch
            </div>
        </div>
    </div>
    
    {{-- Toggle Switch - بزرگتر برای Touch (WCAG compliant) --}}
    <label class="relative inline-flex items-center cursor-pointer min-w-[56px] min-h-[44px] p-1 flex-shrink-0" 
           role="switch" 
           aria-checked="false"
           x-bind:aria-checked="showWithWarranty.toString()">
        <input type="checkbox" x-model="showWithWarranty" class="sr-only peer" aria-label="نمایش قیمت با گارانتی">
        <div class="w-14 h-7 sm:w-16 sm:h-8 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 sm:after:h-7 sm:after:w-7 after:transition-all peer-checked:bg-purple-600"></div>
    </label>
</div>
@endif

{{-- جدول با بهبودهای Responsive و Accessibility --}}
<div class="relative">
    {{-- Scrollbar indicator برای موبایل --}}
    <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 rounded-lg
                shadow-[inset_-10px_0_10px_-10px_rgba(0,0,0,0.1)] 
                md:shadow-none">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 text-right text-xs sm:text-sm md:text-base font-medium text-gray-600 uppercase sticky right-0 bg-gray-50 z-10 border-l-2 border-gray-300">
                        نوع فروش
                    </th>
                    @foreach($tiers as $tier)
                        @php
                            $tierData = $quantityTiers[$tier] ?? null;
                            $tierName = $tierNames[$tier];
                            $minQty = $tierData['min_quantity'] ?? null;
                            $maxQty = $tierData['max_quantity'] ?? null;
                            
                            // ساخت متن محدوده
                            if ($minQty && $maxQty) {
                                $rangeText = "{$minQty}–{$maxQty}";
                            } elseif ($minQty) {
                                $rangeText = "{$minQty}+";
                            } else {
                                $rangeText = null;
                            }
                        @endphp
                        <th scope="col" class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 text-center border-r border-gray-200 min-w-[90px] sm:min-w-[110px] md:min-w-[130px]">
                            {{-- محدوده تعداد: بهینه برای موبایل --}}
                            @if($rangeText)
                                <div class="text-base sm:text-lg md:text-xl font-extrabold text-gray-900 tabular-nums leading-tight mb-0.5">{{ $rangeText }} عدد</div>
                            @endif
                            {{-- نام تیراژ: کوچکتر --}}
                            <div class="text-[10px] sm:text-xs md:text-sm font-medium text-gray-600 uppercase leading-tight">{{ $tierName }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                @foreach($priceTypes as $priceType)
                    <tr class="group hover:bg-gray-50/50 transition-colors">
                        <td class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 text-xs sm:text-sm md:text-base font-medium text-gray-700 whitespace-nowrap sticky right-0 bg-white group-hover:bg-gray-50/50 z-10 transition-colors border-l-2 border-gray-300">
                            @php
                                // بهینه‌سازی عنوان چکی: دوخطی
                                $displayLabel = $priceType['display_label'];
                                $checkPeriodName = $priceType['check_period_name'] ?? '';
                                
                                // اگر چکی است و check_period_name دارد، آن را جدا نمایش بده
                                if ($priceType['code'] === 'check' && $checkPeriodName) {
                                    // استخراج تعداد روز از check_period_name (مثلاً "چک 30 روزه" → "30 روزه")
                                    $periodText = str_replace(['چک ', 'چك '], '', $checkPeriodName);
                                    echo '<div class="leading-tight">';
                                    echo '<div>چکی</div>';
                                    echo '<div class="text-[10px] sm:text-xs md:text-sm text-gray-500">' . $periodText . '</div>';
                                    echo '</div>';
                                } else {
                                    echo $displayLabel;
                                }
                            @endphp
                        </td>
                        
                        @foreach($tiers as $tier)
                            @php
                                $cell = isset($matrix[$priceType['key']][$tier]) ? $matrix[$priceType['key']][$tier] : null;
                                // ساخت anchor ID مطابق با ماتریس بالا
                                $anchorId = "supplier-{$supplierId}-tier-{$tier}-type-" . str_replace('_', '-', $priceType['key']);
                            @endphp
                            
                            <td 
                                id="{{ $anchorId }}"
                                class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 text-xs sm:text-sm md:text-base text-center border-r border-gray-100 transition-all duration-300"
                            >
                                @if($cell)
                                    @php
                                        // آیا این سلول گارانتی دارد؟
                                        $hasWarrantyPrice = $hasWarrantyWithCost && $cell['warranty_amount'] !== null;
                                    @endphp
                                    
                                    <div 
                                        class="transition-all duration-200"
                                        @if($hasWarrantyPrice)
                                            :class="{
                                                'bg-purple-50 border border-purple-200 rounded-lg px-1.5 py-1 sm:px-2 sm:py-1.5 text-purple-800': showWithWarranty,
                                                '@if($cell['is_lowest']) bg-green-50 text-green-800 font-bold ring-2 ring-green-500 rounded-lg px-1.5 py-1 sm:px-2 sm:py-1.5 @else text-gray-800 @endif': !showWithWarranty
                                            }"
                                        @else
                                            class="@if($cell['is_lowest']) bg-green-50 text-green-800 font-bold ring-2 ring-green-500 rounded-lg px-1.5 py-1 sm:px-2 sm:py-1.5 @else text-gray-800 @endif"
                                        @endif
                                    >
                                        <span class="text-base sm:text-lg md:text-xl lg:text-2xl font-semibold tabular-nums leading-tight tracking-tight">
                                            @if($hasWarrantyPrice)
                                                <span x-show="!showWithWarranty">{{ PriceHelper::formatNumber($cell['base_amount']) }}</span>
                                                <span x-show="showWithWarranty" x-cloak>{{ PriceHelper::formatNumber($cell['warranty_amount']) }}</span>
                                            @else
                                                {{ PriceHelper::formatNumber($cell['base_amount']) }}
                                            @endif
                                        </span>
                                    @if($cell['is_lowest'])
                                        <div 
                                            class="text-[10px] sm:text-xs md:text-sm text-green-700 mt-0.5 leading-tight"
                                            @if($hasWarrantyPrice) x-show="!showWithWarranty" @endif
                                        >
                                            ✓ بهترین
                                        </div>
                                    @endif
                                    
                                    @if($hasWarrantyPrice)
                                        <div x-show="showWithWarranty" x-cloak class="text-xs text-purple-600 mt-0.5">
                                            (پایه: {{ PriceHelper::formatNumber($cell['base_amount']) }})
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Scroll hint برای موبایل --}}
<div class="md:hidden text-center text-xs text-gray-500 mt-2 flex items-center justify-center gap-1">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/>
    </svg>
    <span>برای مشاهده تمام ستون‌ها به چپ و راست بکشید</span>
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
    </svg>
</div>
</div>

</div>{{-- End Alpine.js wrapper --}}

{{-- قیمت‌های گارانتی جداگانه --}}
@php
$warrantyPrices = $customWarrantyPrices; // استفاده از متغیر تعریف شده در بالا
$allPrices = $basePrices; // برای استفاده در محاسبه نمونه
@endphp

@if($hasWarranty)
<div class="mt-4" x-data="{ showWarranty: false }">
    {{-- دکمه نمایش جزئیات گارانتی --}}
    <button 
        @click="showWarranty = !showWarranty"
        type="button"
        class="w-full flex items-center justify-between px-4 py-3 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg transition-colors text-right"
    >
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span class="text-sm font-medium text-purple-900">این محصول با گارانتی عرضه می‌شود</span>
        </div>
        <svg 
            class="w-5 h-5 text-purple-600 transition-transform duration-200"
            :class="showWarranty ? 'rotate-180' : ''"
            fill="none" 
            viewBox="0 0 24 24" 
            stroke="currentColor"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- محتوای جزئیات گارانتی (مخفی به صورت پیش‌فرض) --}}
    <div 
        x-show="showWarranty"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mt-3 bg-gradient-to-br from-purple-50 to-purple-100 border-2 border-purple-300 rounded-xl p-5 shadow-sm"
    >
        <div class="flex items-start gap-3 mb-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-lg flex-shrink-0">
                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h5 class="text-lg font-bold text-purple-900 mb-1">جزئیات گارانتی</h5>
                <p class="text-sm text-purple-700">{{ $ps->warranty_description ?? 'اطلاعات کامل گارانتی' }}</p>
            </div>
        </div>

        {{-- جزئیات گارانتی --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            {{-- نوع گارانتی --}}
            @if($ps->warranty_type_coverage)
                <div class="bg-white rounded-lg p-3 border border-purple-200">
                    <div class="flex items-center gap-2 mb-1">
                        @switch($ps->warranty_type_coverage)
                            @case('test')
                                <span class="text-2xl">🧪</span>
                                <span class="text-xs font-semibold text-orange-600">گارانتی تست</span>
                                @break
                            @case('limited')
                                <span class="text-2xl">⏱️</span>
                                <span class="text-xs font-semibold text-blue-600">گارانتی مدت‌دار</span>
                                @break
                            @case('lifetime')
                                <span class="text-2xl">♾️</span>
                                <span class="text-xs font-semibold text-green-600">گارانتی مادام‌العمر</span>
                                @break
                        @endswitch
                    </div>
                    @if($ps->warranty_duration_days && $ps->warranty_type_coverage !== 'lifetime')
                        <div class="text-lg font-bold text-gray-800">{{ number_format($ps->warranty_duration_days) }} روز</div>
                        <div class="text-xs text-gray-500">
                            (≈ {{ round($ps->warranty_duration_days / 30) }} ماه / {{ number_format($ps->warranty_duration_days / 365, 1) }} سال)
                        </div>
                    @endif
                </div>
            @endif

            {{-- ارائه‌دهنده گارانتی --}}
            @if($ps->warranty_provider && $ps->warranty_provider !== 'none')
                <div class="bg-white rounded-lg p-3 border border-purple-200">
                    <div class="text-xs font-semibold text-purple-600 mb-1">ارائه‌دهنده</div>
                    <div class="text-sm font-bold text-gray-800">
                        @if($ps->warranty_provider === 'manufacturer')
                            🏭 گارانتی شرکتی
                        @elseif($ps->warranty_provider === 'seller')
                            🏪 فروشنده
                        @endif
                    </div>
                </div>
            @endif

            {{-- نوع پوشش --}}
            @if($ps->warranty_coverage_type)
                <div class="bg-white rounded-lg p-3 border border-purple-200">
                    <div class="text-xs font-semibold text-purple-600 mb-1">نوع پوشش</div>
                    <div class="text-sm font-bold text-gray-800">
                        @switch($ps->warranty_coverage_type)
                            @case('full') ✅ پوشش کامل @break
                            @case('limited') ⚠️ پوشش محدود @break
                            @case('parts_only') 🔧 فقط قطعات @break
                            @case('service_only') 🛠️ فقط خدمات @break
                        @endswitch
                    </div>
                </div>
            @endif

            {{-- هزینه گارانتی --}}
            <div class="bg-white rounded-lg p-3 border border-purple-200">
                <div class="text-xs font-semibold text-purple-600 mb-1">هزینه گارانتی</div>
                <div class="text-sm font-bold text-gray-800">
                    @switch($warrantyType)
                        @case('none')
                            🆓 بدون هزینه اضافی
                            @break
                        @case('percentage')
                            📊 {{ $warrantyValue }}% اضافه بر قیمت پایه
                            @break
                        @case('fixed')
                            💰 {{ number_format($warrantyValue) }} تومان ثابت
                            @break
                        @case('custom')
                            ⚙️ سفارشی (بر اساس تیراژ)
                            @break
                    @endswitch
                </div>
            </div>
        </div>

        {{-- قیمت‌های سفارشی گارانتی --}}
        @if($warrantyType === 'custom' && $warrantyPrices->isNotEmpty())
            <div class="bg-white rounded-lg p-4 border border-purple-200">
                <h6 class="text-sm font-semibold text-purple-800 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    هزینه گارانتی بر اساس تیراژ
                </h6>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    @foreach($warrantyPrices as $warranty)
                        <div class="bg-purple-50 rounded-lg p-3 border border-purple-300 text-center hover:shadow-md transition-shadow">
                            <div class="text-xs text-purple-600 mb-1">
                                @if(isset($tierNames[$warranty->tier_index]))
                                    {{ $tierNames[$warranty->tier_index] }}
                                @else
                                    تیراژ {{ $warranty->tier_index }}
                                @endif
                            </div>
                            <div class="text-base font-bold text-purple-900 tabular-nums">
                                +{{ number_format($warranty->price) }}
                            </div>
                            <div class="text-xs text-purple-600">تومان</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- نمایش نمونه محاسبه --}}
        @if($warrantyType !== 'custom' && $allPrices->isNotEmpty())
            @php
                $sampleBasePrice = $allPrices->min('price');
                $sampleWarrantyPrice = $ps->calculatePriceWithWarranty($sampleBasePrice);
                $warrantyCost = $sampleWarrantyPrice - $sampleBasePrice;
            @endphp
            
            <div class="bg-white/80 rounded-lg p-3 border border-purple-300 border-dashed">
                <div class="text-xs text-purple-700 mb-2 font-medium">💡 مثال محاسبه:</div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">قیمت پایه:</span>
                    <span class="font-semibold text-gray-800 tabular-nums">{{ number_format($sampleBasePrice) }} تومان</span>
                </div>
                
                @if($warrantyCost > 0)
                    <div class="flex items-center justify-between text-sm mt-1">
                        <span class="text-purple-600">+ هزینه گارانتی:</span>
                        <span class="font-semibold text-purple-800 tabular-nums">{{ number_format($warrantyCost) }} تومان</span>
                    </div>
                    <div class="border-t border-purple-200 mt-2 pt-2 flex items-center justify-between">
                        <span class="text-sm font-bold text-purple-900">قیمت با گارانتی:</span>
                        <span class="text-lg font-extrabold text-purple-900 tabular-nums">{{ number_format($sampleWarrantyPrice) }} تومان</span>
                    </div>
                @else
                    <div class="flex items-center justify-between text-sm mt-1">
                        <span class="text-green-600">+ هزینه گارانتی:</span>
                        <span class="font-semibold text-green-800">رایگان 🎁</span>
                    </div>
                    <div class="border-t border-purple-200 mt-2 pt-2 flex items-center justify-between">
                        <span class="text-sm font-bold text-purple-900">قیمت با گارانتی:</span>
                        <span class="text-lg font-extrabold text-purple-900 tabular-nums">{{ number_format($sampleWarrantyPrice) }} تومان</span>
                    </div>
                @endif
            </div>
        @endif

        {{-- توضیح --}}
        <div class="mt-3 flex items-start gap-2 text-xs text-purple-700 bg-purple-50/50 rounded-lg p-2 border border-purple-200">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="leading-relaxed">
                <strong>نکته:</strong> گارانتی به عنوان یک آپشن اضافی ارائه می‌شود. مشتری می‌تواند محصول را با یا بدون گارانتی خریداری کند.
                @if($warrantyCost > 0)
                    در صورت انتخاب گارانتی، هزینه آن به قیمت پایه اضافه خواهد شد.
                @else
                    گارانتی این محصول رایگان ارائه می‌شود.
                @endif
            </p>
        </div>
    </div>
</div>
@endif
