@if($branchRows->count() > 1 || $branchRows->contains(fn ($row) => $row['label'] !== 'شعبه اصلی'))
<div class="mt-2 max-h-24 space-y-1 overflow-y-auto rounded-lg bg-amber-50/70 px-2.5 py-2 text-xs text-gray-700" aria-label="وضعیت شعبه‌ها و قیمت هر شعبه">
    @foreach($branchRows as $branchRow)
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-x-2 gap-y-0.5">
        <span class="min-w-0 flex-1 truncate">
            {{ $branchRow['label'] }} · {{ $branchRow['city'] ?: 'شهر ثبت نشده' }}
        </span>
        <span class="shrink-0 text-left font-semibold text-amber-800">
            @if($branchRow['price'] !== null)
                {{ PriceHelper::formatNumber($branchRow['price']) }} تومان
            @elseif($branchRow['can_view_price'])
                قیمت فعال ندارد
            @else
                <span class="text-gray-500">قیمت نیازمند دسترسی</span>
            @endif
            <span class="mr-1 text-[11px] font-medium text-gray-600">
                @if($branchRow['is_primary'])
                    قیمت پایه
                @else
                    ({{ $branchRow['percentage'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($branchRow['percentage'], 2, '.', ''), '0'), '.') }}٪)
                @endif
            </span>
        </span>
    </div>
    @endforeach
</div>
@endif
