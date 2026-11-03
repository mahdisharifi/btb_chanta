<div class="p-4 sm:p-6" wire:keydown.escape.window="cancelDelete">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
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

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
        <table class="min-w-[680px] w-full text-sm text-right text-gray-500 dark:text-gray-400">
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
                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                    @php
                        $hasPrice = $pricedOfferIds->has((int) $product->pivot->id)
                            || (float) $product->pivot->foreign_price_amount > 0
                            || (int) $product->pivot->cost_price > 0;
                    @endphp
                    {{ $hasPrice ? 'قیمت ثبت شده' : 'قیمت ثبت نشده' }}
                </td>
                <td class="px-6 py-4">
                    @if($product->pivot->status == 'approved')
                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">تایید شده</span>
                    @elseif($product->pivot->status == 'pending')
                    <span class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">در انتظار</span>
                    @else
                    <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">پیش‌نویس</span>
                    @endif
                    <div class="mt-2">
                        @if($product->pivot->is_active)
                        <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">فعال برای فروش</span>
                        @else
                        <span class="px-2 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded-full">بایگانی‌شده</span>
                        @endif
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('supplier.products.edit-price', $product->id) }}"
                            class="inline-flex min-h-11 items-center rounded-md px-3 text-blue-600 hover:bg-blue-50 hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:hover:bg-blue-900/20">
                            {{ $product->pivot->is_active ? 'ویرایش' : 'ویرایش و فعال‌سازی' }}
                        </a>
                        @if($product->pivot->is_active)
                        <button type="button" wire:click="archiveProduct({{ $product->id }})"
                            wire:loading.attr="disabled" wire:target="archiveProduct({{ $product->id }})"
                            class="inline-flex min-h-11 items-center rounded-md border border-amber-300 px-3 text-amber-800 hover:bg-amber-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 disabled:opacity-60 dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-950/30">
                            آرشیو
                        </button>
                        @endif
                        <button type="button" wire:click="requestDeleteProduct({{ $product->id }})"
                            class="inline-flex min-h-11 items-center rounded-md px-3 text-red-600 hover:bg-red-50 hover:text-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:hover:bg-red-950/30">
                            حذف
                        </button>
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

    @if ($pendingDeleteOfferId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
        role="presentation" wire:click.self="cancelDelete">
        <section role="dialog" aria-modal="true" aria-labelledby="delete-product-title"
            aria-describedby="delete-product-warning"
            class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl dark:bg-gray-800 sm:p-6"
            x-data
            x-init="$nextTick(() => $refs.cancelDelete.focus())"
            x-on:keydown.tab="
                if ($event.shiftKey && $event.target === $refs.cancelDelete) {
                    $event.preventDefault(); $refs.confirmDelete.focus();
                } else if (!$event.shiftKey && $event.target === $refs.confirmDelete) {
                    $event.preventDefault(); $refs.cancelDelete.focus();
                }
            ">
            <h3 id="delete-product-title" class="text-lg font-bold text-gray-900 dark:text-white">تأیید حذف کالا</h3>
            <p id="delete-product-warning" class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                {{ $pendingDeleteHasPrices
                    ? 'شما برای این کالا قیمت گذاشته‌اید. آیا از حذف این کالا اطمینان دارید؟'
                    : 'آیا از حذف کالا اطمینان دارید؟' }}
            </p>
            @if ($pendingDeleteProductTitle)
            <p class="mt-2 break-words text-sm font-semibold text-gray-900 dark:text-white">
                {{ $pendingDeleteProductTitle }}
            </p>
            @endif
            <p class="mt-3 text-xs leading-6 text-gray-500 dark:text-gray-400">
                این کار فقط عرضهٔ شما و قیمت‌های وابسته به آن را حذف می‌کند؛ خود محصول در کاتالوگ سایت باقی می‌ماند.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" x-ref="cancelDelete" wire:click="cancelDelete"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    انصراف
                </button>
                <button type="button" x-ref="confirmDelete" wire:click="confirmDeleteProduct"
                    wire:loading.attr="disabled" wire:target="confirmDeleteProduct"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:opacity-60">
                    <span wire:loading.remove wire:target="confirmDeleteProduct">تأیید حذف</span>
                    <span wire:loading wire:target="confirmDeleteProduct">در حال حذف…</span>
                </button>
            </div>
        </section>
    </div>
    @endif
</div>
