@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Search Form --}}
        <div class="max-w-2xl mx-auto mb-8">
            <form action="{{ route('search') }}" method="GET" class="flex gap-2">
                <input type="text"
                       name="q"
                       value="{{ $query }}"
                       placeholder="جستجوی محصول..."
                       autofocus
                       class="flex-grow border-gray-300 rounded-xl px-4 py-3 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-semibold transition">
                    جستجو
                </button>
            </form>
        </div>

        @if($query)
            <h1 class="text-xl font-bold text-gray-800 mb-6">
                نتایج جستجو برای «<span class="text-indigo-600">{{ $query }}</span>»
            </h1>

            {{-- Category Results --}}
            @if($categories->isNotEmpty())
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-700 mb-3">دسته‌بندی‌ها</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($categories as $category)
                            <a href="{{ route('categories.show', $category->slug) }}"
                               class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-4 py-2 rounded-full text-sm font-medium transition">
                                {{ $category->name }}
                                <span class="bg-indigo-200 text-indigo-800 text-xs px-2 py-0.5 rounded-full">{{ $category->products_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Product Results --}}
            @if($products instanceof \Illuminate\Pagination\LengthAwarePaginator && $products->isNotEmpty())
                <h2 class="text-lg font-semibold text-gray-700 mb-4">
                    محصولات
                    <span class="text-sm text-gray-400 font-normal">({{ $products->total() }} نتیجه)</span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($products as $product)
                        <x-product.card :product="$product" :show-supplier="true" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @elseif($categories->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">نتیجه‌ای یافت نشد</h3>
                    <p class="text-gray-400">عبارت دیگری را جستجو کنید</p>
                </div>
            @endif
        @else
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-600">عبارت مورد نظر خود را جستجو کنید</h3>
            </div>
        @endif
    </div>
@endsection
