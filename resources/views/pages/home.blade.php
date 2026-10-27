{{-- resources/views/pages/home.blade.php --}}
@extends('layouts.app')

@section('title', $seo['title'] ?? config('app.name'))
@section('description', $seo['description'] ?? '')

@section('content')

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- HERO SECTION                                            --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <section class="relative bg-gradient-to-bl from-blue-600 via-blue-700 to-indigo-800 overflow-hidden">
        {{-- Background Pattern --}}
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)"/>
            </svg>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
            <div class="text-center">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white leading-tight mb-6">
                    بزرگ‌ترین <span class="text-yellow-300">مارکت‌پلیس عمده‌فروشی</span> ایران
                </h1>
                <p class="text-lg sm:text-xl text-blue-100 max-w-3xl mx-auto mb-10">
                    خرید مستقیم از تأمین‌کنندگان تأیید شده با بهترین قیمت‌های عمده
                </p>

                {{-- Search Bar --}}
                <form action="{{ route('products.index') }}" method="GET"
                      class="max-w-2xl mx-auto">
                    <div class="flex bg-white rounded-xl shadow-2xl overflow-hidden">
                        <input type="text"
                               name="q"
                               placeholder="جستجوی محصول، برند یا تأمین‌کننده..."
                               class="flex-1 px-6 py-4 text-gray-700 text-base focus:outline-none"
                               autocomplete="off">
                        <button type="submit"
                                class="bg-yellow-400 hover:bg-yellow-500 px-8 py-4 text-gray-800 font-bold transition-colors flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            جستجو
                        </button>
                    </div>
                </form>
            </div>

            {{-- Stats Counters --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-14 max-w-4xl mx-auto">
                <div class="text-center">
                    <div class="text-3xl lg:text-4xl font-extrabold text-white">
                        {{ number_format($stats['total_products'] ?? 0) }}
                    </div>
                    <div class="text-blue-200 text-sm mt-1">محصول فعال</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl lg:text-4xl font-extrabold text-white">
                        {{ number_format($stats['total_suppliers'] ?? 0) }}
                    </div>
                    <div class="text-blue-200 text-sm mt-1">تأمین‌کننده تأیید شده</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl lg:text-4xl font-extrabold text-white">
                        {{ number_format($stats['total_categories'] ?? 0) }}
                    </div>
                    <div class="text-blue-200 text-sm mt-1">دسته‌بندی</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl lg:text-4xl font-extrabold text-white">
                        {{ number_format($stats['total_brands'] ?? 0) }}
                    </div>
                    <div class="text-blue-200 text-sm mt-1">برند معتبر</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- CATEGORIES SECTION                                      --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    @if($categories->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">دسته‌بندی محصولات</h2>
                    <p class="text-gray-500 text-sm mt-1">مرور محصولات بر اساس دسته‌بندی</p>
                </div>
                <a href="{{ route('products.index') }}"
                   class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                    مشاهده همه
                    <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($categories as $category)
                    <a href="{{ route('categories.show', $category->slug) }}"
                       class="group bg-white rounded-xl border border-gray-200 p-5 text-center hover:shadow-lg hover:border-blue-300 transition-all duration-200">
                        {{-- Category Icon --}}
                        <div class="w-16 h-16 mx-auto mb-3 bg-blue-50 rounded-xl flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                            @if($category->icon)
                                <span class="text-3xl">{{ $category->icon }}</span>
                            @elseif($category->image_path)
                                <img src="{{ asset('storage/' . $category->image_path) }}"
                                     alt="{{ $category->name }}"
                                     class="w-10 h-10 object-contain">
                            @else
                                <svg class="w-8 h-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Category Name --}}
                        <h3 class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors mb-1">
                            {{ $category->name }}
                        </h3>

                        {{-- Product Count (Recursive) --}}
                        <span class="text-xs text-gray-400">
                            {{ number_format($category->recursive_products_count ?? 0) }} محصول
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- FEATURED PRODUCTS                                       --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    @if($featuredProducts->isNotEmpty())
        <section class="bg-gradient-to-b from-gray-50 to-white py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">
                            <span class="text-yellow-500">⭐</span> محصولات ویژه
                        </h2>
                        <p class="text-gray-500 text-sm mt-1">منتخب محصولات پیشنهادی ما</p>
                    </div>
                    <a href="{{ route('products.index', ['featured' => 1]) }}"
                       class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                        مشاهده همه
                        <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                    @foreach($featuredProducts as $product)
                        @include('components.product.card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- LATEST PRODUCTS                                         --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    @if($latestProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        🆕 جدیدترین محصولات
                    </h2>
                    <p class="text-gray-500 text-sm mt-1">آخرین محصولات اضافه شده به فروشگاه</p>
                </div>
                <a href="{{ route('products.index', ['sort' => 'latest']) }}"
                   class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                    مشاهده همه
                    <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                @foreach($latestProducts as $product)
                    @include('components.product.card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- POPULAR PRODUCTS                                        --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    @if($popularProducts->isNotEmpty())
        <section class="bg-gray-50 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">
                            🔥 پربازدیدترین محصولات
                        </h2>
                        <p class="text-gray-500 text-sm mt-1">محصولاتی که بیشترین بازدید را داشته‌اند</p>
                    </div>
                    <a href="{{ route('products.index', ['sort' => 'most_viewed']) }}"
                       class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                        مشاهده همه
                        <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                    @foreach($popularProducts as $product)
                        @include('components.product.card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- TOP SUPPLIERS                                           --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    @if($topSuppliers->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        🏢 تأمین‌کنندگان برتر
                    </h2>
                    <p class="text-gray-500 text-sm mt-1">تأمین‌کنندگان تأیید شده با بیشترین محصول</p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach($topSuppliers as $supplier)
                    <div class="bg-white rounded-xl border border-gray-200 p-5 text-center hover:shadow-lg hover:border-blue-300 transition-all duration-200">
                        {{-- Supplier Logo --}}
                        <div class="w-16 h-16 mx-auto mb-3 bg-gray-50 rounded-full flex items-center justify-center overflow-hidden">
                            @if($supplier->logo_path)
                                <img src="{{ $supplier->logoUrl('small') }}"
                                     alt="{{ $supplier->company_name }}"
                                     class="w-12 h-12 object-contain">
                            @else
                                <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Supplier Name --}}
                        <h3 class="text-sm font-semibold text-gray-700 mb-1">
                            {{ $supplier->company_name }}
                        </h3>

                        @if($supplier->brand_name)
                            <p class="text-xs text-gray-400 mb-2">{{ $supplier->brand_name }}</p>
                        @endif

                        {{-- Product Count --}}
                        <span class="inline-flex items-center text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded-full">
                            {{ number_format($supplier->products_count ?? 0) }} محصول
                        </span>

                        {{-- Verified Badge --}}
                        @if($supplier->is_verified)
                            <div class="mt-2">
                                <span class="inline-flex items-center text-xs text-green-600 gap-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    تأیید شده
                                </span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- CTA SECTION                                             --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <section class="bg-gradient-to-l from-blue-600 to-indigo-700 py-14">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white mb-4">
                آیا شما تأمین‌کننده هستید؟
            </h2>
            <p class="text-blue-100 text-lg mb-8 max-w-2xl mx-auto">
                همین الان ثبت‌نام کنید و محصولات خود را به هزاران خریدار عمده عرضه کنید.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}"
                   class="bg-yellow-400 hover:bg-yellow-500 text-gray-800 font-bold px-8 py-3 rounded-xl transition-colors text-lg">
                    ثبت‌نام تأمین‌کننده
                </a>
                <a href="{{ route('products.index') }}"
                   class="bg-white/10 hover:bg-white/20 text-white font-semibold px-8 py-3 rounded-xl border border-white/30 transition-colors text-lg">
                    مشاهده محصولات
                </a>
            </div>
        </div>
    </section>

@endsection
