<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">بروزرسانی قیمت با درصد ثابت</h1>
            <p class="mt-1 text-sm text-gray-600">اعمال درصد یکسان روی قیمت‌های موجود با امکان بازبینی و اصلاح قبل از ثبت</p>
        </div>
        <a href="{{ route('supplier.fast-pricing.import') }}"
           class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <x-heroicon-o-arrow-right class="h-5 w-5" />
            بازگشت به بروزرسانی سریع
        </a>
    </div>

    @if($notice)
        <div role="status" aria-live="polite" class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-800">
            {{ $notice }}
        </div>
    @endif

    <div x-data="{ showGuide: false }" class="rounded-xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
        <button type="button" @click="showGuide = !showGuide"
                x-bind:aria-expanded="showGuide.toString()" aria-controls="percentage-update-guide"
                class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg text-right text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <span class="flex items-center gap-2 font-semibold">
                <x-heroicon-o-information-circle class="h-5 w-5" />
                راهنمای بروزرسانی درصدی
            </span>
            <x-heroicon-o-chevron-down class="h-5 w-5 transition motion-reduce:transition-none" x-bind:class="showGuide && 'rotate-180'" />
        </button>
        <div id="percentage-update-guide" x-cloak x-show="showGuide" x-collapse class="mt-3 space-y-3 text-sm leading-7 text-blue-900">
            <p><strong>هدف صفحه:</strong> همه قیمت‌های موجود کالاهای شما با یک درصد ثابت افزایش یا کاهش پیدا می‌کنند.</p>
            <ul class="mr-5 list-disc space-y-1">
                <li>برای افزایش، عدد مثبت مانند <span dir="ltr" class="font-mono">10</span> و برای کاهش، عدد منفی مانند <span dir="ltr" class="font-mono">-5</span> وارد کنید.</li>
                <li>قیمت جدید طبق تنظیمات صفحه «قواعد رند کردن قیمت» محاسبه می‌شود.</li>
                <li>در مرحله پیش‌نمایش می‌توانید هر قیمت و وضعیت فعال بودن هر کالا را جداگانه تغییر دهید.</li>
                <li>درصد صفر نیز قیمت‌ها را دوباره طبق قواعد رند کردن فعلی مرتب می‌کند.</li>
                <li>قیمت‌های صفر یا حذف‌شده وارد محاسبه نمی‌شوند و فعال/غیرفعال بودن خود قیمت‌ها تغییر نمی‌کند.</li>
            </ul>
            <div class="rounded-lg border border-blue-200 bg-white p-3 text-xs">
                <strong>مثال:</strong> قیمت ۱۰۰٬۰۰۰ تومان با درصد ۱۰، پیش از اعمال قانون رند شدن به ۱۱۰٬۰۰۰ تومان می‌رسد.
            </div>
            <div class="flex items-start gap-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-xs text-yellow-900">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" />
                <p>تا زمانی که دکمه «تأیید و اعمال نهایی» را نزنید، هیچ قیمتی در کالاهای شما تغییر نمی‌کند.</p>
            </div>
        </div>
    </div>

    @if(!$batchId)
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="percentage-heading">
            <div class="mb-5 flex items-start gap-3">
                <div class="rounded-lg bg-indigo-100 p-3 text-indigo-600">
                    <x-heroicon-o-calculator class="h-7 w-7" />
                </div>
                <div>
                    <h2 id="percentage-heading" class="font-bold text-gray-900">درصد تغییر را وارد کنید</h2>
                    <p class="mt-1 text-sm text-gray-500">محدوده مجاز از ۹۹٫۹۹- تا ۱۰۰۰ درصد است.</p>
                </div>
            </div>

            <form wire:submit="generatePreview" class="space-y-5">
                <div class="max-w-sm">
                    <label for="percentage" class="mb-2 block text-sm font-medium text-gray-700">درصد افزایش یا کاهش</label>
                    <div class="relative">
                        <input id="percentage" type="text" inputmode="decimal" dir="ltr"
                               wire:model="percentage" autocomplete="off" placeholder="مثال: 10 یا -5.5"
                               aria-describedby="percentage-help @error('percentage') percentage-error @enderror"
                               @error('percentage') aria-invalid="true" @enderror
                               class="block min-h-12 w-full rounded-lg border-gray-300 pl-12 text-left shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('percentage') border-red-400 @enderror" />
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-gray-500">٪</span>
                    </div>
                    <p id="percentage-help" class="mt-2 text-xs text-gray-500">اعداد فارسی یا لاتین و حداکثر دو رقم اعشار مجاز است.</p>
                    @error('percentage')
                        <p id="percentage-error" role="alert" class="mt-2 flex items-center gap-1 text-sm text-red-600">
                            <x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}
                        </p>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="generatePreview"
                        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                    <span wire:loading.remove wire:target="generatePreview" class="flex items-center gap-2">
                        <x-heroicon-o-eye class="h-5 w-5" />محاسبه و مشاهده پیش‌نمایش
                    </span>
                    <span wire:loading wire:target="generatePreview">در حال محاسبه قیمت‌ها...</span>
                </button>
            </form>
        </section>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">درصد انتخاب‌شده</p>
                <p dir="ltr" class="mt-1 text-right text-2xl font-bold {{ (float)$percentage < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $percentage }}%</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">تعداد کالاها</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($totalProducts) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">تعداد قیمت‌ها</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($totalPrices) }}</p>
            </div>
        </div>

        @if($priceErrors !== [])
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">حداقل یک قیمت ذخیره نشده است. خطای نمایش‌داده‌شده کنار فیلد را اصلاح کنید.</div>
        @endif
        @error('commit')
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>
        @enderror

        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-bold text-gray-900">پیش‌نمایش و ویرایش قیمت‌ها</h2>
                <p class="mt-1 text-xs text-gray-500">هر تغییر قیمت یا وضعیت، هنگام خروج از فیلد در پیش‌نویس ذخیره می‌شود.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="button" wire:click="cancelBatch" wire:loading.attr="disabled" wire:target="cancelBatch"
                        wire:confirm="پیش‌نویس لغو شود؟ هیچ قیمتی روی کالاها اعمال نشده است."
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <x-heroicon-o-trash class="h-5 w-5" />لغو پیش‌نویس
                </button>
                <button type="button" wire:click="commitChanges" wire:loading.attr="disabled"
                        @if($priceErrors !== []) disabled @endif
                        wire:confirm="آیا از اعمال نهایی همه قیمت‌ها و وضعیت کالاها اطمینان دارید؟"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60">
                    <span wire:loading.remove wire:target="commitChanges" class="flex items-center gap-2">
                        <x-heroicon-o-check-circle class="h-5 w-5" />تأیید و اعمال نهایی
                    </span>
                    <span wire:loading wire:target="commitChanges" role="status">در حال ثبت نهایی...</span>
                </button>
            </div>
        </div>

        <div class="space-y-4">
            @forelse($items as $item)
                <article wire:key="percentage-item-{{ $item->id }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <header class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="break-words font-bold text-gray-900">{{ $item->productSupplier?->product?->title ?? 'کالای نامشخص' }}</h3>
                            <p class="mt-1 break-all font-mono text-xs text-gray-500" dir="ltr">SKU: {{ $item->productSupplier?->sku ?? '—' }}</p>
                        </div>
                        <div class="flex items-center gap-2" role="group" aria-label="وضعیت فعال بودن کالا">
                            <span class="text-sm text-gray-600">وضعیت کالا:</span>
                            <button type="button"
                                    wire:click="updateProductStatus({{ $item->id }}, {{ $item->new_is_active ? 0 : 1 }})"
                                    wire:loading.attr="disabled"
                                    wire:target="updateProductStatus({{ $item->id }}, {{ $item->new_is_active ? 0 : 1 }})"
                                    aria-pressed="{{ $item->new_is_active ? 'true' : 'false' }}"
                                    aria-label="وضعیت کالای {{ $item->productSupplier?->product?->title ?? 'نامشخص' }}: {{ $item->new_is_active ? 'فعال؛ برای غیرفعال کردن فشار دهید' : 'غیرفعال؛ برای فعال کردن فشار دهید' }}"
                                    class="inline-flex min-h-11 min-w-24 items-center justify-center rounded-full px-4 py-2 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $item->new_is_active ? 'bg-green-100 text-green-800 focus:ring-green-500' : 'bg-red-100 text-red-700 focus:ring-red-500' }}">
                                {{ $item->new_is_active ? 'فعال' : 'غیرفعال' }}
                            </button>
                        </div>
                    </header>

                    <div class="divide-y divide-gray-100">
                        @foreach(($item->new_prices ?? []) as $key => $priceData)
                            @php($oldPrice = (int) ($item->old_prices[$key]['price'] ?? 0))
                            @php($newPrice = (int) ($priceData['price'] ?? 0))
                            @php($fieldErrorKey = $item->id . '-' . $key)
                            @php($fieldError = $priceErrors[$fieldErrorKey] ?? null)
                            <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-[minmax(180px,1fr)_minmax(140px,auto)_minmax(220px,auto)] md:items-center">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ $priceData['tier_name'] ?? 'تیراژ نامشخص' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $priceData['price_type_name'] ?? 'نوع قیمت نامشخص' }}
                                        @if(!empty($priceData['check_period_name']))
                                            ـ {{ $priceData['check_period_name'] }}
                                        @endif
                                        @if(empty($priceData['is_active']))
                                            <span class="mr-1 rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">قیمت غیرفعال</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm">
                                    <span class="text-gray-500">قیمت قبلی:</span>
                                    <strong class="mr-1 text-gray-800">{{ number_format($oldPrice) }}</strong>
                                    <span class="text-xs text-gray-500">تومان</span>
                                </div>
                                <div>
                                    <label for="price-{{ $item->id }}-{{ $loop->index }}" class="mb-1 block text-xs font-medium text-gray-600">
                                        قیمت جدید (تومان)
                                        <span class="sr-only">برای {{ $item->productSupplier?->product?->title ?? 'کالا' }}، {{ $priceData['tier_name'] ?? 'تیراژ نامشخص' }}، {{ $priceData['price_type_name'] ?? 'نوع قیمت نامشخص' }}</span>
                                    </label>
                                    <input id="price-{{ $item->id }}-{{ $loop->index }}" type="text" inputmode="numeric" dir="ltr"
                                           x-data="formattedPriceInput($wire, null)"
                                           x-on:input="input($event)"
                                           x-on:change="$wire.updatePrice({{ $item->id }}, '{{ $key }}', normalize($event.target.value))"
                                           value="{{ $newPrice }}" onwheel="this.blur()"
                                           wire:loading.attr="disabled"
                                           @if($fieldError) aria-invalid="true" aria-describedby="price-error-{{ $item->id }}-{{ $loop->index }}" @endif
                                           class="min-h-11 w-full rounded-lg text-left font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:w-56 {{ $fieldError ? 'border-red-400' : 'border-gray-300' }}" />
                                    @if($fieldError)
                                        <p id="price-error-{{ $item->id }}-{{ $loop->index }}" role="alert" class="mt-1 text-xs text-red-600">{{ $fieldError }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-gray-500">کالایی برای نمایش یافت نشد.</div>
            @endforelse
        </div>

        @if($items->hasPages())
            <div class="rounded-xl border border-gray-200 bg-white p-4">{{ $items->links() }}</div>
        @endif
    @endif
</div>
