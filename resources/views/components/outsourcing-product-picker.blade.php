<div class="relative md:col-span-4" wire:ignore
    x-data="{
        query: '', results: [], selected: null, open: false, loading: false, searched: false, failed: false, active: -1, version: 0,
        ready() { return this.query.trim().length >= 2 || /^[0-9۰-۹٠-٩]+$/.test(this.query.trim()); },
        changed() { this.version++; this.results = []; this.active = -1; this.searched = false; this.failed = false; this.loading = this.ready(); this.open = true; },
        async lookup() {
            if (!this.ready()) { this.loading = false; return; }
            const request = this.version;
            this.loading = true;
            try {
                const rows = await $wire.searchProducts(this.query);
                if (request !== this.version) return;
                this.results = rows; this.searched = true;
            } catch (error) { if (request === this.version) this.failed = true; }
            finally { if (request === this.version) this.loading = false; }
        },
        choose(item) {
            if (!item || item.has_active_task) return;
            this.version++; this.selected = item; this.open = false; this.loading = false;
            $wire.$set('productId', item.id, false);
        },
        clear() {
            this.version++; this.selected = null; this.query = ''; this.results = []; this.open = false;
            this.loading = false; this.searched = false; this.failed = false; this.active = -1;
            $wire.$set('productId', null, false);
        },
        move(step) {
            this.open = true;
            if (!this.results.length) return;
            for (let i = 0; i < this.results.length; i++) {
                this.active = (this.active + step + this.results.length) % this.results.length;
                if (!this.results[this.active].has_active_task) break;
            }
            this.$nextTick(() => document.getElementById('task-product-option-' + this.active)?.scrollIntoView({block: 'nearest'}));
        }
    }"
    @click.outside="open = false" @outsourcing-product-cleared.window="clear()">
    <label for="outsourcing-task-product-search" class="mb-1.5 block text-sm font-medium text-slate-700">محصول برای ایجاد تسک</label>
    <div x-show="!selected">
        <input id="outsourcing-task-product-search" type="search" autocomplete="off" maxlength="100"
            x-model="query" @input="changed()" @input.debounce.350ms="lookup()" @focus="open = true"
            @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
            @keydown.enter.prevent.stop="if (open && active >= 0) choose(results[active])"
            @keydown.escape.prevent.stop="open = false" @keydown.tab="open = false"
            role="combobox" aria-autocomplete="list" aria-controls="outsourcing-product-results"
            :aria-expanded="open && ready()" :aria-activedescendant="open && active >= 0 ? 'task-product-option-' + active : null"
            aria-describedby="outsourcing-product-help" :aria-busy="loading"
            placeholder="نام، مدل، کد یا شناسه محصول…" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
        <p id="outsourcing-product-help" class="mt-1.5 text-xs text-slate-500">حداقل ۲ حرف بنویسید؛ برای شناسه، واردکردن عدد کافی است.</p>
        <div x-cloak x-show="open && ready()" class="absolute inset-x-0 z-30 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
            <div role="status" aria-live="polite" class="border-b bg-slate-50 px-3 py-2 text-xs text-slate-600">
                <span x-show="loading">در حال جستجوی محصول…</span>
                <span x-show="!loading && !failed && searched" x-text="results.length ? results.length + ' نتیجه؛ محصول مورد نظر را انتخاب کنید.' : 'محصولی پیدا نشد؛ نام یا مدل دیگری را امتحان کنید.'"></span>
                <span x-show="failed">جستجو انجام نشد.</span>
                <button x-show="failed" type="button" @click="failed = false; lookup()" class="mr-2 text-indigo-700 underline">تلاش دوباره</button>
            </div>
            <ul id="outsourcing-product-results" role="listbox" aria-label="نتایج جستجوی محصول" class="max-h-72 overflow-y-auto overscroll-contain">
                <template x-for="(item, index) in results" :key="item.id">
                    <li :id="'task-product-option-' + index" role="option" :aria-selected="active === index" :aria-disabled="item.has_active_task">
                        <button type="button" tabindex="-1" @click="choose(item)" @mouseenter="active = index" :disabled="item.has_active_task"
                            :class="active === index ? 'bg-indigo-50' : 'bg-white'"
                            class="block w-full border-b border-slate-100 px-3 py-3 text-right hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-60">
                            <span class="block text-sm font-medium text-slate-800" x-text="item.title"></span>
                            <span class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                <span>شناسه: <bdi x-text="item.id"></bdi></span><bdi x-show="item.sku" x-text="item.sku"></bdi>
                                <span x-show="item.has_active_task" class="text-amber-800">دارای تسک فعال</span>
                            </span>
                        </button>
                    </li>
                </template>
            </ul>
            <p x-show="results.length === 15" class="bg-slate-50 px-3 py-2 text-xs text-slate-500">۱۵ نتیجه اول؛ برای نتایج دقیق‌تر نام مدل را کامل‌تر بنویسید.</p>
        </div>
    </div>
    <div x-cloak x-show="selected" class="rounded-lg border border-indigo-200 bg-indigo-50 p-3">
        <p class="text-sm font-semibold text-indigo-950" x-text="selected?.title"></p>
        <p class="mt-1 text-xs text-indigo-800">شناسه: <bdi x-text="selected?.id"></bdi> <bdi class="mr-2" x-text="selected?.sku"></bdi></p>
        <button type="button" @click="clear(); $nextTick(() => $el.closest('[x-data]').querySelector('input').focus())" class="mt-2 text-xs font-semibold text-indigo-700 underline">تغییر محصول</button>
    </div>
</div>
