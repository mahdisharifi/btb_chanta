{{-- resources/views/components/product/card-list.blade.php --}}
@props([
    'product',
    'showSupplier' => false,
])

@php
    use App\Helpers\PriceHelper;

    $imageUrl = asset('images/placeholder-product.jpg');
    if ($product->relationLoaded('galleries') && $product->galleries->isNotEmpty()) {
        $primaryImage = $product->galleries->firstWhere('is_primary', true) ?? $product->galleries->first();
        if ($primaryImage) $imageUrl = asset('storage/' . $primaryImage->file_path);
    }

    $retailPrice = null;
    if ($product->relationLoaded('retailPrices') && $product->retailPrices->isNotEmpty()) {
        $retailPrice = $product->retailPrices->where('is_published', true)->sortBy('retail_price')->first();
    }

    $supplier = $product->supplier ?? null;
    $productUrl = route('products.show', $product->slug);
@endphp

{{-- ✅ تغییر از <a> به <div> — برای جلوگیری از تداخل لینک‌های تو در تو --}}
<div class="group bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md hover:border-blue-300 transition-all duration-200 flex gap-4 p-3 relative">

    {{-- Image (لینک به محصول) --}}
    <a href="{{ $productUrl }}" class="w-28 h-28 sm:w-36 sm:h-36 bg-gray-100 rounded-lg overflow-hidden shrink-0 block">
        <img src="{{ $imageUrl }}" alt="{{ $product->title }}"
             class="w-full h-full object-contain p-1 group-hover:scale-105 transition-transform duration-300"
             loading="lazy" onerror="this.onerror=null; this.src='{{ asset('images/placeholder-product.jpg') }}';">
    </a>

    {{-- Info --}}
    <div class="flex-1 min-w-0 flex flex-col justify-between">
        <div>
            @if($product->category)
                <span class="text-[11px] text-blue-500 font-medium">{{ $product->category->name }}</span>
            @endif

            {{-- عنوان محصول (لینک به محصول) --}}
            <a href="{{ $productUrl }}" class="block">
                <h3 class="text-sm font-bold text-gray-800 group-hover:text-blue-600 transition line-clamp-2 mt-0.5 leading-6">
                    {{ $product->title }}
                </h3>
            </a>

            @if($product->short_description)
                <p class="text-xs text-gray-400 line-clamp-1 mt-1">{{ $product->short_description }}</p>
            @endif

            {{-- ✅ تأمین‌کننده — لینک‌دار به صفحه پروفایل تأمین‌کننده --}}
            @if($showSupplier && $supplier)
                <a href="{{ route('suppliers.show', $supplier->slug) }}"
                   class="inline-flex items-center gap-1 mt-1 text-[11px] text-gray-400 hover:text-blue-600 transition-colors group/supplier"
                   title="مشاهده پروفایل {{ $supplier->company_name }}">
                    @if($supplier->logo_path)
                        <img src="{{ asset('storage/' . $supplier->logo_path) }}"
                             alt="{{ $supplier->company_name }}"
                             class="w-4 h-4 rounded-full object-cover border border-gray-200">
                    @endif
                    @if($supplier->is_verified)
                        <svg class="w-3 h-3 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    @endif
                    <span class="group-hover/supplier:underline">{{ $supplier->company_name }}</span>
                    <svg class="w-3 h-3 opacity-0 group-hover/supplier:opacity-100 transition-opacity rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            @endif
        </div>

        <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
            @if($retailPrice)
                <div class="flex items-baseline gap-1">
                    <span class="text-base font-extrabold text-emerald-600">{{ PriceHelper::formatNumber($retailPrice->retail_price) }}</span>
                    <span class="text-[11px] text-gray-400">تومان</span>
                </div>
            @else
                <span class="text-sm text-gray-400">قیمت: استعلام</span>
            @endif

            <div class="flex items-center gap-3 text-[10px] text-gray-300">
                <span class="flex items-center gap-0.5">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    {{ number_format($product->view_count ?? 0) }}
                </span>
                <span>{{ $product->created_at?->diffForHumans() }}</span>
            </div>
        </div>
    </div>

    {{-- Featured Badge --}}
    @if($product->is_featured)
        <div class="self-start bg-amber-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0">
            ⭐ ویژه
        </div>
    @endif
</div>
