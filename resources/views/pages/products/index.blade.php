{{-- resources/views/pages/products/index.blade.php --}}
@extends('layouts.app')

@section('title', $seo['title'] ?? 'محصولات')
@section('description', $seo['description'] ?? '')

@section('content')
<div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6"
     x-data="productFilters()"
     @supplier-changed.window="refreshCategoryCounts($event.detail)"
     @category-changed.window="refreshSupplierList($event.detail.categorySlug)">

    {{-- ══════════ Breadcrumb ══════════ --}}
    <nav class="mb-4 text-sm text-gray-500 flex items-center gap-1 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-blue-600 transition">خانه</a>
        <svg class="w-3.5 h-3.5 text-gray-300 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <span class="text-gray-800 font-medium">محصولات</span>
    </nav>

    {{-- ══════════ Active Filters Tags ══════════ --}}
    @if(!empty($activeFilters))
        <div class="mb-4 flex items-center flex-wrap gap-2">
            <span class="text-sm text-gray-500 ml-1">فیلترهای فعال:</span>
            @foreach($activeFilters as $filter)
            <a href="{{ $filter['remove_url'] ?? route('products.index', array_merge(
    request()->except([$filter['key'], 'page']),
)) }}"
                   class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 text-xs font-medium px-3 py-1.5 rounded-full border border-blue-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition group">
                    {{ $filter['label'] }}
                    <svg class="w-3.5 h-3.5 text-blue-400 group-hover:text-red-500 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </a>
            @endforeach
            @if(count($activeFilters) > 1)
                <a href="{{ route('products.index') }}"
                   class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 font-medium mr-2 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    حذف همه
                </a>
            @endif
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-6">

        {{-- ══════════════════════════════════════════ --}}
        {{-- ═══════════ SIDEBAR FILTERS ═══════════ --}}
        {{-- ══════════════════════════════════════════ --}}

        {{-- Mobile Filter Button --}}
        <div class="lg:hidden mb-2">
            <button @click="showMobileFilter = true"
                    class="w-full flex items-center justify-center gap-2 bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
                <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                فیلترها
                @if(!empty($activeFilters))
                    <span class="bg-blue-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center">{{ count($activeFilters) }}</span>
                @endif
            </button>
        </div>

        {{-- Mobile Filter Overlay --}}
        <div x-show="showMobileFilter"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showMobileFilter = false"
             class="fixed inset-0 bg-black/40 z-40 lg:hidden"
             style="display: none;">
        </div>

        {{-- Sidebar (Desktop: static, Mobile: slide-in) --}}
        <aside :class="showMobileFilter ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
               class="fixed top-0 right-0 h-full w-[320px] bg-white z-50 overflow-y-auto shadow-2xl transition-transform duration-300
                      lg:static lg:w-[280px] lg:min-w-[280px] lg:h-auto lg:shadow-none lg:z-auto lg:rounded-2xl lg:border lg:border-gray-100">

            {{-- Mobile Header --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-100 lg:hidden">
                <h2 class="font-bold text-gray-800 text-base">فیلترها</h2>
                <button @click="showMobileFilter = false" class="p-1 hover:bg-gray-100 rounded-lg transition">
                    <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-4 lg:p-5 space-y-5">

                {{-- ─── Search in Products ─── --}}
                <div>
                    <form action="{{ route('products.index') }}" method="GET">
                        @foreach(request()->except(['q', 'page']) as $key => $value)
                        @if(is_array($value))
                        @foreach($value as $subKey => $subValue)
                        @if(is_array($subValue))
                        @foreach($subValue as $nestedValue)
                        <input type="hidden" name="{{ $key }}[{{ $subKey }}][]" value="{{ $nestedValue }}">
                        @endforeach
                        @else
                        <input type="hidden" name="{{ $key }}[{{ $subKey }}]" value="{{ $subValue }}">
                        @endif
                        @endforeach
                        @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                        @endforeach


                        <div class="relative">
                            <input type="text" name="q" value="{{ $searchQuery }}"
                                   placeholder="جستجو در محصولات..."
                                   class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 transition placeholder:text-gray-400">
                            <button type="submit" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-500 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ─── Category Filter (Accordion Tree) ─── --}}
                <div x-data="{ open: true }">
                    <button @click="open = !open" class="w-full flex items-center justify-between py-2">
                        <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                            <svg class="w-4.5 h-4.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            دسته‌بندی
                            <span x-show="categoryCountsLoading" class="inline-block w-3 h-3 border-2 border-blue-400 border-t-transparent rounded-full animate-spin"></span>
                        </h3>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-collapse class="mt-1 space-y-0.5">
                        {{-- All Products --}}
                        <a href="{{ route('products.index', request()->except(['category', 'page'])) }}"
                           @click="$dispatch('category-changed', { categorySlug: null })"
                           class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-all
                                  {{ !request('category') ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-gray-600 hover:bg-gray-50' }}">
                            <span>همه محصولات</span>
                            @if(!request('category'))
                                <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @endif
                        </a>

                        @if(isset($categories) && $categories->count())
                            @foreach($categories as $rootCategory)
                                <div
                                    x-show="catCount({{ $rootCategory->id }}) > 0"
                                    x-data="{ expanded: {{ \App\Helpers\CategoryHelper::isActiveOrHasActiveChild($rootCategory, request('category')) ? 'true' : 'false' }} }"
                                >
                                    <div class="flex items-center">
                                        @if($rootCategory->children->count())
                                            <button @click="expanded = !expanded" class="p-1 hover:bg-gray-100 rounded transition ml-0.5 shrink-0">
                                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="{ 'rotate-90': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        @else
                                            <span class="w-5 shrink-0"></span>
                                        @endif
                                        <a href="{{ route('products.index', array_merge(request()->except(['category', 'page']), ['category' => $rootCategory->slug])) }}"
                                           @click="$dispatch('category-changed', { categorySlug: '{{ $rootCategory->slug }}' })"
                                           class="flex-1 flex items-center justify-between px-2 py-2 rounded-lg text-sm font-semibold transition-all
                                                  {{ request('category') === $rootCategory->slug ? 'bg-blue-50 text-blue-700' : 'text-gray-800 hover:bg-gray-50' }}">
                                            <span class="flex items-center gap-1.5">
                                                @if($rootCategory->icon)
                                                    <i class="{{ $rootCategory->icon }} text-sm opacity-60"></i>
                                                @endif
                                                {{ $rootCategory->name }}
                                            </span>
                                            <span
                                                class="text-[11px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full min-w-[22px] text-center {{ request('category') === $rootCategory->slug ? 'bg-blue-100 text-blue-600' : '' }}"
                                                x-text="catCount({{ $rootCategory->id }})"
                                            ></span>
                                        </a>
                                    </div>

                                    @if($rootCategory->children->count())
                                        <div x-show="expanded" x-collapse class="mr-5 mt-0.5 space-y-0.5 border-r-2 border-gray-100 pr-2">
                                            @foreach($rootCategory->children as $child)
                                                <div
                                                    x-show="catCount({{ $child->id }}) > 0"
                                                    x-data="{ subExpanded: {{ request('category') === $child->slug || ($child->children->count() && $child->children->pluck('slug')->contains(request('category'))) ? 'true' : 'false' }} }"
                                                >
                                                    <div class="flex items-center">
                                                        @if($child->children->count())
                                                            <button @click="subExpanded = !subExpanded" class="p-0.5 hover:bg-gray-100 rounded transition ml-0.5 shrink-0">
                                                                <svg class="w-3 h-3 text-gray-400 transition-transform" :class="{ 'rotate-90': subExpanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                            </button>
                                                        @else
                                                            <span class="w-4 shrink-0"></span>
                                                        @endif
                                                        <a href="{{ route('products.index', array_merge(request()->except(['category', 'page']), ['category' => $child->slug])) }}"
                                                           @click="$dispatch('category-changed', { categorySlug: '{{ $child->slug }}' })"
                                                           class="flex-1 flex items-center justify-between px-2 py-1.5 rounded-lg text-sm transition-all
                                                                  {{ request('category') === $child->slug ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                                                            <span>{{ $child->name }}</span>
                                                            <span
                                                                class="text-[10px] text-gray-400"
                                                                x-text="catCount({{ $child->id }})"
                                                            ></span>
                                                        </a>
                                                    </div>

                                                    @if($child->children->count())
                                                        <div x-show="subExpanded" x-collapse class="mr-4 mt-0.5 space-y-0.5">
                                                            @foreach($child->children as $grandChild)
                                                                <a
                                                                    x-show="catCount({{ $grandChild->id }}) > 0"
                                                                    href="{{ route('products.index', array_merge(request()->except(['category', 'page']), ['category' => $grandChild->slug])) }}"
                                                                    @click="$dispatch('category-changed', { categorySlug: '{{ $grandChild->slug }}' })"
                                                                    class="flex items-center justify-between px-2 py-1.5 rounded-lg text-xs transition-all
                                                                           {{ request('category') === $grandChild->slug ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50' }}">
                                                                    <span>{{ $grandChild->name }}</span>
                                                                    <span
                                                                        class="text-[10px] text-gray-400"
                                                                        x-text="catCount({{ $grandChild->id }})"
                                                                    ></span>
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <hr class="border-gray-100">
                @if(isset($filterableAttributes) && $filterableAttributes->isNotEmpty())
                <div x-data="{ open: true }">
                    <button @click="open = !open" class="w-full flex items-center justify-between py-2">
                        <h3 class="font-bold text-gray-800 text-sm">فیلتر بر اساس مشخصه‌ها</h3>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="w-4 h-4 text-gray-500 transition-transform"
                            :class="{ 'rotate-180': open }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div x-show="open" x-collapse class="mt-3 space-y-4">
                        <form action="{{ route('products.index') }}" method="GET" class="space-y-4">
                            {{-- پارامترهای Hidden برای حفظ فیلترهای دیگر --}}
                            @foreach(request()->except(['attributes', 'page']) as $key => $value)
                            @if(is_array($value))
                            @foreach($value as $subValue)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $subValue }}">
                            @endforeach
                            @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                            @endforeach

                            {{-- حلقه برای نمایش مشخصه‌های قابل فیلتر --}}
                            @foreach($filterableAttributes as $attribute)
                            @php
                            $selectedValues = array_map(
                            'intval',
                            $selectedAttributeFilters[$attribute->id] ?? []
                            );
                            @endphp

                            <div x-data="{ open: false, searchTerm: '' }" class="border border-gray-100 rounded-xl p-3">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="w-full flex items-center justify-between gap-3 text-right"
                                    :aria-expanded="open.toString()"
                                >
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-semibold text-gray-800">
                                            {{ $attribute->name }}
                                        </h4>

                                        @if($attribute->unit)
                                        <span class="text-xs text-gray-400">
                        {{ $attribute->unit }}
                    </span>
                                        @endif
                                    </div>

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="w-4 h-4 text-gray-500 transition-transform"
                                        :class="{ 'rotate-180': open }"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>

                                <div x-show="open" x-transition x-cloak class="mt-3 space-y-3">
                                    @if($attribute->values->count() > 8)
                                    <div class="relative">
                                        <input
                                            type="text"
                                            x-model.debounce.300ms="searchTerm"
                                            placeholder="جستجو در {{ $attribute->name }}"
                                            class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 pl-9 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-[#d4af37] focus:ring-0"
                                        >

                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="w-4 h-4 text-gray-400 absolute right-2 top-1/2 -translate-y-1/2"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3m1.8-5.2a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                                        </svg>
                                    </div>
                                    @endif

                                    <div class="max-h-40 space-y-2 overflow-y-auto pr-1">
                                        @forelse($attribute->values as $value)
                                        <label
                                            x-show="!searchTerm || @js(mb_strtolower($value->value)).includes(searchTerm.toLowerCase())"
                                            class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 transition hover:bg-gray-50"
                                        >
                                            <input
                                                type="checkbox"
                                                name="attributes[{{ $attribute->id }}][]"
                                                value="{{ $value->id }}"
                                                @checked(in_array((int) $value->id, $selectedValues, true))
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            >

                                            <span class="text-sm text-gray-700">
                            {{ $value->value }}
                        </span>
                                        </label>
                                        @empty
                                        <p class="text-sm text-gray-400">
                                            برای این مشخصه مقداری ثبت نشده است.
                                        </p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            @endforeach
                            {{-- دکمه‌های اعمال و حذف فیلتر --}}
                            <div class="flex gap-2 pt-2 border-t border-gray-100">
                                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition">
                                    اعمال فیلتر
                                </button>

                                <a href="{{ route('products.index', request()->except(['attributes', 'page'])) }}"
                                   class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition text-center">
                                    حذف
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                @endif


                <hr class="border-gray-100">

                {{-- ─── Price Range Filter (🔒 فقط برای کاربران با دسترسی قیمت) ─── --}}
                @if(isset($priceRange) && !empty($canSeePriceFilter))
                <div x-data="{
                    open: {{ (request('min_price') || request('max_price')) ? 'true' : 'false' }},
                    minPrice: '{{ request('min_price', '') }}',
                    maxPrice: '{{ request('max_price', '') }}',
                }">
                    <button @click="open = !open" class="w-full flex items-center justify-between py-2">
                        <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                            <svg class="w-4.5 h-4.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            محدوده قیمت
                        </h3>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-collapse class="mt-2">
                        <form action="{{ route('products.index') }}" method="GET">
                            @foreach(request()->except(['min_price', 'max_price', 'page']) as $key => $value)
                            @if(is_array($value))
                            @foreach($value as $subKey => $subValue)
                            @if(is_array($subValue))
                            @foreach($subValue as $nestedValue)
                            <input type="hidden" name="{{ $key }}[{{ $subKey }}][]" value="{{ $nestedValue }}">
                            @endforeach
                            @else
                            <input type="hidden" name="{{ $key }}[{{ $subKey }}]" value="{{ $subValue }}">
                            @endif
                            @endforeach
                            @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                            @endforeach


                            <div class="space-y-3">
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
                                            $isActive = (request('min_price') == $range['min'] && request('max_price') == $range['max']);
                                            $rangeParams = array_merge(
                                                request()->except(['min_price', 'max_price', 'page']),
                                                array_filter(['min_price' => $range['min'], 'max_price' => $range['max']])
                                            );
                                        @endphp
                                        <a href="{{ route('products.index', $rangeParams) }}"
                                           class="text-[11px] px-2 py-1 rounded-full border transition
                                                  {{ $isActive ? 'bg-emerald-50 text-emerald-700 border-emerald-300 font-semibold' : 'bg-gray-50 text-gray-500 border-gray-200 hover:bg-gray-100' }}">
                                            {{ $range['label'] }}
                                        </a>
                                    @endforeach
                                </div>

                                {{-- Custom Range Inputs --}}
                                <div class="flex items-center gap-2">
                                    <div class="flex-1">
                                        <input type="number" name="min_price" x-model="minPrice"
                                               placeholder="از (تومان)"
                                               class="w-full text-xs border-gray-200 rounded-lg py-2 px-2.5 focus:ring-emerald-500 focus:border-emerald-500 text-center placeholder:text-gray-400"
                                               min="0" step="10000">
                                    </div>
                                    <span class="text-gray-300 text-sm">—</span>
                                    <div class="flex-1">
                                        <input type="number" name="max_price" x-model="maxPrice"
                                               placeholder="تا (تومان)"
                                               class="w-full text-xs border-gray-200 rounded-lg py-2 px-2.5 focus:ring-emerald-500 focus:border-emerald-500 text-center placeholder:text-gray-400"
                                               min="0" step="10000">
                                    </div>
                                </div>

                                <button type="submit"
                                        class="w-full text-xs font-medium bg-emerald-500 text-white py-2 rounded-lg hover:bg-emerald-600 transition flex items-center justify-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                    اعمال فیلتر قیمت
                                </button>
                            </div>
                        </form>

                        @if(request('min_price') || request('max_price'))
                            <a href="{{ route('products.index', request()->except(['min_price', 'max_price', 'page'])) }}"
                               class="block text-center text-[11px] text-red-500 hover:text-red-700 mt-2 transition">
                                ✕ حذف فیلتر قیمت
                            </a>
                        @endif
                    </div>
                </div>

                <hr class="border-gray-100">
                @endif

                {{-- ─── Supplier / Brand Filter (🔒 فقط برای کاربران مجاز) ─── --}}
                @if(!empty($canSeeSupplierFilter))
                @php
                    $initialSupplierIds = collect(request()->input('supplier_id', []))
                        ->map(fn($v) => (int)$v)->filter(fn($v) => $v > 0)->values()->toArray();
                @endphp
                <div x-data="{
                    open: {{ !empty($initialSupplierIds) ? 'true' : 'false' }},
                    searchTerm: '',
                    showAll: false,
                    maxVisible: 8,
                    selectedSuppliers: {{ json_encode($initialSupplierIds) }},

                    get filteredList() {
                        return this.$root.__x.$data.supplierList.filter(s =>
                            s.count > 0 &&
                            (!this.searchTerm || s.name.toLowerCase().includes(this.searchTerm.toLowerCase()))
                        );
                    },

                    isSelected(id) {
                        return this.selectedSuppliers.includes(id);
                    },

                    toggle(id) {
                        if (this.isSelected(id)) {
                            this.selectedSuppliers = this.selectedSuppliers.filter(s => s !== id);
                        } else {
                            this.selectedSuppliers.push(id);
                        }
                        this.$root.dispatchEvent(new CustomEvent('supplier-changed', {
                            bubbles: true,
                            detail: { supplier_id: [...this.selectedSuppliers] }
                        }));
                    },

                    buildUrl() {
                        const base = '{{ route('products.index') }}';
                        const params = new URLSearchParams();
                        @foreach(request()->except(['supplier_id', 'page']) as $key => $value)
                            @if(is_array($value))
                                @foreach($value as $subKey => $subValue)
                                    @if(is_array($subValue))
                                        @foreach($subValue as $nestedValue)
                                            params.append('{{ $key }}[{{ $subKey }}][]', '{{ $nestedValue }}');
                                        @endforeach
                                    @else
                                        params.append('{{ $key }}[{{ $subKey }}]', '{{ $subValue }}');
                                    @endif
                                @endforeach
                            @else
                                params.append('{{ $key }}', '{{ $value }}');
                            @endif
                        @endforeach
                        this.selectedSuppliers.forEach(id => params.append('supplier_id[]', id));
                        const qs = params.toString();
                        return qs ? base + '?' + qs : base;
                    },

                    apply() {
                        window.location.href = this.buildUrl();
                    },
                }">
                    <button @click="open = !open" class="w-full flex items-center justify-between py-2">
                        <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                            <svg class="w-4.5 h-4.5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            برند / تأمین‌کننده
                            <span x-show="supplierListLoading" class="inline-block w-3 h-3 border-2 border-purple-400 border-t-transparent rounded-full animate-spin"></span>
                            <span x-show="selectedSuppliers.length > 0"
                                  class="bg-purple-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center"
                                  x-text="selectedSuppliers.length"></span>
                        </h3>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-collapse class="mt-2 space-y-2">
                        {{-- جستجو درون لیست --}}
                        <input type="text" x-model="searchTerm" x-show="supplierList.length > 8"
                               placeholder="جستجوی برند..."
                               class="w-full text-xs border-gray-200 rounded-lg py-2 px-3 focus:ring-purple-500 focus:border-purple-500 bg-gray-50 placeholder:text-gray-400">

                        {{-- لیست کاملاً reactive از supplierList --}}
                        <div class="space-y-0.5 max-h-[300px] overflow-y-auto custom-scrollbar">
                            <template x-for="(s, index) in supplierList.filter(s => s.count > 0)" :key="s.id">
                                <button
                                    type="button"
                                    @click="toggle(s.id)"
                                    x-show="(showAll || index < maxVisible) && (!searchTerm || s.name.toLowerCase().includes(searchTerm.toLowerCase()))"
                                    :class="isSelected(s.id)
                                        ? 'bg-purple-50 text-purple-700 font-semibold border border-purple-200'
                                        : 'text-gray-600 hover:bg-gray-50'"
                                    class="w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-sm transition-all text-right">
                                    <span class="flex items-center gap-2">
                                        <span class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-colors"
                                              :class="isSelected(s.id) ? 'bg-purple-500 border-purple-500' : 'border-gray-300'">
                                            <svg x-show="isSelected(s.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </span>
                                        <span class="truncate" x-text="s.name"></span>
                                        <svg x-show="s.verified" class="w-3 h-3 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full transition-colors"
                                          :class="isSelected(s.id) ? 'bg-purple-100 text-purple-600' : 'bg-gray-100 text-gray-400'"
                                          x-text="s.count"></span>
                                </button>
                            </template>

                            {{-- empty state --}}
                            <p x-show="supplierList.filter(s => s.count > 0).length === 0 && !supplierListLoading"
                               class="text-xs text-gray-400 text-center py-4">
                                تأمین‌کننده‌ای یافت نشد
                            </p>
                        </div>

                        <button
                            x-show="supplierList.filter(s => s.count > 0).length > maxVisible"
                            @click="showAll = !showAll"
                            class="w-full text-xs text-purple-500 hover:text-purple-700 py-1 font-medium transition">
                            <span x-text="showAll ? '▲ نمایش کمتر' : '▼ نمایش بیشتر'"></span>
                        </button>

                        {{-- دکمه اعمال — فقط وقتی selection تغییر کرده --}}
                        <div x-show="JSON.stringify(selectedSuppliers.slice().sort()) !== JSON.stringify({{ json_encode(collect($initialSupplierIds)->sort()->values()->toArray()) }}.slice().sort())"
                             class="pt-2 border-t border-gray-100 flex gap-2">
                            <button @click="apply()"
                                    class="flex-1 text-xs font-medium bg-purple-500 text-white py-2 rounded-lg hover:bg-purple-600 transition">
                                اعمال (<span x-text="selectedSuppliers.length"></span> تأمین‌کننده)
                            </button>
                            <button x-show="selectedSuppliers.length > 0"
                                    @click="selectedSuppliers = []; apply()"
                                    class="text-xs text-red-500 hover:text-red-700 px-3 py-2 rounded-lg border border-red-100 hover:border-red-300 transition">
                                حذف
                            </button>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-100">
                @endif

                {{-- ─── Status Filters ─── --}}
                <div x-data="{ open: {{ (request('featured') || request('in_stock')) ? 'true' : 'false' }} }">
                    <button @click="open = !open" class="w-full flex items-center justify-between py-2">
                        <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                            <svg class="w-4.5 h-4.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            وضعیت محصول
                        </h3>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-collapse class="mt-1 space-y-1">
                        {{-- Featured --}}
                        @php $isFeatured = request('featured') == '1'; @endphp
                        <a href="{{ $isFeatured
                            ? route('products.index', request()->except(['featured', 'page']))
                            : route('products.index', array_merge(request()->except('page'), ['featured' => '1']))
                        }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition-all
                                  {{ $isFeatured ? 'bg-amber-50 text-amber-700 font-semibold border border-amber-200' : 'text-gray-600 hover:bg-gray-50' }}">
                            @if($isFeatured)
                                <svg class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            @else
                                <span class="w-4 h-4 border-2 border-gray-300 rounded shrink-0"></span>
                            @endif
                            <span>⭐ فقط محصولات ویژه</span>
                        </a>

                        {{-- In Stock --}}
                        @php $inStock = request('in_stock') == '1'; @endphp
                        <a href="{{ $inStock
                            ? route('products.index', request()->except(['in_stock', 'page']))
                            : route('products.index', array_merge(request()->except('page'), ['in_stock' => '1']))
                        }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm transition-all
                                  {{ $inStock ? 'bg-green-50 text-green-700 font-semibold border border-green-200' : 'text-gray-600 hover:bg-gray-50' }}">
                            @if($inStock)
                                <svg class="w-4 h-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            @else
                                <span class="w-4 h-4 border-2 border-gray-300 rounded shrink-0"></span>
                            @endif
                            <span>📦 فقط موجود</span>
                        </a>
                    </div>
                </div>

                {{-- ─── Clear All Filters (only on mobile) ─── --}}
                @if(!empty($activeFilters))
                    <div class="pt-2 border-t border-gray-100 lg:hidden">
                        <a href="{{ route('products.index') }}"
                           class="block w-full text-center text-sm font-medium text-red-500 bg-red-50 py-2.5 rounded-xl hover:bg-red-100 transition">
                            🗑️ حذف همه فیلترها
                        </a>
                    </div>
                @endif

            </div>
        </aside>

        {{-- ══════════════════════════════════════════ --}}
        {{-- ═══════════ MAIN CONTENT ═══════════════ --}}
        {{-- ══════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0">

            {{-- ─── Header Bar (✅ Dropdown اصلاح شده) ─── --}}
            <div class="bg-white rounded-xl border border-gray-100 px-4 py-3 mb-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-extrabold text-gray-800">
                        @if(request('category') && isset($activeFilters['category']))
                            {{ str_replace('دسته‌بندی: ', '', $activeFilters['category']['label']) }}
                        @else
                            محصولات
                        @endif
                    </h1>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ number_format($products->total()) }} محصول یافت شد
                    </p>
                </div>

                <div class="flex items-center gap-3 flex-wrap">
                    {{-- Sort Dropdown (✅ اصلاح شده — Alpine.js Dropdown) --}}
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
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute top-full left-0 mt-1.5 w-44 bg-white rounded-xl shadow-xl border border-gray-100 py-1.5 z-30"
                                 style="display: none;">
                                @foreach($sortOptions as $sortKey => $sortLabel)
                                    <a href="{{ route('products.index', array_merge(request()->except(['sort', 'page']), ['sort' => $sortKey])) }}"
                                       class="block px-4 py-2 text-xs transition-colors
                                              {{ $sortBy === $sortKey ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                                        @if($sortBy === $sortKey)
                                            <span class="inline-block w-1.5 h-1.5 bg-blue-500 rounded-full ml-1.5"></span>
                                        @endif
                                        {{ $sortLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Per Page Dropdown (✅ اصلاح شده — Alpine.js Dropdown) --}}
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
                                    <a href="{{ route('products.index', array_merge(request()->except(['per_page', 'page']), ['per_page' => $ppOption])) }}"
                                       class="block px-4 py-2 text-xs text-center transition-colors
                                              {{ $perPage == $ppOption ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-50' }}">
                                        {{ $ppOption }} تا
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- View Mode Toggle --}}
                    <div class="hidden sm:flex items-center border border-gray-200 rounded-lg overflow-hidden">
                        <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-blue-50 text-blue-600' : 'text-gray-400 hover:bg-gray-50'" class="p-2 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </button>
                        <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-blue-50 text-blue-600' : 'text-gray-400 hover:bg-gray-50'" class="p-2 transition border-r border-gray-200">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ─── Products Grid / List ─── --}}
            @if($products->isNotEmpty())
                {{-- Grid View --}}
                <div x-show="viewMode === 'grid'"
                     class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($products as $product)
                        <x-product.card :product="$product" />
                    @endforeach
                </div>

                {{-- List View --}}
                <div x-show="viewMode === 'list'" class="space-y-3" style="display: none;">
                    @foreach($products as $product)
                        <x-product.card :product="$product" view="list" />
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-8">
                    {{ $products->onEachSide(1)->links('pagination.products') }}
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
                    @if(!empty($activeFilters))
                        <a href="{{ route('products.index') }}"
                           class="inline-flex items-center gap-2 bg-blue-500 text-white px-6 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-600 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            بازنشانی فیلترها
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function productFilters() {
    return {
        showMobileFilter: false,
        viewMode: localStorage.getItem('products_view_mode') || 'grid',

        // ─── Dependent Filters ───
        // تعداد محصولات هر دسته‌بندی — مقداردهی اولیه از server
        categoryCounts: @php
            $initialCounts = [];
            if (isset($categories)) {
                foreach ($categories as $root) {
                    $initialCounts[$root->id] = $root->total_products_count ?? 0;
                    foreach ($root->children ?? collect() as $child) {
                        $initialCounts[$child->id] = $child->total_products_count ?? 0;
                        foreach ($child->children ?? collect() as $gc) {
                            $initialCounts[$gc->id] = $gc->active_products_count ?? $gc->total_products_count ?? 0;
                        }
                    }
                }
            }
            echo json_encode($initialCounts);
        @endphp,

        categoryCountsLoading: false,

        // ─── Supplier state (برای وابستگی supplier list به category) ───
        supplierList: @php
            // مقداردهی اولیه لیست supplierها از server
            if (isset($suppliers) && $suppliers->count()) {
                $initialSuppliers = $suppliers->map(fn($s) => [
                    'id'       => $s->id,
                    'name'     => $s->brand_name ?: $s->company_name,
                    'verified' => (bool) $s->is_verified,
                    'count'    => (int) ($s->products_count ?? 0),
                ])->values()->toArray();
            } else {
                $initialSuppliers = [];
            }
            echo json_encode($initialSuppliers);
        @endphp,
        supplierListLoading: false,
        canSeeSupplierFilter: {{ !empty($canSeeSupplierFilter) ? 'true' : 'false' }},

        init() {
            this.$watch('viewMode', (val) => {
                localStorage.setItem('products_view_mode', val);
            });
            this.$watch('showMobileFilter', (val) => {
                document.body.style.overflow = val ? 'hidden' : '';
            });
        },

        // ─── بروزرسانی تعداد categories بر اساس supplier انتخابی ───
        async refreshCategoryCounts(params) {
            this.categoryCountsLoading = true;
            try {
                const url = new URL('{{ route("products.category-counts") }}', window.location.origin);
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.delete('supplier_id[]');
                currentParams.delete('supplier_id');
                currentParams.delete('page');
                currentParams.forEach((v, k) => url.searchParams.append(k, v));
                if (params.supplier_id && params.supplier_id.length > 0) {
                    params.supplier_id.forEach(id => url.searchParams.append('supplier_id[]', id));
                }
                const resp = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await resp.json();
                this.categoryCounts = data.counts || {};
            } catch (e) {
                console.error('refreshCategoryCounts failed', e);
            } finally {
                this.categoryCountsLoading = false;
            }
        },

        // ─── بروزرسانی لیست suppliers بر اساس category انتخابی ───
        async refreshSupplierList(categorySlug) {
            if (!this.canSeeSupplierFilter) return;
            this.supplierListLoading = true;
            try {
                const url = new URL('{{ route("products.filtered-suppliers") }}', window.location.origin);
                if (categorySlug) url.searchParams.set('category', categorySlug);
                // حفظ supplier_id های فعال در URL
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.getAll('supplier_id[]').forEach(id => url.searchParams.append('supplier_id[]', id));
                const resp = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await resp.json();
                if (data.suppliers && data.suppliers.length > 0) {
                    this.supplierList = data.suppliers;
                }
                // اگر لیست خالی بود، لیست فعلی را نگه می‌داریم
            } catch (e) {
                console.error('refreshSupplierList failed', e);
            } finally {
                this.supplierListLoading = false;
            }
        },

        catCount(id) {
            return this.categoryCounts[id] ?? 0;
        },

        supplierCount(id) {
            const s = this.supplierList.find(s => s.id === id);
            return s ? s.count : 0;
        },
    }
}
</script>
@endpush

@push('styles')
<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
@endpush
