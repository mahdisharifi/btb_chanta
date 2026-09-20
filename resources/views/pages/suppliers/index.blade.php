{{-- ═══════════════════════════════════════════════════════════════
     صفحه لیست تأمین‌کنندگان — suppliers/index.blade.php
     ═══════════════════════════════════════════════════════════════ --}}

@extends('layouts.app')

@section('title', $seo['title'] ?? 'تأمین‌کنندگان')

@section('seo')
    <meta name="description" content="{{ $seo['description'] ?? '' }}">
    <meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
    <link rel="canonical" href="{{ $seo['canonical_url'] ?? url()->current() }}">
    
    {{-- Schema.org BreadcrumbList --}}
    @if(!empty($breadcrumbs))
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            @foreach($breadcrumbs as $index => $crumb)
            {
                "@type": "ListItem",
                "position": {{ $index + 1 }},
                "name": "{{ $crumb['name'] }}",
                "item": "{{ $crumb['url'] ?? url()->current() }}"
            }{{ !$loop->last ? ',' : '' }}
            @endforeach
        ]
    }
    </script>
    @endif
    
    {{-- Schema.org ItemList for Suppliers --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "name": "تأمین‌کنندگان",
        "numberOfItems": {{ $stats['total'] ?? 0 }},
        "url": "{{ url()->current() }}"
    }
    </script>
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
        <div class="bg-gradient-to-l from-blue-600 to-indigo-700 rounded-3xl p-8 mb-8 text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 400 200" fill="none">
                    <circle cx="350" cy="50" r="120" fill="currentColor"/>
                    <circle cx="50" cy="180" r="80" fill="currentColor"/>
                </svg>
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl lg:text-4xl font-extrabold mb-3">🏭 تأمین‌کنندگان</h1>
                <p class="text-blue-100 text-lg max-w-2xl leading-relaxed">
                    مجموعه‌ای از تأمین‌کنندگان معتبر و تأیید شده با محصولات متنوع و قیمت‌های رقابتی
                </p>

                {{-- Stats --}}
                <div class="flex flex-wrap gap-6 mt-6">
                    <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span class="font-bold text-lg">{{ number_format($stats['total'] ?? 0) }}</span>
                        <span class="text-sm text-blue-200">تأمین‌کننده</span>
                    </div>
                    <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                        <svg class="w-5 h-5 text-green-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span class="font-bold text-lg">{{ number_format($stats['verified'] ?? 0) }}</span>
                        <span class="text-sm text-blue-200">تأیید شده</span>
                    </div>
                    <div class="flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-xl px-4 py-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="font-bold text-lg">{{ number_format($stats['cities'] ?? 0) }}</span>
                        <span class="text-sm text-blue-200">شهر</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════ SEARCH & FILTER BAR ═══════ --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-8 shadow-sm">
            <form method="GET" action="{{ route('suppliers.index') }}" class="flex flex-wrap items-center gap-4">
                {{-- Search Input --}}
                <div class="flex-1 min-w-[250px] relative">
                    <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" value="{{ $searchQuery }}"
                           placeholder="جستجوی نام شرکت، برند..."
                           class="w-full pr-10 pl-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>

                {{-- Province Filter --}}
                <select name="province"
                        class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 min-w-[160px]">
                    <option value="">همه استان‌ها</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province }}" {{ request('province') == $province ? 'selected' : '' }}>
                            {{ $province }}
                        </option>
                    @endforeach
                </select>

                {{-- Sort --}}
                <select name="sort"
                        class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 min-w-[160px]">
                    <option value="newest" {{ $sortBy == 'newest' ? 'selected' : '' }}>جدیدترین</option>
                    <option value="name_asc" {{ $sortBy == 'name_asc' ? 'selected' : '' }}>نام (الف-ی)</option>
                    <option value="most_products" {{ $sortBy == 'most_products' ? 'selected' : '' }}>بیشترین محصول</option>
                    <option value="verified_first" {{ $sortBy == 'verified_first' ? 'selected' : '' }}>تأیید شده اول</option>
                </select>

                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2.5 rounded-xl text-sm transition-colors">
                    جستجو
                </button>

                @if($searchQuery || request('province') || $sortBy !== 'newest')
                    <a href="{{ route('suppliers.index') }}"
                       class="text-gray-400 hover:text-red-500 text-sm transition-colors">
                        ✕ حذف فیلترها
                    </a>
                @endif
            </form>
        </div>

        {{-- ═══════ SUPPLIERS GRID ═══════ --}}
        @if($suppliers->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                @foreach($suppliers as $supplier)
                    <a href="{{ route('suppliers.show', $supplier->slug) }}"
                       class="group bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg hover:border-blue-300 transition-all duration-300">

                        {{-- Header --}}
                        <div class="bg-gradient-to-l from-gray-50 to-blue-50 p-6 text-center relative">
                            {{-- Verified Badge --}}
                            @if($supplier->is_verified)
                                <span class="absolute top-3 left-3 bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    تأیید شده
                                </span>
                            @endif

                            {{-- Logo --}}
                            <div class="w-20 h-20 mx-auto rounded-2xl bg-white border border-gray-200 overflow-hidden shadow-sm mb-3">
                                @if($supplier->logo_path)
                                    <img src="{{ asset('storage/' . $supplier->logo_path) }}"
                                         alt="{{ $supplier->company_name }}"
                                         class="w-full h-full object-contain p-2"
                                         loading="lazy">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-2xl font-extrabold text-blue-300">
                                        {{ mb_substr($supplier->company_name, 0, 1) }}
                                    </div>
                                @endif
                            </div>

                            {{-- Name --}}
                            <h3 class="font-bold text-gray-800 group-hover:text-blue-600 transition line-clamp-1">
                                {{ $supplier->company_name }}
                            </h3>
                            @if($supplier->brand_name)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $supplier->brand_name }}</p>
                            @endif
                        </div>

                        {{-- Details --}}
                        <div class="p-4 space-y-2">
                            @if($supplier->province || $supplier->city)
                                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $supplier->city ?? '' }}{{ $supplier->city && $supplier->province ? '، ' : '' }}{{ $supplier->province ?? '' }}
                                </div>
                            @endif

                            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                <span class="flex items-center gap-1 text-xs text-gray-500">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    {{ number_format($supplier->products_count ?? 0) }} محصول
                                </span>
                                <span class="text-xs text-blue-500 font-medium group-hover:text-blue-600 flex items-center gap-0.5">
                                    مشاهده
                                    <svg class="w-3 h-3 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $suppliers->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <h3 class="text-lg font-bold text-gray-600 mb-2">تأمین‌کننده‌ای یافت نشد</h3>
                <p class="text-gray-400 text-sm">لطفاً فیلترهای جستجو را تغییر دهید</p>
            </div>
        @endif
    </div>
</div>
@endsection
