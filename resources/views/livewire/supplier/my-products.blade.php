<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">محصولات من</h2>
        <a href="{{ route('supplier.products.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            + افزودن / قیمت‌گذاری محصول جدید
        </a>
    </div>

    @if (session()->has('message'))
    <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
        {{ session('message') }}
    </div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="جستجوی محصول..."
               class="w-full md:w-1/3 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white">
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-right text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">محصول</th>
                <th scope="col" class="px-6 py-3">قیمت</th>
                <th scope="col" class="px-6 py-3">وضعیت تأیید</th>
                <th scope="col" class="px-6 py-3">عملیات</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center overflow-hidden">
                        <span class="text-xs text-gray-500">بدون عکس</span>
                    </div>
                    {{ $product->title_fa }}
                </td>
                <td class="px-6 py-4 text-gray-400">
                    <!-- در صورت پیاده‌سازی ریلیشن قیمت‌ها، متغیر زیر اصلاح شود -->
                    ثبت نشده
                </td>
                <td class="px-6 py-4">
                    @if($product->pivot->status == 'approved')
                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">تایید شده</span>
                    @elseif($product->pivot->status == 'pending')
                    <span class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">در انتظار</span>
                    @else
                    <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">پیش‌نویس</span>
                    @endif
                </td>
                <td class="px-6 py-4">
                    <div class="flex gap-3">
                        <a href="{{ route('supplier.products.edit-price', $product->id) }}" class="text-blue-600 hover:text-blue-900">ویرایش</a>
                        <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="آیا از حذف این محصول اطمینان دارید؟" class="text-red-600 hover:text-red-900">حذف</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                    شما هنوز هیچ محصولی را برای فروش ثبت نکرده‌اید.
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $products->links() }}
    </div>
</div>
