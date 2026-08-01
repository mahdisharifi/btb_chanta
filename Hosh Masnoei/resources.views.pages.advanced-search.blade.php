@extends('layouts.app')

@section('title', 'جستجوی پیشرفته محصولات')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">جستجوی پیشرفته محصولات</h1>
        <p class="text-gray-600">با استفاده از فیلترهای زیر محصولات مورد نظر خود را پیدا کنید</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- فیلترهای سمت راست -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b">فیلترها</h2>

                @livewire('web.advanced-search-filters')
            </div>

            <!-- آمار جستجو -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="font-semibold text-gray-800 mb-3">آمار جستجو</h3>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">تعداد کل محصولات:</span>
                        <span class="font-medium">{{ number_format($products->total()) }}</span>
                    </div>
                    @if(request()->filled('category_id'))
                    @php
                    $category = \App\Models\Category::find(request('category_id'));
                    @endphp
                    @if($category)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">دسته‌بندی:</span>
                        <span class="font-medium">{{ $category->name }}</span>
                    </div>
                    @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- نتایج سمت چپ -->
        <div class="lg:col-span-3">
            <!-- نوار مرتب‌سازی و نتایج -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-gray-700">
                            <span class="font-semibold">{{ $products->total() }}</span> محصول یافت شد
                        </p>
                        @if(request()->filled('q'))
                        <p class="text-sm text-gray-500 mt-1">
                            نتایج جستجو برای: "{{ request('q') }}"
                        </p>
                        @endif
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label for="sort" class="text-sm text-gray-600">مرتب‌سازی بر اساس:</label>
                            <select
                                id="sort"
                                class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                wire:model.live="sortBy"
                            >
                                <option value="created_at">جدیدترین</option>
                                <option value="title">نام محصول</option>
                                <option value="price_asc">قیمت: کم به زیاد</option>
                                <option value="price_desc">قیمت: زیاد به کم</option>
                                <option value="view_count">پربازدیدترین</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="direction" class="text-sm text-gray-600">ترتیب:</label>
                            <select
                                id="direction"
                                class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                wire:model.live="sortDirection"
                            >
                                <option value="desc">نزولی</option>
                                <option value="asc">صعودی</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- نتایج محصولات -->
            @if($products->count())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($products as $product)
                @include('components.product.card', ['product' => $product])
                @endforeach
            </div>

            <!-- صفحه‌بندی -->
            <div class="mt-8">
                {{ $products->links() }}
            </div>
            @else
            <!-- پیام عدم یافتن نتیجه -->
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <x-heroicon-o-magnifying-glass class="mx-auto h-16 w-16 text-gray-400 mb-4" />
                <h3 class="text-xl font-medium text-gray-900 mb-2">نتیجه‌ای یافت نشد</h3>
                <p class="text-gray-600 mb-6">لطفاً فیلترهای جستجو را تغییر دهید یا عبارت جستجوی دیگری را امتحان کنید.</p>
                <button
                    onclick="window.location.href='{{ route('advanced-search') }}'"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <x-heroicon-o-arrow-path class="w-5 h-5 ml-2" />
                    بازنشانی فیلترها
                </button>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('filtersUpdated', (filters) => {
            // به‌روزرسانی URL با فیلترهای جدید
            const url = new URL(window.location.href);
            Object.entries(filters).forEach(([key, value]) => {
                if (value) {
                    if (Array.isArray(value) && value.length > 0) {
                        url.searchParams.set(key, JSON.stringify(value));
                    } else if (!Array.isArray(value)) {
                        url.searchParams.set(key, value);
                    }
                } else {
                    url.searchParams.delete(key);
                }
            });
            url.searchParams.delete('page'); // حذف صفحه‌بندی هنگام تغییر فیلترها

            window.history.pushState({}, '', url);
        });
    });
</script>
@endpush
