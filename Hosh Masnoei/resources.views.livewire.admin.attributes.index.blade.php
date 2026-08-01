<div class="space-y-6">
    {{-- Header Section --}}
    <section class="overflow-hidden rounded-2xl border border-gold-200 bg-gradient-to-br from-white via-gold-50 to-white shadow-lg">
        <div class="flex flex-col gap-5 px-5 py-5 sm:px-6 sm:py-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                {{-- Badge --}}
                <div class="inline-flex items-center gap-2 rounded-full border border-gold-300 bg-gold-100 px-4 py-2 text-xs font-semibold text-gold-700 shadow-sm">
                    <x-heroicon-o-adjustments-horizontal class="h-4 w-4 text-gold-500" />
                    <span>مدیریت ویژگی‌های محصول</span>
                </div>

                {{-- Title --}}
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-gold-900 sm:text-4xl">
                    ایندکس ویژگی‌ها
                </h1>

                {{-- Description --}}
                <p class="mt-3 max-w-3xl text-base leading-7 text-zinc-600">
                    تعریف، جستجو، فیلتر و مدیریت ویژگی‌های فنی محصولات در این بخش انجام می‌شود. این صفحه با دقت بر اساس هویت بصری پروژه، با تمرکز بر ظاهر تجاری، مدرن و کاربرپسند طراحی شده است.
                </p>
            </div>

            {{-- Action Button --}}
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row lg:shrink-0">
                <a
                    href="{{ route('admin.attributes.create') }}"
                    class="inline-flex h-14 items-center justify-center gap-3 rounded-xl bg-gradient-to-br from-gold-400 to-gold-500 px-8 text-lg font-bold text-white shadow-xl transition duration-300 ease-in-out hover:from-gold-500 hover:to-gold-600 focus:outline-none focus:ring-2 focus:ring-gold-500 focus:ring-offset-2 focus:ring-offset-gold-50"
                >
                    <x-heroicon-o-plus class="h-6 w-6" />
                    <span>افزودن ویژگی جدید</span>
                </a>
            </div>
        </div>
    </section>

    {{-- Search and Filter Section --}}
    <section class="rounded-3xl border border-zinc-200 bg-gradient-to-br from-white via-zinc-50 to-white p-6 shadow-inner backdrop-blur-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            {{-- Filter Title --}}
            <div class="flex items-center gap-3 text-lg font-bold text-gold-900">
                <x-heroicon-o-funnel class="h-6 w-6 text-gold-500" />
                <span>جستجو و فیلتر پیشرفته</span>
            </div>

            {{-- Livewire Loading Indicator --}}
            <div
                wire:loading
                wire:target="search, typeFilter, sortBy, gotoPage, nextPage, previousPage"
                class="inline-flex items-center gap-2 rounded-full border border-gold-300 bg-gold-100 px-4 py-2 text-xs font-medium text-gold-700 shadow-sm animate-pulse"
            >
                <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                <span>در حال پردازش نتایج...</span>
            </div>
        </div>

        {{-- Filter Controls --}}
        <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-12">
            {{-- Search Input --}}
            <div class="lg:col-span-8">
                <label for="attribute-search" class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    جستجو بر اساس نام ویژگی
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4">
                        <x-heroicon-o-magnifying-glass class="h-5 w-5 text-zinc-400" />
                    </div>
                    <input
                        id="attribute-search"
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="مثلاً: رنگ، سایز، جنس..."
                        class="block h-14 w-full rounded-xl border-2 border-gold-300 bg-gold-50/70 px-5 py-3 pr-12 text-base font-medium text-gold-900 placeholder:text-gold-500 focus:border-gold-400 focus:ring-gold-400 focus:shadow-md"
                    >
                </div>
            </div>

            {{-- Type Filter --}}
            <div class="lg:col-span-4">
                <label for="attribute-type-filter" class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    نوع ویژگی
                </label>
                <select
                    id="attribute-type-filter"
                    wire:model.live="typeFilter"
                    class="block h-14 w-full rounded-xl border-2 border-gold-300 bg-gold-50/70 px-5 py-3 text-base font-medium text-gold-900 focus:border-gold-400 focus:ring-gold-400 focus:shadow-md"
                >
                    <option value="">همه انواع</option>
                    @foreach(\App\Enums\AttributeType::cases() as $type)
                    <option value="{{ $type->value }}" class="text-gold-900">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    {{-- Data Table Section --}}
    <section class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-lg">
        {{-- Table Header --}}
        <div class="flex flex-col gap-3 border-b border-zinc-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3 text-lg font-bold text-gold-900">
                <x-heroicon-o-rectangle-stack class="h-6 w-6 text-gold-500" />
                <span>فهرست ویژگی‌های ثبت‌شده</span>
            </div>
            {{-- Hidden Loading Indicator for Table --}}
            <div
                wire:loading
                wire:target="search, typeFilter, sortBy, gotoPage, nextPage, previousPage"
                class="inline-flex items-center gap-2 rounded-full border border-gold-300 bg-gold-100 px-4 py-2 text-xs font-medium text-gold-700 shadow-sm animate-pulse"
            >
                <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                <span>در حال بارگذاری داده‌ها...</span>
            </div>
        </div>

        {{-- Table Container (with loading overlay effect) --}}
        <div
            wire:loading.class="pointer-events-none opacity-60"
            wire:target="search, typeFilter, sortBy, gotoPage, nextPage, previousPage"
            class="transition-opacity duration-300"
        >
            {{-- Desktop Table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-right">
                    <thead class="bg-gradient-to-br from-gold-50 via-white to-gold-50">
                    <tr class="border-b border-gold-200">
                        {{-- Table Headers --}}
                        @foreach(['id' => 'شناسه', 'name' => 'نام ویژگی', 'type' => 'نوع ویژگی', 'products_count' => 'محصولات مرتبط'] as $key => $label)
                        <th class="px-6 py-5 text-left">
                            <button
                                type="button"
                                wire:click="sortBy('{{ $key }}')"
                                class="inline-flex items-center gap-2 text-xs font-bold text-zinc-600 transition duration-200 ease-in-out hover:text-gold-800"
                            >
                                <span class="text-sm">{{ $label }}</span>
                                @if($sortField === $key)
                                <span class="text-sm font-bold {{ $sortAsc ? 'text-green-500' : 'text-red-500' }}">{{ $sortAsc ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        @endforeach
                        {{-- Actions Header --}}
                        <th class="px-6 py-5 text-left">
                            <span class="text-sm font-bold text-zinc-600">عملیات</span>
                        </th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 bg-white">
                    @forelse($attributes as $attribute)
                    <tr wire:key="desktop-row-{{ $attribute->id }}" class="group bg-white transition duration-150 ease-in-out hover:bg-gold-50/40">
                        {{-- ID --}}
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-zinc-500">
                            #{{ $attribute->id }}
                        </td>

                        {{-- Name --}}
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-1">
                                <span class="text-base font-bold text-gold-900">{{ $attribute->name }}</span>
                                <span class="text-xs text-zinc-500">ویژگی فنی محصول</span>
                            </div>
                        </td>

                        {{-- Type --}}
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-700 shadow-sm">
                                    {{ $attribute->type->label() }}
                                </span>
                        </td>

                        {{-- Products Count --}}
                        <td class="whitespace-nowrap px-6 py-4 text-center text-sm">
                            @if($attribute->products_count > 0)
                            @php
                            $params = [];
                            foreach ($attribute->values as $val) {
                            $params['attributes'][$attribute->id][] = $val->id;
                            }
                            @endphp
                            <a href="{{ route('products.index') }}?{{ http_build_query($params) }}"
                               class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800 hover:bg-amber-200 transition">
                                {{ $attribute->products_count }} محصول
                            </a>
                            @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-400">
        بدون محصول
    </span>
                            @endif

                        </td>

                        {{-- Actions --}}
                        <td class="px-6 py-4 text-left">
                            <div class="inline-flex items-center gap-3">
                                {{-- Edit Button --}}
                                <a
                                    href="{{ route('admin.attributes.edit', $attribute->id) }}"
                                    class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 text-xs font-bold text-zinc-700 transition duration-200 ease-in-out hover:border-gold-400 hover:bg-gold-50 focus:outline-none focus:ring-2 focus:ring-gold-400 focus:ring-offset-2"
                                >
                                    <x-heroicon-o-pencil-square class="h-4 w-4 text-gold-600" />
                                    <span>ویرایش</span>
                                </a>

                                {{-- Delete Button (Conditional) --}}
                                @if($attribute->products_count > 0)
                                <button
                                    disabled
                                    class="inline-flex h-11 cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 text-xs font-bold text-zinc-400"
                                >
                                    <x-heroicon-o-lock-closed class="h-4 w-4 text-zinc-400" />
                                    <span>غیر قابل حذف</span>
                                </button>
                                @else
                                <button
                                    type="button"
                                    wire:click="deleteAttribute({{ $attribute->id }})"
                                    wire:confirm="آیا از حذف این ویژگی مطمئن هستید؟ این عمل غیر قابل بازگشت است."
                                    wire:loading.attr="disabled"
                                    wire:target="deleteAttribute({{ $attribute->id }})"
                                    class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-red-300 bg-red-50 px-4 text-xs font-bold text-red-600 transition duration-200 ease-in-out hover:border-red-400 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <x-heroicon-o-trash
                                        wire:loading.remove
                                        wire:target="deleteAttribute({{ $attribute->id }})"
                                        class="h-4 w-4 text-red-600"
                                    />
                                    <x-heroicon-o-arrow-path
                                        wire:loading
                                        wire:target="deleteAttribute({{ $attribute->id }})"
                                        class="h-4 w-4 animate-spin text-red-600"
                                    />
                                    <span wire:loading.remove wire:target="deleteAttribute({{ $attribute->id }})">حذف</span>
                                    <span wire:loading wire:target="deleteAttribute({{ $attribute->id }})">در حال حذف...</span>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    {{-- Empty State --}}
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="mx-auto flex max-w-sm flex-col items-center">
                                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gold-100 text-gold-500">
                                    <x-heroicon-o-inbox class="h-8 w-8" />
                                </div>
                                <h3 class="mt-5 text-xl font-bold text-gold-900">هیچ ویژگی‌ای یافت نشد</h3>
                                <p class="mt-2 text-base leading-6 text-zinc-500">
                                    لطفاً فیلترهای جستجو را تغییر دهید یا یک ویژگی جدید اضافه کنید.
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="divide-y divide-zinc-100 md:hidden">
                @forelse($attributes as $attribute)
                <article wire:key="mobile-card-{{ $attribute->id }}" class="space-y-4 px-5 py-5">
                    <div class="flex items-start justify-between gap-4">
                        {{-- Title and ID --}}
                        <div class="min-w-0">
                            <div class="text-xs font-mono text-zinc-400">
                                #{{ $attribute->id }}
                            </div>
                            <h3 class="mt-1 text-lg font-bold text-gold-900">
                                {{ $attribute->name }}
                            </h3>
                        </div>
                        {{-- Type Badge --}}
                        <span class="shrink-0 rounded-full border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-700 shadow-sm">
                            {{ $attribute->type->label() }}
                        </span>
                    </div>

                    {{-- Connection Status Card --}}
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 shadow-sm">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-zinc-500">وضعیت اتصال</span>
                            @if($attribute->products_count > 0)
                            <span class="inline-flex items-center rounded-full border border-gold-200 bg-gold-50 px-3 py-1 text-xs font-semibold text-gold-700 shadow-sm">
                                {{ $attribute->products_count }} محصول
                            </span>
                            @else
                            <span class="inline-flex items-center rounded-full border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-500 shadow-sm">
                                بدون اتصال
                            </span>
                            @endif
                        </div>
                    </div>

                    {{-- Action Buttons Grid --}}
                    <div class="grid grid-cols-2 gap-3">
                        {{-- Edit Button --}}
                        <a
                            href="{{ route('admin.attributes.edit', $attribute->id) }}"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-zinc-300 bg-white text-sm font-bold text-zinc-700 transition duration-200 ease-in-out hover:border-gold-400 hover:bg-gold-50 active:bg-zinc-100"
                        >
                            <x-heroicon-o-pencil-square class="h-5 w-5 text-gold-600" />
                            <span>ویرایش</span>
                        </a>

                        {{-- Delete Button (Conditional) --}}
                        @if($attribute->products_count > 0)
                        <button
                            disabled
                            class="inline-flex h-12 cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 text-sm font-bold text-zinc-400"
                        >
                            <x-heroicon-o-lock-closed class="h-5 w-5 text-zinc-400" />
                            <span>غیر قابل حذف</span>
                        </button>
                        @else
                        <button
                            type="button"
                            wire:click="deleteAttribute({{ $attribute->id }})"
                            wire:confirm="آیا از حذف این ویژگی مطمئن هستید؟ این عمل غیر قابل بازگشت است."
                            wire:loading.attr="disabled"
                            wire:target="deleteAttribute({{ $attribute->id }})"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-red-300 bg-red-50 text-sm font-bold text-red-600 transition duration-200 ease-in-out hover:border-red-400 hover:bg-red-100 active:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <x-heroicon-o-trash
                                wire:loading.remove
                                wire:target="deleteAttribute({{ $attribute->id }})"
                                class="h-5 w-5 text-red-600"
                            />
                            <x-heroicon-o-arrow-path
                                wire:loading
                                wire:target="deleteAttribute({{ $attribute->id }})"
                                class="h-5 w-5 animate-spin text-red-600"
                            />
                            <span wire:loading.remove wire:target="deleteAttribute({{ $attribute->id }})">حذف</span>
                            <span wire:loading wire:target="deleteAttribute({{ $attribute->id }})">در حال حذف...</span>
                        </button>
                        @endif
                    </div>
                </article>
                @empty
                {{-- Mobile Empty State --}}
                <div class="px-5 py-16 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gold-100 text-gold-500">
                        <x-heroicon-o-inbox class="h-8 w-8" />
                    </div>
                    <h3 class="mt-5 text-xl font-bold text-gold-900">موردی یافت نشد</h3>
                    <p class="mt-2 text-base leading-6 text-zinc-500">
                        در حال حاضر داده‌ای برای نمایش وجود ندارد.
                    </p>
                </div>
                @endforelse
            </div>

            {{-- Pagination Links --}}
            @if($attributes->hasPages())
            <div class="border-t border-zinc-200 bg-zinc-50/50 px-6 py-5">
                {{ $attributes->links() }}
            </div>
            @endif
        </div>
    </section>
</div>

{{-- Custom Styles for Gold/Black Theme --}}
@push('styles')
<style>
    /* Gold/Black Theme Adjustments */
    .border-gold-200 { border-color: #fcd34d; } /* Light Gold */
    .bg-gold-50 { background-color: #fffbeb; } /* Very Light Gold */
    .text-gold-700 { color: #a16207; } /* Dark Gold */
    .bg-gold-400 { background-color: #f59e0b; } /* Medium Gold */
    .hover\:bg-gold-500:hover { background-color: #d97706; } /* Darker Gold */
    .focus\:ring-gold-400:focus { --tw-ring-color: #d97706; } /* Dark Gold Ring */
    .focus\:ring-offset-gold-50 { --tw-ring-offset-color: #fef3c7; } /* Light Gold Offset */
    .text-gold-900 { color: #7c3a0e; } /* Deepest Gold */
    .placeholder\:text-gold-500::placeholder { color: #d97706; } /* Dark Gold Placeholder */
    .border-gold-300 { border-color: #fbbf24; } /* Brighter Gold */
    .bg-gold-100 { background-color: #fef3c7; } /* Lightest Gold */
    .text-gold-500 { color: #d97706; } /* Dark Gold */
    .focus\:border-gold-400:focus { border-color: #d97706; } /* Dark Gold Border Focus */
    .focus\:ring-gold-400:focus { --tw-ring-color: #d97706; } /* Dark Gold Ring Focus */
    .shadow-lg { box-shadow: 0 10px 15px -3px rgba(217, 119, 6, 0.3), 0 4px 6px -2px rgba(217, 119, 6, 0.1); } /* Gold-tinted Shadow */
    .shadow-inner { box-shadow: inset 0 2px 4px 0 rgba(124, 58, 14, 0.1); } /* Gold Tinted Inner Shadow */
    .shadow-xl { box-shadow: 0 20px 25px -5px rgba(217, 119, 6, 0.3), 0 8px 10px -6px rgba(217, 119, 6, 0.1); } /* Larger Gold Shadow */

    /* Zinc Adjustments for Contrast */
    .border-zinc-200 { border-color: #e5e7eb; }
    .bg-zinc-50 { background-color: #f9fafb; }
    .text-zinc-600 { color: #4b5563; }
    .text-zinc-500 { color: #6b7280; }
    .text-zinc-700 { color: #374151; }
    .text-zinc-400 { color: #9ca3af; }
    .divide-zinc-100 > * { border-color: #f3f4f6; }

    /* Specific Icon Colors */
    .heroicon-gold-500 { color: #d97706; } /* Dark Gold */
    .heroicon-red-600 { color: #dc2626; } /* Red */

    /* Button Hover/Active States */
    .hover\:bg-zinc-50:hover { background-color: #f9fafb; }
    .active\:bg-zinc-100:active { background-color: #f3f4f6; }
    .active\:bg-red-100:active { background-color: #fee2e2; }
    .hover\:border-gold-400:hover { border-color: #d97706; }
    .hover\:bg-gold-50:hover { background-color: #fffbeb; }

    /* Animation for Loading Spinners */
    .animate-spin { animation: spin 1s linear infinite; }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .animate-pulse { animation: pulse 2s infinite; }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .7; }
    }
</style>
@endpush
