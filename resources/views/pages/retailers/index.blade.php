{{-- ═══════════════════════════════════════════════════════════════
     صفحه لیست کاسبان — retailers/index.blade.php
     ═══════════════════════════════════════════════════════════════ --}}

@extends('layouts.app')

@section('title', $seo['title'] ?? 'کاسبان')

@php
    use App\Helpers\PriceHelper;
@endphp

@section('seo')
    <meta name="description" content="{{ $seo['description'] ?? '' }}">
    <meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
    <link rel="canonical" href="{{ $seo['canonical_url'] ?? url()->current() }}">
@endsection

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        {{-- ═══════ BREADCRUMB ═══════ --}}
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
                                <a href="{{ $crumb['url'] }}" class="hover:text-blue-600 transition-colors font-medium">{{ $crumb['name'] }}</a>
                            @else
                                <span class="text-gray-800 font-semibold">{{ $crumb['name'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        {{-- ═══════ HERO HEADER ═══════ --}}
        <div class="bg-gradient-to-l from-emerald-600 to-teal-700 rounded-3xl p-8 mb-8 text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 400 200" fill="none">
                    <circle cx="350" cy="50" r="120" fill="currentColor"/>
                    <circle cx="50" cy="180" r="80" fill="currentColor"/>
                </svg>
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl lg:text-4xl font-extrabold mb-3">🏪 کاسبان</h1>
                <p class="text-emerald-100 text-lg max-w-2xl leading-relaxed">
                    فروشندگان خرده‌فروشی که قیمت‌های خود را برای محصولات ثبت کرده‌اند — مقایسه و انتخاب کنید
                </p>

                {{-- Stats --}}
                <div class="flex flex-wrap gap-6 mt-6">
                    <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span class="font-bold text-lg">{{ number_format($stats['active_retailers'] ?? 0) }}</span>
                        <span class="text-sm text-emerald-200">کاسب فعال</span>
                    </div>
                    <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        <span class="font-bold text-lg">{{ number_format($stats['total_retail_prices'] ?? 0) }}</span>
                        <span class="text-sm text-emerald-200">قیمت ثبت‌شده</span>
                    </div>
                    <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span class="font-bold text-lg">{{ number_format($stats['total_products'] ?? 0) }}</span>
                        <span class="text-sm text-emerald-200">محصول</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════ SEARCH & SORT BAR ═══════ --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-8 shadow-sm">
            <form method="GET" action="{{ route('retailers.index') }}" class="flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[250px] relative">
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" value="{{ $searchQuery }}"
                           placeholder="جستجوی نام فروشگاه، کاسب..."
                           class="w-full pr-10 pl-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
                </div>

                <select name="sort"
                        class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 min-w-[160px]">
                    <option value="newest" {{ $sortBy == 'newest' ? 'selected' : '' }}>جدیدترین</option>
                    <option value="name_asc" {{ $sortBy == 'name_asc' ? 'selected' : '' }}>نام (الف-ی)</option>
                    <option value="most_products" {{ $sortBy == 'most_products' ? 'selected' : '' }}>بیشترین محصول</option>
                </select>

                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-xl text-sm transition-colors">
                    جستجو
                </button>
            </form>
        </div>

        {{-- ═══════ RETAILERS GRID ═══════ --}}
        @if($retailers->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                @foreach($retailers as $retailer)
                    <a href="{{ route('retailers.show', $retailer->id) }}"
                       class="group bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg hover:border-emerald-300 transition-all duration-300 p-6 text-center">

                        {{-- Avatar --}}
                        <div class="w-16 h-16 mx-auto rounded-full bg-gradient-to-br from-emerald-100 to-teal-200 flex items-center justify-center text-2xl font-extrabold text-emerald-600 mb-4 group-hover:scale-110 transition-transform">
                            {{ mb_substr($retailer->shop_name ?? $retailer->name ?? '?', 0, 1) }}
                        </div>

                        {{-- Name --}}
                        <h3 class="font-bold text-gray-800 group-hover:text-emerald-600 transition line-clamp-1 mb-1">
                            {{ $retailer->shop_name ?? $retailer->name ?? 'کاسب' }}
                        </h3>

                        @if($retailer->city)
                            <p class="text-xs text-gray-400 flex items-center justify-center gap-1 mb-3">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                {{ $retailer->city }}
                            </p>
                        @endif

                        {{-- Stats --}}
                        <div class="flex items-center justify-center gap-4 pt-3 border-t border-gray-100">
                            <span class="text-xs text-gray-500">
                                <span class="font-bold text-emerald-600">{{ number_format($retailer->retail_prices_count ?? 0) }}</span>
                                قیمت
                            </span>
                            <span class="text-xs text-emerald-500 font-medium group-hover:text-emerald-600 flex items-center gap-0.5">
                                مشاهده
                                <svg class="w-3 h-3 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $retailers->links() }}
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <h3 class="text-lg font-bold text-gray-600 mb-2">کاسبی یافت نشد</h3>
                <p class="text-gray-400 text-sm">لطفاً فیلترهای جستجو را تغییر دهید</p>
            </div>
        @endif
    </div>
</div>
@endsection
