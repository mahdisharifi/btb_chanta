@props(['productSupplier', 'product', 'hasMultipleSuppliers' => false, 'overallLowestPrice' => null])

@php
use App\Helpers\PriceHelper;

$ps = $productSupplier;

// جمع‌آوری تمام قیمت‌ها
$allPrices = $ps->prices->filter(function($price) {
    $priceTypeCode = $price->priceType ? $price->priceType->code : 'cash';
    return $priceTypeCode !== 'warranty'; // حذف گارانتی
});

if ($allPrices->isEmpty()) {
    echo '<div class="px-5 py-6 text-center text-gray-400 text-sm">قیمتی برای این تأمین‌کننده ثبت نشده است</div>';
    return;
}

// استخراج تیراژها
$tiers = $allPrices->pluck('tier_index')->unique()->sort()->values();

// دریافت نام تیراژها
$tierNames = [];
$quantityTiers = $product->quantity_tiers ? $product->quantity_tiers : [];
foreach ($tiers as $tierIndex) {
    $tierNames[$tierIndex] = isset($quantityTiers[$tierIndex]['name']) ? $quantityTiers[$tierIndex]['name'] : "تیراژ {$tierIndex}";
}

// استخراج انواع فروش
$priceTypes = $allPrices
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

// ساخت ماتریس
$matrix = [];
foreach ($priceTypes as $priceType) {
    foreach ($tiers as $tier) {
        $cellPrice = $allPrices->first(function($price) use ($priceType, $tier) {
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
            ];
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
$warrantyPrices = $ps->prices->filter(function($price) {
    $code = $price->priceType ? $price->priceType->code : null;
    return $code === 'warranty';
});
@endphp

@if($warrantyPrices->isNotEmpty())
<div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
    <h5 class="text-sm font-semibold text-green-800 mb-2 flex items-center gap-2">
        <span>🛡️</span>
        قیمت‌های گارانتی
    </h5>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        @foreach($warrantyPrices as $warranty)
            <div class="bg-white rounded-lg p-3 border border-green-300">
                <div class="text-sm font-bold text-green-700">
                    {{ PriceHelper::formatNumber($warranty->price) }} تومان
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    @if(isset($tierNames[$warranty->tier_index]))
                        {{ $tierNames[$warranty->tier_index] }}
                    @else
                        تیراژ {{ $warranty->tier_index }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif
