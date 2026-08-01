@extends('layouts.app')

@section('title', $category->seo_title ?? $category->name)
@section('description', $category->seo_description ?? 'محصولات دسته‌بندی ' . $category->name)

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">

    {{-- Breadcrumb --}}
    @if(isset($breadcrumb) && is_array($breadcrumb) && count($breadcrumb) > 0)
        <nav class="bg-white border-b border-gray-200 py-3">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <ol class="flex items-center space-x-2 space-x-reverse text-sm">
                    <li>
                        <a href="{{ url('/') }}" class="text-gray-500 hover:text-blue-600 transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                            </svg>
                        </a>
                    </li>
                    @foreach($breadcrumb as $crumb)
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            @if(!$loop->last && isset($crumb['url']) && $crumb['url'])
                                <a href="{{ $crumb['url'] }}" class="text-gray-500 hover:text-blue-600 transition-colors">
                                    {{ $crumb['name'] ?? '' }}
                                </a>
                            @else
                                <span class="text-gray-800 font-medium">{{ $crumb['name'] ?? '' }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </nav>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Category Header --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $category->name }}</h1>
            @if($category->description)
                <p class="text-gray-600 text-lg">{{ $category->description }}</p>
            @endif
        </div>

        {{-- Subcategories --}}
        @if(isset($subcategories) && $subcategories->count() > 0)
            <div class="mb-10">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">زیردسته‌بندی‌ها</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    @foreach($subcategories as $sub)
                        <a href="{{ route('categories.show', $sub->slug) }}"
                           class="bg-white rounded-xl border border-gray-200 p-4 text-center hover:shadow-lg hover:border-blue-300 transition-all duration-300 group">
                            @if($sub->icon)
                                <div class="w-12 h-12 mx-auto mb-3 text-blue-600 group-hover:text-blue-700">
                                    <img src="{{ asset('storage/' . $sub->icon) }}" alt="{{ $sub->name }}" class="w-full h-full object-contain">
                                </div>
                            @else
                                <div class="w-12 h-12 mx-auto mb-3 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </div>
                            @endif
                            <h3 class="text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">{{ $sub->name }}</h3>
                            @if(isset($sub->products_count) && $sub->products_count > 0)
                                <span class="text-xs text-gray-500 mt-1 block">{{ $sub->products_count }} محصول</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Products Section --}}
        <div>
            {{-- Products Header --}}
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-800">
                    محصولات
                    @if(isset($products) && method_exists($products, 'total') && $products->total() > 0)
                        <span class="text-sm font-normal text-gray-500 mr-2">({{ $products->total() }} محصول)</span>
                    @elseif(isset($products) && $products->count() > 0)
                        <span class="text-sm font-normal text-gray-500 mr-2">({{ $products->count() }} محصول)</span>
                    @endif
                </h2>

                {{-- Sort --}}
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">مرتب‌سازی:</label>
                    <select onchange="window.location.href=this.value"
                            class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}"
                                {{ request('sort', 'newest') == 'newest' ? 'selected' : '' }}>جدیدترین</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}"
                                {{ request('sort') == 'oldest' ? 'selected' : '' }}>قدیمی‌ترین</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}"
                                {{ request('sort') == 'name_asc' ? 'selected' : '' }}>نام (الف تا ی)</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}"
                                {{ request('sort') == 'name_desc' ? 'selected' : '' }}>نام (ی تا الف)</option>
                    </select>
                </div>
            </div>

            {{-- Products Grid --}}
            @if(isset($products) && $products->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($products as $product)
                    <x-product.card :product="$product" />

                    @endforeach
                </div>

                {{-- Pagination --}}
                @if(method_exists($products, 'links'))
                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>
                @endif
            @else
                {{-- Empty State --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
                    <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">محصولی یافت نشد</h3>
                    <p class="text-gray-500 mb-6">در حال حاضر محصولی در این دسته‌بندی موجود نیست.</p>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        بازگشت به صفحه اصلی
                    </a>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
