<div class="space-y-6 pb-20">
    {{-- Header --}}
    <section class="overflow-hidden rounded-2xl border border-gold-200 bg-gradient-to-br from-white via-gold-50 to-white shadow-lg">
        <div class="relative px-5 py-6 sm:px-6 lg:px-8">
            {{-- حذف المان‌های blur تیره و جایگزینی با افکت ملایم --}}
            <div class="pointer-events-none absolute -left-24 -top-24 h-56 w-56 rounded-full bg-amber-50 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-20 bottom-0 h-44 w-44 rounded-full bg-amber-50 blur-3xl"></div>

            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="inline-flex items-center gap-2 rounded-full border border-amber-500/20 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700">
                        <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                        <span>ساختاردهی کاتالوگ و محصولات</span>
                    </div>

                    <h2 class="mt-4 text-2xl font-black tracking-tight text-zinc-900 sm:text-3xl">
                        مدیریت دسته‌بندی‌ها
                    </h2>

                    <p class="mt-2 max-w-2xl text-sm leading-7 text-zinc-500">
                        دسته‌بندی‌ها پایه نظم‌دهی محصولات، جستجوی بهتر و نمایش حرفه‌ای‌تر کاتالوگ هستند.
                        از این بخش می‌توانید ساختار درختی، اولویت، وضعیت و ارتباط دسته‌ها را مدیریت کنید.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center lg:shrink-0">
                    <div class="flex w-fit items-center gap-1 rounded-2xl border border-zinc-200 bg-zinc-50 p-1">
                        <button
                            wire:click="toggleView('tree')"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold transition-all
                        {{ $viewMode === 'tree'
                            ? 'bg-white text-zinc-900 shadow-sm border border-zinc-200'
                            : 'text-zinc-500 hover:text-zinc-900' }}"
                        >
                            <x-heroicon-o-list-bullet class="h-5 w-5" />
                            <span>درختی</span>
                        </button>

                        <button
                            wire:click="toggleView('table')"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold transition-all
                        {{ $viewMode === 'table'
                            ? 'bg-white text-zinc-900 shadow-sm border border-zinc-200'
                            : 'text-zinc-500 hover:text-zinc-900' }}"
                        >
                            <x-heroicon-o-table-cells class="h-5 w-5" />
                            <span>جدول</span>
                        </button>
                    </div>

                    <a
                        href="{{ route('admin.categories.create') }}"
                        wire:navigate
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-zinc-900 px-5 text-sm font-black text-white shadow-lg transition duration-200 hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2"
                    >
                        <x-heroicon-o-plus class="h-5 w-5" />
                        <span>افزودن دسته‌بندی</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Notifications --}}
    @if (session()->has('success'))
    <div class="flex items-center gap-3 rounded-2xl border border-green-500/20 bg-green-50 px-4 py-3 text-green-700 shadow-sm">
        <x-heroicon-o-check-circle class="h-5 w-5 shrink-0" />
        <span class="text-sm font-bold">{{ session('success') }}</span>
    </div>
    @endif

    @if (session()->has('error'))
    <div class="flex items-center gap-3 rounded-2xl border border-red-500/20 bg-red-50 px-4 py-3 text-red-700 shadow-sm">
        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
        <span class="text-sm font-bold">{{ session('error') }}</span>
    </div>
    @endif

    {{-- Filters --}}
    <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800 sm:px-6">
            <div class="flex items-center gap-2 text-sm font-black text-zinc-900 dark:text-white">
                <x-heroicon-o-funnel class="h-5 w-5 text-amber-500" />
                <span>جستجو و فیلتر دسته‌بندی‌ها</span>
            </div>
        </div>

        <div class="px-5 py-5 sm:px-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="relative md:col-span-8">
                    <label for="category-search" class="mb-2 block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                        جستجو در نام یا اسلاگ
                    </label>

                    <div class="relative">
                        <x-heroicon-o-magnifying-glass class="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400" />

                        <input
                            id="category-search"
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="مثلاً: لوازم خانگی، مصالح، بسته‌بندی..."
                            class="h-12 w-full rounded-2xl border border-zinc-200 bg-zinc-50 pr-12 pl-4 text-sm font-medium text-zinc-900 outline-none transition-all placeholder:text-zinc-400 focus:border-amber-400 focus:bg-white focus:ring-2 focus:ring-amber-400/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:focus:border-amber-400"
                        >
                    </div>
                </div>

                @if($viewMode === 'table')
                <div class="md:col-span-4">
                    <label for="category-parent-filter" class="mb-2 block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                        سطح دسته‌بندی
                    </label>

                    <select
                        id="category-parent-filter"
                        wire:model.live="parentId"
                        class="h-12 w-full cursor-pointer rounded-2xl border border-zinc-200 bg-zinc-50 px-4 text-sm font-medium text-zinc-900 outline-none transition-all focus:border-amber-400 focus:bg-white focus:ring-2 focus:ring-amber-400/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:focus:border-amber-400"
                    >
                        <option value="">همه سطوح</option>
                        <option value="root">فقط دسته‌های اصلی</option>

                        @foreach($rootCategories as $root)
                        <option value="{{ $root->id }}">{{ $root->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Content --}}
    <section class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-xl shadow-zinc-200/40 dark:border-zinc-800 dark:bg-zinc-950 dark:shadow-black/20">
        @if($viewMode === 'tree')
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            {{-- Tree Header --}}
            <div class="grid grid-cols-12 border-b border-zinc-200 bg-gradient-to-l from-zinc-100 via-white to-amber-50 px-5 py-4 text-xs font-black uppercase tracking-widest text-zinc-500 dark:border-zinc-800 dark:from-zinc-900 dark:via-zinc-950 dark:to-zinc-900 sm:px-6">
                <div class="col-span-8 md:col-span-7">عنوان و ساختار</div>
                <div class="hidden text-center md:col-span-2 md:block">محصولات</div>
                <div class="hidden text-center md:col-span-1 md:block">ترتیب</div>
                <div class="col-span-4 text-left md:col-span-2">عملیات</div>
            </div>

            @forelse($categories as $category)
            @include('livewire.admin.categories.partials.tree-item', [
            'category' => $category,
            'depth' => 0,
            'icons' => $icons,
            'expandedCategories' => $expandedCategories,
            'forceShow' => !empty($search)
            ])
            @empty
            <div class="flex flex-col items-center justify-center px-5 py-20 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full border border-amber-500/20 bg-amber-50 text-amber-500 dark:bg-amber-500/10">
                    <x-heroicon-o-folder-open class="h-8 w-8" />
                </div>

                <h3 class="mt-4 text-sm font-black text-zinc-900 dark:text-white">
                    هیچ دسته‌بندی یافت نشد
                </h3>

                <p class="mt-2 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    فیلتر جستجو را تغییر دهید یا یک دسته‌بندی جدید ثبت کنید.
                </p>
            </div>
            @endforelse
        </div>
        @else
        {{-- Table Mode --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-right text-sm">
                <thead class="border-b border-zinc-200 bg-gradient-to-l from-zinc-100 via-white to-amber-50 dark:border-zinc-800 dark:from-zinc-900 dark:via-zinc-950 dark:to-zinc-900">
                <tr>
                    <th class="px-6 py-4 text-xs font-black tracking-wide text-zinc-600 dark:text-zinc-300">
                        نام و نشان
                    </th>

                    <th class="px-6 py-4 text-xs font-black tracking-wide text-zinc-600 dark:text-zinc-300">
                        دسته‌بندی والد
                    </th>

                    <th class="px-6 py-4 text-center text-xs font-black tracking-wide text-zinc-600 dark:text-zinc-300">
                        تعداد کالا
                    </th>

                    <th class="px-6 py-4 text-center text-xs font-black tracking-wide text-zinc-600 dark:text-zinc-300">
                        اولویت
                    </th>

                    <th class="px-6 py-4 text-center text-xs font-black tracking-wide text-zinc-600 dark:text-zinc-300">
                        وضعیت
                    </th>

                    <th class="px-6 py-4 text-left text-xs font-black tracking-wide text-zinc-600 dark:text-zinc-300">
                        مدیریت
                    </th>
                </tr>
                </thead>

                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($categories as $category)
                @php
                // اصلاح منطق آیکن بصورت متمرکز
                $iconKey = str_replace('heroicon-o-', '', $category->icon);
                $displayIcon = in_array($iconKey, $icons) ? 'heroicon-o-'.$iconKey : 'heroicon-o-squares-2x2';
                @endphp
                <tr
                    x-data="{ rowHovered: false }"
                    @mouseenter="rowHovered = true"
                    @mouseleave="rowHovered = false"
                    :class="rowHovered ? 'bg-amber-50/50 dark:bg-zinc-900' : ''"
                    class="transition-colors"
                >
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div
                                :class="rowHovered
                    ? 'scale-105 border-amber-400 bg-zinc-950 text-amber-300 dark:bg-amber-500/10'
                    : 'border-amber-500/20 bg-zinc-950 text-amber-400 dark:bg-amber-500/10'"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border shadow-sm transition-all duration-200"
                            >
                                <x-dynamic-component
                                    :component="$displayIcon"
                                    ::class="rowHovered ? 'scale-110' : 'scale-100'"
                                    class="h-6 w-6 transition-transform duration-200"
                                />
                            </div>

                            <div class="min-w-0">
                                <div class="truncate font-black text-zinc-900 dark:text-white">
                                    {{ $category->name }}
                                </div>

                                <div class="mt-0.5 truncate text-[11px] font-mono uppercase text-zinc-400">
                                    {{ $category->slug }}
                                </div>
                            </div>
                        </div>
                    </td>

                    <td class="px-6 py-4">
                        @if($category->parent)
                        <span class="inline-flex items-center rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-bold text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                {{ $category->parent->name }}
            </span>
                        @else
                        <span class="inline-flex items-center rounded-full border border-amber-500/20 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                دسته اصلی
            </span>
                        @endif
                    </td>

                    <td class="px-6 py-4 text-center">
        <span class="inline-flex min-w-10 items-center justify-center rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-black text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
            {{ $category->products_count }}
        </span>
                    </td>

                    <td class="px-6 py-4 text-center">
        <span class="font-mono text-sm font-black text-zinc-700 dark:text-zinc-300">
            {{ $category->sort_order }}
        </span>
                    </td>

                    <td class="px-6 py-4 text-center">
                        <div class="flex justify-center">
                            @if($category->is_active)
                            <span class="inline-flex items-center gap-2 rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-bold text-green-700 dark:bg-green-500/10 dark:text-green-300">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                    </span>
                    فعال
                </span>
                            @else
                            <span class="inline-flex items-center gap-2 rounded-full border border-red-500/20 bg-red-50 px-3 py-1 text-xs font-bold text-red-700 dark:bg-red-500/10 dark:text-red-300">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-500"></span>
                    </span>
                    غیرفعال
                </span>
                            @endif
                        </div>
                    </td>

                    <td class="px-6 py-4">
                        <div class="flex justify-end gap-2">
                            <a
                                href="{{ route('admin.categories.edit', $category) }}"
                                wire:navigate
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 transition-all hover:border-amber-400 hover:bg-amber-50 hover:text-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400/30 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-amber-400 dark:hover:bg-amber-500/10 dark:hover:text-amber-300"
                                title="ویرایش"
                            >
                                <x-heroicon-o-pencil-square class="h-5 w-5" />
                            </a>

                            <button
                                type="button"
                                wire:click="deleteCategory({{ $category->id }})"
                                wire:confirm="آیا از حذف این دسته‌بندی اطمینان دارید؟"
                                wire:loading.attr="disabled"
                                wire:target="deleteCategory({{ $category->id }})"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-500 transition-all hover:border-red-300 hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-400/30 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300"
                                title="حذف"
                            >
                                <x-heroicon-o-trash
                                    wire:loading.remove
                                    wire:target="deleteCategory({{ $category->id }})"
                                    class="h-5 w-5"
                                />

                                <x-heroicon-o-arrow-path
                                    wire:loading
                                    wire:target="deleteCategory({{ $category->id }})"
                                    class="h-5 w-5 animate-spin"
                                />
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-zinc-100 bg-zinc-50/80 px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900/60 sm:px-6">
            {{ $categories->links() }}
        </div>
        @endif
    </section>
</div>
