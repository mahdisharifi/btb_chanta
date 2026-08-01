<div class="space-y-6">
    <!-- هدر صفحه -->
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
            جزئیات محصول: {{ $product->title }}
        </h2>
        <div class="flex space-x-3 space-x-reverse">
            <a href="{{ route('admin.products.edit', $product->id) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                ویرایش محصول
            </a>
            <a href="{{ route('admin.products.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                بازگشت
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- اطلاعات پایه -->
        <div class="md:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold border-b pb-2 mb-4 text-gray-700 dark:text-gray-300">اطلاعات کلی</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <span class="block text-sm font-medium text-gray-500">کد محصول (SKU)</span>
                    <span class="block mt-1 text-gray-900 dark:text-gray-100">{{ $product->sku ?? 'ندارد' }}</span>
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-500">دسته‌بندی</span>
                    <span class="block mt-1 text-gray-900 dark:text-gray-100">{{ $product->category->name ?? 'بدون دسته‌بندی' }}</span>
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-500">وضعیت</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1 {{ $product->status->value === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $product->status->label() ?? $product->status }}
                    </span>
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-500">تاریخ ثبت</span>
                    <span class="block mt-1 text-gray-900 dark:text-gray-100" dir="ltr">
                        {{ jdate($product->created_at)->format('%Y/%m/%d H:i') }}
                    </span>
                </div>
            </div>

            <div class="mt-6">
                <span class="block text-sm font-medium text-gray-500">توضیحات کوتاه</span>
                <p class="mt-1 text-gray-900 dark:text-gray-100 text-justify leading-relaxed">
                    {{ $product->short_description ?? 'توضیحات کوتاهی ثبت نشده است.' }}
                </p>
            </div>

            <div class="mt-6">
                <span class="block text-sm font-medium text-gray-500">توضیحات کامل</span>
                <div class="mt-1 prose dark:prose-invert max-w-none text-gray-900 dark:text-gray-100">
                    {!! $product->description ?? 'توضیحاتی ثبت نشده است.' !!}
                </div>
            </div>
        </div>

        <!-- تصویر و گالری -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold border-b pb-2 mb-4 text-gray-700 dark:text-gray-300">تصویر اصلی</h3>
                @if($product->image)
                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->title }}" class="w-full rounded-lg object-cover">
                @else
                <div class="w-full h-48 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center text-gray-400">
                    بدون تصویر
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ویژگی‌ها (Attributes) -->
    @if($product->attributeValues && $product->attributeValues->count() > 0)
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mt-6">
        <h3 class="text-lg font-semibold border-b pb-2 mb-4 text-gray-700 dark:text-gray-300">ویژگی‌های محصول</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            @foreach($product->attributeValues as $attributeValue)
            <div class="border border-gray-200 dark:border-gray-700 rounded p-3">
                <span class="block text-xs text-gray-500 mb-1">{{ $attributeValue->attribute->name }}</span>
                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ $attributeValue->value }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
