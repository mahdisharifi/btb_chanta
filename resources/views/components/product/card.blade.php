@props([
'product',
'view' => 'grid',
'supplier' => null,  // supplier مربوط به صفحه (برای دریافت قیمت‌های آن supplier)
])

@php
use App\Helpers\PriceHelper;

$user = auth()->user();
$userRole = 'guest';

if ($user) {
if ($user->hasRole('super_admin')) {
$userRole = 'super_admin';
} elseif ($user->hasRole('supplier')) {
$userRole = 'supplier';
} elseif ($user->hasRole('retailer_premium')) {
$userRole = 'retailer_premium';
} elseif ($user->hasRole('retailer_basic')) {
$userRole = 'retailer_basic';
} else {
$userRole = 'end_user';
}
}

$canSeeSupplier = in_array($userRole, ['super_admin', 'supplier', 'retailer_premium'], true);
$canSeeWholesalePrice = in_array($userRole, ['super_admin', 'supplier', 'retailer_premium'], true);

// اگر supplier مشخص شده (در صفحه supplier)، قیمت‌های آن supplier را نمایش بده
$productSupplier = $supplier ?? ($product->relationLoaded('supplier') ? $product->supplier : null);
$category = $product->relationLoaded('category') ? $product->category : null;

$imageUrl = null;
$imageAlt = $product->title ?? 'محصول';

if ($product->relationLoaded('galleries') && $product->galleries->isNotEmpty()) {

$primaryImage = $product->galleries->firstWhere('is_primary', true) ?? $product->galleries->first();
if ($primaryImage) {
$imageUrl = $product->primaryImage?->getVersionUrl('sm');
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

$wholesalePrice = null;
if ($canSeeWholesalePrice && $product->prices->isNotEmpty()) {
    // اگر supplier مشخص شده، فقط قیمت‌های آن supplier را نمایش بده
    if ($supplier) {
        $wholesalePrice = $product->prices
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->sortBy('price')
            ->first();
    } else {
        // در غیر این صورت، کمترین قیمت را نمایش بده
        $wholesalePrice = $product->prices
            ->where('is_active', true)
            ->sortBy('price')
            ->first();
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
<div class="group flex flex-row overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:border-amber-200 hover:shadow-md">
    <a href="{{ $productUrl }}" class="relative min-h-[170px] w-44 flex-shrink-0 overflow-hidden bg-gray-50 sm:w-52">
        <img
            src="{{ $imageUrl }}"
            alt="{{ $imageAlt }}"
            title="{{ $product->title }}"
            class="h-full w-full object-contain p-3 transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
            onerror="this.onerror=null; this.src='{{ asset('images/placeholders/product-default.svg') }}';"
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
            <span class="text-[10px] text-gray-400">کد: {{ $product->sku }}</span>
            @endif
        </div>

        <div class="mt-3 flex items-end justify-between border-t border-gray-50 pt-3">
            <div>
                @if($retailPrice)
                <div class="flex items-center gap-1">
                            <span class="text-lg font-extrabold text-emerald-600">
                                {{ PriceHelper::formatNumber($retailPrice->retail_price) }}
                            </span>
                    <span class="text-[10px] text-gray-400">تومان</span>
                </div>
                @endif

                @if($canSeeWholesalePrice && $wholesalePrice)
                <div class="mt-0.5 flex items-center gap-1">
                    <x-heroicon-o-arrow-trending-up class="h-3 w-3 text-amber-600" />
                    <span class="text-xs font-semibold text-amber-700">
                        @if($supplier)
                            قیمت این تامین‌کننده: {{ PriceHelper::formatNumber($wholesalePrice->price) }} تومان
                        @else
                            عمده: {{ PriceHelper::formatNumber($wholesalePrice->price) }} تومان
                        @endif
                    </span>
                </div>
                @elseif(!$canSeeWholesalePrice && Route::has('register'))
                <a href="{{ route('register') }}" class="mt-1 inline-block text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                    برای مشاهده قیمت عمده ثبت‌نام کنید
                </a>
                @endif

                @if(!$retailPrice && !($canSeeWholesalePrice && $wholesalePrice))
                <span class="text-sm font-medium text-gray-400">تماس بگیرید</span>
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
                    src="{{ asset('storage/' . $supplier->logo_path) }}"
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
                    src="{{ asset('storage/' . $supplier->logo_path) }}"
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
<div class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:border-amber-300 hover:shadow-xl hover:-translate-y-1">
    
    {{-- Product Image با Overlay جذاب --}}
    <a href="{{ $productUrl }}" class="relative aspect-square overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100">
        <img
            src="{{ $imageUrl }}"
            alt="{{ $imageAlt }}"
            title="{{ $product->title }}"
            class="h-full w-full object-contain p-5 transition-all duration-500 group-hover:scale-110 group-hover:rotate-2"
            loading="lazy"
            onerror="this.onerror=null; this.src='{{ asset('images/placeholders/product-default.svg') }}';"
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
    <div class="flex flex-1 flex-col p-4">
        
        {{-- Category Badge --}}
        @if($categoryName && $categorySlug)
        <a
            href="{{ route('products.index', ['category' => $categorySlug]) }}"
            class="mb-2 inline-flex items-center gap-1 w-fit rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 px-2.5 py-1 text-[11px] font-bold text-blue-700 transition-all hover:from-blue-100 hover:to-indigo-100"
        >
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            {{ $categoryName }}
        </a>
        @endif

        {{-- Product Title --}}
        <a href="{{ $productUrl }}" class="block flex-1">
            <h3 class="mb-3 line-clamp-2 text-sm font-bold leading-relaxed text-gray-800 transition-colors group-hover:text-amber-600">
                {{ $product->title }}
            </h3>
        </a>

        {{-- SKU --}}
        @if($product->sku)
        <div class="mb-3 flex items-center gap-1.5 text-[10px] text-gray-400">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
            <span>کد: {{ $product->sku }}</span>
        </div>
        @endif

        {{-- Divider --}}
        <div class="border-t border-gray-100 my-3"></div>

        {{-- Price Section با طراحی جذاب --}}
        <div class="mt-auto space-y-2">
            {{-- Retail Price --}}
            @if($retailPrice)
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-medium text-gray-400">قیمت خرده‌فروشی</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-lg font-extrabold bg-gradient-to-l from-emerald-600 to-green-600 bg-clip-text text-transparent">
                        {{ PriceHelper::formatNumber($retailPrice->retail_price) }}
                    </span>
                    <span class="text-[10px] text-gray-400">تومان</span>
                </div>
            </div>
            @endif

            {{-- Wholesale Price --}}
            @if($canSeeWholesalePrice && $wholesalePrice)
            <div class="rounded-xl bg-gradient-to-br from-amber-50 via-yellow-50 to-amber-50 p-3 border border-amber-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        <span class="text-[10px] font-bold text-amber-700">
                            @if($supplier)
                                قیمت این تامین‌کننده
                            @else
                                قیمت عمده
                            @endif
                        </span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-base font-extrabold text-amber-700">
                            {{ PriceHelper::formatNumber($wholesalePrice->price) }}
                        </span>
                        <span class="text-[9px] text-amber-600">تومان</span>
                    </div>
                </div>
                
                @if($wholesalePrice->min_quantity > 1)
                <div class="mt-1.5 flex items-center gap-1 text-[9px] text-amber-600">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span class="font-medium">حداقل {{ $wholesalePrice->min_quantity }} عدد</span>
                </div>
                @endif
            </div>
            @elseif(!$canSeeWholesalePrice && Route::has('register'))
            <a href="{{ route('register') }}" class="block text-center w-full rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-amber-500/30 transition-all hover:shadow-xl hover:shadow-amber-500/40 hover:scale-105">
                <div class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    مشاهده قیمت عمده
                </div>
            </a>
            @endif

            {{-- Contact for Price --}}
            @if(!$retailPrice && !($canSeeWholesalePrice && $wholesalePrice))
            <div class="flex items-center justify-center gap-2 rounded-xl bg-gray-50 p-3 text-gray-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <span class="text-sm font-bold">تماس بگیرید</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Hover Effect Border --}}
    <div class="absolute inset-0 rounded-2xl border-2 border-amber-400 opacity-0 transition-opacity duration-300 group-hover:opacity-100 pointer-events-none"></div>
</div>
@endif
