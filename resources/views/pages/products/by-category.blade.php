{{-- resources/views/pages/products/show.blade.php --}}
@extends('layouts.app')

@section('breadcrumb')
    <a href="{{ route('home') }}" class="text-gray-500 hover:text-gray-700">خانه</a>
    <span class="mx-2 text-gray-400">/</span>
    <a href="{{ route('products.index') }}" class="text-gray-500 hover:text-gray-700">محصولات</a>
    @foreach($breadcrumb as $crumb)
        <span class="mx-2 text-gray-400">/</span>
        @if($crumb['url'])
            <a href="{{ $crumb['url'] }}" class="text-gray-500 hover:text-gray-700">{{ $crumb['name'] }}</a>
        @else
            <span class="text-gray-700 font-medium">{{ $crumb['name'] }}</span>
        @endif
    @endforeach
@endsection

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- PRODUCT MAIN SECTION                                    --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 lg:p-8 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                {{-- ─── Gallery ─── --}}
                <div x-data="{
                    activeImage: '{{ ($product->galleries->where('is_primary', true)->first()?->file_path) ? asset('storage/' . $product->galleries->where('is_primary', true)->first()->file_path) : asset('images/placeholder-product.jpg') }}',
                    showLightbox: false,
                    lightboxImage: ''
                }">
                    {{-- Main Image --}}
                    <div class="aspect-square bg-gray-100 rounded-xl overflow-hidden mb-4 relative group cursor-zoom-in"
                         @click="lightboxImage = activeImage; showLightbox = true">
                        <img :src="activeImage"
                             alt="{{ $product->title }}"
                             class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                             loading="lazy">
                        {{-- Zoom Hint --}}
                        <div class="absolute bottom-3 left-3 bg-black/50 text-white text-xs px-2 py-1 rounded-lg opacity-0 group-hover:opacity-100 transition">
                            🔍 کلیک برای بزرگ‌نمایی
                        </div>
                    </div>

                    {{-- Thumbnails --}}
                    @if($product->galleries->isNotEmpty())
                        <div class="grid grid-cols-5 gap-2">
                            @foreach($product->galleries as $gallery)
                                @if($gallery->file_type === 'image')
                                    <button @click="activeImage = '{{ asset('storage/' . $gallery->file_path) }}'"
                                            class="aspect-square bg-gray-100 rounded-lg overflow-hidden border-2 hover:border-indigo-500 transition-all duration-200"
                                            :class="activeImage === '{{ asset('storage/' . $gallery->file_path) }}' ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-transparent'">
                                        <img src="{{ asset('storage/' . $gallery->file_path) }}"
                                             alt="{{ $gallery->alt_text ?? $product->title }}"
                                             class="w-full h-full object-cover"
                                             loading="lazy">
                                    </button>
                                @elseif($gallery->file_type === 'video')
                                    <div class="aspect-square bg-gray-800 rounded-lg overflow-hidden border-2 border-transparent hover:border-indigo-500 flex items-center justify-center cursor-pointer">
                                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    {{-- Lightbox --}}
                    <div x-show="showLightbox"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click.self="showLightbox = false"
                         @keydown.escape.window="showLightbox = false"
                         class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
                         style="display: none;">
                        <button @click="showLightbox = false" class="absolute top-4 left-4 text-white bg-white/20 hover:bg-white/30 rounded-full p-2 transition">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <img :src="lightboxImage" alt="{{ $product->title }}" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl">
                    </div>
                </div>

                {{-- ─── Product Info ─── --}}
                <div>
                    {{-- Category Badge --}}
                    @if($product->category)
                        <a href="{{ route('categories.show', $product->category->slug) }}"
                           class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 font-medium bg-indigo-50 px-3 py-1 rounded-full transition">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                            {{ $product->category->name }}
                        </a>
                    @endif

                    {{-- Title --}}
                    <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-800 mt-3 mb-4 leading-10">
                        {{ $product->title }}
                    </h1>

                    {{-- Meta Info Row --}}
                    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 mb-6">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            کد: {{ $product->sku }}
                        </span>
                        <span class="text-gray-300">|</span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            {{ number_format($product->view_count) }} بازدید
                        </span>
                        @if($hasMultipleSuppliers)
                            <span class="text-gray-300">|</span>
                            <span class="flex items-center gap-1 text-emerald-600">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $productSuppliers->count() }} تأمین‌کننده
                            </span>
                        @endif
                        @if($product->is_featured)
                            <span class="bg-amber-100 text-amber-700 px-2.5 py-0.5 rounded-full text-xs font-bold">⭐ محصول ویژه</span>
                        @endif
                    </div>

                    {{-- Short Description --}}
                    @if($product->short_description)
                        <p class="text-gray-600 leading-7 mb-6 text-sm lg:text-base">
                            {{ $product->short_description }}
                        </p>
                    @endif

                    {{-- ═══════════════════════════════════════════════ --}}
                    {{-- RETAIL PRICE — for End Users / Guests           --}}
                    {{-- ═══════════════════════════════════════════════ --}}
                    @if($retailPrices->isNotEmpty())
                        <div class="bg-gradient-to-l from-green-50 to-emerald-50 border border-green-200 rounded-xl p-5 mb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm font-medium text-green-700">قیمت فروش</span>
                            </div>
                            <div class="text-3xl font-extrabold text-green-700">
                                {{ number_format($retailPrices->min('retail_price')) }}
                                <span class="text-base font-normal mr-1">تومان</span>
                            </div>
                            @if($retailPrices->count() > 1)
                                <div class="text-xs text-green-500 mt-2 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                                    از {{ number_format($retailPrices->min('retail_price')) }}
                                    تا {{ number_format($retailPrices->max('retail_price')) }} تومان
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 mb-6 text-center">
                            <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span class="text-gray-500 font-medium">برای اطلاع از قیمت تماس بگیرید</span>
                        </div>
                    @endif

                    {{-- ═══════════════════════════════════════════════════════ --}}
                    {{-- WHOLESALE PRICES — PREMIUM RETAILERS ONLY              --}}
                    {{-- Multi-Supplier Price Comparison                         --}}
                    {{-- ═══════════════════════════════════════════════════════ --}}
                    @auth
                        @if(auth()->user()->hasRole('retailer_premium'))

                            {{-- ─── Multi-Supplier Price Section ─── --}}
                            @if($productSuppliers->isNotEmpty())
                                <div class="mb-6" x-data="{ activeSupplier: {{ $productSuppliers->first()->id ?? 0 }}, compareMode: false }">

                                    {{-- Section Header --}}
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="font-bold text-blue-700 flex items-center gap-2 text-base">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                            قیمت‌های عمده
                                            @if($hasMultipleSuppliers)
                                                <span class="text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-normal">
                                                    {{ $productSuppliers->count() }} تأمین‌کننده
                                                </span>
                                            @endif
                                        </h3>

                                        {{-- Compare Toggle (only when multiple suppliers) --}}
                                        @if($hasMultipleSuppliers)
                                            <button @click="compareMode = !compareMode"
                                                    class="text-xs flex items-center gap-1 px-3 py-1.5 rounded-lg transition-all"
                                                    :class="compareMode ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-600 hover:bg-blue-100'">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                                <span x-text="compareMode ? 'بستن مقایسه' : 'مقایسه قیمت‌ها'"></span>
                                            </button>
                                        @endif
                                    </div>

                                    {{-- ─── TAB VIEW (Default: One Supplier at a Time) ─── --}}
                                    <div x-show="!compareMode" x-transition>

                                        {{-- Supplier Tabs (when multiple suppliers) --}}
                                        @if($hasMultipleSuppliers)
                                            <div class="flex gap-2 mb-4 overflow-x-auto pb-2 scrollbar-thin">
                                                @foreach($productSuppliers as $ps)
                                                    <button @click="activeSupplier = {{ $ps->id }}"
                                                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium whitespace-nowrap transition-all duration-200 border-2"
                                                            :class="activeSupplier === {{ $ps->id }}
                                                                ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-200'
                                                                : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-600'">
                                                        {{-- Supplier Logo/Avatar --}}
                                                        @if($ps->supplier->logo_path)
                                                            <img src="{{ $ps->supplier->logoUrl('small') }}"
                                                                 alt="{{ $ps->supplier->company_name }}"
                                                                 class="w-6 h-6 rounded-full object-cover">
                                                        @else
                                                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                                                  :class="activeSupplier === {{ $ps->id }} ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-500'">
                                                                {{ mb_substr($ps->supplier->company_name, 0, 1) }}
                                                            </span>
                                                        @endif
                                                        {{ $ps->supplier->company_name }}
                                                        @if($ps->is_primary)
                                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full"
                                                                  :class="activeSupplier === {{ $ps->id }} ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-600'">
                                                                اصلی
                                                            </span>
                                                        @endif
                                                        @if($ps->supplier->is_verified)
                                                            <svg class="w-4 h-4" :class="activeSupplier === {{ $ps->id }} ? 'text-green-300' : 'text-green-500'" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                        @endif
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Price Tables per Supplier --}}
                                        @foreach($productSuppliers as $ps)
                                            <div x-show="activeSupplier === {{ $ps->id }}"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 translate-y-2"
                                                 x-transition:enter-end="opacity-100 translate-y-0"
                                                 class="bg-blue-50 border border-blue-200 rounded-xl overflow-hidden">

                                                {{-- Supplier Info Header --}}
                                                <div class="bg-gradient-to-l from-blue-100 to-blue-50 px-4 py-3 border-b border-blue-200">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            @if($ps->supplier->logo_path)
                                                                <img src="{{ $ps->supplier->logoUrl('small') }}"
                                                                     alt="{{ $ps->supplier->company_name }}"
                                                                     class="w-10 h-10 rounded-lg object-cover border border-blue-200">
                                                            @else
                                                                <div class="w-10 h-10 rounded-lg bg-blue-200 flex items-center justify-center text-blue-600 font-bold">
                                                                    {{ mb_substr($ps->supplier->company_name, 0, 1) }}
                                                                </div>
                                                            @endif
                                                            <div>
                                                                <div class="font-bold text-gray-800 text-sm flex items-center gap-1.5">
                                                                    {{ $ps->supplier->company_name }}
                                                                    @if($ps->supplier->is_verified)
                                                                        <span class="text-green-500 text-xs">✅</span>
                                                                    @endif
                                                                </div>
                                                                @if($ps->supplier->brand_name)
                                                                    <div class="text-xs text-gray-500">{{ $ps->supplier->brand_name }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        {{-- Price Range Badge --}}
                                                        <div class="text-left">
                                                            <div class="text-[11px] text-gray-500">محدوده قیمت</div>
                                                            <div class="text-sm font-bold text-blue-700">{{ $ps->formatted_price_range }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Price Table --}}
                                                <div class="divide-y divide-blue-100">
                                                    @forelse($ps->activePrices as $price)
                                                        <div class="flex items-center justify-between px-4 py-3 hover:bg-blue-100/50 transition text-sm">
                                                            <div class="flex items-center gap-3">
                                                                {{-- Price Type Icon --}}
                                                                @switch($price->price_type->value)
                                                                    @case('sub_carton')
                                                                        <span class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center text-sm">📦</span>
                                                                        @break
                                                                    @case('carton')
                                                                        <span class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-sm">🏗️</span>
                                                                        @break
                                                                    @case('warranty')
                                                                        <span class="w-8 h-8 rounded-lg bg-green-100 text-green-600 flex items-center justify-center text-sm">🛡️</span>
                                                                        @break
                                                                    @default
                                                                        <span class="w-8 h-8 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-sm">📋</span>
                                                                @endswitch

                                                                <div>
                                                                    <div class="font-medium text-gray-700">
                                                                        {{ $price->price_type->label() }}
                                                                        @if($price->warranty_months)
                                                                            <span class="text-green-600">({{ $price->warranty_months }} ماه)</span>
                                                                        @endif
                                                                        @if($price->check_months)
                                                                            <span class="text-purple-600">({{ $price->check_months }} ماهه)</span>
                                                                        @endif
                                                                    </div>
                                                                    @if($price->description)
                                                                        <div class="text-xs text-gray-400 mt-0.5">{{ $price->description }}</div>
                                                                    @endif
                                                                    @if($price->min_quantity > 1 || $price->max_quantity)
                                                                        <div class="text-xs text-gray-400 mt-0.5">
                                                                            تعداد: {{ $price->min_quantity }}
                                                                            @if($price->max_quantity)
                                                                                تا {{ $price->max_quantity }}
                                                                            @else
                                                                                به بالا
                                                                            @endif
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            <div class="text-left">
                                                                <span class="font-extrabold text-gray-800 text-base">
                                                                    {{ number_format($price->price) }}
                                                                </span>
                                                                <span class="text-xs text-gray-500 mr-1">تومان</span>
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="px-4 py-6 text-center text-gray-400 text-sm">
                                                            قیمتی ثبت نشده است
                                                        </div>
                                                    @endforelse
                                                </div>

                                                {{-- Supplier Contact (Bottom) --}}
                                                <div class="bg-blue-100/50 px-4 py-2.5 flex items-center justify-between text-xs text-gray-500">
                                                    <div class="flex items-center gap-4">
                                                        @if($ps->supplier->phone)
                                                            <span class="flex items-center gap-1">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                                {{ $ps->supplier->phone }}
                                                            </span>
                                                        @endif
                                                        @if($ps->supplier->mobile)
                                                            <span class="flex items-center gap-1">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                                {{ $ps->supplier->mobile }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($ps->sku)
                                                        <span>SKU تأمین‌کننده: {{ $ps->sku }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- ─── COMPARE VIEW (Side-by-Side) ─── --}}
                                    @if($hasMultipleSuppliers)
                                        <div x-show="compareMode"
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0"
                                             x-transition:enter-end="opacity-100"
                                             class="overflow-x-auto">

                                            <table class="w-full text-sm border border-blue-200 rounded-xl overflow-hidden">
                                                {{-- Header: Supplier Names --}}
                                                <thead>
                                                    <tr class="bg-gradient-to-l from-blue-100 to-blue-50">
                                                        <th class="text-right px-4 py-3 font-bold text-gray-700 border-b border-blue-200 min-w-[140px]">
                                                            نوع قیمت
                                                        </th>
                                                        @foreach($productSuppliers as $ps)
                                                            <th class="text-center px-4 py-3 border-b border-blue-200 min-w-[160px]">
                                                                <div class="flex flex-col items-center gap-1">
                                                                    @if($ps->supplier->logo_path)
                                                                        <img src="{{ $ps->supplier->logoUrl('small') }}"
                                                                             class="w-8 h-8 rounded-lg object-cover">
                                                                    @else
                                                                        <div class="w-8 h-8 rounded-lg bg-blue-200 flex items-center justify-center text-blue-600 font-bold text-xs">
                                                                            {{ mb_substr($ps->supplier->company_name, 0, 1) }}
                                                                        </div>
                                                                    @endif
                                                                    <span class="font-bold text-gray-700 text-xs">{{ $ps->supplier->company_name }}</span>
                                                                    @if($ps->is_primary)
                                                                        <span class="text-[9px] bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded-full">اصلی</span>
                                                                    @endif
                                                                </div>
                                                            </th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-blue-100">
                                                    {{-- Collect all unique price types across suppliers --}}
                                                    @php
                                                        $allPriceTypes = $productSuppliers
                                                            ->flatMap(fn($ps) => $ps->activePrices)
                                                            ->map(function($price) {
                                                                $key = $price->price_type->value;
                                                                if ($price->warranty_months) $key .= '_w' . $price->warranty_months;
                                                                if ($price->check_months)    $key .= '_c' . $price->check_months;
                                                                return [
                                                                    'key'              => $key,
                                                                    'label'            => $price->price_type->label(),
                                                                    'warranty_months'  => $price->warranty_months,
                                                                    'check_months'     => $price->check_months,
                                                                    'price_type_value' => $price->price_type->value,
                                                                ];
                                                            })
                                                            ->unique('key')
                                                            ->values();
                                                    @endphp

                                                    @foreach($allPriceTypes as $priceTypeInfo)
                                                        <tr class="hover:bg-blue-50/50 transition">
                                                            {{-- Price Type Label --}}
                                                            <td class="px-4 py-3 text-right">
                                                                <div class="font-medium text-gray-700">
                                                                    {{ $priceTypeInfo['label'] }}
                                                                    @if($priceTypeInfo['warranty_months'])
                                                                        <span class="text-green-600 text-xs">({{ $priceTypeInfo['warranty_months'] }} ماه)</span>
                                                                    @endif
                                                                    @if($priceTypeInfo['check_months'])
                                                                        <span class="text-purple-600 text-xs">({{ $priceTypeInfo['check_months'] }} ماهه)</span>
                                                                    @endif
                                                                </div>
                                                            </td>

                                                            {{-- Each Supplier's Price for This Type --}}
                                                            @php
                                                                $pricesForType = $productSuppliers->map(function($ps) use ($priceTypeInfo) {
                                                                    return $ps->activePrices->first(function($p) use ($priceTypeInfo) {
                                                                        $key = $p->price_type->value;
                                                                        if ($p->warranty_months) $key .= '_w' . $p->warranty_months;
                                                                        if ($p->check_months)    $key .= '_c' . $p->check_months;
                                                                        return $key === $priceTypeInfo['key'];
                                                                    });
                                                                });
                                                                $validPrices = $pricesForType->filter()->pluck('price');
                                                                $lowestForType  = $validPrices->min();
                                                            @endphp

                                                            @foreach($pricesForType as $price)
                                                                <td class="px-4 py-3 text-center">
                                                                    @if($price)
                                                                        <div class="font-bold {{ $price->price === $lowestForType ? 'text-green-600' : 'text-gray-800' }}">
                                                                            {{ number_format($price->price) }}
                                                                            <span class="text-[10px] font-normal text-gray-400">تومان</span>
                                                                        </div>
                                                                        @if($price->price === $lowestForType && $validPrices->count() > 1)
                                                                            <span class="text-[10px] bg-green-100 text-green-600 px-1.5 py-0.5 rounded-full">
                                                                                ✓ ارزان‌ترین
                                                                            </span>
                                                                        @endif
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
                                    @endif
                                </div>
                            @endif

                        @elseif(auth()->user()->hasRole('retailer_basic'))
                            {{-- ─── BASIC RETAILER: Upgrade CTA ─── --}}
                            <div class="bg-gradient-to-l from-violet-50 to-purple-50 border border-violet-200 rounded-xl p-5 mb-6">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-violet-800 mb-1">قیمت‌های عمده و اطلاعات تأمین‌کننده</h4>
                                        <p class="text-sm text-violet-600 leading-6 mb-3">
                                            برای مشاهده قیمت‌های عمده {{ $hasMultipleSuppliers ? $productSuppliers->count() . ' تأمین‌کننده' : 'تأمین‌کننده' }}
                                            و اطلاعات تماس آن‌ها، اشتراک ویژه تهیه کنید.
                                        </p>
                                        <a href="#"
                                           class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition shadow-sm">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                            ارتقا به اشتراک ویژه
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endauth

                    {{-- ═══════════════════════════════════════════════ --}}
                    {{-- SUPPLIER INFO — PREMIUM RETAILERS ONLY         --}}
                    {{-- ═══════════════════════════════════════════════ --}}
                    @auth
                        @if(auth()->user()->hasRole('retailer_premium') && $productSuppliers->isNotEmpty())
                            {{-- For single supplier - simple card --}}
                            @if(!$hasMultipleSuppliers)
                                @php $mainSupplier = $productSuppliers->first()->supplier; @endphp
                                <div class="bg-gradient-to-l from-purple-50 to-violet-50 border border-purple-200 rounded-xl p-4 mb-6">
                                    <h3 class="font-semibold text-purple-700 mb-3 flex items-center gap-2 text-sm">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        اطلاعات تأمین‌کننده
                                    </h3>
                                    <div class="flex items-center gap-3">
                                        @if($mainSupplier->logo_path)
                                            <img src="{{ $mainSupplier->logoUrl('small') }}"
                                                 alt="{{ $mainSupplier->company_name }}"
                                                 class="w-12 h-12 rounded-lg object-cover border border-purple-200">
                                        @else
                                            <div class="w-12 h-12 rounded-lg bg-purple-200 flex items-center justify-center text-purple-600 font-bold text-lg">
                                                {{ mb_substr($mainSupplier->company_name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-bold text-gray-800 flex items-center gap-1.5">
                                                {{ $mainSupplier->company_name }}
                                                @if($mainSupplier->is_verified)
                                                    <span class="text-green-500 text-xs">✅ تأیید شده</span>
                                                @endif
                                            </div>
                                            @if($mainSupplier->brand_name)
                                                <div class="text-xs text-gray-500">{{ $mainSupplier->brand_name }}</div>
                                            @endif
                                            <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                                @if($mainSupplier->phone)
                                                    <span>📞 {{ $mainSupplier->phone }}</span>
                                                @endif
                                                @if($mainSupplier->email)
                                                    <span>✉️ {{ $mainSupplier->email }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            {{-- For multiple suppliers, the info is already shown in the price tabs --}}
                        @endif
                    @endauth

                    {{-- Share Buttons --}}
                    <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-400">اشتراک‌گذاری:</span>
                        <a href="https://t.me/share/url?url={{ urlencode(request()->url()) }}&text={{ urlencode($product->title) }}"
                           target="_blank"
                           class="w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-500 flex items-center justify-center transition"
                           title="اشتراک در تلگرام">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                        </a>
                        <a href="https://api.whatsapp.com/send?text={{ urlencode($product->title . ' - ' . request()->url()) }}"
                           target="_blank"
                           class="w-8 h-8 rounded-lg bg-green-50 hover:bg-green-100 text-green-500 flex items-center justify-center transition"
                           title="اشتراک در واتساپ">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.612.637l4.663-1.217A11.953 11.953 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.345 0-4.508-.795-6.235-2.13l-.436-.352-3.02.788.826-3.012-.383-.453A9.962 9.962 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                        </a>
                        <button onclick="navigator.clipboard.writeText('{{ request()->url() }}'); alert('لینک کپی شد!')"
                                class="w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-500 flex items-center justify-center transition"
                                title="کپی لینک">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- DESCRIPTION & SPECIFICATIONS                            --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div x-data="{ activeTab: 'description' }" class="bg-white rounded-2xl border border-gray-200 mb-8 overflow-hidden">

            {{-- Tab Navigation --}}
            <div class="flex border-b border-gray-200 bg-gray-50/50">
                <button @click="activeTab = 'description'"
                        class="flex items-center gap-2 px-6 py-4 text-sm font-medium transition-all border-b-2"
                        :class="activeTab === 'description' ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    توضیحات محصول
                </button>
                @if($product->specifications && count($product->specifications) > 0)
                    <button @click="activeTab = 'specs'"
                            class="flex items-center gap-2 px-6 py-4 text-sm font-medium transition-all border-b-2"
                            :class="activeTab === 'specs' ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        مشخصات فنی
                    </button>
                @endif
                @auth
                    @if(auth()->user()->hasRole('retailer_premium') && $hasMultipleSuppliers)
                        <button @click="activeTab = 'suppliers'"
                                class="flex items-center gap-2 px-6 py-4 text-sm font-medium transition-all border-b-2"
                                :class="activeTab === 'suppliers' ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            تأمین‌کنندگان
                            <span class="text-xs bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded-full">{{ $productSuppliers->count() }}</span>
                        </button>
                    @endif
                @endauth
            </div>

            {{-- Tab Content --}}
            <div class="p-6 lg:p-8">

                {{-- Description Tab --}}
                <div x-show="activeTab === 'description'" x-transition>
                    @if($product->full_description)
                        <div class="prose prose-sm lg:prose max-w-none text-gray-600 leading-8">
                            {!! $product->full_description !!}
                        </div>
                    @else
                        <div class="text-center text-gray-400 py-8">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            توضیحاتی ثبت نشده است
                        </div>
                    @endif
                </div>

                {{-- Specifications Tab --}}
                @if($product->specifications && count($product->specifications) > 0)
                    <div x-show="activeTab === 'specs'" x-transition style="display: none;">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                            @foreach($product->specifications as $key => $value)
                                <div class="flex items-center justify-between py-3 px-4 border-b border-gray-100 text-sm {{ $loop->index % 2 === 0 ? 'bg-gray-50/50' : '' }}">
                                    <span class="text-gray-500 font-medium">{{ $key }}</span>
                                    <span class="font-semibold text-gray-800">{{ $value }}</span>
                                </div>
                            @endforeach

                            {{-- Weight --}}
                            @if($product->weight)
                                <div class="flex items-center justify-between py-3 px-4 border-b border-gray-100 text-sm bg-gray-50/50">
                                    <span class="text-gray-500 font-medium">وزن</span>
                                    <span class="font-semibold text-gray-800">{{ number_format($product->weight) }} گرم</span>
                                </div>
                            @endif

                            {{-- Dimensions --}}
                            @if($product->dimensions)
                                <div class="flex items-center justify-between py-3 px-4 border-b border-gray-100 text-sm">
                                    <span class="text-gray-500 font-medium">ابعاد (طول × عرض × ارتفاع)</span>
                                    <span class="font-semibold text-gray-800">
                                        {{ $product->dimensions['length'] ?? '-' }} × {{ $product->dimensions['width'] ?? '-' }} × {{ $product->dimensions['height'] ?? '-' }} سانتی‌متر
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Suppliers Tab (Premium Only, Multi-Supplier) --}}
                @auth
                    @if(auth()->user()->hasRole('retailer_premium') && $hasMultipleSuppliers)
                        <div x-show="activeTab === 'suppliers'" x-transition style="display: none;">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($productSuppliers as $ps)
                                    <div class="border border-gray-200 rounded-xl p-5 hover:border-blue-300 hover:shadow-md transition-all duration-200">
                                        {{-- Supplier Header --}}
                                        <div class="flex items-center gap-3 mb-4">
                                            @if($ps->supplier->logo_path)
                                                <img src="{{ $ps->supplier->logoUrl('small') }}"
                                                     alt="{{ $ps->supplier->company_name }}"
                                                     class="w-14 h-14 rounded-xl object-cover border border-gray-200">
                                            @else
                                                <div class="w-14 h-14 rounded-xl bg-gradient-to-bl from-blue-100 to-indigo-100 flex items-center justify-center text-blue-600 font-bold text-xl">
                                                    {{ mb_substr($ps->supplier->company_name, 0, 1) }}
                                                </div>
                                            @endif
                                            <div class="flex-1">
                                                <div class="font-bold text-gray-800 flex items-center gap-2">
                                                    {{ $ps->supplier->company_name }}
                                                    @if($ps->is_primary)
                                                        <span class="text-[10px] bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full">تأمین‌کننده اصلی</span>
                                                    @endif
                                                </div>
                                                @if($ps->supplier->brand_name)
                                                    <div class="text-xs text-gray-500 mt-0.5">{{ $ps->supplier->brand_name }}</div>
                                                @endif
                                                <div class="flex items-center gap-2 mt-1">
                                                    @if($ps->supplier->is_verified)
                                                        <span class="text-xs text-green-600 flex items-center gap-0.5">
                                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                            تأیید شده
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Contact Info --}}
                                        <div class="bg-gray-50 rounded-lg p-3 mb-4 space-y-2 text-xs">
                                            @if($ps->supplier->phone)
                                                <div class="flex items-center gap-2 text-gray-600">
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                    تلفن: {{ $ps->supplier->phone }}
                                                </div>
                                            @endif
                                            @if($ps->supplier->mobile)
                                                <div class="flex items-center gap-2 text-gray-600">
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                    موبایل: {{ $ps->supplier->mobile }}
                                                </div>
                                            @endif
                                            @if($ps->supplier->email)
                                                <div class="flex items-center gap-2 text-gray-600">
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                    ایمیل: {{ $ps->supplier->email }}
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Price Summary --}}
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-500">محدوده قیمت:</span>
                                            <span class="font-bold text-blue-700">{{ $ps->formatted_price_range }}</span>
                                        </div>
                                        <div class="flex items-center justify-between text-sm mt-1">
                                            <span class="text-gray-500">تعداد نوع قیمت:</span>
                                            <span class="font-medium text-gray-700">{{ $ps->activePrices->count() }} نوع</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endauth
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- RELATED PRODUCTS                                        --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if($relatedProducts->isNotEmpty())
            <section class="mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        محصولات مرتبط
                    </h2>
                    @if($product->category)
                        <a href="{{ route('categories.show', $product->category->slug) }}"
                           class="text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                            مشاهده همه
                            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($relatedProducts as $related)
                        <x-product.card :product="$related" />
                    @endforeach
                </div>
            </section>
        @endif

    </div>
@endsection
