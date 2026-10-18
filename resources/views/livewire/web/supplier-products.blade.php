@php
    use App\Helpers\PriceHelper;
    use Illuminate\Support\Str;
@endphp

<div class="min-h-screen bg-gray-50">
    {{-- JSON-LD Structured Data (Organization) --}}
    @push('head')
    <script type="application/ld+json">
    {!! json_encode($jsonLd ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>
    @endpush

    {{-- JSON-LD Structured Data (Breadcrumb) --}}
    @isset($breadcrumbs)
        @if(!empty($breadcrumbs))
            @push('head')
            <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "BreadcrumbList",
                "itemListElement": [
                    @foreach($breadcrumbs as $index => $crumb)
                    {
                        "@@type": "ListItem",
                        "position": {{ $index + 1 }},
                        "name": "{{ $crumb['name'] }}",
                        "item": "{{ $crumb['url'] ?? url()->current() }}"
                    }{{ !$loop->last ? ',' : '' }}
                    @endforeach
                ]
            }
            </script>
            @endpush
        @endif
    @endisset
    
    {{-- ═══════ BREADCRUMB ═══════ --}}
    @if(!empty($breadcrumbs))
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 pt-4 sm:pt-6">
            <nav class="mb-4 sm:mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center flex-wrap gap-1 text-xs sm:text-sm text-gray-500">
                    @foreach($breadcrumbs as $index => $crumb)
                        <li class="flex items-center">
                            @if($index > 0)
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-gray-300 mx-1 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            @endif
                            @if($crumb['url'])
                                <a href="{{ $crumb['url'] }}" class="hover:text-blue-600 transition-colors">{{ $crumb['name'] }}</a>
                            @else
                                <span class="text-gray-800 font-medium">{{ $crumb['name'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        </div>
    @endif

    {{-- ═══════ SUPPLIER HEADER CARD ═══════ --}}
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl sm:rounded-3xl border border-gray-200 overflow-hidden shadow-sm mb-6">
            {{-- Banner --}}
            <div class="h-24 sm:h-32 bg-gradient-to-l from-blue-500 via-indigo-500 to-purple-600 relative">
                <div class="absolute inset-0 opacity-20">
                    <svg viewBox="0 0 800 200" fill="none" class="w-full h-full">
                        <circle cx="700" cy="50" r="150" fill="white"/>
                        <circle cx="100" cy="180" r="100" fill="white"/>
                    </svg>
                </div>
            </div>

            <div class="px-4 sm:px-6 lg:px-8 pb-6 sm:pb-8 -mt-10 sm:-mt-12 relative">
                <div class="flex flex-col gap-4">
                    {{-- Row 1: Logo + Basic Info --}}
                    <div class="flex flex-col sm:flex-row gap-4">
                        {{-- Logo --}}
                        <div class="shrink-0 mx-auto sm:mx-0">
                            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-white border-4 border-white shadow-lg overflow-hidden">
                                @if($supplier->logo_path)
                                    <img src="{{ $supplier->logoUrl('medium') }}"
                                         alt="{{ $supplier->company_name }}"
                                         width="96" height="96"
                                         loading="eager"
                                         class="w-full h-full object-contain p-2">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-2xl sm:text-3xl font-extrabold text-blue-300 bg-blue-50">
                                        {{ mb_substr($supplier->company_name, 0, 1) }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Basic Info --}}
                        <div class="flex-1 text-center sm:text-right">
                            <div class="flex items-center justify-center sm:justify-start gap-2 flex-wrap mb-2">
                                <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-gray-900">
                                    {{ $supplier->company_name }}
                                </h1>
                                @if($supplier->is_verified)
                                    <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        تأیید شده
                                    </span>
                                @endif
                            </div>

                            @if($supplier->brand_name)
                                <p class="text-gray-500 text-sm mb-1">برند: <span class="font-medium text-gray-700">{{ $supplier->brand_name }}</span></p>
                            @endif

                            @if($supplier->company_name_en)
                                <p class="text-gray-400 text-xs mb-1">{{ $supplier->company_name_en }}</p>
                            @endif

                            @if($supplier->province || $supplier->city)
                                <p class="text-gray-400 text-sm flex items-center justify-center sm:justify-start gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $supplier->city ?? '' }}{{ $supplier->city && $supplier->province ? '، ' : '' }}{{ $supplier->province ?? '' }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Description --}}
                    @if($supplier->description)
                        <div class="bg-gray-50 rounded-xl p-3 sm:p-4">
                            <p class="text-gray-600 text-sm leading-relaxed">{{ $supplier->description }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══════ STATS BAR ═══════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-200 p-3 sm:p-4 text-center">
                <div class="text-xl sm:text-2xl font-extrabold text-blue-600">{{ number_format($supplierStats['total_products'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">محصول</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-200 p-3 sm:p-4 text-center">
                <div class="text-xl sm:text-2xl font-extrabold text-purple-600">{{ number_format($supplierStats['total_categories'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">دسته‌بندی</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-200 p-3 sm:p-4 text-center">
                <div class="text-xl sm:text-2xl font-extrabold text-green-600">{{ number_format($supplierStats['total_views'] ?? 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">بازدید</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-200 p-3 sm:p-4 text-center">
                <div class="text-xl sm:text-2xl font-extrabold text-amber-600">{{ $supplierStats['featured_count'] ?? 0 }}</div>
                <div class="text-xs text-gray-400 mt-1">محصول ویژه</div>
            </div>
        </div>

        {{-- ═══════ CONTACT INFO CARD ═══════ --}}
        @if($canViewContact)
            <div class="bg-white rounded-2xl border border-gray-200 p-4 sm:p-6 mb-6">
                <h2 class="font-bold text-gray-800 text-base sm:text-lg mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    اطلاعات تماس
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @if($supplier->phone)
                        <a href="tel:{{ $supplier->phone }}" class="flex items-center gap-2 bg-gray-50 hover:bg-blue-50 rounded-xl px-3 py-2.5 transition group">
                            <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition">
                                <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">تلفن</div>
                                <div class="text-sm font-bold text-gray-800 rtl:direction-ltr">{{ $supplier->phone }}</div>
                            </div>
                        </a>
                    @endif

                    @if($supplier->mobile)
                        <a href="tel:{{ $supplier->mobile }}" class="flex items-center gap-2 bg-gray-50 hover:bg-green-50 rounded-xl px-3 py-2.5 transition group">
                            <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition">
                                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">موبایل</div>
                                <div class="text-sm font-bold text-gray-800 rtl:direction-ltr">{{ $supplier->mobile }}</div>
                            </div>
                        </a>
                    @endif

                    @if($supplier->email)
                        <a href="mailto:{{ $supplier->email }}" class="flex items-center gap-2 bg-gray-50 hover:bg-red-50 rounded-xl px-3 py-2.5 transition group">
                            <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center group-hover:bg-red-200 transition">
                                <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">ایمیل</div>
                                <div class="text-sm font-bold text-gray-800 break-all">{{ $supplier->email }}</div>
                            </div>
                        </a>
                    @endif

                    @if($supplier->website)
                        <a href="{{ $supplier->website }}" target="_blank" rel="noopener" class="flex items-center gap-2 bg-gray-50 hover:bg-purple-50 rounded-xl px-3 py-2.5 transition group">
                            <div class="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 transition">
                                <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">وب‌سایت</div>
                                <div class="text-sm font-bold text-gray-800">{{ Str::limit(str_replace(['http://', 'https://'], '', $supplier->website), 25) }}</div>
                            </div>
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ═══════ PRODUCTS SECTION WITH SIDEBAR ═══════ --}}
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 pb-8">
        
        {{-- Mobile Filter Button --}}
        <div class="lg:hidden mb-4">
            <button @click="$wire.showMobileFilter = true"
                    class="w-full flex items-center justify-center gap-2 bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
                <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                فیلترها
                @if($activeFiltersCount > 0)
                    <span class="bg-blue-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center">
                        {{ $activeFiltersCount }}
                    </span>
                @endif
            </button>
        </div>

        {{-- Mobile Filter Overlay --}}
        <div x-show="$wire.showMobileFilter"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="$wire.showMobileFilter = false"
             class="fixed inset-0 bg-black/40 z-40 lg:hidden"
             style="display: none;">
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            
            {{-- ═══════════ SIDEBAR FILTERS ═══════════ --}}
            <aside :class="$wire.showMobileFilter ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
                   class="fixed top-0 right-0 h-full w-[320px] bg-white z-50 overflow-y-auto shadow-2xl transition-transform duration-300
                          lg:static lg:w-[280px] lg:min-w-[280px] lg:h-auto lg:shadow-none lg:z-auto lg:rounded-2xl lg:border lg:border-gray-100">

                {{-- Mobile Header --}}
                <div class="flex items-center justify-between p-4 border-b border-gray-100 lg:hidden">
                    <h2 class="font-bold text-gray-800 text-base">فیلترها</h2>
                    <button @click="$wire.showMobileFilter = false" class="p-1 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-4 lg:p-5 space-y-5">
                    
                    {{-- ─── Search in Products ─── --}}
                    <div>
                        <div class="relative">
                            <input type="text" 
                                   wire:model.live.debounce.500ms="searchQuery"
                                   placeholder="جستجو در محصولات {{ $supplier->brand_name ?? $supplier->company_name }}..."
                                   class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 transition placeholder:text-gray-400">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>

                    {{-- ─── Category Filter ─── --}}
                    <div x-data="{ open: true }">
                        <button @click="open = !open" class="w-full flex items-center justify-between py-2">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <svg class="w-4.5 h-4.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                دسته‌بندی
                            </h3>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div x-show="open" x-collapse class="mt-1 space-y-0.5">
                            {{-- All Products --}}
                            <button wire:click="$set('categorySlug', null)"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all text-right
                                           {{ !$categorySlug ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-600 hover:bg-gray-50' }}">
                                <span>همه محصولات</span>
                                <span class="text-[11px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full min-w-[22px] text-center {{ !$categorySlug ? 'bg-blue-100 text-blue-600' : '' }}">
                                    {{ $supplierStats['total_products'] ?? 0 }}
                                </span>
                            </button>

                            @foreach($supplierCategories as $cat)
                                <button wire:click="$set('categorySlug', '{{ $cat->slug }}')"
                                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all text-right
                                               {{ $categorySlug == $cat->slug ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <span class="flex items-center gap-1.5">
                                        @if($cat->icon)
                                            <i class="{{ $cat->icon }} text-sm opacity-60"></i>
                                        @endif
                                        {{ $cat->name }}
                                    </span>
                                    <span class="text-[10px] text-gray-400">{{ $cat->products_count }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    {{-- ─── Price Range Filter ─── --}}
                    @if(($supplierStats['min_price'] ?? 0) > 0)
                    <div x-data="{ open: {{ ($minPrice || $maxPrice) ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="w-full flex items-center justify-between py-2">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <svg class="w-4.5 h-4.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                محدوده قیمت
                            </h3>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div x-show="open" x-collapse class="mt-2 space-y-3">
                            {{-- Quick Price Ranges --}}
                            <div class="flex flex-wrap gap-1.5">
                                @php
                                    $quickRanges = [
                                        ['label' => 'تا ۱ میلیون', 'min' => null, 'max' => 1000000],
                                        ['label' => '۱ تا ۵ میلیون', 'min' => 1000000, 'max' => 5000000],
                                        ['label' => '۵ تا ۲۰ میلیون', 'min' => 5000000, 'max' => 20000000],
                                        ['label' => '۲۰ تا ۵۰ میلیون', 'min' => 20000000, 'max' => 50000000],
                                        ['label' => 'بالای ۵۰ میلیون', 'min' => 50000000, 'max' => null],
                                    ];
                                @endphp
                                @foreach($quickRanges as $range)
                                    @php
                                        $isActive = ($minPrice == $range['min'] && $maxPrice == $range['max']);
                                    @endphp
                                    <button wire:click="applyQuickPriceRange({{ $range['min'] ?? 'null' }}, {{ $range['max'] ?? 'null' }})"
                                            class="text-[11px] px-2 py-1 rounded-full border transition
                                                   {{ $isActive ? 'bg-emerald-50 text-emerald-700 border-emerald-300 font-semibold' : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100' }}">
                                        {{ $range['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- Custom Range Inputs --}}
                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <input type="number" 
                                           wire:model.live.debounce.600ms="minPrice"
                                           placeholder="از (تومان)"
                                           class="w-full text-xs border-gray-200 rounded-lg py-2 px-2.5 focus:ring-emerald-500 focus:border-emerald-500 text-center placeholder:text-gray-400"
                                           min="0" step="10000"
                                           onwheel="this.blur()">
                                </div>
                                <span class="text-gray-300 text-sm">—</span>
                                <div class="flex-1">
                                    <input type="number" 
                                           wire:model.live.debounce.600ms="maxPrice"
                                           placeholder="تا (تومان)"
                                           class="w-full text-xs border-gray-200 rounded-lg py-2 px-2.5 focus:ring-emerald-500 focus:border-emerald-500 text-center placeholder:text-gray-400"
                                           min="0" step="10000"
                                           onwheel="this.blur()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">
                    @endif

                    {{-- ─── Status Filters ─── --}}
                    <div x-data="{ open: {{ ($featured || $inStock) ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="w-full flex items-center justify-between py-2">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <svg class="w-4.5 h-4.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                وضعیت محصول
                            </h3>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div x-show="open" x-collapse class="mt-1 space-y-1">
                            {{-- Featured --}}
                            <button wire:click="$toggle('featured')"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition-all text-right
                                           {{ $featured ? 'bg-amber-50 text-amber-700 font-semibold border border-amber-200' : 'text-gray-600 hover:bg-gray-50' }}">
                                @if($featured)
                                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                @else
                                    <span class="w-4 h-4 border-2 border-gray-300 rounded shrink-0"></span>
                                @endif
                                <span>⭐ فقط محصولات ویژه</span>
                            </button>

                            {{-- In Stock --}}
                            <button wire:click="$toggle('inStock')"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition-all text-right
                                           {{ $inStock ? 'bg-green-50 text-green-700 font-semibold border border-green-200' : 'text-gray-600 hover:bg-gray-50' }}">
                                @if($inStock)
                                    <svg class="w-4 h-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                @else
                                    <span class="w-4 h-4 border-2 border-gray-300 rounded shrink-0"></span>
                                @endif
                                <span>📦 فقط موجود</span>
                            </button>
                        </div>
                    </div>

                    {{-- ─── Clear All Filters ─── --}}
                    @if($activeFiltersCount > 0)
                        <div class="pt-2 border-t border-gray-100">
                            <button wire:click="clearFilters"
                                    class="w-full text-center text-sm font-medium text-red-500 bg-red-50 py-2.5 rounded-xl hover:bg-red-100 transition">
                                🗑️ حذف همه فیلترها
                            </button>
                        </div>
                    @endif

                </div>
            </aside>

            {{-- ═══════════ MAIN CONTENT ═══════════ --}}
            <div class="flex-1 min-w-0">

                {{-- ─── Header Bar ─── --}}
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 mb-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-extrabold text-gray-800">
                            @if($categorySlug && $supplierCategories->where('slug', $categorySlug)->first())
                                {{ $supplierCategories->where('slug', $categorySlug)->first()->name }}
                            @else
                                محصولات {{ $supplier->brand_name ?? $supplier->company_name }}
                            @endif
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ number_format($products->total()) }} محصول یافت شد
                        </p>
                    </div>

                    <div class="flex items-center gap-3 flex-wrap">
                        {{-- Sort Dropdown --}}
                        <div class="flex items-center gap-2" x-data="{ sortOpen: false }">
                            <label class="text-xs text-gray-500 whitespace-nowrap hidden sm:inline">مرتب‌سازی:</label>
                            <div class="relative">
                                <button @click="sortOpen = !sortOpen" @click.away="sortOpen = false"
                                        class="flex items-center gap-2 text-xs border border-gray-200 rounded-lg py-2 px-3 bg-gray-50 font-medium text-gray-700 hover:bg-gray-100 transition min-w-[130px]">
                                    <span>{{ $sortOptions[$sortBy] ?? 'جدیدترین' }}</span>
                                    <svg class="w-3.5 h-3.5 text-gray-400 mr-auto transition-transform" :class="{ 'rotate-180': sortOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <div x-show="sortOpen"
                                     x-transition
                                     class="absolute top-full left-0 mt-1.5 w-44 bg-white rounded-xl shadow-xl border border-gray-100 py-1.5 z-30"
                                     style="display: none;">
                                    @foreach($sortOptions as $sortKey => $sortLabel)
                                        <button wire:click="$set('sortBy', '{{ $sortKey }}')"
                                                class="w-full text-right px-4 py-2 text-xs transition-colors
                                                       {{ $sortBy === $sortKey ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                                            @if($sortBy === $sortKey)
                                                <span class="inline-block w-1.5 h-1.5 bg-blue-500 rounded-full ml-1.5"></span>
                                            @endif
                                            {{ $sortLabel }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Per Page Dropdown --}}
                        <div class="flex items-center gap-2" x-data="{ ppOpen: false }">
                            <label class="text-xs text-gray-500 whitespace-nowrap hidden sm:inline">تعداد:</label>
                            <div class="relative">
                                <button @click="ppOpen = !ppOpen" @click.away="ppOpen = false"
                                        class="flex items-center gap-2 text-xs border border-gray-200 rounded-lg py-2 px-3 bg-gray-50 font-medium text-gray-700 hover:bg-gray-100 transition">
                                    <span>{{ $perPage }} تا</span>
                                    <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="{ 'rotate-180': ppOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <div x-show="ppOpen"
                                     x-transition
                                     class="absolute top-full left-0 mt-1.5 w-24 bg-white rounded-xl shadow-xl border border-gray-100 py-1.5 z-30"
                                     style="display: none;">
                                    @foreach($perPageOptions as $ppOption)
                                        <button wire:click="$set('perPage', {{ $ppOption }})"
                                                class="w-full px-4 py-2 text-xs text-center transition-colors
                                                       {{ $perPage == $ppOption ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                                            {{ $ppOption }} تا
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Loading Indicator ─── --}}
                <div wire:loading class="fixed inset-0 bg-black/20 z-50 flex items-center justify-center">
                    <div class="bg-white rounded-2xl p-6 shadow-2xl">
                        <svg class="w-10 h-10 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>

                {{-- ─── Products Grid ─── --}}
                @if($products->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                        @foreach($products as $product)
                            <x-product.card :product="$product" :supplier="$supplier" />
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>

                    {{-- Results Info --}}
                    <p class="text-center text-xs text-gray-400 mt-3">
                        نمایش {{ $products->firstItem() }} تا {{ $products->lastItem() }} از {{ number_format($products->total()) }} محصول
                    </p>
                @else
                    {{-- Empty State --}}
                    <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-5">
                            <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-600 mb-2">محصولی یافت نشد!</h3>
                        <p class="text-sm text-gray-400 mb-6 max-w-sm mx-auto">با تغییر فیلترها یا جستجوی دیگری دوباره امتحان کنید.</p>
                        @if($activeFiltersCount > 0)
                            <button wire:click="clearFilters"
                                    class="inline-flex items-center gap-2 bg-blue-500 text-white px-6 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-600 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                بازنشانی فیلترها
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    {{-- Hide scrollbar for horizontal category scroll --}}
    <style>
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</div>
