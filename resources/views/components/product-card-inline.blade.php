{{-- resources/views/components/product-card-inline.blade.php --}}
{{--
    متغیرهای مورد نیاز:
    - $product (Model: Product) — با روابط galleries, prices, retailPrices, category, supplier
    - $badge (string|null) — 'featured', 'new', 'popular' یا null
    - $userRole (string) — از متغیر والد @section ارث‌بری می‌کند
--}}

@php
    // ✅ استفاده از Service برای دریافت دسترسی‌ها
    $priceVisibilityService = app(\App\Services\PriceVisibilityService::class);
    $user = auth()->user();
    
    $userRole = $userRole ?? $priceVisibilityService->getUserRole($user);
    $badge    = $badge ?? null;

    $canSeePrices    = $priceVisibilityService->canSeeRetailPrice($user);
    $canSeeWholesale = $priceVisibilityService->canSeeWholesalePrice($user);
    $canSeeSupplier  = $priceVisibilityService->canSeeSupplierInfo($user);

    // تصویر اصلی
    $gallery  = $product->relationLoaded('galleries') ? $product->galleries->first() : null;
    $imageUrl = $gallery?->file_path ? asset('storage/' . $gallery->file_path) : null;

    // قیمت‌ها - استفاده از ساختار جدید productSuppliers
    $wholesalePrices = collect();
    if ($product->relationLoaded('productSuppliers')) {
        foreach ($product->productSuppliers as $ps) {
            if ($ps->relationLoaded('prices')) {
                $wholesalePrices = $wholesalePrices->merge($ps->prices->where('is_active', true));
            }
        }
    }
    
    $retailPriceModel = $product->relationLoaded('retailPrices') ? $product->retailPrices->first() : null;

    $lowestWholesale  = $wholesalePrices->isNotEmpty() ? $wholesalePrices->min('price') : null;
    $highestWholesale = $wholesalePrices->isNotEmpty() ? $wholesalePrices->max('price') : null;
    $retailPrice      = $retailPriceModel?->retail_price;

    // تخفیف عمده نسبت به خرده‌فروشی
    $discount = null;
    if ($retailPrice && $lowestWholesale && $retailPrice > $lowestWholesale) {
        $discount = round((($retailPrice - $lowestWholesale) / $retailPrice) * 100);
    }
@endphp

<a href="{{ route('products.show', $product) }}"
   class="group bg-white rounded-2xl border border-gray-100 hover:border-blue-200
          hover:shadow-xl hover:shadow-blue-100/50 transition-all duration-300 overflow-hidden
          flex flex-col h-full">

    {{-- ═══ IMAGE ═══ --}}
    <div class="relative aspect-square bg-gray-50 overflow-hidden">
        @if($imageUrl)
            <img src="{{ $imageUrl }}"
                 alt="{{ $product->title }}"
                 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                 loading="lazy">
        @else
            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100">
                <svg class="w-16 h-16 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        @endif

        {{-- ═══ Badges ═══ --}}
        <div class="absolute top-2 right-2 flex flex-col gap-1.5">
            @if($badge === 'featured' || $product->is_featured)
                <span class="bg-gradient-to-r from-amber-400 to-orange-500 text-white text-[10px] font-bold
                             px-2 py-0.5 rounded-full shadow-sm flex items-center gap-1">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    ویژه
                </span>
            @endif

            @if($badge === 'new')
                <span class="bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                    جدید
                </span>
            @endif

            @if($badge === 'popular' && $product->view_count > 0)
                <span class="bg-rose-500/90 backdrop-blur-sm text-white text-[10px] font-bold
                             px-2 py-0.5 rounded-full shadow-sm flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ number_format($product->view_count) }}
                </span>
            @endif

            @if($discount && $canSeeWholesale)
                <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                    {{ $discount }}%−
                </span>
            @endif
        </div>

        {{-- ═══ Hover Overlay ═══ --}}
        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/50 via-black/20 to-transparent
                    p-3 translate-y-full group-hover:translate-y-0 transition-transform duration-300">
            <span class="block w-full text-center text-white text-xs font-medium bg-blue-600/80 backdrop-blur-sm
                         rounded-lg py-2 hover:bg-blue-600 transition">
                مشاهده جزئیات محصول
            </span>
        </div>
    </div>

    {{-- ═══ CONTENT ═══ --}}
    <div class="p-3 sm:p-4 flex-1 flex flex-col">

        {{-- Category Badge --}}
        @if($product->category)
            <span class="text-[10px] sm:text-[11px] font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full self-start mb-2 truncate max-w-full">
                {{ $product->category->name }}
            </span>
        @endif

        {{-- Title --}}
        <h3 class="font-bold text-gray-800 text-xs sm:text-sm leading-snug line-clamp-2 group-hover:text-blue-600 transition flex-1 mb-2">
            {{ $product->title }}
        </h3>

        {{-- Supplier Info --}}
        @if($canSeeSupplier && $product->supplier)
            <p class="text-[11px] text-gray-400 mb-2 truncate flex items-center gap-1">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span class="truncate">{{ $product->supplier->brand_name ?: $product->supplier->company_name }}</span>
                @if($product->supplier->is_verified)
                    <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </p>
        @endif

        {{-- ═══ PRICE SECTION ═══ --}}
        <div class="mt-auto pt-3 border-t border-gray-50">

            {{-- Case 1: عمده --}}
            @if($canSeeWholesale && $lowestWholesale)
                <div class="flex items-baseline justify-between flex-wrap gap-1">
                    <div>
                        <span class="text-sm sm:text-base font-black text-blue-600 tracking-tight">
                            {{ number_format($lowestWholesale) }}
                        </span>
                        <span class="text-[10px] sm:text-xs text-gray-400 font-medium mr-0.5">تومان</span>
                    </div>
                    @if($highestWholesale && $highestWholesale != $lowestWholesale)
                        <span class="text-[10px] sm:text-xs text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded">
                            تا {{ number_format($highestWholesale) }}
                        </span>
                    @endif
                </div>

                @if($retailPrice && $retailPrice > $lowestWholesale)
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[11px] text-gray-400 line-through decoration-red-400">
                            {{ number_format($retailPrice) }}
                        </span>
                        <span class="text-[10px] font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded-full">
                            {{ $discount }}% تخفیف عمده
                        </span>
                    </div>
                @endif

            {{-- Case 2: خرده --}}
            @elseif($canSeePrices && $retailPrice)
                <div>
                    <span class="text-xs sm:text-base font-black text-emerald-600 tracking-tight">
                        {{ number_format($retailPrice) }}
                    </span>
                    <span class="text-[10px] sm:text-xs text-gray-400 font-medium mr-0.5">تومان</span>
                </div>

            {{-- Case 3: مهمان --}}
            @elseif($userRole === 'guest')
                <div class="flex items-center gap-1.5 text-gray-400">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <span class="text-[10px] sm:text-xs">برای مشاهده قیمت وارد شوید</span>
                </div>

            {{-- Case 4: بدون قیمت --}}
            @else
                <span class="text-xs text-gray-300 italic">قیمت‌گذاری نشده</span>
            @endif
        </div>
    </div>
</a>
