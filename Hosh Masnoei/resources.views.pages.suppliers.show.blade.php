{{-- ═══════════════════════════════════════════════════════════════
     صفحه پروفایل تأمین‌کننده — suppliers/show.blade.php
     ═══════════════════════════════════════════════════════════════ --}}

@extends('layouts.app')

@section('title', $seo['title'] ?? $supplier->company_name)

@php
    use App\Helpers\PriceHelper;
@endphp

@section('seo')
    <meta name="description" content="{{ $seo['description'] ?? '' }}">
    <meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
    <link rel="canonical" href="{{ $seo['canonical_url'] ?? url()->current() }}">
    <meta property="og:title" content="{{ $seo['og_title'] ?? $supplier->company_name }}">
    <meta property="og:description" content="{{ $seo['og_description'] ?? '' }}">
    <meta property="og:image" content="{{ $seo['og_image'] ?? '' }}">
    <meta property="og:type" content="{{ $seo['og_type'] ?? 'profile' }}">
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

        {{-- ═══════ SUPPLIER PROFILE CARD ═══════ --}}
        <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm mb-8">
            {{-- Banner Gradient --}}
            <div class="h-32 bg-gradient-to-l from-blue-500 via-indigo-500 to-purple-600 relative">
                <div class="absolute inset-0 opacity-20">
                    <svg viewBox="0 0 800 200" fill="none" class="w-full h-full">
                        <circle cx="700" cy="50" r="150" fill="white"/>
                        <circle cx="100" cy="180" r="100" fill="white"/>
                    </svg>
                </div>
            </div>

            <div class="px-6 lg:px-8 pb-8 -mt-12 relative">
                <div class="flex flex-col lg:flex-row gap-6">
                    {{-- Logo --}}
                    <div class="shrink-0">
                        <div class="w-24 h-24 rounded-2xl bg-white border-4 border-white shadow-lg overflow-hidden">
                            @if($supplier->logo_path)
                                <img src="{{ asset('storage/' . $supplier->logo_path) }}"
                                     alt="{{ $supplier->company_name }}"
                                     class="w-full h-full object-contain p-2">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-3xl font-extrabold text-blue-300 bg-blue-50">
                                    {{ mb_substr($supplier->company_name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 pt-4 lg:pt-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <h1 class="text-2xl lg:text-3xl font-extrabold text-gray-900">
                                        {{ $supplier->company_name }}
                                    </h1>
                                    @if($supplier->is_verified)
                                        <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                            تأیید شده
                                        </span>
                                    @endif
                                </div>

                                @if($supplier->brand_name)
                                    <p class="text-gray-500 text-sm mb-2">برند: <span class="font-medium text-gray-700">{{ $supplier->brand_name }}</span></p>
                                @endif

                                @if($supplier->province || $supplier->city)
                                    <p class="text-gray-400 text-sm flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        {{ $supplier->city ?? '' }}{{ $supplier->city && $supplier->province ? '، ' : '' }}{{ $supplier->province ?? '' }}
                                    </p>
                                @endif
                            </div>

                            {{-- Contact Info (Premium only) --}}
                            @if($canViewContact)
                                <div class="flex flex-wrap gap-2">
                                    @if($supplier->phone)
                                        <a href="tel:{{ $supplier->phone }}"
                                           class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs font-medium px-3 py-2 rounded-xl transition">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            {{ $supplier->phone }}
                                        </a>
                                    @endif
                                    @if($supplier->mobile)
                                        <a href="tel:{{ $supplier->mobile }}"
                                           class="inline-flex items-center gap-1.5 bg-green-50 text-green-600 hover:bg-green-100 text-xs font-medium px-3 py-2 rounded-xl transition">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            {{ $supplier->mobile }}
                                        </a>
                                    @endif
                                    @if($supplier->website)
                                        <a href="{{ $supplier->website }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1.5 bg-purple-50 text-purple-600 hover:bg-purple-100 text-xs font-medium px-3 py-2 rounded-xl transition">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                            وب‌سایت
                                        </a>
                                    @endif
                                </div>
                            @else
                                <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-700 max-w-xs">
                                    <span class="font-bold">🔒</span>
                                    برای مشاهده اطلاعات تماس، به اشتراک ویژه ارتقا دهید.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════ STATS BAR ═══════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
                <div class="text-2xl font-extrabold text-blue-600">{{ number_format($supplierStats['total_products'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">محصول</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
                <div class="text-2xl font-extrabold text-purple-600">{{ number_format($supplierStats['total_categories'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">دسته‌بندی</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
                <div class="text-2xl font-extrabold text-green-600">{{ number_format($supplierStats['total_views'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">بازدید</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
                <div class="text-2xl font-extrabold text-amber-600">{{ $supplierStats['featured_count'] ?? 0 }}</div>
                <div class="text-xs text-gray-400 mt-1">محصول ویژه</div>
            </div>
        </div>

        {{-- ═══════ MAIN CONTENT: Categories + Products ═══════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

            {{-- Sidebar: Categories --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-200 p-5 sticky top-24 shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                        دسته‌بندی‌ها
                    </h3>
                    <div class="space-y-1">
                        <a href="{{ route('suppliers.show', $supplier->slug) }}"
                           class="block px-3 py-2 text-sm rounded-lg transition {{ !request('category') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">
                            همه محصولات
                            <span class="text-xs text-gray-400">({{ $supplier->products_count }})</span>
                        </a>
                        @foreach($supplierCategories as $cat)
                            <a href="{{ route('suppliers.show', ['slug' => $supplier->slug, 'category' => $cat->slug]) }}"
                               class="block px-3 py-2 text-sm rounded-lg transition {{ request('category') == $cat->slug ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-gray-50' }}">
                                {{ $cat->icon ?? '' }} {{ $cat->name }}
                                <span class="text-xs text-gray-400">({{ $cat->products_count }})</span>
                            </a>
                        @endforeach
                    </div>

                    {{-- Price Range Info --}}
                    @if(($supplierStats['min_price'] ?? 0) > 0)
                        <div class="mt-6 pt-4 border-t border-gray-100">
                            <h4 class="text-xs text-gray-400 font-medium mb-2">بازه قیمت محصولات</h4>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">از</span>
                                    <span class="font-bold text-green-600">{{ PriceHelper::formatNumber($supplierStats['min_price']) }} تومان</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">تا</span>
                                    <span class="font-bold text-red-500">{{ PriceHelper::formatNumber($supplierStats['max_price']) }} تومان</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Products Grid --}}
            <div class="lg:col-span-3">
                {{-- Sort Bar --}}
                <div class="flex items-center justify-between mb-6">
                    <p class="text-sm text-gray-500">
                        <span class="font-bold text-gray-700">{{ number_format($products->total()) }}</span> محصول
                    </p>
                    <form method="GET" class="flex items-center gap-2">
                        @if(request('category'))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif
                        <select name="sort" onchange="this.form.submit()"
                                class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500">
                            <option value="latest" {{ $sortBy == 'latest' ? 'selected' : '' }}>جدیدترین</option>
                            <option value="popular" {{ $sortBy == 'popular' ? 'selected' : '' }}>محبوب‌ترین</option>
                            <option value="price_low" {{ $sortBy == 'price_low' ? 'selected' : '' }}>ارزان‌ترین</option>
                            <option value="price_high" {{ $sortBy == 'price_high' ? 'selected' : '' }}>گران‌ترین</option>
                            <option value="name_asc" {{ $sortBy == 'name_asc' ? 'selected' : '' }}>نام (الف-ی)</option>
                        </select>
                    </form>
                </div>

                @if($products->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                        @foreach($products as $product)
                            <x-product.card :product="$product" :showSupplier="false" />
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
                        <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <h3 class="text-lg font-bold text-gray-600 mb-2">محصولی یافت نشد</h3>
                        <p class="text-gray-400 text-sm">این تأمین‌کننده هنوز محصولی در این دسته‌بندی ثبت نکرده است</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
