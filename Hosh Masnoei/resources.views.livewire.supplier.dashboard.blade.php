<div>
    <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-6 mt-6">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <x-heroicon-o-document-duplicate class="w-6 h-6 text-indigo-500" />
                    امکانات جانبی: خروجی کاتالوگ PDF
                </h3>
                <p class="text-sm text-gray-500 mt-1">با فعالسازی این امکان می‌توانید محصولات خود را به صورت کاتالوگ حرفه‌ای خروجی بگیرید.</p>
            </div>

            @if(auth()->user()->hasFeature('catalog-pdf'))
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                <x-heroicon-m-check-circle class="w-4 h-4 ml-1" />
                فعال
            </span>
            @else
            <!-- شناسه 1 جهت تست (آیدی ویژگی مربوطه در دیتابیس) -->
            <a href="{{ auth()->user()->isSupplier() ? route('supplier.features.buy', 1) : route('retailer.features.buy', 1) }}" class="inline-flex items-center px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-lg font-bold text-sm text-indigo-700 hover:bg-indigo-100 transition-colors">
                خرید و فعالسازی
                <x-heroicon-m-chevron-left class="w-4 h-4 mr-1" />
            </a>
            @endif
        </div>
    </div>

<x-slot name="header">
        داشبورد آمار و اطلاعات
    </x-slot>

    <!-- نمایش وضعیت حساب تامین‌کننده -->
    @if(!$supplier->is_active)
    <div class="mb-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg">
        <strong>توجه:</strong> حساب کاربری شما در انتظار تایید مدیریت است. تا زمان تایید، محصولات شما در سایت نمایش داده نمی‌شوند.
    </div>
    @endif

    <!-- کارت‌های آمار (Stats Grid) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- فرض بر این است که کامپوننت x-admin.stat-card در سیستم شما وجود دارد -->
        <x-admin.stat-card title="کل محصولات شما" :value="$stats['total_products'] ?? 0" icon="cube" />
        <x-admin.stat-card title="محصولات فعال" :value="$stats['active_products'] ?? 0" icon="check-circle" text-color="text-green-600" />
        <x-admin.stat-card title="در انتظار بررسی" :value="$stats['pending_review_products'] ?? 0" icon="clock" text-color="text-yellow-600" />
        <x-admin.stat-card title="کل بازدید محصولات" :value="$stats['total_views'] ?? 0" icon="eye" />
    </div>

    <!-- جدول آخرین محصولات -->
    <x-admin.card title="جدیدترین محصولات ثبت شده شما">
        <x-admin.recent-table>
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">تصویر و عنوان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">دسته‌بندی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">عملیات</th>
                </tr>
            </x-slot>

            <x-slot name="body">
                @forelse($recentProducts as $product)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            @if($product->galleries->first())
                            <img class="h-10 w-10 rounded-md object-cover ml-3" src="{{ Storage::url($product->galleries->first()->image_path) }}" alt="{{ $product->title }}">
                            @else
                            <div class="h-10 w-10 rounded-md bg-gray-200 dark:bg-gray-600 flex items-center justify-center ml-3">
                                <x-icon name="heroicon-o-photo" class="w-5 h-5 text-gray-400" />
                            </div>
                            @endif
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $product->title }}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                        {{ $product->category->title ?? 'بدون دسته' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <x-admin.status-badge :status="$product->status->value ?? $product->status" />
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="{{ route('supplier.products.edit', $product->id) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">ویرایش</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                        شما هنوز هیچ محصولی ثبت نکرده‌اید.
                    </td>
                </tr>
                @endforelse
            </x-slot>
        </x-admin.recent-table>
    </x-admin.card>
</div>
