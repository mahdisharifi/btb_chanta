<div class="space-y-6" dir="rtl">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">قیمت‌گذاری شعبه: {{ $product->title }}</h1>
        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
            قیمت پایه از شعبهٔ اصلی می‌آید. برای این شعبه فقط درصد اختلاف را تعیین کنید؛ مبلغ هر نوع قیمت و تیراژ به‌طور خودکار محاسبه می‌شود.
        </p>
    </div>

    <form wire:submit="save" class="space-y-5">
        <section class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900 dark:bg-indigo-950/30">
            <div class="grid gap-5 md:grid-cols-2">
                <div class="rounded-lg bg-white p-4 dark:bg-gray-800">
                    <p class="text-sm text-gray-600 dark:text-gray-300">کمترین قیمت معتبر شعبهٔ اصلی</p>
                    @if($branchReferencePrice !== null)
                        <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ number_format($branchReferencePrice) }} <span class="text-sm font-medium">تومان</span></p>
                    @else
                        <p class="mt-2 text-sm font-semibold text-amber-700 dark:text-amber-300">هنوز قیمت معتبری در شعبهٔ اصلی ثبت نشده است.</p>
                    @endif
                </div>

                <div class="rounded-lg bg-white p-4 dark:bg-gray-800">
                    <label for="branch-price-percentage" class="block text-sm font-semibold text-gray-800 dark:text-gray-100">درصد اختلاف این شعبه</label>
                    <div class="mt-2 flex items-center gap-2">
                        <input id="branch-price-percentage" type="number" step="0.000000000001" min="-99.999999" max="999.999999" dir="ltr"
                            wire:model.live="branchPricePercentage" required
                            class="min-h-12 w-full rounded-lg border-gray-300 text-left text-lg font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white sm:max-w-xs">
                        <span class="text-lg font-semibold text-gray-700 dark:text-gray-200">٪</span>
                    </div>
                    @error('branchPricePercentage') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                    <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">مثبت، قیمت را بالاتر می‌برد؛ منفی، پایین‌تر می‌آورد. نمونه: ۱۰+ یعنی ده درصد بالاتر.</p>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-indigo-200 bg-white px-4 py-3 dark:border-indigo-800 dark:bg-gray-800" aria-live="polite">
                <p class="text-sm text-gray-600 dark:text-gray-300">پیش‌نمایش کمترین قیمت این شعبه</p>
                @if($this->branchCalculatedPrice !== null)
                    <p class="mt-1 text-xl font-bold text-indigo-800 dark:text-indigo-200">{{ number_format($this->branchCalculatedPrice) }} تومان</p>
                @else
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">پس از ثبت قیمت در شعبهٔ اصلی، مبلغ این شعبه به‌صورت خودکار محاسبه می‌شود.</p>
                @endif
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">گارانتی این شعبه</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">تنظیمات گارانتی این نسخه مستقل از شعبهٔ اصلی ذخیره می‌شود.</p>
            </div>

            <label class="flex min-h-11 items-center gap-3 text-sm font-medium text-gray-800 dark:text-gray-100">
                <input type="checkbox" wire:model="hasWarranty" class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                این محصول در این شعبه گارانتی دارد
            </label>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="branch-warranty-provider" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">ارائه‌دهنده</label>
                    <select id="branch-warranty-provider" wire:model="warrantyProvider" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        <option value="none">بدون گارانتی</option>
                        <option value="manufacturer">تولیدکننده</option>
                        <option value="seller">فروشنده</option>
                    </select>
                </div>
                <div>
                    <label for="branch-warranty-coverage" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">پوشش</label>
                    <select id="branch-warranty-coverage" wire:model="warrantyCoverageType" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        <option value="full">کامل</option>
                        <option value="limited">محدود</option>
                        <option value="parts_only">قطعات</option>
                        <option value="service_only">خدمات</option>
                    </select>
                </div>
                <div>
                    <label for="branch-warranty-type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">نوع گارانتی</label>
                    <select id="branch-warranty-type" wire:model="warrantyTypeCoverage" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        <option value="test">تست</option>
                        <option value="limited">مدت‌دار</option>
                        <option value="lifetime">مادام‌العمر</option>
                    </select>
                </div>
                <div>
                    <label for="branch-warranty-days" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">مدت (روز)</label>
                    <input id="branch-warranty-days" type="number" min="0" max="36500" wire:model="warrantyDurationDays" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label for="branch-warranty-cost-type" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">روش هزینهٔ گارانتی</label>
                    <select id="branch-warranty-cost-type" wire:model="warrantyType" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                        <option value="none">بدون هزینه</option>
                        <option value="percentage">درصدی</option>
                        <option value="fixed">ثابت</option>
                        <option value="custom">سفارشی</option>
                    </select>
                </div>
                <div>
                    <label for="branch-warranty-value" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">مقدار هزینه</label>
                    <input id="branch-warranty-value" type="number" min="0" step="0.01" wire:model="warrantyValue" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                </div>
            </div>
        </section>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="min-h-12 rounded-lg bg-indigo-600 px-6 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">ذخیرهٔ درصد و گارانتی</button>
            <a href="{{ route('supplier.products.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-lg border border-gray-300 px-6 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">بازگشت به محصولات</a>
        </div>
    </form>
</div>
