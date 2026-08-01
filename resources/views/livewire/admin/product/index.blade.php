<div>
    {{-- هدر صفحه --}}
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">محصولات</h1>
            <p class="mt-1 text-sm text-gray-500">مدیریت تمام محصولات فروشگاه</p>
        </div>
        <a href="{{ route('admin.products.create') }}"
           class="mt-4 sm:mt-0 inline-flex items-center gap-x-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            محصول جدید
        </a>
    </div>

    {{-- پیام‌های فلش --}}
    @if (session('message'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="mb-6 rounded-lg bg-green-50 p-4 text-sm text-green-800 border border-green-200 flex items-center justify-between">
        <span>{{ session('message') }}</span>
        <button @click="show = false" class="text-green-600 hover:text-green-900">&times;</button>
    </div>
    @endif

    @if (session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-800 border border-red-200 flex items-center justify-between">
        <span>{{ session('error') }}</span>
        <button @click="show = false" class="text-red-600 hover:text-red-900">&times;</button>
    </div>
    @endif

    {{-- فیلترها --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- جستجو --}}
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">جستجو</label>
                <input wire:model.live.debounce.300ms="search" type="text" id="search"
                       placeholder="نام یا SKU..."
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>

            {{-- وضعیت --}}
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">وضعیت</label>
                <select wire:model.live="statusFilter" id="status"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">همه</option>
                    @foreach($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- دسته‌بندی --}}
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی</label>
                <select wire:model.live="categoryFilter" id="category"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">همه</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- تأمین‌کننده --}}
            <div>
                <label for="supplier" class="block text-sm font-medium text-gray-700 mb-1">تأمین‌کننده</label>
                <select wire:model.live="supplierFilter" id="supplier"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focusring-indigo-500 text-sm">
                    <option value="">همه</option>
                    @foreach($suppliers as $sup)
                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- دکمه ریست --}}
        @if($search || $statusFilter !== '' || $categoryFilter || $supplierFilter)
        <div class="mt-4">
            <button wire:click="resetFilters"
                    class="text-sm text-indigo-600 hover:text-indigo-800 underline">
                حذف تمام فیلترها
            </button>
        </div>
        @endif
    </div>

    {{-- جدول محصولات --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تصویر</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">نام محصول</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">دسته‌بندی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تأمین‌کننده</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">قیمت پایه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تاریخ ثبت</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">عملیات</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                @forelse($products as $product)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $product->id }}</td>

                    {{-- تصویر --}}
                    <td class="px-6 py-4">
                        <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100">
                            @if($product->image)
                            <img src="{{ asset('storage/' . $product->image) }}"
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover">
                            @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            @endif
                        </div>
                    </td>

                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $product->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $product->sku }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $product->category?->name ?? '—' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $product->supplier?->name ?? ($product->suppliers->first()?->name ?? '—') }}
                    </td>

                    {{-- وضعیت با قابلیت تغییر سریع --}}
                    <td class="px-6 py-4">
                        <select wire:change="changeStatus({{ $product->id }}, $event.target.value)"
                                class="text-xs rounded-full px-3 py-1 font-semibold border-0 cursor-pointer
                                               {{ $product->status->is(ProductStatus::ACTIVE) ? 'bg-green-100 text-green-800' : '' }}
                                               {{ $product->status->is(ProductStatus::DRAFT) ? 'bg-gray-100 text-gray-800' : '' }}
                                               {{ $product->status->is(ProductStatus::INACTIVE) ? 'bg-red-100 text-red-800' : '' }}
                                               {{ $product->status->is(ProductStatus::ARCHIVED) ? 'bg-yellow-100 text-yellow-800' : '' }}">
                            <option value="{{ $product->status->value }}" selected>
                                {{ $product->status->label() }}
                            </option>
                            @foreach($product->status->availableTransitions() as $transition)
                            <option value="{{ $transition->value }}">
                                {{ $transition->label() }}
                            </option>
                            @endforeach
                        </select>
                    </td>

                    <td class="px-6 py-4 text-sm text-gray-900">
                        {{ $product->basePrice ? number_format($product->basePrice->price) . ' تومان' : '—' }}
                    </td>

                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $product->created_at?->toJalali('Y/m/d') ?? '—' }}
                    </td>

                    {{-- عملیات --}}
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-x-2">
                            {{-- نمایش --}}
                            <a href="{{ route('admin.products.show', $product) }}"
                               class="text-gray-500 hover:text-indigo-600 transition p-1"
                               title="مشاهده">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>

                            {{-- ویرایش --}}
                            <a href="{{ route('admin.products.edit', $product) }}"
                               class="text-gray-500 hover:text-amber-600 transition p-1"
                               title="ویرایش">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>

                            {{-- حذف --}}
                            <button wire:click="delete({{ $product->id }})"
                                    wire:confirm="آیا از حذف این محصول اطمینان دارید؟"
                                    class="text-gray-500 hover:text-red-600 transition p-1"
                                    title="حذف">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-6 py-20 text-center text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <p class="text-lg font-medium">هیچ محصولی یافت نشد</p>
                        <p class="text-sm mt-1">برای شروع، یک محصول جدید ایجاد کنید.</p>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- صفحه‌بندی --}}
        @if($products->hasPages())
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $products->links() }}
        </div>
        @endif
    </div>
</div>
