@props(['productSupplier', 'product', 'hasMultipleSuppliers' => false, 'overallLowestPrice' => null])

@php
use App\Helpers\PriceHelper;

$ps = $productSupplier;

// جمع‌آوری تمام قیمت‌های نقدی و چکی (بدون گارانتی)
$basePrices = $ps->prices->filter(function($price) {
    $priceTypeCode = $price->priceType ? $price->priceType->code : 'cash';
    return $priceTypeCode !== 'warranty';
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

// استخراج تیراژها
$tiers = $basePrices->pluck('tier_index')->unique()->sort()->values();

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

// ✅ اضافه کردن نوع قیمت گارانتی (اگر گارانتی فعال باشد)
if ($hasWarranty && $warrantyType !== 'none') {
    $priceTypes = $priceTypes->push([
        'key' => 'warranty_0',
        'code' => 'warranty',
        'name' => 'با گارانتی',
        'check_period_name' => '',
        'sort_key' => 1000, // بعد از همه
        'display_label' => '🛡️ با گارانتی',
    ]);
}

// ساخت ماتریس
$matrix = [];
foreach ($priceTypes as $priceType) {
    foreach ($tiers as $tier) {
        // اگر نوع warranty نیست، قیمت عادی را پیدا کن
        if ($priceType['code'] !== 'warranty') {
            $cellPrice = $basePrices->first(function($price) use ($priceType, $tier) {
                $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
                $priceTypeCode = $price->priceType ? $price->priceType->code : 'cash';
                $itemKey = "{$priceTypeCode}_{$checkPeriodId}";
                return $itemKey === $priceType['key'] && $price->tier_index == $tier;
            });
            
            if ($cellPrice) {
                $isLowest = $hasMultipleSuppliers && $cellPrice->price == $overallLowestPrice;
                $matrix[$priceType['key']][$tier] = [
                    'price' => $cellPrice,
                    'amount' => $cellPrice->price,
                    'is_lowest' => $isLowest,
                    'is_warranty' => false,
                ];
            }
        } else {
            // ✅ محاسبه قیمت با گارانتی
            // ابتدا قیمت پایه را پیدا کن (نقدی یا اولین قیمت موجود)
            $basePrice = $basePrices->first(function($price) use ($tier) {
                $code = $price->priceType ? $price->priceType->code : 'cash';
                return $code === 'cash' && $price->tier_index == $tier;
            });
            
            // اگر قیمت نقدی نداریم، از اولین قیمت موجود استفاده کن
            if (!$basePrice) {
                $basePrice = $basePrices->first(function($price) use ($tier) {
                    return $price->tier_index == $tier;
                });
            }
            
            if ($basePrice) {
                $basePriceAmount = $basePrice->price;
                $warrantyPriceAmount = null;
                
                // بررسی نوع محاسبه
                if ($warrantyType === 'custom') {
                    // قیمت سفارشی از دیتابیس
                    $customPrice = $customWarrantyPrices->first(function($price) use ($tier) {
                        return $price->tier_index == $tier;
                    });
                    
                    if ($customPrice) {
                        // قیمت سفارشی = قیمت پایه + هزینه گارانتی
                        $warrantyPriceAmount = $basePriceAmount + $customPrice->price;
                    }
                } else {
                    // محاسبه با متد calculatePriceWithWarranty
                    $warrantyPriceAmount = $ps->calculatePriceWithWarranty($basePriceAmount);
                }
                
                if ($warrantyPriceAmount !== null) {
                    $isLowest = $hasMultipleSuppliers && $warrantyPriceAmount == $overallLowestPrice;
                    $matrix[$priceType['key']][$tier] = [
                        'price' => null, // قیمت محاسبه‌شده است، نه از دیتابیس
                        'amount' => $warrantyPriceAmount,
                        'is_lowest' => $isLowest,
                        'is_warranty' => true,
                        'base_price' => $basePriceAmount,
                        'warranty_cost' => $warrantyPriceAmount - $basePriceAmount,
                    ];
                }
            }
        }
    }
}

// ساخت anchor ID برای لینک از ماتریس بالا
$supplierId = $ps->supplier->id;
@endphp

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                    نوع فروش
                </th>
                @foreach($tiers as $tier)
                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase border-r border-gray-200">
                        {{ $tierNames[$tier] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
            @foreach($priceTypes as $priceType)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-4 py-3 text-sm font-medium text-gray-700 whitespace-nowrap">
                        {{ $priceType['display_label'] }}
                    </td>
                    
                    @foreach($tiers as $tier)
                        @php
                            $cell = isset($matrix[$priceType['key']][$tier]) ? $matrix[$priceType['key']][$tier] : null;
                            // ساخت anchor ID مطابق با ماتریس بالا
                            $anchorId = "supplier-{$supplierId}-tier-{$tier}-type-" . str_replace('_', '-', $priceType['key']);
                        @endphp
                        
                        <td 
                            id="{{ $anchorId }}"
                            class="px-4 py-3 text-sm text-center border-r border-gray-100 transition-all duration-300"
                        >
                            @if($cell)
                                <div class="
                                    @if($cell['is_lowest'])
                                        bg-green-50 text-green-800 font-bold ring-2 ring-green-500 rounded-lg px-2 py-1.5
                                    @elseif($cell['is_warranty'])
                                        bg-purple-50 text-purple-800 rounded-lg px-2 py-1.5 border border-purple-200
                                    @else
                                        text-gray-800
                                    @endif
                                ">
                                    <span class="text-base font-semibold tabular-nums">
                                        {{ PriceHelper::formatNumber($cell['amount']) }}
                                    </span>
                                    <span class="text-xs text-gray-400 mr-1">تومان</span>
                                    
                                    @if($cell['is_lowest'])
                                        <div class="text-xs text-green-700 mt-0.5">✓ بهترین قیمت</div>
                                    @elseif($cell['is_warranty'])
                                        <div class="text-xs text-purple-600 mt-0.5">
                                            (پایه: {{ PriceHelper::formatNumber($cell['base_price']) }})
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- قیمت‌های گارانتی جداگانه --}}
@php
$warrantyPrices = $customWarrantyPrices; // استفاده از متغیر تعریف شده در بالا
$allPrices = $basePrices; // برای استفاده در محاسبه نمونه
@endphp

@if($hasWarranty && $warrantyType !== 'none')
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
                            🏭 شرکتی (سازنده)
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
        @if(in_array($warrantyType, ['percentage', 'fixed']) && $allPrices->isNotEmpty())
            @php
                $sampleBasePrice = $allPrices->min('price');
                $sampleWarrantyPrice = $ps->calculatePriceWithWarranty($sampleBasePrice);
                $warrantyCost = $sampleWarrantyPrice - $sampleBasePrice;
            @endphp
            @if($warrantyCost > 0)
                <div class="bg-white/80 rounded-lg p-3 border border-purple-300 border-dashed">
                    <div class="text-xs text-purple-700 mb-2 font-medium">💡 مثال محاسبه:</div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">قیمت پایه:</span>
                        <span class="font-semibold text-gray-800 tabular-nums">{{ number_format($sampleBasePrice) }} تومان</span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-1">
                        <span class="text-purple-600">+ هزینه گارانتی:</span>
                        <span class="font-semibold text-purple-800 tabular-nums">{{ number_format($warrantyCost) }} تومان</span>
                    </div>
                    <div class="border-t border-purple-200 mt-2 pt-2 flex items-center justify-between">
                        <span class="text-sm font-bold text-purple-900">قیمت با گارانتی:</span>
                        <span class="text-lg font-extrabold text-purple-900 tabular-nums">{{ number_format($sampleWarrantyPrice) }} تومان</span>
                    </div>
                </div>
            @endif
        @endif

        {{-- توضیح --}}
        <div class="mt-3 flex items-start gap-2 text-xs text-purple-700 bg-purple-50/50 rounded-lg p-2 border border-purple-200">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="leading-relaxed">
                <strong>نکته:</strong> گارانتی به عنوان یک آپشن اضافی ارائه می‌شود. مشتری می‌تواند محصول را با یا بدون گارانتی خریداری کند.
                @if($warrantyType !== 'none')
                    در صورت انتخاب گارانتی، هزینه آن به قیمت پایه اضافه خواهد شد.
                @endif
            </p>
        </div>
    </div>
</div>
@endif
