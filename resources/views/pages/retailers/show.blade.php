{{-- ═══════════════════════════════════════════════════════════════
     صفحه پروفایل کاسب — retailers/show.blade.php
     ═══════════════════════════════════════════════════════════════ --}}

@extends('layouts.app')

@section('title', $seo['title'] ?? 'کاسب')

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
                                <a href="{{ $crumb['url'] }}" class="hover:text-emerald-600 transition-colors font-medium">{{ $crumb['name'] }}</a>
                            @else
                                <span class="text-gray-800 font-semibold">{{ $crumb['name'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        {{-- ═══════ RETAILER PROFILE CARD ═══════ --}}
        <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm mb-8">
            <div class="h-28 bg-gradient-to-l from-emerald-500 via-teal-500 to-cyan-600 relative">
                <div class="absolute inset-0 opacity-20">
                    <svg viewBox="0 0 800 200" fill="none" class="w-full h-full">
                        <circle cx="700" cy="50" r="150" fill="white"/>
                        <circle cx="100" cy="180" r="100" fill="white"/>
                    </svg>
                </div>
            </div>

            <div class="px-6 lg:px-8 pb-8 -mt-10 relative">
                <div class="flex flex-col sm:flex-row gap-6 items-start">
                    {{-- Avatar --}}
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500 border-4 border-white shadow-lg flex items-center justify-center text-3xl font-extrabold text-white shrink-0">
                        {{ mb_substr($retailer->shop_name ?? $retailer->name ?? '?', 0, 1) }}
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 pt-2 sm:pt-4">
                        <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-900 mb-1">
                            🏪 {{ $retailer->shop_name ?? $retailer->name ?? 'کاسب' }}
                        </h1>
                        @if($retailer->shop_name && $retailer->name)
                            <p class="text-sm text-gray-400">{{ $retailer->name }}</p>
                        @endif
                        @if($retailer->city)
                            <p class="text-gray-400 text-sm flex items-center gap-1 mt-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                {{ $retailer->city }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════ STATS BAR ═══════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
                <div class="text-2xl font-extrabold text-emerald-600">{{ number_format($retailerStats['total_products'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">محصول قیمت‌گذاری‌شده</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
                <div class="text-2xl font-extrabold text-purple-600">{{ $retailerStats['categories'] ?? 0 }}</div>
                <div class="text-xs text-gray-400 mt-1">دسته‌بندی</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
                @if(($retailerStats['min_price'] ?? 0) > 0)
                    <div class="text-lg font-extrabold text-green-600">{{ PriceHelper::formatNumber($retailerStats['min_price']) }}</div>
                    <div class="text-xs text-gray-400 mt-1">ارزان‌ترین (تومان)</div>
                @else
                    <div class="text-lg font-extrabold text-gray-300">—</div>
                    <div class="text-xs text-gray-400 mt-1">ارزان‌ترین</div>
                @endif
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
                @if(($retailerStats['avg_price'] ?? 0) > 0)
                    <div class="text-lg font-extrabold text-blue-600">{{ PriceHelper::formatNumber((int)$retailerStats['avg_price']) }}</div>
                    <div class="text-xs text-gray-400 mt-1">میانگین قیمت (تومان)</div>
                @else
                    <div class="text-lg font-extrabold text-gray-300">—</div>
                    <div class="text-xs text-gray-400 mt-1">میانگین قیمت</div>
                @endif
            </div>
        </div>

        {{-- ═══════ MAIN CONTENT ═══════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

            {{-- Sidebar: Categories --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-200 p-5 sticky top-24 shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        دسته‌بندی‌ها
                    </h3>
                    <div class="space-y-1">
                        <a href="{{ route('retailers.show', $retailer->id) }}"
                           class="block px-3 py-2 text-sm rounded-lg transition {{ !request('category') ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">
                            همه محصولات
                            <span class="text-xs text-gray-400">({{ $retailerStats['total_products'] ?? 0 }})</span>
                        </a>
                        @foreach($retailerCategories as $cat)
                            <a href="{{ route('retailers.show', ['id' => $retailer->id, 'category' => $cat->slug]) }}"
                               class="block px-3 py-2 text-sm rounded-lg transition {{ request('category') == $cat->slug ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">
                                {{ $cat->icon ?? '' }} {{ $cat->name }}
                                <span class="text-xs text-gray-400">({{ $cat->products_count }})</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Products List --}}
            <div class="lg:col-span-3">

                {{-- Sort Bar --}}
                <div class="flex items-center justify-between mb-6">
                    <p class="text-sm text-gray-500">
                        <span class="font-bold text-gray-700">{{ number_format($retailPrices->total()) }}</span> محصول
                    </p>
                    <form method="GET" class="flex items-center gap-2">
                        @if(request('category'))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif
                        <select name="sort" onchange="this.form.submit()"
                                class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-emerald-500">
                            <option value="cheapest" {{ $sortBy == 'cheapest' ? 'selected' : '' }}>ارزان‌ترین</option>
                            <option value="expensive" {{ $sortBy == 'expensive' ? 'selected' : '' }}>گران‌ترین</option>
                            <option value="newest" {{ $sortBy == 'newest' ? 'selected' : '' }}>جدیدترین</option>
                        </select>
                    </form>
                </div>

                @if($retailPrices->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($retailPrices as $rp)
                            @if($rp && $rp->product)
                                @php
                                    $product = $rp->product;
                                    $imageUrl = asset('images/placeholder-product.jpg');
                                    if ($product->relationLoaded('galleries') && $product->galleries->isNotEmpty()) {
                                        $primary = $product->galleries->firstWhere('is_primary', true) ?? $product->galleries->first();
                                        if ($primary) $imageUrl = asset('storage/' . $primary->file_path);
                                    }
                                @endphp

                                <a href="{{ route('products.show', $product->slug) }}"
                                   class="group bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md hover:border-emerald-300 transition-all duration-200 flex gap-4 p-4">

                                    {{-- Image --}}
                                    <div class="w-24 h-24 sm:w-28 sm:h-28 bg-gray-100 rounded-lg overflow-hidden shrink-0">
                                        <img src="{{ $imageUrl }}" alt="{{ $product->title }}"
                                             class="w-full h-full object-contain p-1 group-hover:scale-105 transition-transform"
                                             loading="lazy">
                                    </div>

                                    {{-- Info --}}
                                    <div class="flex-1 min-w-0 flex flex-col justify-between">
                                        <div>
                                            @if($product->category)
                                                <span class="text-[11px] text-emerald-500 font-medium">{{ $product->category->name }}</span>
                                            @endif
                                            <h3 class="text-sm font-bold text-gray-800 group-hover:text-emerald-600 transition line-clamp-2 mt-0.5 leading-6">
                                                {{ $product->title }}
                                            </h3>
                                            @if($product->supplier)
                                                <p class="text-[11px] text-gray-400 mt-1 flex items-center gap-1">
                                                    @if($product->supplier->is_verified)
                                                        <svg class="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                    @endif
                                                    تأمین‌کننده: {{ $product->supplier->company_name }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                            <div class="flex items-baseline gap-1">
                                                <span class="text-lg font-extrabold text-emerald-600">
                                                    {{ PriceHelper::formatNumber($rp->retail_price) }}
                                                </span>
                                                <span class="text-[11px] text-gray-400">تومان</span>
                                            </div>
                                            <span class="text-[10px] text-gray-300">
                                                {{ $rp->created_at?->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $retailPrices->links() }}
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
                        <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <h3 class="text-lg font-bold text-gray-600 mb-2">محصولی یافت نشد</h3>
                        <p class="text-gray-400 text-sm">این کاسب هنوز قیمتی در این دسته‌بندی ثبت نکرده است</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
