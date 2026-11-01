@php use App\Helpers\PriceHelper; @endphp

@if($showSupplierBranchBreakdown)
<div class="mt-2 max-h-24 space-y-1 overflow-y-auto rounded-lg bg-amber-50/70 px-2.5 py-2 text-xs text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2" role="region" tabindex="0" aria-label="وضعیت شعبه‌ها و قیمت هر شعبه" data-live-price-branch-container data-live-price-product="{{ $product->id }}">
    @foreach($branchRows as $branchRow)
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-x-2 gap-y-0.5" data-live-price-branch-row="{{ $branchRow['supplier_id'] }}">
        <span class="min-w-0 flex-1">
            @if($canSeeSupplier)
                <span class="block truncate">{{ $branchRow['label'] }}</span>
                <span class="block truncate text-[10px] text-gray-500">{{ $branchRow['city'] ?: 'شهر ثبت نشده' }}</span>
            @else
                <span class="block truncate">{{ $branchRow['is_primary'] ? 'قیمت پایه' : 'قیمت شعبه' }}</span>
            @endif
        </span>
        <span class="min-w-0 max-w-full text-left font-semibold text-amber-800 [overflow-wrap:anywhere]" data-live-price-branch-price dir="auto">
            @if($branchRow['price'] !== null)
                <span data-live-price-branch-amount>از {{ PriceHelper::formatNumber($branchRow['price']) }} تومان</span>
            @elseif($branchRow['can_view_price'])
                <span data-live-price-branch-amount>قیمت فعال ندارد</span>
            @else
                <span data-live-price-branch-amount class="text-gray-500">قیمت نیازمند دسترسی</span>
            @endif
        </span>
        @php
            $branchPriceTypeLabel = match ($branchRow['price_type'] ?? null) {
                'cash' => 'نقدی',
                'check' => filled($branchRow['check_period_label'] ?? null)
                    ? 'چکی · ' . $branchRow['check_period_label']
                    : 'چکی',
                default => null,
            };
        @endphp
        <span class="text-[10px] font-medium text-gray-600" data-live-price-branch-type>{{ $branchPriceTypeLabel ? '(' . $branchPriceTypeLabel . ')' : '' }}</span>
        @if($canSeeSupplier)
        <span class="shrink-0 text-left text-[11px] font-medium text-gray-600">
            @if($branchRow['is_primary'])
                <bdi data-live-price-branch-percentage dir="ltr">قیمت پایه</bdi>
            @else
            @php
                $percentageText = rtrim(rtrim(number_format($branchRow['percentage'], 2, '.', ''), '0'), '.');
                $percentageText = strtr($percentageText, [
                    '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳',
                    '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷',
                    '8' => '۸', '9' => '۹', ',' => '٬', '.' => '٫',
                ]);
            @endphp
            <bdi data-live-price-branch-percentage dir="ltr">({{ $branchRow['percentage'] > 0 ? '+' : '' }}{{ $percentageText }}٪)</bdi>
            @endif
        </span>
        @endif
    </div>
    @endforeach
</div>
@endif
