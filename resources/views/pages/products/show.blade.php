
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

{{-- ✅ Canonical URL: همیشه به URL بدون پارامتر اشاره می‌کند --}}
<link rel="canonical" href="{{ $seo['canonical_url'] ?? route('products.show', ['product' => $product->slug]) }}">

{{-- Open Graph --}}
<meta property="og:title" content="{{ $seo['og_title'] ?? $product->title }}">
<meta property="og:description" content="{{ $seo['og_description'] ?? '' }}">
<meta property="og:image" content="{{ $seo['og_image'] ?? asset('images/og-default.jpg') }}">
<meta property="og:type" content="{{ $seo['og_type'] ?? 'product' }}">
<meta property="og:url" content="{{ $seo['og_url'] ?? route('products.show', ['product' => $product->slug]) }}">
<meta property="og:site_name" content="{{ config('app.name') }}">

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
    <div class="max-w-7xl mx-auto px-1 sm:px-2 lg:px-4 py-6">

        {{-- ═══════════════════════════════════════════════════════
        BREADCRUMB
        ═══════════════════════════════════════════════════════ --}}
        @if(!empty($breadcrumbs))
        <nav class="mb-4" aria-label="Breadcrumb">
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
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">



            {{-- ──── LEFT: Product Gallery ──── --}}
            <x-product-gallery :product="$product" />


            {{-- ──── RIGHT: Product Info ──── --}}
            <div class="lg:col-span-7 space-y-4">

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
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-900 leading-tight">
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

                {{-- Short Description --}}
                @if($product->short_description)
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        توضیحات کوتاه
                    </h3>
                    <p class="text-gray-600 leading-relaxed text-sm">
                        {{ $product->short_description }}
                    </p>
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
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg sm:text-xl font-semibold text-gray-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                    قیمت‌های عمده تأمین‌کنندگان
                    @if($hasMultipleSuppliers)
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                                        {{ $productSuppliers->count() }} تأمین‌کننده
                                    </span>
                    @endif
                </h3>
                @if($hasMultipleSuppliers && $usePivotPrices)
                <button @click="compareMode = !compareMode; if(compareMode) { setTimeout(() => { const el = document.getElementById('price-comparison-section'); const yOffset = -80; const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset; window.scrollTo({top: y, behavior: 'smooth'}); }, 150); }"
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
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                <div class="bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-center">
                    <div class="text-xs sm:text-sm font-medium text-green-600">ارزان‌ترین قیمت</div>
                    <div class="text-base sm:text-lg md:text-xl font-extrabold text-green-700 tabular-nums">{{ PriceHelper::formatNumber($supplierStats['lowest_price'] ?? 0) }}</div>
                    <div class="text-[10px] sm:text-xs text-green-500">تومان</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-center">
                    <div class="text-xs sm:text-sm font-medium text-red-600">گران‌ترین</div>
                    <div class="text-base sm:text-lg md:text-xl font-extrabold text-red-700 tabular-nums">{{ PriceHelper::formatNumber($supplierStats['highest_price'] ?? 0) }}</div>
                    <div class="text-[10px] sm:text-xs text-red-500">تومان</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg px-2 py-1.5 text-center">
                    <div class="text-xs sm:text-sm font-medium text-blue-600">میانگین</div>
                    <div class="text-base sm:text-lg md:text-xl font-extrabold text-blue-700 tabular-nums">{{ PriceHelper::formatNumber($supplierStats['avg_price'] ?? 0) }}</div>
                    <div class="text-[10px] sm:text-xs text-blue-500">تومان</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg px-2 py-1.5 text-center">
                    <div class="text-xs sm:text-sm font-medium text-purple-600">اختلاف قیمت</div>
                    <div class="text-base sm:text-lg md:text-xl font-extrabold text-purple-700 tabular-nums">{{ PriceHelper::formatNumber(($supplierStats['highest_price'] ?? 0) - ($supplierStats['lowest_price'] ?? 0)) }}</div>
                    <div class="text-[10px] sm:text-xs text-purple-500">تومان</div>
                </div>
            </div>
            @endif
            
            {{-- ✅ بررسی تیراژهای فعال --}}
            @php
                // جمع‌آوری تمام تیراژهایی که حداقل یک تامین‌کننده قیمت برای آن‌ها دارد
                $allActiveTiers = $productSuppliers
                    ->flatMap(fn($ps) => $ps->prices)
                    ->pluck('tier_index')
                    ->unique()
                    ->sort()
                    ->values();
                
                $hasAnyActiveTier = $allActiveTiers->isNotEmpty();
            @endphp

            {{-- ══════════════════════════════════════════
            PRICE MATRIX COMPARISON — ماتریس بهترین قیمت‌ها
            ══════════════════════════════════════════ --}}
            
            {{-- 🔍 DEBUG: چک کردن شرایط --}}
            @if(!$hasMatrixPricing)
                <div class="mb-6 bg-yellow-50 border-2 border-yellow-400 rounded-xl p-4">
                    <div class="font-bold text-yellow-800 mb-2">🔍 DEBUG: چرا ماتریس نمایش داده نمی‌شود؟</div>
                    <div class="text-sm text-yellow-700 space-y-1">
                        <div><strong>hasMatrixPricing:</strong> {{ $hasMatrixPricing ? 'true' : 'false' }}</div>
                        <div><strong>hasMultipleSuppliers:</strong> {{ $hasMultipleSuppliers ? 'true' : 'false' }}</div>
                        <div><strong>productSuppliers count:</strong> {{ $productSuppliers->count() }}</div>
                        <div><strong>Total prices:</strong> {{ $productSuppliers->flatMap(fn($ps) => $ps->prices)->count() }}</div>
                        @foreach($productSuppliers as $ps)
                            <div class="mt-2 border-t border-yellow-300 pt-2">
                                <div><strong>Supplier {{ $loop->iteration }}:</strong> {{ $ps->supplier->company_name ?? 'Unknown' }}</div>
                                <div class="mr-4">Prices count: {{ $ps->prices->count() }}</div>
                                @if($ps->prices->isEmpty())
                                    <div class="mr-4 text-red-600">⚠️ این تأمین‌کننده هیچ قیمتی ندارد!</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            
            
            {{-- ⚠️ هشدار: بدون قیمت‌گذاری فعال --}}
            @if(!$hasAnyActiveTier)
                <div class="mb-6 bg-gradient-to-br from-red-50 to-orange-50 border-2 border-red-300 rounded-2xl p-6 shadow-xl">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-lg sm:text-xl font-black text-red-900 mb-3 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                محصول فاقد قیمت‌گذاری فعال است
                            </h4>
                            <div class="space-y-3">
                                <p class="text-sm sm:text-base text-gray-700 leading-relaxed">
                                    این محصول در حال حاضر قیمت‌گذاری فعالی ندارد. ممکن است دلایل زیر باشد:
                                </p>
                                <ul class="text-sm text-gray-700 space-y-2 mr-6">
                                    <li class="flex items-start gap-2">
                                        <span class="text-red-500 font-bold mt-1">•</span>
                                        <span>تامین‌کنندگان هنوز قیمتی برای این محصول ثبت نکرده‌اند</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="text-red-500 font-bold mt-1">•</span>
                                        <span>محصول به صورت موقت ناموجود است</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="text-red-500 font-bold mt-1">•</span>
                                        <span>محصول در حال بروزرسانی قیمت است</span>
                                    </li>
                                </ul>
                                
                                {{-- اطلاعات تماس --}}
                                <div class="mt-4 bg-white rounded-xl p-4 border-2 border-red-200 shadow-sm">
                                    <p class="text-sm font-bold text-gray-900 mb-2 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        برای دریافت قیمت و سفارش:
                                    </p>
                                    <div class="text-sm text-gray-700 space-y-1">
                                        @if($canViewSupplierInfo && $productSuppliers->isNotEmpty())
                                            <p>لطفاً با تامین‌کنندگان زیر تماس بگیرید:</p>
                                            <div class="mt-2 space-y-2">
                                                @foreach($productSuppliers as $ps)
                                                    @if($ps->supplier)
                                                    <div class="flex items-center gap-2 text-sm">
                                                        <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        <span class="font-semibold">{{ $ps->supplier->company_name }}</span>
                                                        @if($ps->supplier->mobile)
                                                            <span class="text-gray-600">— {{ $ps->supplier->mobile }}</span>
                                                        @endif
                                                    </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <p>لطفاً از طریق پشتیبانی سایت اقدام کنید</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            
            @if($hasMatrixPricing && $hasAnyActiveTier && $hasMultipleSuppliers)
            
            {{-- 📘 Info Box: راهنمای تیراژها برای کاربران --}}
            <div class="mb-4 bg-gradient-to-l from-indigo-50 to-blue-50 border-r-4 border-indigo-500 rounded-lg p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 space-y-2">
                        <h4 class="text-sm font-bold text-indigo-900">
                            💡 تیراژ چیست و چگونه از آن استفاده کنیم؟
                        </h4>
                        <p class="text-sm text-gray-700 leading-relaxed">
                            <strong>تیراژ</strong> به تعداد کل محصولی که قصد خرید آن را دارید اشاره دارد (نه تعداد کارتن یا بسته). 
                            قیمت‌های نمایش داده شده در جدول زیر، <strong>نقاط مرجع</strong> برای مقایسه تأمین‌کنندگان هستند.
                        </p>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 space-y-2 border border-indigo-200">
                            <p class="text-xs font-semibold text-gray-800 flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                نحوه استفاده:
                            </p>
                            <ul class="text-xs text-gray-700 space-y-1.5 mr-4">
                                <li class="flex items-start gap-2">
                                    <span class="text-indigo-500 font-bold mt-0.5">1.</span>
                                    <span>جدول زیر قیمت‌های تمام تأمین‌کنندگان را در تیراژهای مختلف نمایش می‌دهد</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-indigo-500 font-bold mt-0.5">2.</span>
                                    <span>می‌توانید قیمت‌ها را مقایسه کرده و بهترین گزینه را انتخاب کنید</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-amber-600 font-bold mt-0.5">⚠️</span>
                                    <span class="text-amber-700 font-medium">
                                        <strong>برای تعداد دقیق خود، حتماً با تأمین‌کننده تماس بگیرید</strong> — این قیمت‌ها فقط برای مقایسه هستند
                                    </span>
                                </li>
                            </ul>
                        </div>
                        @if($product->packaging_unit_type && $product->quantity_per_packaging)
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-2.5">
                            <p class="text-xs text-purple-900 flex items-center gap-2">
                                <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <strong>بسته‌بندی:</strong> 
                                {{ $product->packaging_unit_label }} 
                                (هر {{ $product->packaging_unit_label }} = {{ number_format($product->quantity_per_packaging) }} عدد)
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="mb-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border-2 border-blue-200 p-2 sm:p-2 shadow-lg" x-data="{ showWarrantyPrices: false }">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl sm:text-2xl font-extrabold text-gray-900">ماتریس بهترین قیمت‌ها</h3>
                            <p class="text-sm sm:text-base text-gray-600">مقایسه قیمت‌های تمام تامین‌کنندگان در یک نگاه</p>
                        </div>
                    </div>
                    
                    @php
                        // بررسی اینکه آیا حداقل یک تامین‌کننده گارانتی دارد
                        // نکته: warranty_value = 0 معتبر است (گارانتی رایگان)
                        $hasAnyWarranty = $productSuppliers->contains(function($ps) {
                            return $ps->has_warranty 
                                && ($ps->warranty_type === 'none' || $ps->warranty_type === 'custom' || $ps->warranty_value !== null);
                        });
                    @endphp
                    
                    @if($hasAnyWarranty)
                    {{-- Toggle برای نمایش قیمت با گارانتی - بهبود Touch Target --}}
                    <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-lg shadow-sm border border-purple-200">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span class="text-sm sm:text-base font-medium text-gray-700">با گارانتی</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer min-w-[48px] min-h-[44px]" 
                               role="switch"
                               aria-checked="false"
                               x-bind:aria-checked="showWarrantyPrices.toString()">
                            <input type="checkbox" x-model="showWarrantyPrices" class="sr-only peer" aria-label="نمایش قیمت با گارانتی">
                            <div class="w-12 h-6 sm:w-14 sm:h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 sm:after:h-6 sm:after:w-6 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                    @endif
                </div>
                
                {{-- 📊 Quick Summary: خلاصه سریع تیراژها --}}
                @if(!empty($tierNames))
                <div class="mb-3 bg-white border border-gray-200 rounded-lg p-3">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <span class="text-sm font-semibold text-gray-700">تیراژهای قابل مقایسه:</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @php
                            // ✅ فیلتر تیراژهای فعال برای Quick Summary
                            $enabledTiersForSummary = [];
                            foreach ($productSuppliers as $ps) {
                                // ✅ tier_enabled از model به صورت array برمی‌گردد (cast شده)
                                $tierEnabled = $ps->tier_enabled ?? [];
                                if (empty($tierEnabled)) {
                                    // اگر هیچ تنظیماتی نباشد، همه تیراژها فعال هستند
                                    foreach ($tierNames as $index => $tier) {
                                        $enabledTiersForSummary[$index] = true;
                                    }
                                } else {
                                    foreach ($tierEnabled as $index => $enabled) {
                                        if ($enabled) {
                                            $enabledTiersForSummary[$index] = true;
                                        }
                                    }
                                }
                            }
                        @endphp
                        @foreach($tierNames as $index => $tierData)
                            @if($enabledTiersForSummary[$index] ?? true)
                                @php
                                    $minQty = $tierData['min_quantity'] ?? null;
                                    $maxQty = $tierData['max_quantity'] ?? null;
                                    $tierName = $tierData['name'] ?? 'تیراژ';
                                    
                                    if ($minQty && $maxQty) {
                                        $rangeText = number_format($minQty) . ' تا ' . number_format($maxQty);
                                    } elseif ($minQty) {
                                        $rangeText = number_format($minQty) . ' به بالا';
                                    } else {
                                        $rangeText = 'نامشخص';
                                    }
                                @endphp
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 border border-indigo-200 rounded-lg">
                                    <span class="text-xs font-bold text-indigo-900">{{ $tierName }}:</span>
                                    <span class="text-xs text-indigo-700">{{ $rangeText }} عدد</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif
                
                {{-- ماتریس بهترین قیمت‌ها - بهینه‌سازی شده --}}
                <div class="relative">
                    {{-- Scrollbar indicator --}}
                    <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 rounded-lg border border-gray-200 bg-white
                                shadow-[inset_-10px_0_10px_-10px_rgba(0,0,0,0.1)] 
                                md:shadow-none">
                        <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 text-right text-xs sm:text-sm md:text-base font-medium text-gray-600 uppercase sticky right-0 bg-gray-50 z-10 border-l-2 border-gray-300">نوع فروش</th>
                                @php
                                    // ✅ فیلتر تیراژهای فعال + دارای قیمت
                                    $enabledTiers = [];
                                    
                                    // مرحله 1: جمع‌آوری تیراژهای فعال از همه suppliers
                                    foreach ($productSuppliers as $ps) {
                                        $tierEnabled = $ps->tier_enabled ?? [];
                                        
                                        if (empty($tierEnabled)) {
                                            // اگر تنظیمات نباشد، همه تیراژها فعال
                                            foreach ($product->quantity_tiers ?? [] as $index => $tier) {
                                                $enabledTiers[$index] = true;
                                            }
                                        } else {
                                            // فقط تیراژهای فعال
                                            foreach ($tierEnabled as $index => $enabled) {
                                                if ($enabled) {
                                                    $enabledTiers[$index] = true;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // مرحله 2: فیلتر نهایی - فقط تیراژهایی که قیمت فعال دارند
                                    $allTiers = $productSuppliers->flatMap(function($ps) use ($enabledTiers) {
                                        // فیلتر اول: تیراژهای فعال
                                        $tierEnabled = $ps->tier_enabled ?? [];
                                        
                                        return $ps->prices->filter(function($price) use ($tierEnabled, $enabledTiers) {
                                            $tier = $price->tier_index;
                                            
                                            // بررسی 1: آیا در لیست کلی تیراژهای فعال است؟
                                            if (!($enabledTiers[$tier] ?? true)) {
                                                return false;
                                            }
                                            
                                            // بررسی 2: آیا برای این supplier فعال است؟
                                            if (!empty($tierEnabled) && !($tierEnabled[$tier] ?? true)) {
                                                return false;
                                            }
                                            
                                            return true;
                                        })->pluck('tier_index');
                                    })->unique()->sort()->values();
                                    
                                    $tierNames = $product->quantity_tiers ?? [];
                                @endphp
                                @foreach($allTiers as $tier)
                                    @php
                                        $tierData = $tierNames[$tier] ?? null;
                                        $tierName = $tierData['name'] ?? "تیراژ {$tier}";
                                        $minQty = $tierData['min_quantity'] ?? null;
                                        $maxQty = $tierData['max_quantity'] ?? null;
                                        
                                        // ساخت متن محدوده
                                        if ($minQty && $maxQty) {
                                            $rangeText = number_format($minQty) . '–' . number_format($maxQty);
                                        } elseif ($minQty) {
                                            $rangeText = number_format($minQty) . '+';
                                        } else {
                                            $rangeText = null;
                                        }
                                    @endphp
                                    <th class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 text-center border-r border-gray-200 min-w-[90px] sm:min-w-[110px] md:min-w-[130px]" 
                                        x-data="{ showTooltip: false }">
                                        <div class="relative">
                                            {{-- محدوده تعداد + Tooltip --}}
                                            <div class="flex items-center justify-center gap-1"
                                                 @mouseenter="showTooltip = true" 
                                                 @mouseleave="showTooltip = false">
                                                @if($rangeText)
                                                    <div class="text-base sm:text-lg md:text-xl font-extrabold text-gray-900 tabular-nums leading-tight">
                                                        {{ $rangeText }} عدد
                                                    </div>
                                                @endif
                                                <button type="button" class="text-gray-400 hover:text-indigo-600 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            {{-- نام تیراژ --}}
                                            <div class="text-[10px] sm:text-xs md:text-sm font-medium text-gray-600 uppercase leading-tight mt-0.5">{{ $tierName }}</div>
                                            
                                            {{-- Tooltip --}}
                                            <div x-show="showTooltip" 
                                                 x-cloak
                                                 class="absolute top-full left-1/2 -translate-x-1/2 mt-2 w-48 bg-gray-900 text-white text-xs rounded-lg p-3 shadow-xl z-30"
                                                 style="pointer-events: none;">
                                                <div class="space-y-1.5">
                                                    <p class="font-semibold text-indigo-300">{{ $tierName }}</p>
                                                    @if($minQty && $maxQty)
                                                        <p>محدوده: {{ number_format($minQty) }} تا {{ number_format($maxQty) }} عدد</p>
                                                    @elseif($minQty)
                                                        <p>از {{ number_format($minQty) }} عدد به بالا</p>
                                                    @endif
                                                    <p class="text-amber-300 text-[10px] mt-1.5 border-t border-gray-700 pt-1.5">
                                                        💡 برای تعداد دقیق خود با تأمین‌کننده تماس بگیرید
                                                    </p>
                                                </div>
                                                {{-- Arrow --}}
                                                <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 rotate-45"></div>
                                            </div>
                                        </div>
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
                                            'check_period_days' => $checkPeriodDays,
                                            'sort_key' => $code === 'cash' ? 0 : $checkPeriodDays,
                                            'display' => $checkPeriodName ? "{$name} ({$checkPeriodName})" : $name,
                                        ];
                                    })
                                    ->unique('key')
                                    ->sortBy('sort_key')
                                    ->values();
                            @endphp
                            
                            @foreach($allPriceTypes as $priceType)
                                <tr class="group hover:bg-gray-50 transition-colors">
                                    <td class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 text-xs sm:text-sm md:text-base font-semibold text-gray-900 whitespace-nowrap sticky right-0 bg-white group-hover:bg-gray-50 z-10 transition-colors border-l-2 border-gray-300">
                                        {{-- Task #3: عنوان بهینه برای چکی --}}
                                        @if($priceType['check_period_name'])
                                            <div class="leading-tight">
                                                <div>{{ $priceType['name'] }}</div>
                                                <div class="text-[10px] sm:text-xs md:text-sm text-gray-500 mt-0.5">{{ $priceType['check_period_days'] }} روزه</div>
                                            </div>
                                        @else
                                            {{ $priceType['display'] }}
                                        @endif
                                    </td>
                                    
                                    @foreach($allTiers as $tier)
                                        @php
                                            // ✅ فیلتر: فقط تیراژهای فعال را در body نمایش بده
                                            if (!($enabledTiers[$tier] ?? true)) {
                                                continue; // تیراژ غیرفعال → skip
                                            }
                                            
                                            // پیدا کردن تمام قیمت‌ها برای این سلول (بدون گارانتی)
                                            $cellPrices = $productSuppliers->flatMap(function($ps) use ($priceType, $tier) {
                                                // ✅ فیلتر تیراژهای فعال برای این supplier
                                                $tierEnabled = $ps->tier_enabled ?? [];
                                                if (!empty($tierEnabled) && !($tierEnabled[$tier] ?? true)) {
                                                    return collect(); // تیراژ غیرفعال برای این supplier → skip
                                                }
                                                
                                                return $ps->prices->filter(function($price) use ($priceType, $tier) {
                                                    $code = $price->priceType ? $price->priceType->code : 'cash';
                                                    $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
                                                    $key = "{$code}_{$checkPeriodId}";
                                                    // فقط قیمت‌های غیر warranty
                                                    return $code !== 'warranty' && $key === $priceType['key'] && $price->tier_index == $tier;
                                                })->map(function($price) use ($ps, $tier) {
                                                    // محاسبه قیمت با گارانتی
                                                    $baseAmount = $price->price;
                                                    $warrantyAmount = null;
                                                    
                                                    // نکته 1: warranty_type = 'none' یعنی گارانتی رایگان (بدون هزینه اضافه) و معتبر است!
                                                    // نکته 2: warranty_value = 0 هم معتبر است (گارانتی رایگان)
                                                    $hasWarrantyWithCost = $ps->has_warranty 
                                                        && ($ps->warranty_type === 'none' || $ps->warranty_type === 'custom' || $ps->warranty_value !== null);
                                                    
                                                    if ($hasWarrantyWithCost) {
                                                        if ($ps->warranty_type === 'custom') {
                                                            // قیمت سفارشی
                                                            $customWarrantyPrice = $ps->prices->first(function($p) use ($tier) {
                                                                $code = $p->priceType ? $p->priceType->code : null;
                                                                return $code === 'warranty' && $p->tier_index == $tier;
                                                            });
                                                            
                                                            if ($customWarrantyPrice) {
                                                                $warrantyAmount = $baseAmount + $customWarrantyPrice->price;
                                                            }
                                                        } else {
                                                            // محاسبه percentage/fixed
                                                            $warrantyAmount = $ps->calculatePriceWithWarranty($baseAmount);
                                                        }
                                                    }
                                                    
                                                    return [
                                                        'supplier' => $ps->supplier,
                                                        'supplier_id' => $ps->supplier->id,
                                                        'amount' => $baseAmount,
                                                        'warranty_amount' => $warrantyAmount,
                                                        'has_warranty' => $hasWarrantyWithCost,
                                                        'warranty_type' => $ps->warranty_type,
                                                        'productSupplier' => $ps,
                                                    ];
                                                });
                                            })->sortBy('amount');
                                            
                                            // پیدا کردن کمترین قیمت (بدون گارانتی)
                                            $lowestAmount = $cellPrices->min('amount');
                                            
                                            // پیدا کردن تمام تامین‌کنندگان با کمترین قیمت
                                            $bestPriceSuppliers = $cellPrices->filter(function($item) use ($lowestAmount) {
                                                return $item['amount'] == $lowestAmount;
                                            })->values();
                                            
                                            // محاسبه بهترین گارانتی با الگوریتم منصفانه
                                            $allWarrantyPricesForCell = $cellPrices->filter(fn($item) => $item['warranty_amount'] !== null);
                                            
                                            // 1. گارانتی رایگان (cost = 0) که جزء بهترین قیمت‌های پایه باشد
                                            // 2. کمترین cost_per_day
                                            $bestWarrantyItems = $allWarrantyPricesForCell->map(function($item) use ($lowestAmount) {
                                                $warrantyCost = $item['warranty_amount'] - $item['amount'];
                                                $ps = $item['productSupplier'];
                                                
                                                // محاسبه cost per day
                                                $costPerDay = $ps->getWarrantyCostPerDay();
                                                
                                                // اگر گارانتی رایگان باشد و قیمت پایه بهترین باشد
                                                $isFreeAndBest = ($warrantyCost == 0 || $warrantyCost === null) && $item['amount'] == $lowestAmount;
                                                
                                                return [
                                                    'item' => $item,
                                                    'cost_per_day' => $costPerDay ?? PHP_FLOAT_MAX,
                                                    'is_free_and_best' => $isFreeAndBest,
                                                    'warranty_cost' => $warrantyCost,
                                                ];
                                            })
                                            ->sortBy(function($data) {
                                                // اولویت 1: رایگان + بهترین قیمت پایه
                                                if ($data['is_free_and_best']) {
                                                    return -1000000;
                                                }
                                                // اولویت 2: cost per day
                                                return $data['cost_per_day'];
                                            });
                                            
                                            $bestWarrantyData = $bestWarrantyItems->first();
                                            $lowestWarrantyAmount = $bestWarrantyData ? $bestWarrantyData['item']['warranty_amount'] : null;
                                            
                                            // فیلتر کردن تامین‌کنندگان با بهترین گارانتی
                                            $bestWarrantySuppliers = $allWarrantyPricesForCell->filter(function($item) use ($lowestWarrantyAmount) {
                                                return $item['warranty_amount'] == $lowestWarrantyAmount;
                                            })->values();
                                        @endphp
                                        
                                        <td class="px-2 sm:px-3 py-1.5 sm:py-2 text-center border-r border-gray-100">
                                            {{-- نمایش قیمت بدون گارانتی --}}
                                            <div x-show="!showWarrantyPrices">
                                                @if($bestPriceSuppliers->isNotEmpty())
                                                    @php 
                                                        $bestPrice = $bestPriceSuppliers->first();
                                                        $supplierId = $bestPrice['supplier_id'];
                                                        $anchorId = "supplier-{$supplierId}-tier-{$tier}-type-" . str_replace('_', '-', $priceType['key']);
                                                        $hasMultipleBest = $bestPriceSuppliers->count() > 1;
                                                    @endphp
                                                    <a href="#{{ $anchorId }}" 
                                                       class="block bg-green-50 hover:bg-green-100 text-green-800 font-bold px-2 py-1.5 sm:px-3 sm:py-2 rounded-md transition-all duration-200 ring-2 ring-green-400 hover:ring-green-500 cursor-pointer group relative"
                                                       onclick="event.preventDefault(); document.getElementById('{{ $anchorId }}').scrollIntoView({behavior: 'smooth', block: 'center'}); this.classList.add('ring-4', 'bg-green-100'); setTimeout(() => this.classList.remove('ring-4', 'bg-green-100'), 2000);">
                                        <div class="text-base sm:text-lg md:text-xl lg:text-2xl font-extrabold tabular-nums leading-tight tracking-tight">{{ number_format($bestPrice['amount']) }}</div>
                                                        @if($showSupplierInfo && !$hasMultipleBest)
                                                            <div class="text-[10px] sm:text-xs md:text-sm text-green-700 mt-0.5 font-medium leading-tight">
                                                                {{ $bestPrice['supplier']->brand_name ?? $bestPrice['supplier']->company_name }}
                                                            </div>
                                                        @elseif($hasMultipleBest)
                                                            <div class="text-[10px] sm:text-xs md:text-sm text-green-700 mt-0.5 font-medium leading-tight">
                                                                {{ $bestPriceSuppliers->count() }} تامین‌کننده
                                                            </div>
                                                            {{-- Tooltip --}}
                                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10 w-48">
                                                                <div class="bg-gray-900 text-white text-[10px] rounded-lg py-2 px-3 shadow-lg">
                                                                    <div class="font-semibold mb-1.5">تامین‌کنندگان با قیمت یکسان:</div>
                                                                    <ul class="space-y-1">
                                                                        @foreach($bestPriceSuppliers as $supplier)
                                                                            <li class="flex items-center gap-1.5">
                                                                                <svg class="w-3 h-3 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                                                </svg>
                                                                                <span>{{ $supplier['supplier']->brand_name ?? $supplier['supplier']->company_name }}</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </a>
                                                @else
                                                    <span class="text-gray-300 text-sm sm:text-base">—</span>
                                                @endif
                                            </div>
                                            
                                            {{-- نمایش قیمت با گارانتی --}}
                                            <div x-show="showWarrantyPrices" x-cloak>
                                                @if($bestWarrantySuppliers->isNotEmpty())
                                                    @php 
                                                        $bestWarrantyPrice = $bestWarrantySuppliers->first();
                                                        $wSupplierId = $bestWarrantyPrice['supplier_id'];
                                                        $wAnchorId = "supplier-{$wSupplierId}-tier-{$tier}-type-" . str_replace('_', '-', $priceType['key']);
                                                        $hasMultipleBestWarranty = $bestWarrantySuppliers->count() > 1;
                                                    @endphp
                                                    <a href="#{{ $wAnchorId }}" 
                                                       class="block bg-purple-50 hover:bg-purple-100 text-purple-800 font-bold px-2 py-1.5 sm:px-3 sm:py-2 rounded-md transition-all duration-200 ring-2 ring-purple-400 hover:ring-purple-500 cursor-pointer group relative"
                                                       onclick="event.preventDefault(); document.getElementById('{{ $wAnchorId }}').scrollIntoView({behavior: 'smooth', block: 'center'}); this.classList.add('ring-4', 'bg-purple-100'); setTimeout(() => this.classList.remove('ring-4', 'bg-purple-100'), 2000);">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                                            </svg>
                                                            <div class="text-base sm:text-lg md:text-xl lg:text-2xl font-extrabold tabular-nums leading-tight tracking-tight">{{ number_format($bestWarrantyPrice['warranty_amount']) }}</div>
                                                        </div>
                                                        @if($showSupplierInfo && !$hasMultipleBestWarranty)
                                                            <div class="text-[10px] sm:text-xs md:text-sm text-purple-700 mt-0.5 font-medium leading-tight">
                                                                {{ $bestWarrantyPrice['supplier']->brand_name ?? $bestWarrantyPrice['supplier']->company_name }}
                                                            </div>
                                                        @elseif($hasMultipleBestWarranty)
                                                            <div class="text-[10px] sm:text-xs md:text-sm text-purple-700 mt-0.5 font-medium leading-tight">
                                                                {{ $bestWarrantySuppliers->count() }} تامین‌کننده
                                                            </div>
                                                            {{-- Tooltip --}}
                                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10 w-48">
                                                                <div class="bg-gray-900 text-white text-[10px] rounded-lg py-2 px-3 shadow-lg">
                                                                    <div class="font-semibold mb-1.5">تامین‌کنندگان با بهترین گارانتی:</div>
                                                                    <ul class="space-y-1">
                                                                        @foreach($bestWarrantySuppliers as $supplier)
                                                                            <li class="flex items-center gap-1.5">
                                                                                <svg class="w-3 h-3 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                                                </svg>
                                                                                <span>{{ $supplier['supplier']->brand_name ?? $supplier['supplier']->company_name }}</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </a>
                                                @else
                                                    <span class="text-gray-400 text-sm">گارانتی موجود نیست</span>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                {{-- Scroll hint - بهبود یافته --}}
                <div class="md:hidden mt-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                    <div class="flex items-center justify-center gap-2 text-xs text-blue-700">
                        <svg class="w-4 h-4 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/>
                        </svg>
                        <span class="font-medium">جدول را به چپ و راست بکشید</span>
                        <svg class="w-4 h-4 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </div>
                </div>
            </div>
                
                {{-- راهنما - بهبود یافته با توضیح واحد قیمت --}}
                <div class="mt-3 bg-white border border-gray-200 rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2 mb-2.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-semibold text-gray-700">راهنمای جدول:</span>
                    </div>
                    <div class="flex items-start gap-4 flex-wrap">
                        <div class="flex items-center gap-1.5" x-show="!showWarrantyPrices">
                            <div class="w-3 h-3 bg-green-50 ring-2 ring-green-400 rounded flex-shrink-0"></div>
                            <span class="text-xs sm:text-sm text-gray-600">بهترین قیمت</span>
                        </div>
                        <div class="flex items-center gap-1.5" x-show="showWarrantyPrices" x-cloak>
                            <div class="w-3 h-3 bg-purple-50 ring-2 ring-purple-400 rounded flex-shrink-0"></div>
                            <span class="text-xs sm:text-sm text-gray-600">بهترین قیمت با گارانتی</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                            </svg>
                            <span class="text-xs sm:text-sm text-gray-600">کلیک → جزئیات تامین‌کننده</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs sm:text-sm text-gray-600"><strong class="text-amber-700">قیمت‌ها به ازای هر عدد کالا</strong> است (نه کل تیراژ)</span>
                        </div>
                    </div>
                </div>
                
                {{-- 📞 CTA: تماس با تأمین‌کننده برای تعداد دقیق --}}
                <div class="mt-4 bg-gradient-to-l from-amber-50 to-orange-50 border-2 border-amber-400 rounded-xl p-4 shadow-md">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 mt-1">
                            <div class="w-12 h-12 bg-amber-500 rounded-full flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-base sm:text-lg font-bold text-amber-900 mb-2 flex items-center gap-2">
                                📞 تعداد دقیق خود را می‌دانید؟
                            </h4>
                            <p class="text-sm text-amber-800 leading-relaxed mb-3">
                                <strong class="text-amber-900">💡 نکته مهم:</strong> تمام قیمت‌های نمایش داده شده در جداول بالا، <strong class="text-amber-900">قیمت هر یک عدد کالا</strong> است (نه قیمت کل تیراژ).
                                <br>
                                قیمت‌های بالا فقط برای <strong>مقایسه در نقاط مرجع</strong> (تیراژهای از پیش تعریف شده) هستند. 
                                برای <strong class="text-amber-900">قیمت نهایی با تعداد دقیق خود</strong> (مثلاً 73 عدد، 250 عدد، یا هر تعداد دیگر)، 
                                لطفاً با تأمین‌کننده مورد نظرتان تماس بگیرید.
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="#supplier-contact-section" 
                                   onclick="event.preventDefault(); const el = document.getElementById('supplier-contact-section') || document.querySelectorAll('[data-supplier-card]')[0]; if(el) { el.scrollIntoView({behavior: 'smooth', block: 'start'}); el.classList.add('ring-4', 'ring-amber-400'); setTimeout(() => el.classList.remove('ring-4', 'ring-amber-400'), 2000); }"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm rounded-lg shadow-md transition-all transform hover:scale-105">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    مشاهده اطلاعات تماس تأمین‌کنندگان
                                </a>
                                <span class="text-xs text-amber-700">
                                    یا به پایین صفحه بروید
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @else
            {{-- بخش زرد قبلی حذف شد چون حالا DEBUG جدید داریم --}}
            @endif

            {{-- ══════════════════════════════════════════
            STACKED VIEW — همه تأمین‌کنندگان زیر هم
            ══════════════════════════════════════════ --}}
            <div x-show="!compareMode" x-transition>
                @if($usePivotPrices)
                <div class="space-y-4">
                    @foreach($productSuppliers as $psIndex => $ps)
                    <div class="border border-gray-200 rounded-xl overflow-hidden transition-shadow hover:shadow-md"
                         data-supplier-card
                         id="supplier-card-{{ $ps->supplier->id }}"
                         x-data="{ expanded: {{ $psIndex === 0 ? 'true' : ($productSuppliers->count() <= 3 ? 'true' : 'false') }} }">

                        {{-- ── هدر تأمین‌کننده (قابل کلیک برای باز/بسته) ── --}}
                        <button @click="expanded = !expanded"
                                class="w-full bg-gradient-to-l from-blue-50 via-white to-blue-50 px-2 py-2 border-b border-gray-100 cursor-pointer transition-colors hover:from-blue-100/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    {{-- لوگو + لینک به فروشگاه تأمین‌کننده --}}
                                    @if($ps->supplier && $ps->supplier->slug)
                                    <a href="{{ route('suppliers.show', $ps->supplier->slug) }}"
                                       class="relative group/supplier shrink-0"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="مشاهده فروشگاه {{ $ps->supplier->company_name ?? 'تأمین‌کننده' }}"
                                       onclick="event.stopPropagation()">
                                        @if($ps->supplier->logo_path)
                                        <img src="{{ $ps->supplier->logoUrl('small') }}"
                                             alt="{{ $ps->supplier->company_name ?? '' }}"
                                             class="w-12 h-12 rounded-xl object-cover border-2 border-blue-100 shadow-sm group-hover/supplier:border-blue-400 group-hover/supplier:ring-2 group-hover/supplier:ring-blue-200 transition-all">
                                        @else
                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-600 font-bold text-lg shadow-sm group-hover/supplier:from-blue-200 group-hover/supplier:to-blue-300 group-hover/supplier:ring-2 group-hover/supplier:ring-blue-200 transition-all">
                                            {{ mb_substr($ps->supplier->company_name ?? '?', 0, 1) }}
                                        </div>
                                        @endif
                                        {{-- Badge نمایش در hover --}}
                                        <span class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center opacity-0 group-hover/supplier:opacity-100 scale-75 group-hover/supplier:scale-100 transition-all shadow-md">
                                            <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3 text-white" />
                                        </span>
                                    </a>
                                    @else
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-600 font-bold text-lg shadow-sm shrink-0">
                                        {{ mb_substr($ps->supplier->company_name ?? '?', 0, 1) }}
                                    </div>
                                    @endif

                                    <div class="text-right min-w-0 flex-1">
                                        <div class="font-bold text-gray-800 flex items-center gap-2 flex-wrap">
                                            {{-- لینک نام شرکت به فروشگاه تأمین‌کننده --}}
                                            @if($ps->supplier && $ps->supplier->slug)
                                            <a href="{{ route('suppliers.show', $ps->supplier->slug) }}"
                                               class="inline-flex items-center gap-1 hover:text-blue-600 transition-colors border-b-2 border-transparent hover:border-blue-300 cursor-pointer group/supplier-name"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               title="مشاهده فروشگاه {{ $ps->supplier->company_name ?? 'تأمین‌کننده' }}"
                                               onclick="event.stopPropagation()">
                                                <span class="truncate">{{ $ps->supplier->company_name ?? 'تأمین‌کننده' }}</span>
                                                <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5 text-gray-400 group-hover/supplier-name:text-blue-500 transition-colors" />
                                            </a>
                                            @else
                                            <span class="truncate">{{ $ps->supplier->company_name ?? 'تأمین‌کننده' }}</span>
                                            @endif
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
                                    :allProductSuppliers="$productSuppliers"
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
            COMPARE VIEW — جدول مقایسه‌ای تفکیک شده به تیراژ
            ══════════════════════════════════════════ --}}
            @if($hasMultipleSuppliers && $usePivotPrices)
            <div x-show="compareMode"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-3"
                 id="price-comparison-section">

                {{-- عنوان بخش مقایسه --}}
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900 flex items-center gap-1.5">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        مقایسه جامع قیمت تامین‌کنندگان
                    </h3>
                    <span class="text-xs sm:text-sm text-gray-500">{{ $productSuppliers->count() }} تامین‌کننده</span>
                </div>

                @php
                // ✅ استخراج تیراژهای فعال برای مقایسه
                $enabledTiersForComparison = [];
                
                // مرحله 1: جمع‌آوری تیراژهای فعال از همه suppliers
                foreach ($productSuppliers as $ps) {
                    $tierEnabled = $ps->tier_enabled ?? [];
                    
                    if (empty($tierEnabled)) {
                        // اگر تنظیمات نباشد، همه تیراژها فعال
                        foreach ($product->quantity_tiers ?? [] as $index => $tier) {
                            $enabledTiersForComparison[$index] = true;
                        }
                    } else {
                        // فقط تیراژهای فعال
                        foreach ($tierEnabled as $index => $enabled) {
                            if ($enabled) {
                                $enabledTiersForComparison[$index] = true;
                            }
                        }
                    }
                }
                
                // مرحله 2: فیلتر نهایی - فقط تیراژهایی که قیمت فعال دارند
                $allTiersForComparison = $productSuppliers
                    ->flatMap(function($ps) use ($enabledTiersForComparison) {
                        $tierEnabled = $ps->tier_enabled ?? [];
                        
                        return $ps->prices->filter(function($price) use ($tierEnabled, $enabledTiersForComparison) {
                            $tier = $price->tier_index;
                            
                            // بررسی 1: آیا در لیست کلی تیراژهای فعال است؟
                            if (!($enabledTiersForComparison[$tier] ?? true)) {
                                return false;
                            }
                            
                            // بررسی 2: آیا برای این supplier فعال است؟
                            if (!empty($tierEnabled) && !($tierEnabled[$tier] ?? true)) {
                                return false;
                            }
                            
                            return true;
                        })->pluck('tier_index');
                    })
                    ->unique()
                    ->sort()
                    ->values();
                    
                $tierNamesForComparison = $product->quantity_tiers ?? [];
                @endphp

                {{-- جداول مقایسه برای هر تیراژ --}}
                <div class="space-y-3">
                    @foreach($allTiersForComparison as $tierIndex)
                        @php
                            $tierData = $tierNamesForComparison[$tierIndex] ?? null;
                            $tierName = $tierData['name'] ?? "تیراژ {$tierIndex}";
                            $minQty = $tierData['min_quantity'] ?? null;
                            $maxQty = $tierData['max_quantity'] ?? null;
                            
                            // ساخت متن محدوده
                            if ($minQty && $maxQty) {
                                $rangeText = "{$minQty} تا {$maxQty} عدد";
                            } elseif ($minQty) {
                                $rangeText = "{$minQty}+ عدد";
                            } else {
                                $rangeText = null;
                            }
                            
                            // ✅ جمع‌آوری انواع قیمت برای این تیراژ (فقط قیمت‌های تیراژهای فعال)
                            $priceTypesForTier = $productSuppliers
                                ->flatMap(function($ps) use ($tierIndex) {
                                    // بررسی اینکه آیا این تیراژ برای این supplier فعال است
                                    $tierEnabled = $ps->tier_enabled ?? [];
                                    if (!empty($tierEnabled) && !($tierEnabled[$tierIndex] ?? true)) {
                                        return collect(); // تیراژ غیرفعال → skip
                                    }
                                    
                                    return $ps->prices->filter(fn($p) => $p->tier_index == $tierIndex);
                                })
                                ->map(function($price) {
                                    $code = $price->priceType ? $price->priceType->code : 'cash';
                                    $name = $price->priceType ? $price->priceType->name : 'نقدی';
                                    $checkPeriodId = $price->checkPeriod ? $price->checkPeriod->id : 0;
                                    $checkPeriodDays = $price->checkPeriod ? $price->checkPeriod->days : 0;
                                    $checkPeriodName = $price->checkPeriod ? $price->checkPeriod->name : '';
                                    
                                    return [
                                        'key' => "{$code}_{$checkPeriodId}",
                                        'name' => $name,
                                        'code' => $code,
                                        'check_period_name' => $checkPeriodName,
                                        'check_period_days' => $checkPeriodDays,
                                        'sort_key' => $code === 'cash' ? 0 : $checkPeriodDays,
                                    ];
                                })
                                ->unique('key')
                                ->sortBy('sort_key')
                                ->values();
                        @endphp

                        <div class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl border-2 border-blue-200 p-2 sm:p-3 shadow-md">
                            {{-- عنوان تیراژ --}}
                            <div class="mb-2 flex items-center gap-2">
                                <div class="w-8 h-8 sm:w-9 sm:h-9 md:w-10 md:h-10 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-base sm:text-lg">
                                    {{ $tierIndex }}
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-base sm:text-lg md:text-xl font-bold text-gray-900">{{ $tierName }}</span>
                                    @if($rangeText)
                                        <span class="text-lg sm:text-xl md:text-2xl font-extrabold text-blue-700 tabular-nums">{{ $rangeText }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- جدول مقایسه --}}
                            @php
                                // ✅ فیلتر کردن suppliers که برای این تیراژ قیمت فعال دارند
                                $activeSuppliersForTier = $productSuppliers->filter(function($ps) use ($tierIndex) {
                                    $tierEnabled = $ps->tier_enabled ?? [];
                                    
                                    // بررسی 1: آیا تیراژ برای این supplier فعال است؟
                                    if (!empty($tierEnabled) && !($tierEnabled[$tierIndex] ?? true)) {
                                        return false;
                                    }
                                    
                                    // بررسی 2: آیا حداقل یک قیمت برای این تیراژ دارد؟
                                    return $ps->prices->contains(fn($p) => $p->tier_index == $tierIndex);
                                });
                            @endphp
                            
                            @if($activeSuppliersForTier->isEmpty())
                                {{-- اگر هیچ supplier فعالی نداریم، جدول نمایش نمی‌دهیم --}}
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                                    <p class="text-sm text-yellow-700">هیچ تامین‌کننده‌ای برای این تیراژ قیمت فعال ندارد</p>
                                </div>
                            @else
                            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="text-right px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 text-xs sm:text-sm md:text-base font-medium text-gray-600 uppercase sticky right-0 bg-gray-50 z-10 border-l-2 border-gray-300">نوع فروش</th>
                                            @foreach($activeSuppliersForTier as $ps)
                                            <th class="text-center px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 border-b min-w-[90px] sm:min-w-[110px] md:min-w-[130px]">
                                                <div class="flex flex-col items-center gap-0.5">
                                                    @if($ps->supplier && $ps->supplier->logo_path)
                                                    <img src="{{ $ps->supplier->logoUrl('small') }}" class="w-7 h-7 sm:w-8 sm:h-8 md:w-9 md:h-9 rounded-lg object-cover border border-gray-200">
                                                    @else
                                                    <div class="w-7 h-7 sm:w-8 sm:h-8 md:w-9 md:h-9 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-xs sm:text-sm">
                                                        {{ mb_substr($ps->supplier->company_name ?? '?', 0, 1) }}
                                                    </div>
                                                    @endif
                                                    <span class="font-bold text-gray-700 text-[10px] sm:text-xs md:text-sm leading-tight text-center">{{ $ps->supplier->company_name ?? '?' }}</span>
                                                    <div class="flex items-center gap-0.5">
                                                        @if($ps->is_primary)
                                                        <span class="text-[9px] sm:text-[10px] bg-amber-100 text-amber-700 px-0.5 py-0.5 rounded-full">⭐</span>
                                                        @endif
                                                        @if($ps->supplier && $ps->supplier->is_verified)
                                                        <span class="text-[9px] sm:text-[10px] bg-green-100 text-green-700 px-0.5 py-0.5 rounded-full">✓</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($priceTypesForTier as $idx => $pti)
                                            @php
                                            // ✅ یافتن قیمت مطابق هر تأمین‌کننده فعال برای این تیراژ و نوع قیمت
                                            $pricesForType = $activeSuppliersForTier->map(function($ps) use ($pti, $tierIndex) {
                                                return $ps->prices->first(function($p) use ($pti, $tierIndex) {
                                                    $code = $p->priceType ? $p->priceType->code : 'cash';
                                                    $checkPeriodId = $p->checkPeriod ? $p->checkPeriod->id : 0;
                                                    $key = "{$code}_{$checkPeriodId}";
                                                    return $key === $pti['key'] && $p->tier_index == $tierIndex;
                                                });
                                            });
                                            $validPrices = $pricesForType->filter()->pluck('price');
                                            $lowestForType = $validPrices->min();
                                            @endphp
                                            <tr class="group {{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }} hover:bg-blue-50/30 transition-colors">
                                                <td class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 text-right sticky right-0 {{ $idx % 2 === 0 ? 'bg-white group-hover:bg-blue-50/30' : 'bg-gray-50/50 group-hover:bg-blue-50/30' }} z-10 transition-colors border-l-2 border-gray-300">
                                                    {{-- عنوان بهینه برای چکی (مثل ماتریس‌ها) --}}
                                                    @if($pti['check_period_name'])
                                                        <div class="leading-tight">
                                                            <div class="text-[10px] sm:text-xs md:text-sm font-semibold text-gray-900">{{ $pti['name'] }}</div>
                                                            <div class="text-[9px] sm:text-[10px] md:text-xs text-gray-500">{{ $pti['check_period_days'] }} روزه</div>
                                                        </div>
                                                    @else
                                                        <div class="text-[10px] sm:text-xs md:text-sm font-semibold text-gray-900">{{ $pti['name'] }}</div>
                                                    @endif
                                                </td>
                                                @foreach($pricesForType as $price)
                                                <td class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 text-center">
                                                    @if($price)
                                                    <div class="font-extrabold text-sm sm:text-base md:text-lg tabular-nums {{ $price->price == $lowestForType && $validPrices->count() > 1 ? 'text-green-600' : 'text-gray-800' }}">
                                                        {{ PriceHelper::formatNumber($price->price) }}
                                                    </div>
                                                    @if($price->price == $lowestForType && $validPrices->count() > 1)
                                                    <span class="text-[9px] sm:text-[10px] bg-green-100 text-green-700 px-1 py-0.5 rounded-full mt-0.5 inline-block">✓</span>
                                                    @elseif($price->price != $lowestForType && $lowestForType > 0)
                                                    @php $diff = round((($price->price - $lowestForType) / $lowestForType) * 100, 1); @endphp
                                                    <div class="text-[10px] sm:text-xs text-red-400">+{{ $diff }}%</div>
                                                    @endif
                                                    @else
                                                    <span class="text-gray-300 text-sm">—</span>
                                                    @endif
                                                </td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $activeSuppliersForTier->count() + 1 }}" class="px-3 py-4 text-center text-gray-400 text-[10px]">
                                                    قیمتی برای این تیراژ ثبت نشده است
                                                </td>
                                            </tr>
                                        @endforelse

                                        {{-- ردیف خلاصه: پایین‌ترین قیمت هر تأمین‌کننده در این تیراژ --}}
                                        <tr class="bg-blue-50 font-bold border-t-2 border-blue-200">
                                            <td class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 text-right sticky right-0 bg-blue-50 z-10 text-blue-700 text-[10px] sm:text-xs md:text-sm border-l-2 border-gray-300">
                                                🏆 بهترین
                                            </td>
                                            @foreach($activeSuppliersForTier as $ps)
                                            @php 
                                                $tierLowest = $ps->prices
                                                    ->filter(fn($p) => $p->tier_index == $tierIndex)
                                                    ->min('price');
                                                
                                                // محاسبه کمترین قیمت کلی (فقط از suppliers فعال در این تیراژ)
                                                $tierOverallLowest = $activeSuppliersForTier
                                                    ->flatMap(fn($supplier) => $supplier->prices)
                                                    ->filter(fn($p) => $p->tier_index == $tierIndex)
                                                    ->min('price');
                                            @endphp
                                            <td class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 text-center">
                                                @if($tierLowest)
                                                <span class="text-sm sm:text-base md:text-lg tabular-nums {{ $tierLowest == $tierOverallLowest ? 'text-green-600' : 'text-gray-700' }}">
                                                    {{ PriceHelper::formatNumber($tierLowest) }}
                                                </span>
                                                @if($tierLowest == $tierOverallLowest)
                                                <span class="text-[9px] sm:text-[10px] bg-green-200 text-green-800 px-1 py-0.5 rounded-full">🏆</span>
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
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- راهنما - بهبود یافته با توضیح واحد قیمت --}}
                <div class="mt-3 bg-white rounded-xl border border-gray-200 p-3 shadow-sm">
                    <div class="flex items-center gap-2 mb-2.5">
                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-semibold text-gray-700">راهنمای جدول مقایسه:</span>
                    </div>
                    <div class="flex items-start gap-4 flex-wrap">
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 bg-green-50 ring-2 ring-green-400 rounded flex-shrink-0"></div>
                            <span class="text-xs sm:text-sm md:text-base text-gray-600">ارزان‌ترین</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs sm:text-sm md:text-base text-red-400 font-bold">+X%</span>
                            <span class="text-xs sm:text-sm md:text-base text-gray-600">درصد گران‌تر</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs sm:text-sm bg-green-200 text-green-800 px-1 py-0.5 rounded-full">🏆</span>
                            <span class="text-xs sm:text-sm md:text-base text-gray-600">بهترین کل</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs sm:text-sm md:text-base text-gray-600"><strong class="text-amber-700">قیمت‌ها به ازای هر عدد کالا</strong> است (نه کل تیراژ)</span>
                        </div>
                    </div>
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
            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4">مشخصات فنی</h3>
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm sm:text-base font-medium text-gray-700 w-1/3 border-l border-gray-200">
                            {{ $attribute->name }}
                        </td>
                        <td class="px-6 py-4 text-sm sm:text-base text-gray-600 w-2/3">
                            {{ $mergedValues }}
                            @if($attribute->unit)
                            <span class="text-xs sm:text-sm text-gray-400 mr-1">({{ $attribute->unit }})</span>
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
            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center gap-2.5 mb-4">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-200">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h12"/>
                        </svg>
                    </span>
                توضیحات کامل
            </h2>
            <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                <div class="prose prose-sm sm:prose-base max-w-none text-gray-700 leading-relaxed">
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
            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center gap-2.5 mb-5">
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
            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center gap-2.5 mb-5">
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
