
{{-- ═══════════════════════════════════════════════════════════════
صفحه جزئیات محصول — show.blade.php
پشتیبانی از چند تأمین‌کننده، مقایسه قیمت، SEO کامل
═══════════════════════════════════════════════════════════════ --}}

@extends('layouts.app')

@section('title', $seo['title'] ?? $product->title)

@php
use App\Helpers\PriceHelper;
@endphp

@section('seo')
{{-- Meta Tags --}}
<meta name="description" content="{{ $seo['description'] ?? '' }}">
<meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
<link rel="canonical" href="{{ $seo['canonical_url'] ?? url()->current() }}">

{{-- Open Graph --}}
<meta property="og:title" content="{{ $seo['og_title'] ?? $product->title }}">
<meta property="og:description" content="{{ $seo['og_description'] ?? '' }}">
<meta property="og:image" content="{{ $seo['og_image'] ?? '' }}">
<meta property="og:type" content="{{ $seo['og_type'] ?? 'product' }}">
<meta property="og:url" content="{{ $seo['og_url'] ?? url()->current() }}">

{{-- Schema.org JSON-LD --}}
@if(!empty($seo['schema']))
<script type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
@if(!empty($seo['breadcrumb_schema']))
<script type="application/ld+json">{!! json_encode($seo['breadcrumb_schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
@endsection

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        {{-- ═══════════════════════════════════════════════════════
        BREADCRUMB
        ═══════════════════════════════════════════════════════ --}}
        @if(!empty($breadcrumbs))
        <nav class="mb-6" aria-label="Breadcrumb">
            <ol class="flex items-center flex-wrap gap-1.5 text-sm text-gray-500">
                @foreach($breadcrumbs as $index => $crumb)
                <li class="flex items-center">
                    @if($index > 0)
                    <svg class="w-4 h-4 text-gray-300 mx-1.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    @endif
                    @if($crumb['url'])
                    <a href="{{ $crumb['url'] }}"
                       class="hover:text-blue-600 transition-colors duration-200 font-medium">
                        {{ $crumb['name'] }}
                    </a>
                    @else
                    <span class="text-gray-800 font-semibold">{{ $crumb['name'] }}</span>
                    @endif
                </li>
                @endforeach
            </ol>
        </nav>
        @endif

        {{-- ═══════════════════════════════════════════════════════
        MAIN PRODUCT SECTION (Gallery + Info)
        ═══════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-10">



            {{-- ──── LEFT: Product Gallery ──── --}}
            @php
            // جمع‌آوری تصاویر و ویدئوها
            $allMedia = $product->allMediaSorted->map(function ($gallery) use ($product) {
                return [
                    'id' => $gallery->id,
                    'type' => $gallery->file_type, // 'image' یا 'video'
                    'url' => $gallery->file_type === 'image' ? $gallery->getVersionUrl('xl') : $gallery->full_url,
                    'thumb' => $gallery->file_type === 'image' ? $gallery->getVersionUrl('sm') : $gallery->getVideoThumbnailUrl(),
                    'alt' => $gallery->alt_text ?? $product->title,
                    'title' => $gallery->title,
                ];
            })->values();
            @endphp

            <div class="lg:col-span-5" x-data="{
    activeMedia: 0,
    zoomOpen: false,
    media: {{ Js::from($allMedia) }},
    get currentMedia() { return this.media[this.activeMedia] || this.media[0] || null; },
    get isVideo() { return this.currentMedia?.type === 'video'; },
    get isImage() { return this.currentMedia?.type === 'image'; },
    next() { this.activeMedia = (this.activeMedia + 1) % this.media.length; },
    prev() { this.activeMedia = (this.activeMedia - 1 + this.media.length) % this.media.length; },
    openZoom() { if (this.isImage) this.zoomOpen = true; },
    closeZoom() { this.zoomOpen = false; },
}">

                {{-- Main Media Display --}}
                <div class="relative bg-white rounded-2xl border border-gray-200 overflow-hidden aspect-square mb-4 shadow-sm group">
                    {{-- Image Display --}}
                    <template x-if="currentMedia && isImage">
                        <div @click="openZoom()" class="cursor-zoom-in">
                            <img :src="currentMedia.url"
                                 :alt="currentMedia.alt"
                                 width="800" height="800"
                                 loading="eager"
                                 fetchpriority="high"
                                 decoding="async"
                                 onerror="this.src='{{ asset('images/placeholder.webp') }}'"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 class="w-full h-full object-contain p-4 transition-transform duration-300 group-hover:scale-105">
                            
                            {{-- Zoom Icon Hint --}}
                            <div class="absolute bottom-3 left-3 bg-black/60 text-white px-3 py-1.5 rounded-lg text-xs flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <x-heroicon-o-magnifying-glass-plus class="w-4 h-4" />
                                <span>کلیک برای بزرگنمایی</span>
                            </div>
                        </div>
                    </template>

                    {{-- Video Display --}}
                    <template x-if="currentMedia && isVideo">
                        <div class="w-full h-full flex items-center justify-center p-4">
                            <video 
                                :src="currentMedia.url"
                                :poster="currentMedia.thumb"
                                controls
                                class="w-full h-full object-contain rounded-lg"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100">
                                مرورگر شما از پخش ویدئو پشتیبانی نمی‌کند.
                            </video>
                        </div>
                    </template>

                    {{-- No Media Placeholder --}}
                    <template x-if="!currentMedia">
                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                            <svg class="w-24 h-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </template>

                    {{-- Navigation Arrows --}}
                    <template x-if="media.length > 1">
                        <div>
                            <button @click="prev()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm shadow-lg flex items-center justify-center text-gray-600 hover:bg-white hover:text-blue-600 transition-all opacity-0 group-hover:opacity-100 z-10">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <button @click="next()"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 backdrop-blur-sm shadow-lg flex items-center justify-center text-gray-600 hover:bg-white hover:text-blue-600 transition-all opacity-0 group-hover:opacity-100 z-10">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                        </div>
                    </template>

                    {{-- Featured Badge --}}
                    @if($product->is_featured)
                    <span class="absolute top-3 right-3 bg-amber-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg z-10">
                            ⭐ ویژه
                        </span>
                    @endif
                </div>

                {{-- Thumbnail Gallery --}}
                <template x-if="media.length > 1">
                    <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-gray-300">
                        <template x-for="(item, index) in media" :key="index">
                            <button @click="activeMedia = index"
                                    class="relative shrink-0 w-16 h-16 rounded-xl border-2 overflow-hidden transition-all duration-200"
                                    :class="activeMedia === index
                                        ? 'border-blue-500 shadow-lg shadow-blue-100 ring-2 ring-blue-200'
                                        : 'border-gray-200 hover:border-gray-300'">
                                <img :src="item.thumb" :alt="item.alt" class="w-full h-full object-cover">
                                
                                {{-- Video Badge --}}
                                <template x-if="item.type === 'video'">
                                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                        </svg>
                                    </div>
                                </template>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- Zoom Modal (فقط برای تصاویر) --}}
                <div x-show="zoomOpen" 
                     x-cloak
                     @click.self="closeZoom()"
                     @keydown.escape.window="closeZoom()"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0">
                    
                    {{-- Close Button --}}
                    <button @click="closeZoom()"
                            class="absolute top-4 left-4 w-10 h-10 rounded-full bg-white/10 backdrop-blur-sm hover:bg-white/20 flex items-center justify-center text-white transition-all z-10">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    {{-- Navigation in Zoom --}}
                    <template x-if="media.length > 1">
                        <div>
                            <button @click.stop="prev()"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 backdrop-blur-sm hover:bg-white/20 flex items-center justify-center text-white transition-all">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <button @click.stop="next()"
                                    class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 backdrop-blur-sm hover:bg-white/20 flex items-center justify-center text-white transition-all">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                        </div>
                    </template>

                    {{-- Zoomed Image --}}
                    <template x-if="currentMedia && isImage">
                        <img :src="currentMedia.url"
                             :alt="currentMedia.alt"
                             class="max-w-full max-h-full object-contain"
                             @click.stop>
                    </template>

                    {{-- Image Counter --}}
                    <div class="absolute bottom-4 right-1/2 translate-x-1/2 bg-white/10 backdrop-blur-sm text-white px-4 py-2 rounded-full text-sm">
                        <span x-text="activeMedia + 1"></span> / <span x-text="media.length"></span>
                    </div>
                </div>
            </div>


            {{-- ──── RIGHT: Product Info ──── --}}
            <div class="lg:col-span-7 space-y-6">

                {{-- Product Title --}}
                <div>
                    @if($product->category)
                    <a href="{{ route('categories.show', ['category' => $product->category->slug]) }}"
                       class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium mb-2 transition-colors">
                        @if($product->category->icon)
                        <span>{{ $product->category->icon }}</span>
                        @endif
                        {{ $product->category->name }}
                    </a>
                    @endif
                    <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-900 leading-tight">
                        {{ $product->title }}
                    </h1>
                    @if($product->sku)
                    <div class="mt-2 flex items-center gap-3 text-sm text-gray-400">
                        <span>کد محصول: <span class="font-mono text-gray-600">{{ $product->sku }}</span></span>
                        <span class="text-gray-300">|</span>
                        <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                {{ number_format($product->view_count ?? 0) }} بازدید
                            </span>
                    </div>
                    @endif
                </div>

                {{-- ✅ اصلاح #1: Retail Price Card (Visible to ALL) --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-gray-500 font-medium mb-1">قیمت خرده‌فروشی</div>
                            @if($displayRetailPrice)
                            <div class="flex items-baseline gap-2">
                                    <span class="text-3xl font-extrabold text-gray-900 tabular-nums">
                                        {{ PriceHelper::formatNumber($displayRetailPrice->retail_price ?? 0) }}
                                    </span>
                                <span class="text-sm text-gray-500 font-medium">تومان</span>
                            </div>
                            @else
                            <div class="text-lg text-gray-400 font-medium">قیمت ثبت نشده</div>
                            @endif
                        </div>
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-green-100 to-green-200 flex items-center justify-center">
                            <svg class="w-7 h-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- ✅ اصلاح #2: My Retail Price (for retailers) --}}
                    @if(($canSetRetailPrice ?? false) && ($myRetailPrice ?? null))
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400">قیمت تنظیم‌شده شما:</span>
                            <span class="text-sm font-bold text-blue-600">
                                    {{ PriceHelper::format($myRetailPrice->retail_price ?? 0) }}
                                </span>
                        </div>
                    </div>
                    @endif

                    {{-- ✅ اصلاح #3: Profit Margin (for retailer_premium) --}}
                    @if(!empty($profitMargin))
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <div class="flex items-center gap-4 text-xs">
                                <span class="flex items-center gap-1 {{ $profitMargin['profit_amount'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="{{ $profitMargin['profit_amount'] >= 0 ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' }}"/>
                                    </svg>
                                    سود: {{ PriceHelper::formatNumber(abs($profitMargin['profit_amount'])) }} تومان
                                    ({{ $profitMargin['profit_percentage'] }}%)
                                </span>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Short Description --}}
                @if($product->short_description)
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        توضیحات کوتاه
                    </h3>
                    <p class="text-gray-600 leading-relaxed text-sm">
                        {{ $product->short_description }}
                    </p>
                </div>
                @endif

                {{-- Supplier Info (for retailer_premium / admin only) --}}
                @if($showSupplierInfo && $product->supplier)
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        اطلاعات تأمین‌کننده
                    </h3>
                    <div class="flex items-center gap-4">
                        @if($product->supplier->logo_path)
                        <img src="{{ asset('storage/' . $product->supplier->logo_path) }}"
                             alt="{{ $product->supplier->company_name }}"
                             class="w-14 h-14 rounded-xl object-cover border border-gray-200">
                        @else
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-600 font-bold text-lg">
                            {{ mb_substr($product->supplier->company_name ?? '?', 0, 1) }}
                        </div>
                        @endif
                        <div>
                            <div class="font-bold text-gray-800 flex items-center gap-2">
                                {{ $product->supplier->company_name }}
                                @if($product->supplier->is_verified)
                                <span class="inline-flex items-center gap-1 text-[11px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            تأیید شده
                                        </span>
                                @endif
                            </div>
                            @if($product->supplier->brand_name)
                            <div class="text-xs text-gray-500 mt-0.5">برند: {{ $product->supplier->brand_name }}</div>
                            @endif
                            @if($canViewSupplierInfo)
                            <div class="flex items-center gap-3 mt-1.5 text-xs text-gray-400">
                                @if($product->supplier->phone)
                                <a href="tel:{{ $product->supplier->phone }}" class="hover:text-blue-600 transition-colors">
                                    📞 {{ $product->supplier->phone }}
                                </a>
                                @endif
                                @if($product->supplier->mobile)
                                <a href="tel:{{ $product->supplier->mobile }}" class="hover:text-blue-600 transition-colors">
                                    📱 {{ $product->supplier->mobile }}
                                </a>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>

        {{-- ═══════════════════════════════════════
        بخش قیمت‌های عمده + تأمین‌کنندگان
        (Stacked View — همه زیر هم)
        ═══════════════════════════════════════ --}}
        @if($showWholesalePrices && ($usePivotPrices ? $productSuppliers->isNotEmpty() : $directPrices->isNotEmpty()))
        <div x-data="{ compareMode: false }">

            {{-- ── هدر بخش ── --}}
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-gray-700 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                    قیمت‌های عمده تأمین‌کنندگان
                    @if($hasMultipleSuppliers)
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                                        {{ $productSuppliers->count() }} تأمین‌کننده
                                    </span>
                    @endif
                </h3>
                @if($hasMultipleSuppliers && $usePivotPrices)
                <button @click="compareMode = !compareMode"
                        class="text-xs flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-all border"
                        :class="compareMode
                                            ? 'bg-blue-600 text-white border-blue-600 shadow-md'
                                            : 'bg-white text-blue-600 border-blue-200 hover:border-blue-400 hover:shadow-sm'">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span x-text="compareMode ? 'بستن مقایسه' : 'مقایسه قیمت‌ها'"></span>
                </button>
                @endif
            </div>

            {{-- ✅ اصلاح #4 تا #7: نوار آمار سریع --}}
            @if($hasMultipleSuppliers && !empty($supplierStats))
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                <div class="bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-center">
                    <div class="text-[10px] text-green-600 font-medium">ارزان‌ترین قیمت</div>
                    <div class="text-sm font-extrabold text-green-700 tabular-nums">{{ PriceHelper::formatNumber($supplierStats['lowest_price'] ?? 0) }}</div>
                    <div class="text-[9px] text-green-500">تومان</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-center">
                    <div class="text-[10px] text-red-600 font-medium">گران‌ترین</div>
                    <div class="text-sm font-extrabold text-red-700 tabular-nums">{{ PriceHelper::formatNumber($supplierStats['highest_price'] ?? 0) }}</div>
                    <div class="text-[9px] text-red-500">تومان</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 text-center">
                    <div class="text-[10px] text-blue-600 font-medium">میانگین</div>
                    <div class="text-sm font-extrabold text-blue-700 tabular-nums">{{ PriceHelper::formatNumber($supplierStats['avg_price'] ?? 0) }}</div>
                    <div class="text-[9px] text-blue-500">تومان</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg px-3 py-2 text-center">
                    <div class="text-[10px] text-purple-600 font-medium">اختلاف قیمت</div>
                    <div class="text-sm font-extrabold text-purple-700 tabular-nums">{{ PriceHelper::formatNumber(($supplierStats['highest_price'] ?? 0) - ($supplierStats['lowest_price'] ?? 0)) }}</div>
                    <div class="text-[9px] text-purple-500">تومان</div>
                </div>
            </div>
            @endif

            {{-- ══════════════════════════════════════════
            PRICE MATRIX COMPARISON — ماتریس بهترین قیمت‌ها
            ══════════════════════════════════════════ --}}
            @if($hasMatrixPricing)
            <div class="mb-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border-2 border-blue-200 p-6 shadow-lg">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-gray-900">ماتریس بهترین قیمت‌ها</h3>
                        <p class="text-sm text-gray-600">مقایسه قیمت‌های تمام تامین‌کنندگان در یک نگاه</p>
                    </div>
                </div>
                
                {{-- ماتریس بهترین قیمت‌ها --}}
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">نوع فروش</th>
                                @php
                                    $allTiers = $productSuppliers->flatMap(fn($ps) => $ps->prices)->pluck('tier_index')->unique()->sort()->values();
                                    $tierNames = $product->quantity_tiers ?? [];
                                @endphp
                                @foreach($allTiers as $tier)
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider border-r border-gray-200">
                                        {{ $tierNames[$tier]['name'] ?? "تیراژ {$tier}" }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                // جمع‌آوری تمام انواع قیمت یکتا
                                $allPriceTypes = $productSuppliers
                                    ->flatMap(fn($ps) => $ps->prices)
                                    ->map(function($price) {
                                        $code = $price->priceType ? $price->priceType->code : 'cash';
                                        $name = $price->priceType ? $price->priceType->name : 'نقدی';
                                        $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
                                        $checkPeriodDays = $price->checkPeriod ? $price->checkPeriod->days : 0;
                                        $checkPeriodName = $price->checkPeriod ? $price->checkPeriod->name : '';
                                        
                                        return [
                                            'key' => "{$code}_{$checkPeriodId}",
                                            'name' => $name,
                                            'check_period_name' => $checkPeriodName,
                                            'sort_key' => $code === 'cash' ? 0 : $checkPeriodDays,
                                            'display' => $checkPeriodName ? "{$name} ({$checkPeriodName})" : $name,
                                        ];
                                    })
                                    ->unique('key')
                                    ->sortBy('sort_key')
                                    ->values();
                            @endphp
                            
                            @foreach($allPriceTypes as $priceType)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap">{{ $priceType['display'] }}</td>
                                    
                                    @foreach($allTiers as $tier)
                                        @php
                                            // پیدا کردن تمام قیمت‌ها برای این سلول
                                            $cellPrices = $productSuppliers->flatMap(function($ps) use ($priceType, $tier) {
                                                return $ps->prices->filter(function($price) use ($priceType, $tier) {
                                                    $code = $price->priceType ? $price->priceType->code : 'cash';
                                                    $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
                                                    $key = "{$code}_{$checkPeriodId}";
                                                    return $key === $priceType['key'] && $price->tier_index == $tier;
                                                })->map(function($price) use ($ps) {
                                                    return [
                                                        'supplier' => $ps->supplier,
                                                        'amount' => $price->price,
                                                    ];
                                                });
                                            })->sortBy('amount');
                                        @endphp
                                        
                                        <td class="px-4 py-3 text-center border-r border-gray-100">
                                            @if($cellPrices->isNotEmpty())
                                                @php 
                                                    $bestPrice = $cellPrices->first();
                                                    $supplierId = $bestPrice['supplier']->id;
                                                    $anchorId = "supplier-{$supplierId}-tier-{$tier}-type-" . str_replace('_', '-', $priceType['key']);
                                                @endphp
                                                <a href="#{{ $anchorId }}" 
                                                   class="block bg-green-50 hover:bg-green-100 text-green-800 font-bold px-3 py-2 rounded-lg transition-all duration-200 ring-2 ring-green-400 hover:ring-green-500 cursor-pointer"
                                                   onclick="event.preventDefault(); document.getElementById('{{ $anchorId }}').scrollIntoView({behavior: 'smooth', block: 'center'}); this.classList.add('ring-4', 'bg-green-100'); setTimeout(() => this.classList.remove('ring-4', 'bg-green-100'), 2000);">
                                                    <div class="text-base font-bold">{{ number_format($bestPrice['amount']) }}</div>
                                                    @if($showSupplierInfo)
                                                        <div class="text-xs text-green-700 mt-1 font-medium">
                                                            {{ $bestPrice['supplier']->brand_name ?? $bestPrice['supplier']->company_name }}
                                                        </div>
                                                    @endif
                                                </a>
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
                
                {{-- راهنما --}}
                <div class="mt-3 flex items-center gap-6 text-xs text-gray-600 flex-wrap">
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-green-50 ring-2 ring-green-400 rounded"></div>
                        <span>بهترین قیمت از تمام تامین‌کنندگان</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                        </svg>
                        <span>کلیک روی قیمت → نمایش جزئیات تامین‌کننده</span>
                    </div>
                </div>
            </div>
            @else
            <div class="mb-6 bg-yellow-100 border-2 border-yellow-400 rounded p-4">
                <strong>⚠️ شرط false است:</strong><br>
                hasMatrixPricing: {{ $hasMatrixPricing ? 'true' : 'false' }}<br>
                hasMultipleSuppliers: {{ $hasMultipleSuppliers ? 'true' : 'false' }}
            </div>
            @endif

            {{-- ══════════════════════════════════════════
            STACKED VIEW — همه تأمین‌کنندگان زیر هم
            ══════════════════════════════════════════ --}}
            <div x-show="!compareMode" x-transition>
                @if($usePivotPrices)
                <div class="space-y-4">
                    @foreach($productSuppliers as $psIndex => $ps)
                    <div class="border border-gray-200 rounded-xl overflow-hidden transition-shadow hover:shadow-md"
                         x-data="{ expanded: {{ $psIndex === 0 ? 'true' : ($productSuppliers->count() <= 3 ? 'true' : 'false') }} }">

                        {{-- ── هدر تأمین‌کننده (قابل کلیک برای باز/بسته) ── --}}
                        <button @click="expanded = !expanded"
                                class="w-full bg-gradient-to-l from-blue-50 via-white to-blue-50 px-5 py-4 border-b border-gray-100 cursor-pointer transition-colors hover:from-blue-100/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    {{-- لوگو --}}
                                    @if($ps->supplier && $ps->supplier->logo_path)
                                    <img src="{{ asset('storage/' . $ps->supplier->logo_path) }}"
                                         alt="{{ $ps->supplier->company_name ?? '' }}"
                                         class="w-12 h-12 rounded-xl object-cover border-2 border-blue-100 shadow-sm">
                                    @else
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-600 font-bold text-lg shadow-sm">
                                        {{ mb_substr($ps->supplier->company_name ?? '?', 0, 1) }}
                                    </div>
                                    @endif

                                    <div class="text-right">
                                        <div class="font-bold text-gray-800 flex items-center gap-2 flex-wrap">
                                            {{ $ps->supplier->company_name ?? 'تأمین‌کننده' }}
                                            @if($ps->supplier && $ps->supplier->is_verified)
                                            <span class="inline-flex items-center gap-1 text-[11px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                                        تأیید شده
                                                                    </span>
                                            @endif
                                            @if($ps->is_primary)
                                            <span class="text-[11px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">⭐ تأمین‌کننده اصلی</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 flex-wrap">
                                            @if($ps->supplier && $ps->supplier->brand_name)
                                            <span>برند: {{ $ps->supplier->brand_name }}</span>
                                            @endif
                                            @if($ps->supplier && $ps->supplier->city)
                                            <span class="flex items-center gap-0.5">
                                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                                        {{ $ps->supplier->city }}{{ $ps->supplier->province ? '، ' . $ps->supplier->province : '' }}
                                                                    </span>
                                            @endif
                                            @if($ps->sku)
                                            <span>SKU: {{ $ps->sku }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- ✅ اصلاح #8: سمت چپ: محدوده قیمت + فلش --}}
                                <div class="flex items-center gap-3 shrink-0">
                                    <div class="text-left">
                                        <div class="text-[11px] text-gray-500 mb-0.5">محدوده قیمت</div>
                                        <div class="text-base font-extrabold text-blue-700">
                                            @php
                                            $psMinPrice = $ps->activePrices->min('price');
                                            $psMaxPrice = $ps->activePrices->max('price');
                                            @endphp
                                            {{ PriceHelper::formatRange($psMinPrice, $psMaxPrice) }}
                                        </div>
                                        @if($ps->stock_quantity !== null)
                                        <div class="mt-1">
                                            @php $stockColor = $ps->stock_status_color; @endphp
                                            <span class="text-[10px] px-2 py-0.5 rounded-full font-medium
                                                                        {{ $stockColor === 'green' ? 'bg-green-100 text-green-700' : '' }}
                                                                        {{ $stockColor === 'yellow' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                                                        {{ $stockColor === 'red' ? 'bg-red-100 text-red-700' : '' }}
                                                                        {{ $stockColor === 'blue' ? 'bg-blue-100 text-blue-700' : '' }}">
                                                                        {{ $ps->stock_status }}
                                                                    </span>
                                        </div>
                                        @endif
                                    </div>
                                    {{-- فلش باز/بسته --}}
                                    <svg class="w-5 h-5 text-gray-400 transition-transform duration-300"
                                         :class="expanded ? 'rotate-180' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>
                        </button>

                        {{-- ── بدنه: ماتریس قیمت‌ها (Collapsible) ── --}}
                        <div x-show="expanded"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-2">

                            {{-- ✅ نمایش ماتریسی قیمت‌ها --}}
                            <div class="px-5 py-4">
                                <x-supplier-price-matrix 
                                    :productSupplier="$ps" 
                                    :product="$product"
                                    :hasMultipleSuppliers="$hasMultipleSuppliers"
                                    :overallLowestPrice="$overallLowestPrice"
                                />
                            </div>

                            {{-- فوتر: اطلاعات تماس --}}
                            @if($canViewSupplierInfo)
                            <div class="bg-gray-50 px-5 py-3 border-t border-gray-100">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-4 text-xs text-gray-500 flex-wrap">
                                        @if($ps->supplier && $ps->supplier->phone)
                                        <a href="tel:{{ $ps->supplier->phone }}" class="flex items-center gap-1 hover:text-blue-600 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            {{ $ps->supplier->phone }}
                                        </a>
                                        @endif
                                        @if($ps->supplier && $ps->supplier->mobile)
                                        <a href="tel:{{ $ps->supplier->mobile }}" class="flex items-center gap-1 hover:text-blue-600 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            {{ $ps->supplier->mobile }}
                                        </a>
                                        @endif
                                        @if($ps->supplier && $ps->supplier->email)
                                        <a href="mailto:{{ $ps->supplier->email }}" class="flex items-center gap-1 hover:text-blue-600 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            {{ $ps->supplier->email }}
                                        </a>
                                        @endif
                                    </div>
                                    @if($ps->formatted_lead_time && $ps->lead_time_days !== null)
                                    <span class="text-xs text-gray-400 flex items-center gap-1">
                                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                    زمان تحویل: {{ $ps->formatted_lead_time }}
                                                                </span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                @elseif($directPrices->isNotEmpty())
                {{-- ✅ اصلاح #10: Fallback: قیمت‌های مستقیم (بدون تأمین‌کننده مشخص) --}}
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gray-50 px-5 py-3 border-b border-gray-100">
                        <span class="font-bold text-gray-700 text-sm">قیمت‌های ثبت‌شده</span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($directPrices as $price)
                        @php $ptVal = is_object($price->price_type) ? $price->price_type->value : (string) $price->price_type; @endphp
                        <div class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                @switch($ptVal)
                                @case('carton') <span class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center text-lg">📦</span> @break
                                @case('sub_carton') <span class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center text-lg">📋</span> @break
                                @case('warranty') <span class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center text-lg">🛡️</span> @break
                                @case('check') <span class="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center text-lg">📝</span> @break
                                @default <span class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-lg">💰</span>
                                @endswitch
                                <div>
                                    <div class="font-semibold text-gray-700 text-sm">
                                        {{ method_exists($price->price_type, 'label') ? $price->price_type->label() : $ptVal }}
                                        @if($price->warranty_months)
                                        <span class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">{{ $price->warranty_months }} ماه</span>
                                        @endif
                                        @if($price->check_months)
                                        <span class="text-[11px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">{{ $price->check_months }} ماهه</span>
                                        @endif
                                    </div>
                                    @if(($price->min_quantity ?? 0) > 1 || $price->max_quantity)
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        تعداد: {{ $price->min_quantity ?? 1 }}
                                        @if($price->max_quantity) تا {{ number_format($price->max_quantity) }} @else+ @endif
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-left shrink-0">
                                <span class="font-extrabold text-gray-800 text-lg tabular-nums">{{ PriceHelper::formatNumber($price->price) }}</span>
                                <span class="text-xs text-gray-400 mr-1">تومان</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- ══════════════════════════════════════════
            COMPARE VIEW — جدول مقایسه‌ای Side-by-Side
            ══════════════════════════════════════════ --}}
            @if($hasMultipleSuppliers && $usePivotPrices)
            <div x-show="compareMode"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-4">

                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead>
                        <tr class="bg-gray-50">
                            <th class="text-right px-4 py-3 font-bold text-gray-600 border-b min-w-[150px] sticky right-0 bg-gray-50 z-10">نوع قیمت</th>
                            @foreach($productSuppliers as $ps)
                            <th class="text-center px-3 py-3 border-b min-w-[140px]">
                                <div class="flex flex-col items-center gap-1">
                                    @if($ps->supplier && $ps->supplier->logo_path)
                                    <img src="{{ asset('storage/' . $ps->supplier->logo_path) }}" class="w-8 h-8 rounded-lg object-cover border border-gray-200">
                                    @else
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-xs">
                                        {{ mb_substr($ps->supplier->company_name ?? '?', 0, 1) }}
                                    </div>
                                    @endif
                                    <span class="font-bold text-gray-700 text-xs">{{ $ps->supplier->company_name ?? '?' }}</span>
                                    <div class="flex items-center gap-1">
                                        @if($ps->is_primary)
                                        <span class="text-[9px] bg-amber-100 text-amber-700 px-1 py-0.5 rounded-full">⭐</span>
                                        @endif
                                        @if($ps->supplier && $ps->supplier->is_verified)
                                        <span class="text-[9px] bg-green-100 text-green-700 px-1 py-0.5 rounded-full">✓</span>
                                        @endif
                                    </div>
                                </div>
                            </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @php
                        // جمع‌آوری تمام انواع قیمت یکتا از همه تأمین‌کنندگان (ساختار جدید)
                        $allPriceTypes = $productSuppliers
                            ->flatMap(fn($ps) => $ps->prices)
                            ->map(function($price) {
                                $priceTypeCode = $price->priceType?->code ?? 'cash';
                                $priceTypeName = $price->priceType?->name ?? 'نقدی';
                                $checkPeriodId = $price->check_period_id ?? 0;
                                $tierIndex = $price->tier_index ?? 0;
                                
                                // کلید یکتا برای هر ترکیب
                                $key = "{$priceTypeCode}_{$checkPeriodId}_{$tierIndex}";
                                
                                return [
                                    'key' => $key,
                                    'label' => $priceTypeName,
                                    'check_period_name' => $price->checkPeriod?->name,
                                    'tier_index' => $tierIndex,
                                    'price_type_code' => $priceTypeCode,
                                    'check_period_id' => $checkPeriodId,
                                ];
                            })
                            ->unique('key')
                            ->values();
                        @endphp

                        @forelse($allPriceTypes as $idx => $pti)
                        @php
                        // یافتن قیمت مطابق هر تأمین‌کننده
                        $pricesForType = $productSuppliers->map(function($ps) use ($pti) {
                            return $ps->prices->first(function($p) use ($pti) {
                                $priceTypeCode = $p->priceType?->code ?? 'cash';
                                $checkPeriodId = $p->check_period_id ?? 0;
                                $tierIndex = $p->tier_index ?? 0;
                                $key = "{$priceTypeCode}_{$checkPeriodId}_{$tierIndex}";
                                return $key === $pti['key'];
                            });
                        });
                        $validPrices = $pricesForType->filter()->pluck('price');
                        $lowestForType = $validPrices->min();
                        @endphp
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }} hover:bg-blue-50/30 transition-colors">
                            <td class="px-4 py-3 text-right sticky right-0 {{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }} z-10">
                                <div class="font-semibold text-gray-700 text-xs">{{ $pti['label'] }}</div>
                                @if($pti['check_period_name'])
                                <div class="text-[10px] text-purple-600 mt-0.5">{{ $pti['check_period_name'] }}</div>
                                @endif
                                @if($pti['tier_index'] > 0)
                                <div class="text-[10px] text-blue-600 mt-0.5">تیراژ: {{ $pti['tier_index'] }}</div>
                                @endif
                            </td>
                            @foreach($pricesForType as $price)
                            <td class="px-3 py-3 text-center">
                                @if($price)
                                {{-- ✅ اصلاح #11: قیمت در جدول مقایسه --}}
                                <div class="font-extrabold text-sm tabular-nums {{ $price->price == $lowestForType && $validPrices->count() > 1 ? 'text-green-600' : 'text-gray-800' }}">
                                    {{ PriceHelper::formatNumber($price->price) }}
                                </div>
                                @if($price->price == $lowestForType && $validPrices->count() > 1)
                                <span class="text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full mt-0.5 inline-block">✓ ارزان‌ترین</span>
                                @elseif($price->price != $lowestForType && $lowestForType > 0)
                                @php $diff = round((($price->price - $lowestForType) / $lowestForType) * 100, 1); @endphp
                                <div class="text-[10px] text-red-400 mt-0.5">+{{ $diff }}%</div>
                                @endif
                                @else
                                <span class="text-gray-300 text-lg">—</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $productSuppliers->count() + 1 }}" class="px-4 py-8 text-center text-gray-400">
                                قیمتی برای مقایسه وجود ندارد
                            </td>
                        </tr>
                        @endforelse

                        {{-- ✅ اصلاح #12: ردیف خلاصه: پایین‌ترین قیمت هر تأمین‌کننده --}}
                        <tr class="bg-blue-50 font-bold border-t-2 border-blue-200">
                            <td class="px-4 py-3 text-right sticky right-0 bg-blue-50 z-10 text-blue-700 text-xs">
                                🏆 پایین‌ترین قیمت
                            </td>
                            @foreach($productSuppliers as $ps)
                            @php $sLowest = $ps->activePrices->min('price'); @endphp
                            <td class="px-3 py-3 text-center">
                                @if($sLowest)
                                <span class="text-sm tabular-nums {{ $sLowest == $overallLowestPrice ? 'text-green-600' : 'text-gray-700' }}">
                                                                {{ PriceHelper::formatNumber($sLowest) }}
                                                            </span>
                                @if($sLowest == $overallLowestPrice)
                                <span class="text-[9px] bg-green-200 text-green-800 px-1.5 py-0.5 rounded-full mr-0.5">🏆</span>
                                @endif
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
        @endif





        {{-- ═══════════════════════════════════════════════════════
        SPECIFICATIONS
        ═══════════════════════════════════════════════════════ --}}
        @if($product->attributeValues && $product->attributeValues->count() > 0)
        @php
        // گروه‌بندی مقادیر بر اساس شناسه ویژگی
        $groupedAttributes = $product->attributeValues
        ->filter(fn($attrValue) => $attrValue->attribute && $attrValue->attribute->is_visible !== false)
        ->groupBy('attribute_id');
        @endphp

        @if($groupedAttributes->count() > 0)
        <div class="mt-8" id="specifications">
            <h3 class="text-lg font-bold text-gray-900 mb-4">مشخصات فنی</h3>
            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($groupedAttributes as $attributeId => $values)
                    @php
                    $firstVal = $values->first();
                    $attribute = $firstVal->attribute;

                    // استخراج مقادیر واقعی (اولویت با مقدار جدول واسط pivot و سپس مقدار پیش‌فرض)
                    $mergedValues = $values->map(function($attrValue) {
                    return filled($attrValue->pivot?->value) ? $attrValue->pivot->value : $attrValue->value;
                    })->filter()->unique()->join('، ');
                    @endphp

                    <tr class="{{ $loop->index % 2 === 0 ? 'bg-gray-50' : 'bg-white' }} hover:bg-gray-100 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700 w-1/3 border-l border-gray-200">
                            {{ $attribute->name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 w-2/3">
                            {{ $mergedValues }}
                            @if($attribute->unit)
                            <span class="text-xs text-gray-400 mr-1">({{ $attribute->unit }})</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endif



        {{-- ═══════════════════════════════════════════════════════
        FULL DESCRIPTION
        ═══════════════════════════════════════════════════════ --}}
        @if($product->full_description)
        <div class="mb-10">
            <h2 class="font-bold text-gray-800 flex items-center gap-2.5 text-xl mb-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-200">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h12"/>
                        </svg>
                    </span>
                توضیحات کامل
            </h2>
            <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed">
                    {!! $product->full_description !!}
                </div>
            </div>
        </div>
        @endif



        {{-- ═══════════════════════════════════════════════════════
        RECENTLY VIEWED
        ═══════════════════════════════════════════════════════ --}}
        @if(isset($recentlyViewed) && $recentlyViewed->isNotEmpty())
        <div class="mb-10">
            <h2 class="font-bold text-gray-800 flex items-center gap-2.5 text-xl mb-5">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-teal-500 to-teal-600 flex items-center justify-center shadow-lg shadow-teal-200">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                بازدیدهای اخیر شما
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($recentlyViewed as $recent)
                <x-product.card :product="$recent" />
                @endforeach
            </div>
        </div>
        @endif


        {{-- ═══════════════════════════════════════════════════════
        RELATED PRODUCTS
        ═══════════════════════════════════════════════════════ --}}
        @if($relatedProducts->isNotEmpty())
        <div class="mb-10">
            <h2 class="font-bold text-gray-800 flex items-center gap-2.5 text-xl mb-5">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center shadow-lg shadow-orange-200">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                        </svg>
                    </span>
                محصولات مرتبط
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">

                @foreach($relatedProducts as $related)
                <x-product.card :product="$related" />
                @endforeach


            </div>
        </div>
        @endif


    </div>
</div>
@endsection
