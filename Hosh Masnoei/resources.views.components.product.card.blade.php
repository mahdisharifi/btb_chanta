@props([
'product',
'view' => 'grid',
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

$supplier = $product->relationLoaded('supplier') ? $product->supplier : null;
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
$wholesalePrice = $product->prices
->where('is_active', true)
->sortBy('price')
->first();
}

$productUrl = route('products.show', $product->slug);

$supplierUrl = ($canSeeSupplier && $supplier && $supplier->slug)
? route('suppliers.show', $supplier->slug)
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
                                عمده: {{ PriceHelper::formatNumber($wholesalePrice->price) }} تومان
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
<div class="group flex h-full flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:border-amber-200 hover:shadow-md">
    <a href="{{ $productUrl }}" class="relative aspect-square overflow-hidden bg-gray-50">
        <img
            src="{{ $imageUrl }}"
            alt="{{ $imageAlt }}"
            title="{{ $product->title }}"
            class="h-full w-full object-contain p-4 transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
            onerror="this.onerror=null; this.src='{{ asset('images/placeholders/product-default.svg') }}';"
        >

        @if($product->is_featured)
        <span class="absolute right-3 top-3 rounded-full bg-gradient-to-l from-amber-500 to-yellow-500 px-2.5 py-1 text-[10px] font-bold text-white shadow-lg">
                    ⭐ ویژه
                </span>
        @endif

        <div class="absolute inset-0 flex items-center justify-center bg-black/0 transition-colors group-hover:bg-black/5">
                <span class="translate-y-4 transform rounded-full bg-white/90 px-4 py-2 text-xs font-medium text-gray-700 opacity-0 shadow-lg backdrop-blur-sm transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                    مشاهده محصول
                </span>
        </div>
    </a>

    <div class="flex flex-1 flex-col p-4">
        @if($categoryName && $categorySlug)
        <a
            href="{{ route('products.index', ['category' => $categorySlug]) }}"
            class="mb-1.5 inline-block w-fit text-[11px] font-medium text-amber-700 transition hover:text-amber-800"
        >
            {{ $categoryName }}
        </a>
        @endif

        <a href="{{ $productUrl }}" class="block flex-1">
            <h3 class="mb-3 line-clamp-2 text-sm font-bold leading-relaxed text-gray-800 transition-colors group-hover:text-amber-700">
                {{ $product->title }}
            </h3>
        </a>
<!--        @if($canSeeWholesalePrice && $canSeeSupplier && $supplier)-->
<!--        @if($supplierUrl)-->
<!--        <a-->
<!--            href="{{ $supplierUrl }}"-->
<!--            class="group/supplier -mx-1.5 mb-3 flex items-center gap-1.5 rounded-lg border-b border-gray-50 px-1.5 py-1 pb-3 transition-colors hover:bg-amber-50"-->
<!--            title="مشاهده پروفایل {{ $supplier->company_name }}"-->
<!--        >-->
<!--            @if($supplier->logo_path)-->
<!--            <img-->
<!--                src="{{ asset('storage/' . $supplier->logo_path) }}"-->
<!--                alt="{{ $supplier->company_name }}"-->
<!--                class="h-5 w-5 rounded-full border border-gray-200 object-cover"-->
<!--            >-->
<!--            @else-->
<!--            <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-200">-->
<!--                <x-heroicon-o-building-office-2 class="h-3 w-3 text-gray-400" />-->
<!--            </div>-->
<!--            @endif-->
<!---->
<!--            <span class="truncate text-[11px] text-gray-500 transition-colors group-hover/supplier:text-amber-700 group-hover/supplier:underline">-->
<!--                            {{ $supplier->company_name }}-->
<!--                        </span>-->
<!---->
<!--            @if($supplier->is_verified)-->
<!--            <x-heroicon-s-check-badge class="h-3.5 w-3.5 flex-shrink-0 text-amber-600" />-->
<!--            @endif-->
<!---->
<!--            <x-heroicon-o-arrow-top-right-on-square class="mr-auto h-3 w-3 shrink-0 text-gray-300 transition-colors group-hover/supplier:text-amber-500 rtl:rotate-180" />-->
<!--        </a>-->
<!--        @else-->
<!--        <div class="-mx-1.5 mb-3 flex items-center gap-1.5 rounded-lg border-b border-gray-50 px-1.5 py-1 pb-3">-->
<!--            @if($supplier->logo_path)-->
<!--            <img-->
<!--                src="{{ asset('storage/' . $supplier->logo_path) }}"-->
<!--                alt="{{ $supplier->company_name }}"-->
<!--                class="h-5 w-5 rounded-full border border-gray-200 object-cover"-->
<!--            >-->
<!--            @else-->
<!--            <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-200">-->
<!--                <x-heroicon-o-building-office-2 class="h-3 w-3 text-gray-400" />-->
<!--            </div>-->
<!--            @endif-->
<!---->
<!--            <span class="truncate text-[11px] text-gray-500">-->
<!--                            {{ $supplier->company_name }}-->
<!--                        </span>-->
<!---->
<!--            @if($supplier->is_verified)-->
<!--            <x-heroicon-s-check-badge class="h-3.5 w-3.5 flex-shrink-0 text-amber-600" />-->
<!--            @endif-->
<!--        </div>-->
<!--        @endif-->
<!--        @endif-->

        <div class="mt-auto">
            @if($retailPrice)
            <div class="flex items-baseline justify-between">
                <div class="flex items-baseline gap-1">
                            <span class="text-lg font-extrabold text-emerald-600">
                                {{ PriceHelper::formatNumber($retailPrice->retail_price) }}
                            </span>
                    <span class="text-[10px] text-gray-400">تومان</span>
                </div>
            </div>
            @endif
            @if($canSeeWholesalePrice && $wholesalePrice)
            <div class="mt-1 flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-1">
                <x-heroicon-o-arrow-trending-up class="h-3 w-3 text-amber-600" />
                <span class="text-[11px] font-semibold text-amber-700">
                            عمده: {{ PriceHelper::formatNumber($wholesalePrice->price) }} تومان
                        </span>

                @if($wholesalePrice->min_quantity > 1)
                <span class="mr-auto text-[9px] text-amber-500">
                                (حداقل {{ $wholesalePrice->min_quantity }} عدد)
                            </span>
                @endif
            </div>
            @elseif(!$canSeeWholesalePrice && Route::has('register'))
            <a href="{{ route('register') }}" class="mt-2 inline-block text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                برای مشاهده قیمت عمده ثبت‌نام کنید
            </a>
            @else

            <div class="flex items-center gap-1.5 text-gray-400">
                <x-heroicon-o-phone class="h-4 w-4" />
                <span class="text-sm font-medium">تماس بگیرید</span>
            </div>
            @endif
        </div>
    </div>
</div>
@endif
