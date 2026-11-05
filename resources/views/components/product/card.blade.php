@props([
'product',
'view' => 'grid',
'supplier' => null,  // supplier مربوط به صفحه (برای دریافت قیمت‌های آن supplier)
])

@php
use App\Helpers\PriceHelper;

// ✅ یک بار تنظیمات نقش را می‌گیریم تا هر کارت چند کوئری تکراری نزند.
$priceVisibilityService = app(\App\Services\PriceVisibilityService::class);
$user = auth()->user();
$cardVisibilitySettings = $priceVisibilityService->getSettings($user);
$canSeeSupplier = $cardVisibilitySettings?->canSeeSupplierInfo() ?? false;
$canSeeWholesalePrice = $cardVisibilitySettings?->canSeeWholesalePrice() ?? false;
$allowedCardPriceTypes = $cardVisibilitySettings?->getAllowedPriceTypes();
$liveCardSupplierId = $supplier && $canSeeSupplier ? (int) $supplier->id : null;
$canSubscribeCardPrices = auth()->check() && $canSeeWholesalePrice && (!$supplier || $canSeeSupplier);
$priceSnapshotUrl = $canSubscribeCardPrices && Route::has('products.price-snapshot')
    ? route('products.price-snapshot', [
        'product' => $product->id,
        ...($liveCardSupplierId ? ['card_supplier_id' => $liveCardSupplierId] : []),
    ])
    : null;

// اگر supplier مشخص شده (در صفحه supplier)، قیمت‌های آن supplier را نمایش بده
$productSupplier = $supplier ?? ($product->relationLoaded('supplier') ? $product->supplier : null);
$category = $product->relationLoaded('category') ? $product->category : null;

$imageUrl = null;
$imageAlt = $product->title ?? 'محصول';

if ($product->relationLoaded('galleries') && $product->galleries->isNotEmpty()) {
    $primaryImage = $product->galleries->firstWhere('is_primary', true) ?? $product->galleries->first();
    if ($primaryImage) {
        $imageUrl = $primaryImage->getVersionUrl('sm');
        // اگر URL نداشتیم، از placeholder استفاده کن
        if (!$imageUrl) {
            $imageUrl = asset('images/placeholders/product-default.svg');
        }
        $imageAlt = $primaryImage->alt_text ?: (($product->title ?? 'محصول') . ' - خرید از ' . config('app.name'));
    }
}

if (!$imageUrl) {
    $imageUrl = asset('images/placeholders/product-default.svg');
}

$retailPrice = null;
if ($product->relationLoaded('retailPrices') && $product->retailPrices->isNotEmpty()) {
$retailPrice = $product->retailPrices
->where('is_published', true)
->sortBy('retail_price')
->first();
}

// ✅ بهبود یافته: نمایش ماتریس قیمت یا بهترین قیمت
$wholesalePriceData = null;
$priceMatrix = null;

if ($canSeeWholesalePrice && $product->relationLoaded('productSuppliers')) {
    // جمع‌آوری تمام قیمت‌های فعال از productSuppliers فعال
    $allPrices = collect();
    $activeSuppliersCount = 0; // ✅ شمارش تامین‌کنندگان با قیمت فعال
    $priceOffers = $product->productSuppliers;

    // کارت‌های عمومی خانوادهٔ محصول نمایندهٔ محصول اصلی هستند؛ قیمت‌های شعبه‌ها
    // روی رکوردهای وابسته ذخیره می‌شوند و باید در همان کارت لحاظ شوند.
    if (!$supplier && $product->relationLoaded('familyProducts')) {
        foreach ($product->familyProducts as $familyProduct) {
            if (!$familyProduct->is_active
                || $familyProduct->status?->value !== 'active'
                || !$familyProduct->relationLoaded('productSuppliers')) {
                continue;
            }

            $priceOffers = $priceOffers->merge($familyProduct->productSuppliers);
        }
    }

    $priceOffers = $priceOffers->unique(fn ($offer) => (int) $offer->getKey())->values();
    
    foreach ($priceOffers as $ps) {
        // ✅ فقط productSuppliers فعال را در نظر بگیر
        $offerSupplier = $ps->relationLoaded('supplier') ? $ps->supplier : null;
        if ($ps->is_active
            && $ps->relationLoaded('prices')
            && (!$ps->relationLoaded('supplier') || ($offerSupplier?->is_active && $offerSupplier?->is_verified))) {
            $tierEnabled = (array) ($ps->tier_enabled ?? []);
            $activePrices = $ps->prices->filter(function ($price) use ($allowedCardPriceTypes, $tierEnabled): bool {
                $type = $price->priceType?->code ?? 'cash';
                $tierIndex = (int) $price->tier_index;

                return $price->is_active
                    && $price->isValid()
                    && ($allowedCardPriceTypes === null || in_array($type, $allowedCardPriceTypes, true))
                    && (empty($tierEnabled) || (bool) ($tierEnabled[$tierIndex] ?? true));
            });
            if ($activePrices->isNotEmpty()) {
                $allPrices = $allPrices->merge($activePrices);
                $activeSuppliersCount++; // ✅ افزایش شمارنده فقط اگر قیمت فعال دارد
            }
        }
    }

    $allPrices = $allPrices
        ->groupBy(fn ($price) => implode(':', [
            $price->product_supplier_id,
            $price->price_type_id,
            $price->tier_index,
            $price->check_period_id ?? 0,
        ]))
        ->map(fn ($rows) => $rows->sortBy('price')->first())
        ->values();
    
    if ($allPrices->isNotEmpty()) {
        // اگر supplier مشخص شده، فقط قیمت‌های آن supplier را نمایش بده
        if ($supplier) {
            $supplierProductSupplierIds = $product->productSuppliers
                ->where('supplier_id', $supplier->id)
                ->pluck('id');
                
            $filteredPrices = $allPrices->filter(function($price) use ($supplierProductSupplierIds) {
                return $supplierProductSupplierIds->contains($price->product_supplier_id);
            });
            
            if ($filteredPrices->isNotEmpty()) {
                $lowestPrice = $filteredPrices->min('price');
                $highestPrice = $filteredPrices->max('price');
                
                $wholesalePriceData = [
                    'lowest' => $lowestPrice,
                    'highest' => $highestPrice,
                    'has_range' => $lowestPrice != $highestPrice,
                    'tiers_count' => $filteredPrices->pluck('tier_index')->unique()->count(),
                ];
            }
        } else {
            // محاسبه محدوده قیمت برای نمایش ماتریس
            $lowestPrice = $allPrices->min('price');
            $highestPrice = $allPrices->max('price');
            
            $wholesalePriceData = [
                'lowest' => $lowestPrice,
                'highest' => $highestPrice,
                'has_range' => $lowestPrice != $highestPrice,
                'tiers_count' => $allPrices->pluck('tier_index')->unique()->count(),
                'suppliers_count' => $activeSuppliersCount, // ✅ استفاده از شمارنده صحیح
            ];
        }
    }
}

$productUrl = route('products.show', $product->slug);

$supplierUrl = ($canSeeSupplier && $productSupplier && $productSupplier->slug)
? route('suppliers.show', $productSupplier->slug)
: null;

$categoryName = $category?->name;
$categorySlug = $category?->slug;
@endphp

@if($view === 'list')
<div class="group flex flex-row overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:border-amber-200 hover:shadow-md"
     @if($canSubscribeCardPrices) data-live-price-product="{{ $product->id }}" data-live-price-channel-role="{{ $priceVisibilityService->getUserRole($user) }}" data-live-price-snapshot-url="{{ $priceSnapshotUrl }}" @endif>
    <a href="{{ $productUrl }}" class="relative min-h-[170px] w-44 flex-shrink-0 overflow-hidden bg-gray-50 sm:w-52" 
       x-data="{
           handleError(event) {
               event.target.src = '{{ asset('images/placeholders/product-default.svg') }}';
               event.target.onerror = null;
           }
       }">
        <img
            src="{{ $imageUrl }}"
            alt="{{ $imageAlt }}"
            title="{{ $product->title }}"
            class="h-full w-full object-contain p-3 transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
            x-on:error="handleError($event)"
        >

        @if($product->is_featured)
        <span class="absolute right-2 top-2 rounded-full bg-gradient-to-l from-amber-500 to-yellow-500 px-2 py-0.5 text-[10px] font-bold text-white shadow">
                    ⭐ ویژه
                </span>
        @endif
    </a>

    <div class="flex min-w-0 flex-1 flex-col justify-between p-4">
        <div>
            @if($categoryName && $categorySlug)
            <a
                href="{{ route('products.index', ['category' => $categorySlug]) }}"
                class="mb-1 inline-block text-[11px] font-medium text-amber-700 transition hover:text-amber-800"
            >
                {{ $categoryName }}
            </a>
            @endif

            <a href="{{ $productUrl }}" class="block">
                <h3 class="mb-2 line-clamp-2 text-sm font-bold leading-relaxed text-gray-800 transition-colors group-hover:text-amber-700">
                    {{ $product->title }}
                </h3>
            </a>

            @if($product->short_description)
            <p class="mb-2 line-clamp-2 text-xs text-gray-500">
                {{ \Illuminate\Support\Str::limit(strip_tags($product->short_description), 150) }}
            </p>
            @endif

            @if($product->sku)
            <span class="text-[10px] text-gray-600 font-medium">کد: {{ $product->sku }}</span>
            @endif
        </div>

        <div class="mt-3 flex items-end justify-between border-t border-gray-50 pt-3">
            <div>
                @if($retailPrice)
                <div class="flex items-baseline gap-0.5">
                    <span class="text-lg font-extrabold text-emerald-600">
                        {{ PriceHelper::formatNumber($retailPrice->retail_price) }}
                    </span>
                </div>
                @endif

                @if($canSeeWholesalePrice)
                <div class="mt-0.5 flex items-center gap-1" @if($canSubscribeCardPrices) data-live-price-product="{{ $product->id }}" @endif data-live-price-card-range @if($liveCardSupplierId) data-live-price-card-supplier-id="{{ $liveCardSupplierId }}" @endif @if(!$wholesalePriceData) hidden @endif>
                    <x-heroicon-o-arrow-trending-up class="h-3 w-3 text-amber-600" />
                    <span class="text-xs font-semibold text-amber-700">
                        @if($supplier)
                            قیمت این تامین‌کننده:
                        @else
                            عمده:
                        @endif
                        <span data-live-price-card-min>{{ $wholesalePriceData ? PriceHelper::formatNumber($wholesalePriceData['lowest']) : '—' }}</span>
                        <span data-live-price-card-separator @if(!$wholesalePriceData || !$wholesalePriceData['has_range']) hidden @endif> - </span>
                        <span data-live-price-card-max @if(!$wholesalePriceData || !$wholesalePriceData['has_range']) hidden @endif>{{ $wholesalePriceData ? PriceHelper::formatNumber($wholesalePriceData['highest']) : '—' }}</span>
                        @if(!$supplier)
                        <span data-live-price-card-count @if(!$wholesalePriceData || $wholesalePriceData['tiers_count'] <= 1) hidden @endif>({{ $wholesalePriceData['tiers_count'] ?? 0 }} تیراژ)</span>
                        @endif
                    </span>
                </div>
                @elseif(!$canSeeWholesalePrice && Route::has('register'))
                <a href="{{ route('register') }}" class="mt-1 inline-block text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                    برای مشاهده قیمت عمده ثبت‌نام کنید
                </a>
                @endif

                @if(!$retailPrice && $canSeeWholesalePrice)
                <div @if($canSubscribeCardPrices) data-live-price-product="{{ $product->id }}" @endif data-live-price-empty-label data-live-price-range="card" class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 border border-red-200 px-2.5 py-1" @if($wholesalePriceData) hidden @endif>
                    <svg class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <x-product-unpriced-label class="text-xs font-bold text-red-600" />
                </div>
                @elseif(!$retailPrice && !$canSeeWholesalePrice)
                <div class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 border border-red-200 px-2.5 py-1">
                    <svg class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <x-product-unpriced-label class="text-xs font-bold text-red-600" />
                </div>
                @endif
            </div>

            @if($canSeeSupplier && $supplier)
            @if($supplierUrl)
            <a
                href="{{ $supplierUrl }}"
                class="group/supplier flex items-center gap-1.5 rounded-lg px-2 py-1 transition-colors hover:bg-amber-50"
                title="مشاهده پروفایل {{ $supplier->company_name }}"
            >
                @if($supplier->logo_path)
                <img
                    src="{{ $supplier->logoUrl('small') }}"
                    alt="{{ $supplier->company_name }}"
                    class="h-5 w-5 rounded-full border object-cover"
                >
                @endif

                <span class="text-[10px] text-gray-500 transition-colors group-hover/supplier:text-amber-700 group-hover/supplier:underline">
                                {{ $supplier->company_name }}
                            </span>

                @if($supplier->is_verified)
                <x-heroicon-s-check-badge class="h-3.5 w-3.5 shrink-0 text-amber-600" />
                @endif

                <x-heroicon-o-arrow-top-right-on-square class="h-3 w-3 shrink-0 text-gray-300 transition-colors group-hover/supplier:text-amber-500 rtl:rotate-180" />
            </a>
            @else
            <div class="flex items-center gap-1.5 rounded-lg px-2 py-1">
                @if($supplier->logo_path)
                <img
                    src="{{ $supplier->logoUrl('small') }}"
                    alt="{{ $supplier->company_name }}"
                    class="h-5 w-5 rounded-full border object-cover"
                >
                @endif

                <span class="text-[10px] text-gray-500">
                                {{ $supplier->company_name }}
                            </span>

                @if($supplier->is_verified)
                <x-heroicon-s-check-badge class="h-3.5 w-3.5 shrink-0 text-amber-600" />
                @endif
            </div>
            @endif
            @endif
        </div>
    </div>
</div>
@else
{{-- ═══════════════════════════════════════════════════════════
     GRID VIEW - کارت محصول بهبود یافته
     طراحی شده با همکاری کارشناسان گرافیک، فرانت‌اند و UX
     ═══════════════════════════════════════════════════════════ --}}
<div class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:border-amber-300 hover:shadow-xl hover:-translate-y-1"
     @if($canSubscribeCardPrices) data-live-price-product="{{ $product->id }}" data-live-price-channel-role="{{ $priceVisibilityService->getUserRole($user) }}" data-live-price-snapshot-url="{{ $priceSnapshotUrl }}" @endif
     x-data="{ imageLoaded: false }"
     x-init="$nextTick(() => { imageLoaded = true; })">
    
    {{-- Skeleton Loading --}}
    <div x-show="!imageLoaded" 
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute inset-0 z-10 bg-white">
        <div class="animate-pulse h-full flex flex-col">
            <div class="aspect-square bg-gray-200"></div>
            <div class="flex-1 p-3 space-y-2">
                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                <div class="flex-1"></div>
                <div class="h-10 bg-gray-200 rounded-lg"></div>
            </div>
        </div>
    </div>
    
    {{-- Product Image با Overlay جذاب --}}
    <a href="{{ $productUrl }}" class="relative aspect-square overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100"
       x-data="{
           handleError(event) {
               event.target.src = '{{ asset('images/placeholders/product-default.svg') }}';
               event.target.onerror = null;
           }
       }">
        <img
            src="{{ $imageUrl }}"
            alt="{{ $imageAlt }}"
            title="{{ $product->title }}"
            width="208"
            height="208"
            class="h-full w-full object-contain p-3 transition-all duration-500 group-hover:scale-110 group-hover:rotate-2"
            loading="lazy"
            decoding="async"
            x-on:error="handleError($event)"
            @load="imageLoaded = true"
        >

        {{-- Badge ویژه با انیمیشن --}}
        @if($product->is_featured)
        <div class="absolute right-3 top-3 animate-bounce">
            <span class="inline-flex items-center gap-1 rounded-xl bg-gradient-to-l from-amber-500 via-yellow-500 to-amber-400 px-3 py-1.5 text-[10px] font-bold text-white shadow-lg shadow-amber-500/50">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                ویژه
            </span>
        </div>
        @endif

        {{-- Quick View Overlay --}}
        <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 transition-all duration-300 group-hover:opacity-100">
            <div class="transform translate-y-4 transition-all duration-300 group-hover:translate-y-0">
                <span class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-gray-800 shadow-2xl backdrop-blur-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    مشاهده جزئیات
                </span>
            </div>
        </div>
    </a>

    {{-- Product Info Section --}}
    <div class="flex flex-1 flex-col p-1">
        
        {{-- Category Badge --}}
        @if($categoryName && $categorySlug)
        <a
            href="{{ route('products.index', ['category' => $categorySlug]) }}"
            class="mb-2 inline-flex items-center gap-1 w-fit rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 px-2.5 py-1 text-[10px] sm:text-[11px] font-bold text-blue-700 transition-all hover:from-blue-100 hover:to-indigo-100"
        >
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            {{ $categoryName }}
        </a>
        @endif

        {{-- Product Title --}}
        <a href="{{ $productUrl }}" class="block flex-1">
            <h3 class="mb-2 line-clamp-2 text-xs sm:text-sm font-bold leading-snug text-gray-800 transition-colors group-hover:text-amber-600">
                {{ $product->title }}
            </h3>
        </a>

        {{-- Divider --}}
        <div class="border-t border-gray-100 mb-2"></div>

        {{-- Price Section با طراحی جذاب --}}
        <div class="mt-auto space-y-1.5">
            {{-- Retail Price --}}
            @if($retailPrice)
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-xs font-medium text-gray-400">قیمت خرده‌فروشی</span>
                <div class="flex items-baseline gap-0.5">
                    <span class="text-sm sm:text-base font-extrabold bg-gradient-to-l from-emerald-600 to-green-600 bg-clip-text text-transparent">
                        {{ PriceHelper::formatNumber($retailPrice->retail_price) }}
                    </span>
                </div>
            </div>
            @endif

            {{-- Wholesale Price با طراحی مینیمال --}}
            @if($canSeeWholesalePrice)
            <div class="rounded-xl bg-amber-50 p-2.5 border border-amber-200" @if($canSubscribeCardPrices) data-live-price-product="{{ $product->id }}" @endif data-live-price-card-range @if($liveCardSupplierId) data-live-price-card-supplier-id="{{ $liveCardSupplierId }}" @endif @if(!$wholesalePriceData) hidden @endif>
                <div class="flex items-baseline justify-center gap-1 flex-wrap">
                    <span class="text-sm sm:text-base font-extrabold text-amber-700" data-live-price-card-min>{{ $wholesalePriceData ? PriceHelper::formatNumber($wholesalePriceData['lowest']) : '—' }}</span>
                    <span class="text-xs sm:text-sm text-amber-600" data-live-price-card-separator @if(!$wholesalePriceData || !$wholesalePriceData['has_range']) hidden @endif>تا</span>
                    <span class="text-xs sm:text-sm font-extrabold text-amber-700" data-live-price-card-max @if(!$wholesalePriceData || !$wholesalePriceData['has_range']) hidden @endif>{{ $wholesalePriceData ? PriceHelper::formatNumber($wholesalePriceData['highest']) : '—' }}</span>
                </div>
            </div>
            @elseif(!$canSeeWholesalePrice && Route::has('register'))
            <a href="{{ route('register') }}" class="block text-center w-full rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-amber-500/30 transition-all hover:shadow-xl hover:shadow-amber-500/40 hover:scale-105">
                <div class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    مشاهده قیمت عمده
                </div>
            </a>
            @endif

            {{-- Contact for Price - بهبود یافته (Updated: 2026-09-26) --}}
            @if(!$retailPrice && $canSeeWholesalePrice)
            <div @if($canSubscribeCardPrices) data-live-price-product="{{ $product->id }}" @endif data-live-price-empty-label data-live-price-range="card" class="flex items-center justify-center gap-1.5 rounded-xl bg-red-50 border border-red-200 px-3 py-2" @if($wholesalePriceData) hidden @endif>
                <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <x-product-unpriced-label class="text-xs font-bold text-red-600 sm:text-sm" />
            </div>
            @elseif(!$retailPrice && !$canSeeWholesalePrice)
            <div class="flex items-center justify-center gap-1.5 rounded-xl bg-red-50 border border-red-200 px-3 py-2">
                <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <x-product-unpriced-label class="text-xs font-bold text-red-600 sm:text-sm" />
            </div>
            @endif
        </div>
    </div>

    {{-- Hover Effect Border --}}
    <div class="absolute inset-0 rounded-2xl border-2 border-amber-400 opacity-0 transition-opacity duration-300 group-hover:opacity-100 pointer-events-none"></div>
</div>
@endif
