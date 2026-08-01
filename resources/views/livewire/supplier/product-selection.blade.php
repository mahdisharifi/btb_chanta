<div>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">انتخاب کالا برای فروش</h1>
            <p class="text-sm text-gray-500 mt-1">محصولات مورد نظر خود را از کاتالوگ انتخاب کرده و قیمت‌گذاری کنید.</p>
        </div>
    </div>

    <!-- Toolbar & Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-6">
        <div class="p-4 border-b border-gray-100 bg-gray-50/50">
            <!-- Search -->
            <div class="relative max-w-2xl">
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input wire:model.live.debounce.500ms="search" type="text" placeholder="جستجو با نام یا کد کالا (SKU)..." class="w-full pl-10 pr-12 py-2.5 border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm transition-shadow">
                @if($search)
                <button wire:click="$set('search', '')" class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400 hover:text-red-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
                @endif
            </div>
        </div>

        <div class="p-4 flex flex-col lg:flex-row gap-4 items-center justify-between">
            <!-- Filters -->
            <div class="w-full lg:w-auto grid grid-cols-1 sm:grid-cols-3 gap-3 flex-grow max-w-4xl">
                <select wire:model.live="categoryId" class="w-full bg-white border border-gray-200 text-gray-700 py-2 px-3 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm">
                    <option value="">همه دسته‌ها</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="pricingStatus" class="w-full bg-white border border-gray-200 text-gray-700 py-2 px-3 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm">
                    <option value="all">همه محصولات (کاتالوگ کامل)</option>
                    <option value="unpriced">فروش نرفته</option>
                    <option value="priced">در حال فروش من</option>
                </select>

                <select wire:model.live="sort" class="w-full bg-white border border-gray-200 text-gray-700 py-2 px-3 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm">
                    <option value="latest">جدیدترین‌ها</option>
                    <option value="title_asc">حروف الفبا</option>
                </select>
            </div>

            <!-- View Toggle & Export -->
            <div class="w-full lg:w-auto flex items-center justify-between gap-3 lg:border-r lg:border-gray-200 lg:pr-4 mt-2 lg:mt-0">
                <div class="flex items-center bg-gray-100 p-1 rounded-xl">
                    <button wire:click="$set('viewMode', 'grid')" class="p-1.5 rounded-lg transition-all {{ $viewMode === 'grid' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-900' }}" title="نمایش شبکه‌ای">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    </button>
                    <button wire:click="$set('viewMode', 'list')" class="p-1.5 rounded-lg transition-all {{ $viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-900' }}" title="نمایش لیستی">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>

                <button wire:click="exportCsv" class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 hover:text-indigo-600 transition-colors shadow-sm text-sm font-bold" title="خروجی اکسل">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span class="hidden sm:inline">اکسل</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div wire:loading.flex wire:target="search, categoryId, pricingStatus, sort, viewMode, gotoPage, nextPage, previousPage" class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-70 backdrop-blur-sm">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Products Container -->
    @if($viewMode === 'grid')
    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-5">
        @forelse($products as $product)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 group relative flex flex-col">
            @if(in_array($product->id, $pricedProductIds))
            <div class="absolute top-2 right-2 z-10 bg-green-500 text-white text-[11px] font-bold px-2 py-1 rounded-lg shadow flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                در فروش شما
            </div>
            @endif

            <!-- Product Image -->
            <div class="bg-gray-50 relative cursor-pointer h-56 overflow-hidden p-3" wire:click="openQuickView({{ $product->id }})">
                @php
                $mainImage = $product->galleries->where('is_main', true)->first() ?? $product->galleries->first();
                @endphp
                @if($mainImage)
                <img src="{{ asset('storage/' . $mainImage->file_path) }}"
                     alt="{{ $mainImage->alt_text ?? $product->title }}"
                     class="w-full h-full object-contain mix-blend-multiply group-hover:scale-105 transition-transform duration-300"
                     loading="lazy">
                @else
                <div class="w-full h-full flex items-center justify-center text-gray-300">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                @endif
            </div>

            <!-- Product Details -->
            <div class="p-4 border-t border-gray-100 flex-grow flex flex-col bg-white">
                <div class="text-[11px] text-gray-500 mb-2 flex justify-between items-center bg-gray-50 p-1.5 rounded-md">
                    <span class="truncate max-w-[50%] font-medium">{{ $product->category->name ?? 'بدون دسته' }}</span>
                    <span class="font-mono bg-gray-200/60 px-1.5 py-0.5 rounded text-gray-600">SKU: {{ $product->sku }}</span>
                </div>
                <h3 class="text-sm font-bold text-gray-800 mb-3 line-clamp-2 leading-relaxed flex-grow hover:text-indigo-600 transition-colors cursor-pointer" wire:click="openQuickView({{ $product->id }})">
                    {{ $product->title }}
                </h3>

                <div class="mt-auto pt-3 border-t border-gray-50">
                    @if(in_array($product->id, $pricedProductIds))
                    <a href="{{ route('supplier.products.edit-price', $product->id) }}" target="_blank" class="block w-full text-center py-2 px-3 border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-600 hover:text-white rounded-xl text-sm font-bold transition-all">
                        ویرایش موجودی و قیمت
                    </a>
                    @else
                    <a href="{{ route('supplier.products.edit-price', $product->id) }}" target="_blank" class="w-full py-2 px-3 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition-all flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        افزودن به لیست فروش
                    </a>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full py-16 bg-white rounded-2xl border border-gray-200 text-center shadow-sm">
            <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            <h3 class="text-lg font-bold text-gray-900 mb-1">محصولی یافت نشد</h3>
            <p class="text-sm text-gray-500">با تغییر فیلترها دوباره تلاش کنید.</p>
        </div>
        @endforelse
    </div>
    @else
    <!-- List View (Compact & No Horizontal Scroll) -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 w-full">
        <table class="w-full text-sm text-right">
            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500">
            <tr>
                <th scope="col" class="px-4 py-3 font-bold">محصول</th>
                <th scope="col" class="px-4 py-3 font-bold w-24">کد (SKU)</th>
                <th scope="col" class="px-4 py-3 font-bold hidden sm:table-cell w-32">دسته‌بندی</th>
                <th scope="col" class="px-4 py-3 font-bold w-24">وضعیت</th>
                <th scope="col" class="px-4 py-3 font-bold text-center w-20">عملیات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($products as $product)
            <tr class="hover:bg-gray-50/80 transition-colors group">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 h-12 w-12 bg-white border border-gray-100 rounded-lg p-1 overflow-hidden shadow-sm group-hover:border-indigo-200 transition-colors cursor-pointer" wire:click="openQuickView({{ $product->id }})">
                            @php
                            $mainImage = $product->galleries->where('is_main', true)->first() ?? $product->galleries->first();
                            @endphp
                            @if($mainImage)
                            <img class="h-full w-full object-contain" src="{{ asset('storage/' . $mainImage->file_path) }}" alt="{{ $mainImage->alt_text ?? $product->title }}" loading="lazy">
                            @else
                            <div class="h-full w-full bg-gray-50 flex items-center justify-center rounded-md">
                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            @endif
                        </div>
                        <div class="font-bold text-gray-800 cursor-pointer hover:text-indigo-600 transition-colors line-clamp-2" wire:click="openQuickView({{ $product->id }})" title="{{ $product->title }}">
                            {{ $product->title }}
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="font-mono text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded-md">{{ $product->sku }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600 font-medium hidden sm:table-cell">
                    <span class="line-clamp-1" title="{{ $product->category->name ?? '-' }}">{{ $product->category->name ?? '-' }}</span>
                </td>
                <td class="px-4 py-3">
                    @if(in_array($product->id, $pricedProductIds))
                    <span class="px-2 py-1 inline-flex text-[11px] font-bold rounded-md bg-green-100 text-green-700">در فروش</span>
                    @else
                    <span class="px-2 py-1 inline-flex text-[11px] font-bold rounded-md bg-gray-100 text-gray-600">فروش نرفته</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        @if(in_array($product->id, $pricedProductIds))
                        <a href="{{ route('supplier.products.edit-price', $product->id) }}" target="_blank" class="inline-flex items-center justify-center w-8 h-8 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors" title="ویرایش موجودی و قیمت">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </a>
                        @else
                        <a href="{{ route('supplier.products.edit-price', $product->id) }}" target="_blank" class="inline-flex items-center justify-center w-8 h-8 text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm" title="افزودن به لیست فروش">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        </a>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-12 text-center text-gray-500 text-sm">
                    محصولی با این مشخصات یافت نشد.
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endif

    <!-- Pagination -->
    <div class="mt-6">
        {{ $products->links() }}
    </div>

    <!-- Quick View Modal (unchanged) -->
    @if($showQuickViewModal && $viewingProduct)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900/60 transition-opacity backdrop-blur-sm" wire:click="$set('showQuickViewModal', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-3xl text-right overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle max-w-4xl w-full border border-gray-100">
                <div class="p-8">
                    <div class="flex justify-between items-start mb-8 pb-4 border-b border-gray-100">
                        <h3 class="text-2xl font-bold text-gray-900 leading-tight pr-4 border-r-4 border-indigo-600">{{ $viewingProduct->title }}</h3>
                        <button wire:click="$set('showQuickViewModal', false)" class="text-gray-400 hover:text-red-500 bg-gray-50 hover:bg-red-50 rounded-full p-2 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <!-- Image Gallery with Alpine.js -->
                        @php
                        $vMainImage = $viewingProduct->galleries->where('is_main', true)->first() ?? $viewingProduct->galleries->first();
                        $defaultImageSrc = $vMainImage ? asset('storage/' . $vMainImage->file_path) : '';
                        @endphp

                        <div class="space-y-4" x-data="{ currentImage: '{{ $defaultImageSrc }}' }">
                            <div class="bg-gray-50/80 border border-gray-100 rounded-2xl overflow-hidden flex items-center justify-center h-96 relative group p-6">
                                @if($defaultImageSrc)
                                <img :src="currentImage"
                                     alt="{{ $viewingProduct->title }}"
                                     class="max-h-full object-contain mix-blend-multiply group-hover:scale-105 transition-transform duration-500">
                                @else
                                <svg class="w-24 h-24 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                @endif
                            </div>

                            @if($viewingProduct->galleries->count() > 1)
                            <div class="grid grid-cols-5 gap-3">
                                @foreach($viewingProduct->galleries as $gallery)
                                @php $gallerySrc = asset('storage/' . $gallery->file_path); @endphp
                                <div @click="currentImage = '{{ $gallerySrc }}'"
                                     class="bg-white border rounded-xl overflow-hidden h-20 flex items-center justify-center cursor-pointer transition-all shadow-sm p-1.5"
                                     :class="currentImage === '{{ $gallerySrc }}' ? 'border-indigo-600 ring-2 ring-indigo-100 shadow-md scale-105' : 'border-gray-200 hover:border-indigo-400 hover:shadow'">
                                    <img src="{{ $gallerySrc }}" alt="{{ $gallery->alt_text ?? $viewingProduct->title }}" class="max-h-full object-contain mix-blend-multiply">
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        <!-- Details -->
                        <div class="flex flex-col h-full">
                            <div class="space-y-6 flex-grow">
                                <div class="bg-gray-50/50 rounded-2xl p-5 border border-gray-100 space-y-4">
                                    <div class="flex items-center justify-between pb-4 border-b border-gray-200/60">
                                        <span class="text-sm font-bold text-gray-500">کد محصول (SKU)</span>
                                        <span class="font-mono text-base font-bold text-gray-900 bg-white border border-gray-200 px-3 py-1 rounded-lg shadow-sm">{{ $viewingProduct->sku }}</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-4 border-b border-gray-200/60">
                                        <span class="text-sm font-bold text-gray-500">دسته‌بندی</span>
                                        <span class="text-sm font-bold text-gray-800 bg-indigo-50 text-indigo-700 px-3 py-1 rounded-lg">{{ $viewingProduct->category->name ?? 'بدون دسته' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-bold text-gray-500">وضعیت</span>
                                        @if(in_array($viewingProduct->id, $pricedProductIds))
                                        <span class="px-3 py-1.5 inline-flex text-xs leading-5 font-bold rounded-lg bg-green-100 text-green-800 border border-green-200 shadow-sm">در لیست فروش شما</span>
                                        @else
                                        <span class="px-3 py-1.5 inline-flex text-xs leading-5 font-bold rounded-lg bg-blue-100 text-blue-800 border border-blue-200 shadow-sm">آماده برای فروش</span>
                                        @endif
                                    </div>
                                </div>

                                @if($viewingProduct->short_description)
                                <div class="pt-2">
                                    <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        توضیح کوتاه:
                                    </h4>
                                    <p class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-5 rounded-2xl border border-gray-100">{{ $viewingProduct->short_description }}</p>
                                </div>
                                @endif
                            </div>

                            <div class="mt-8 flex gap-4">
                                @if(in_array($viewingProduct->id, $pricedProductIds))
                                <a href="{{ route('supplier.products.edit-price', $viewingProduct->id) }}" target="_blank" class="flex-1 py-3.5 px-4 bg-white border-2 border-indigo-600 text-indigo-700 rounded-xl flex items-center justify-center text-sm font-bold hover:bg-indigo-50 hover:shadow-md transition-all duration-300">
                                    ویرایش موجودی و قیمت من
                                </a>
                                @else
                                <a href="{{ route('supplier.products.edit-price', $viewingProduct->id) }}" target="_blank" class="flex-1 py-3.5 px-4 bg-indigo-600 text-white rounded-xl flex items-center justify-center text-sm font-bold hover:bg-indigo-700 transition-all duration-300 shadow-md hover:shadow-xl hover:-translate-y-0.5 gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    افزودن به لیست فروش من
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif


    <!-- کامپوننت Slide-over -->
    <div x-data="{ open: @entangle('showQuickEdit') }"
         x-show="open"
         style="display: none;"
         class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">

        <!-- پس‌زمینه تاریک (Backdrop) -->
        <div x-show="open"
             x-transition:enter="ease-in-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity backdrop-blur-sm"></div>

        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <!-- پنل کشویی (باز شدن از سمت راست برای زبان فارسی) -->
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                    <div x-show="open"
                         x-transition:enter="transform transition ease-in-out duration-300"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-300"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full"
                         class="pointer-events-auto w-screen max-w-3xl">

                        <div class="flex h-full flex-col divide-y divide-gray-200 bg-white shadow-xl">
                            <!-- هدر Slide-over -->
                            <div class="px-6 py-5 sm:px-8 bg-gray-50 flex items-center justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900" id="slide-over-title">ورود سریع اطلاعات (حالت اکسل)</h2>
                                    <p class="mt-1 text-sm text-gray-500">قیمت‌ها و موجودی کالاها را مستقیماً وارد کرده و ثبت کنید.</p>
                                </div>
                                <div class="mr-3 flex h-7 items-center">
                                    <button type="button" wire:click="$set('showQuickEdit', false)" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <span class="sr-only">بستن پنل</span>
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- نوار جستجو در Slide-over -->
                            <div class="p-4 border-b border-gray-100 bg-white">
                                <div class="relative">
                                    <input type="text" wire:model.live.debounce.300ms="quickSearch" placeholder="جستجوی نام کالا یا شناسه..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- محتوای اصلی (جدول شبیه اکسل) -->
                            <div class="relative flex-1 px-4 py-6 sm:px-6 overflow-y-auto bg-gray-50/50">
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-100">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">نام محصول</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-40">قیمت (تومان)</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider w-32">موجودی</th>
                                        </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                        <!-- فرض بر این است که متغیر $products مقادیر را پاس می‌دهد -->
                                        @foreach([1, 2, 3, 4, 5] as $item)
                                        <tr class="hover:bg-indigo-50/30 transition-colors">
                                            <td class="px-4 py-3 text-sm text-gray-900 font-medium">گوشی موبایل مدل تستی {{ $item }}</td>
                                            <td class="px-4 py-2">
                                                <input type="number" wire:model="quickPrices.{{ $item }}" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-left" dir="ltr" placeholder="0">
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" wire:model="quickStocks.{{ $item }}" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-center" dir="ltr" placeholder="0">
                                            </td>
                                        </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- فوتر و دکمه ذخیره -->
                            <div class="flex flex-shrink-0 justify-end px-4 py-4 sm:px-6 bg-white">
                                <button type="button" wire:click="$set('showQuickEdit', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 ml-3">
                                    انصراف
                                </button>
                                <button type="button" wire:click="saveQuickEdits" class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    ذخیره تغییرات
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
