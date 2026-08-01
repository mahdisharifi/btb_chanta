@php
$hasChildren = $category->allChildrenRecursive && $category->allChildrenRecursive->count() > 0;
// اصلاح: استفاده از expandedCategories که از کلاس Index می‌آید
$isExpanded = in_array($category->id, $expandedCategories ?? []) || (isset($forceShow) && $forceShow);

// اصلاح منطق آیکن برای تطابق کامل با لیست آیکن‌های مجاز
$iconName = str_replace('heroicon-o-', '', $category->icon);
$safeIcon = in_array($iconName, $icons ?? [])
? 'heroicon-o-'.$iconName
: 'heroicon-o-squares-2x2';
@endphp

<div class="group">
    {{-- اصلاح: Grid Columns برای هماهنگی با Header (7+2+1+2 = 12) --}}
    <div class="grid grid-cols-12 items-center gap-3 px-6 py-4 hover:bg-amber-50/20 dark:hover:bg-zinc-800/40 transition-colors border-b border-gray-100 dark:border-zinc-800">

        {{-- ستون عنوان و ساختار (Col 7) --}}
        <div class="col-span-8 md:col-span-7 flex items-center gap-2 min-w-0" style="padding-right: {{ $depth * 24 }}px">
            @if ($hasChildren)
            <button
                type="button"
                wire:click="toggleCategory({{ $category->id }})"
                class="flex items-center justify-center w-8 h-8 rounded-lg text-zinc-500 hover:text-amber-600 hover:bg-amber-50 transition-all {{ $isExpanded ? 'rotate-90' : '' }}"
            >
                <x-heroicon-o-chevron-left class="w-4 h-4" />
            </button>
            @else
            <div class="w-8 h-8 flex items-center justify-center opacity-20">
                <div class="w-1 h-1 rounded-full bg-zinc-400"></div>
            </div>
            @endif

            <div class="flex items-center gap-3 min-w-0">
                @if ($category->image_path)
                <img src="{{ asset('storage/' . $category->image_path) }}"
                     class="w-9 h-9 rounded-xl object-cover border border-gray-200 dark:border-zinc-700 shrink-0">
                @else
                <div class="w-9 h-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shrink-0 group-hover:bg-black group-hover:text-amber-400 transition-colors">
                    <x-dynamic-component :component="$safeIcon" class="w-5 h-5 text-zinc-500 group-hover:text-amber-400" />
                </div>
                @endif

                <div class="min-w-0">
                    <div class="font-bold truncate {{ $category->is_active ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 line-through' }}">
                        {{ $category->name }}
                    </div>
                    <span class="text-[9px] text-zinc-400 block truncate font-mono uppercase" dir="ltr">
                        {{ $category->slug }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ستون تعداد کالا (Col 2) --}}
        <div class="hidden md:flex col-span-2 justify-center">
            <span class="px-3 py-1 rounded-lg bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 text-xs font-bold border border-zinc-100 dark:border-zinc-700">
                {{ $category->products_count }} کالا
            </span>
        </div>

        {{-- ستون ترتیب (Col 1) --}}
        <div class="hidden md:flex col-span-1 justify-center">
            <span class="text-xs font-mono font-bold text-zinc-500">
                {{ $category->sort_order }}
            </span>
        </div>

        {{-- ستون عملیات (Col 2) --}}
        <div class="col-span-4 md:col-span-2 flex justify-end items-center gap-1">
            <a href="{{ route('admin.categories.edit', $category) }}" wire:navigate
               class="p-2 text-zinc-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all" title="ویرایش">
                <x-heroicon-o-pencil-square class="w-5 h-5" />
            </a>

            <button type="button"
                    wire:click="deleteCategory({{ $category->id }})"
                    wire:confirm="با حذف این دسته تمام زیرمجموعه‌های آن به دسته‌های بدون والد تبدیل می‌شوند. آیا مطمئن هستید؟"
                    class="p-2 text-zinc-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="حذف">
                <x-heroicon-o-trash class="w-5 h-5" />
            </button>
        </div>
    </div>

    {{-- نمایش زیرمجموعه‌ها بصورت بازگشتی --}}
    @if ($isExpanded && $hasChildren)
    <div class="bg-zinc-50/30 dark:bg-zinc-900/10 border-r-2 border-amber-500/10 mr-4">
        @foreach ($category->allChildrenRecursive as $child)
        @include('livewire.admin.categories.partials.tree-item', [
        'category' => $child,
        'depth' => $depth + 1,
        'expandedCategories' => $expandedCategories,
        'icons' => $icons ?? [],
        'forceShow' => $forceShow ?? false,
        ])
        @endforeach
    </div>
    @endif
</div>
