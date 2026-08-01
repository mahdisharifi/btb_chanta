@extends('layouts.app')

@push('head')
<script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'دسته‌بندی محصولات',
        'description' => $seoDescription ?? 'مرور محصولات بر اساس دسته‌بندی',
        'url' => route('categories.index'),
        'breadcrumb' => [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'دسته‌بندی‌ها', 'item' => route('categories.index')],
            ],
        ],
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => $categories->count(),
            'itemListElement' => $categories->values()->map(fn($cat, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $cat->name,
                'url' => route('categories.show', $cat->slug),
            ])->all(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}
</script>
@endpush

@section('content')
<div
    x-data="{
        search: '',
        cards: {{ Js::from($categories->map(fn($c) => [
            'id' => $c->id,
            's'  => $c->name,
            'c'  => (int) ($c->total_products_count ?? 0),
            'ch' => $c->children->map(fn($ch) => [
                'id' => $ch->id,
                's'  => trim($ch->name . ' ' . ($ch->children?->pluck('name')->implode(' ') ?? '')),
                'c'  => (int) ($ch->total_products_count ?? 0),
            ])->values()->all(),
        ])->values()->all()) }},
        norm(txt) {
            return String(txt || '')
                .replace(/ي/g, 'ی')
                .replace(/ك/g, 'ک')
                .replace(/\u200c/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
        },
        match(text) {
            const q = this.norm(this.search);
            if (!q) return true;
            return this.norm(text).includes(q);
        },
        matchCard(card) {
            if (!this.norm(this.search)) return true;
            return this.match(card.s) || card.ch.some(ch => this.match(ch.s));
        },
        cardCount(card) {
            return card.c;
        },
        chipShow(card, j) {
            if (!this.norm(this.search)) return true;
            return this.match(card.s) || this.match(card.ch[j].s);
        },
        visibleCats() {
            return this.cards.filter(c => this.matchCard(c)).length;
        },
        visibleProducts() {
            return this.cards.filter(c => this.matchCard(c))
                .reduce((sum, c) => sum + c.c, 0);
        },
        anyMatch() {
            return this.visibleCats() > 0;
        },
        fmt(n) {
            return Number(n || 0).toLocaleString('fa-IR');
        }
    }"
    class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-100 text-gray-800"
    dir="rtl"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">

        {{-- Hero / Executive Editorial --}}
        <section class="relative overflow-hidden rounded-[2rem] border border-[#2b2110] bg-gradient-to-br from-[#1a1408] via-[#0f172a] to-[#111827] text-white shadow-[0_30px_90px_rgba(0,0,0,0.35)]">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-gold-400 to-transparent"></div>
            <div class="absolute -left-20 top-10 h-64 w-64 rounded-full bg-gold-400/10 blur-3xl"></div>
            <div class="absolute -bottom-16 right-0 h-72 w-72 rounded-full bg-white/5 blur-3xl"></div>

            <div class="relative px-6 py-10 sm:px-10 sm:py-14 lg:px-14 lg:py-16">
                <div class="grid gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-end">
                    <div>
                        <div class="flex items-center gap-3 text-sm text-slate-300">
                            <a href="{{ route('home') }}" class="hover:text-gold-400 transition">خانه</a>
                            <span class="text-white/30">/</span>
                            <span class="text-gold-400">دسته‌بندی‌ها</span>
                        </div>

                        <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-gold-400/25 bg-gold-400/10 px-4 py-1.5 text-sm font-medium text-gold-200">
                            <x-heroicon-s-bolt class="h-4 w-4" />
                            ساختار مدیریتی و پرمیوم
                        </div>

                        <h1 class="mt-5 text-3xl font-black leading-tight sm:text-4xl lg:text-5xl">
                            دسته‌بندی محصولات
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">
                            نمایی رسمی و لوکس برای مرور سریع دسته‌ها، زیردسته‌ها و محصولات با تمرکز بر نظم بصری و تجربه حرفه‌ای.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-[1.5rem] border border-white/10 bg-white/5 p-5 backdrop-blur-md">
                            <div class="text-slate-400 text-sm">کل دسته‌ها</div>
                            <div class="mt-2 text-3xl font-black text-white">{{ number_format($categories->count()) }}</div>
                        </div>

                        <div class="rounded-[1.5rem] border border-white/10 bg-white/5 p-5 backdrop-blur-md">
                            <div class="text-slate-400 text-sm">کل محصولات</div>
                            <div class="mt-2 text-3xl font-black text-white">{{ number_format($totalProducts) }}</div>
                        </div>

                        <div class="sm:col-span-2 rounded-[1.5rem] border border-gold-400/20 bg-slate-950/55 p-5 shadow-inner">
                            <label for="category-search" class="mb-3 block text-sm font-medium text-slate-200">
                                جستجو در دسته‌بندی‌ها
                            </label>

                            <div class="relative">
                                <input
                                    id="category-search"
                                    type="text"
                                    x-model="search"
                                    placeholder="نام دسته یا زیردسته را بنویسید..."
                                    aria-label="جستجو در دسته‌بندی‌ها"
                                    class="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pe-12 text-sm text-white placeholder:text-slate-400 focus:border-gold-400 focus:ring-2 focus:ring-gold-400/25"
                                />
                                <button
                                    type="button"
                                    x-show="search.length"
                                    x-cloak
                                    @click="search = ''"
                                    aria-label="پاک کردن جستجو"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full p-1.5 text-slate-400 hover:bg-white/10 hover:text-white"
                                >
                                    <x-heroicon-o-x-mark class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Grid --}}
        <section class="mt-8">
            @if($categories->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($categories as $category)
                @php $i = $loop->index; @endphp
                <div
                    x-show="matchCard(cards[{{ $i }}])"
                    x-transition.opacity.duration.150ms
                    class="group relative rounded-2xl bg-white border border-gray-200/80 p-5 hover:border-gold-400/40 hover:shadow-lg hover:shadow-gray-200/50 transition-all duration-300"
                >
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3 min-w-0">
                            @if($category->icon)
                            <x-dynamic-component
                                :component="str_starts_with($category->icon, 'heroicon-') ? $category->icon : 'heroicon-o-' . $category->icon"
                                class="w-6 h-6 text-amber-500"
                            />
                            @else
                            <div class="w-10 h-10 bg-zinc-900/50 border border-zinc-800 rounded-lg flex items-center justify-center">
                                <x-heroicon-o-squares-2x2 class="w-5 h-5 text-amber-500/80 group-hover:text-amber-500 transition-colors" />
                            </div>
                            @endif


                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-gray-900 group-hover:text-gold-600 transition-colors truncate">
                                    {{ $category->name }}
                                </h2>
                                <span class="text-xs text-gray-500">
                                    <span class="font-semibold text-gray-700" x-text="fmt(cardCount(cards[{{ $i }}]))">{{ number_format($category->total_products_count ?? 0) }}</span> کالا
                                </span>
                            </div>
                        </div>

                        <a href="{{ route('categories.show', $category->slug) }}"
                           class="p-2 rounded-lg bg-gray-50 hover:bg-gold-400 hover:text-white text-gray-600 transition-all duration-200"
                           aria-label="ورود به دسته بندی">
                            <x-heroicon-o-arrow-left-start-on-rectangle class="w-4 h-4" />
                        </a>
                    </div>

                    @if($category->children->isNotEmpty())
                    <div class="border-t border-gray-100 pt-3">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($category->children as $child)
                            <a href="{{ route('categories.show', $child->slug) }}"
                               x-show="chipShow(cards[{{ $i }}], {{ $loop->index }})"
                               x-transition.opacity.duration.150ms
                               class="inline-flex items-center gap-1 bg-gray-50 hover:bg-gold-50 hover:text-gold-700 border border-gray-200/60 hover:border-gold-200 text-gray-600 text-xs px-2.5 py-1 rounded-lg transition duration-150">
                                @if($child->icon)
                                <x-dynamic-component
                                    :component="str_starts_with($child->icon, 'heroicon-') ? $child->icon : 'heroicon-o-' . $child->icon"
                                    class="w-6 h-6 text-amber-500"
                                />
                                @else
                                <div class="w-10 h-10 bg-zinc-900/50 border border-zinc-800 rounded-lg flex items-center justify-center">
                                    <x-heroicon-o-squares-2x2 class="w-5 h-5 text-amber-500/80 group-hover:text-amber-500 transition-colors" />
                                </div>
                                @endif
                                <span>{{ $child->name }}</span>
                                <span class="text-[10px] text-gray-400 font-sans">({{ number_format($child->total_products_count ?? 0) }})</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            <div x-show="!anyMatch()" x-cloak class="text-center py-16 bg-white border border-gray-200 rounded-2xl mt-6">
                <div class="w-14 h-14 mx-auto bg-gray-50 rounded-full flex items-center justify-center mb-3">
                    <x-heroicon-o-magnifying-glass class="w-6 h-6 text-gray-400" />
                </div>
                <p class="text-gray-600 text-sm">
                    نتیجه‌ای برای جستجوی «<span x-text="search" class="font-bold text-gold-600"></span>» پیدا نشد.
                </p>
                <button
                    type="button"
                    @click="search = ''"
                    class="mt-3 inline-flex items-center gap-1 px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-lg text-xs font-semibold transition"
                >
                    <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                    حذف فیلتر
                </button>
            </div>
            @else
            <div class="text-center py-20 bg-white border border-gray-200 rounded-2xl mt-6">
                <div class="w-16 h-16 mx-auto bg-gray-50 rounded-xl flex items-center justify-center mb-4">
                    <x-heroicon-o-squares-2x2 class="w-8 h-8 text-gray-400" />
                </div>
                <h3 class="text-base font-bold text-gray-800">دسته‌بندی فعالی ثبت نشده است</h3>
            </div>
            @endif
        </section>
    </div>
</div>
@endsection
