<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تایید و اعمال تغییرات') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- آمار کلی -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">کل ردیف‌ها</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalRecords) }}</p>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <x-heroicon-o-document-text class="w-6 h-6 text-blue-600"/>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">آماده اعمال</p>
                        <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($validRecords) }}</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-lg">
                        <x-heroicon-o-check-circle class="w-6 h-6 text-green-600"/>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">دارای خطا</p>
                        <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($invalidRecords) }}</p>
                    </div>
                    <div class="p-3 bg-red-50 rounded-lg">
                        <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-600"/>
                    </div>
                </div>
            </div>

            <!-- جدول تغییرات -->
            <div class="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">
                <div
                    class="p-6 border-b border-gray-200 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">جزئیات تغییرات</h3>
                        <p class="text-sm text-gray-500 mt-1">ردیف‌های معتبر آماده ذخیره‌سازی هستند. در صورت خالی بودن
                            قیمت یا موجودی، مقادیر غیرفعال خواهند شد.</p>
                    </div>
                    <div class="flex gap-3">
                        <button wire:click="cancelBatch"
                                wire:confirm="آیا از لغو این عملیات اطمینان دارید؟ تمامی داده‌های این آپلود حذف خواهند شد."
                                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-medium text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                            <x-heroicon-o-x-mark class="w-5 h-5 ml-2 -mr-1"/>
                            لغو عملیات
                        </button>

                        <button wire:click="commitChanges"
                                wire:loading.attr="disabled"
                                @if($validRecords== 0) disabled @endif
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="commitChanges" class="flex items-center">
                                <x-heroicon-o-check class="w-5 h-5 ml-2 -mr-1"/>
                                تایید و اعمال نهایی
                            </span>
                            <span wire:loading wire:target="commitChanges" class="flex items-center">
                                <svg class="animate-spin -mr-1 ml-3 h-5 w-5 text-white"
                                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                در حال پردازش...
                            </span>
                        </button>
                    </div>
                </div>

                @if($errors->has('commit'))
                <div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 mx-6 mt-4 rounded-r">
                    <p class="font-medium">خطا!</p>
                    <p class="text-sm mt-1">{{ $errors->first('commit') }}</p>
                </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-gray-50 text-gray-600 font-medium border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">ردیف Excel</th>
                            <th class="px-6 py-4">محصول (SKU)</th>
                            <th class="px-6 py-4">تغییرات قیمت</th>
                            <th class="px-6 py-4 text-center">موجودی</th>
                            <th class="px-6 py-4 text-center">وضعیت</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                        @forelse($items as $item)
                        <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 text-gray-500 font-mono">{{ $item->row_number }}</td>

                            <td class="px-6 py-4 align-top">
                                @if($item->productSupplier)
                                <div class="font-medium text-gray-900">{{ $item->productSupplier->product->title ??
                                    'کالای نامشخص' }}
                                </div>
                                <div
                                    class="text-gray-500 text-xs mt-1 font-mono bg-gray-100 inline-block px-2 py-0.5 rounded">
                                    {{ $item->productSupplier->sku }}
                                </div>
                                @else
                                <span class="text-red-500 font-medium">کالا یافت نشد</span>
                                @endif
                            </td>

                            <!-- تغییرات قیمت -->
                            <td class="px-6 py-4 align-top">
                                @if($item->new_prices && !empty($item->new_prices))
                                <div class="space-y-2">
                                    @foreach($item->new_prices as $type => $priceData)
                                    @php
                                    $newPrice = is_array($priceData) ? ($priceData['price'] ?? null) : $priceData;
                                    $oldPrice = is_array($item->old_prices) ? ($item->old_prices[$type]['price'] ??
                                    $item->old_prices[$type] ?? 0) : 0;
                                    $isDeactivated = ($newPrice === null || $newPrice === '' || (is_numeric($newPrice)
                                    && $newPrice <= 0));
                                    @endphp

                                    <div class="flex items-center justify-between bg-gray-50/80 p-2 rounded-lg text-xs">
                                        <span
                                            class="text-gray-500 font-medium">قیمت {{ str_replace('_', ' ', $type) }}:</span>
                                        <div class="flex items-center gap-2 rtl:space-x-reverse" dir="ltr">
                                            @if($oldPrice && $oldPrice != $newPrice)
                                            <span class="text-gray-400 line-through decoration-red-400/50">{{ number_format($oldPrice) }}</span>
                                            <x-heroicon-o-arrow-right class="w-3 h-3 text-gray-300"/>
                                            @endif

                                            @if($isDeactivated)
                                            <span
                                                class="font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded border border-red-100">غیرفعال شد</span>
                                            @else
                                            <span
                                                class="font-bold {{ $newPrice > $oldPrice ? 'text-blue-600' : 'text-green-600' }}">
                                                        {{ number_format($newPrice) }}
                                                    </span>
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @else
                                <span class="text-gray-400 text-xs flex items-center gap-1">
                                        <x-heroicon-o-minus class="w-3 h-3"/>
                                        بدون تغییر
                                    </span>
                                @endif
                            </td>

                            <!-- موجودی -->
                            <td class="px-6 py-4 align-top text-center">
                                @php
                                $stock = $item->new_stock;
                                $isStockZero = ($stock === null || $stock === '' || (is_numeric($stock) && (int)$stock
                                <= 0));
                                @endphp

                                @if($isStockZero)
                                <div class="flex flex-col items-center gap-1">
                                    <span class="font-bold text-red-600 text-sm">۰</span>
                                    @else
                                    <span class="font-medium text-gray-900">{{ number_format($stock)}}</span>
                                    <br>
                                    @endif
                                    @if($item->new_is_active)
                                    <span class="text-[10px] bg-green-100 text-black-700 px-1.5 py-0.5 rounded whitespace-nowrap">کالا فعال می‌شود</span>
                                    @else
                                    <span class="text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded whitespace-nowrap">کالا غیرفعال می‌شود</span>
                                    @endif
                                </div>
                            </td>

                            <!-- وضعیت پردازش -->
                            <td class="px-6 py-4 align-top text-center">
                                @if($item->status === 'valid')
                                <span
                                    class="inline-flex items-center gap-1 bg-green-50 text-green-700 px-2.5 py-1 rounded-full text-xs font-medium border border-green-200">
                                            <x-heroicon-o-check-circle class="w-4 h-4"/>
                                            معتبر
                                        </span>
                                @else
                                <div class="flex flex-col items-center gap-1">
                                            <span
                                                class="inline-flex items-center gap-1 bg-red-50 text-red-700 px-2.5 py-1 rounded-full text-xs font-medium border border-red-200">
                                                <x-heroicon-o-x-circle class="w-4 h-4"/>
                                                خطا
                                            </span>
                                    @if($item->errors)
                                    <div class="text-[10px] text-red-500 max-w-[150px] text-center leading-tight">
                                        @foreach(json_decode($item->errors, true) ?? [] as $error)
                                        {{ is_array($error) ? implode(', ', $error) : $error }}<br>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-gray-300 mb-3"/>
                                هیچ داده‌ای برای نمایش یافت نشد.
                            </td>
                        </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($items->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    {{ $items->links() }}
                </div>
                @endif
            </div>

        </div>
    </div>
</div>
