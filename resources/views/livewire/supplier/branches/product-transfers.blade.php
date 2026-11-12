<div class="space-y-5 text-gray-900 dark:text-gray-100" dir="rtl">
    <h1 class="text-xl font-bold">مدیریت محصولات شعبه</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400">لیست فروش و قیمت هر شعبه مستقل است. اطلاعات محصول تأییدشده قابل ویرایش یا حذف نیست.</p>
    @if($this->branches->isEmpty())
        <a href="{{ route('supplier.branches.index') }}" class="inline-flex min-h-11 items-center text-indigo-600">ابتدا شعبه ایجاد کنید</a>
    @else
        <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm">شعبهٔ مورد مدیریت
                    <select wire:model.live="branchId" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                        @foreach($this->branches as $branch)<option value="{{ $branch->id }}">{{ $branch->branchLabel() }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm">جستجو
                    <input type="search" wire:model.live.debounce.350ms="search" placeholder="نام یا کد محصول" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                </label>
            </div>
            <details>
                <summary class="min-h-11 cursor-pointer text-sm leading-10">فیلترهای بیشتر</summary>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm">دسته‌بندی
                        <select wire:model.live="categoryId" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                            <option value="">همه دسته‌ها</option>
                            @foreach($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                        </select>
                    </label>
                    @if($tab === 'products')
                        <label class="text-sm">وضعیت فروش
                            <select wire:model.live="status" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900"><option value="">همه</option><option value="active">فعال</option><option value="inactive">غیرفعال</option></select>
                        </label>
                    @endif
                </div>
            </details>
        </div>
        <form wire:submit="savePricingSettings" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="grid items-end gap-3 sm:grid-cols-3">
                <label class="text-sm" for="branch-pricing-mode">روش قیمت‌گذاری شعبه
                    <select id="branch-pricing-mode" wire:model.live="pricingMode" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                        <option value="independent">مستقل</option>
                        <option value="percentage">درصدی نسبت به شعبهٔ اصلی</option>
                    </select>
                </label>
                @if($pricingMode === 'percentage')
                    <label class="text-sm" for="branch-global-percentage">درصد کلی محصولات
                        <input id="branch-global-percentage" type="number" dir="ltr" step="0.000000000001" min="-99.999999" max="999.999999" wire:model="branchPercentage" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                    </label>
                @endif
                <button type="submit" wire:confirm="روش قیمت‌گذاری ذخیره شود؟ در حالت درصدی قیمت محصولات دارای مبنا با شعبهٔ اصلی همگام می‌شود. در حالت مستقل قیمت‌های فعلی حفظ می‌شوند." wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white disabled:opacity-50">ذخیرهٔ روش قیمت‌گذاری</button>
            </div>
            @error('pricingMode')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('branchPercentage')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="text-xs leading-6 text-gray-500 dark:text-gray-400">در حالت مستقل، قیمت هر محصول را مانند شعبهٔ اصلی تعیین می‌کنید. در حالت درصدی، درصد کلی برای محصولات فعلی و جدید دارای مبنا اعمال می‌شود؛ محصولات بدون مبنا مستقل قیمت‌گذاری می‌شوند.</p>
            <button type="button" wire:click="openSelection" class="min-h-11 text-sm font-semibold text-indigo-600 dark:text-indigo-300">افزودن محصول از فهرست محصولات</button>
        </form>
        <div class="flex flex-wrap gap-2" role="group" aria-label="نمایش لیست">
            @foreach(['products' => 'محصولات شعبه', 'pending' => 'در انتظار افزودن'] as $value => $label)
                <button type="button" wire:click="$set('tab', '{{ $value }}')" aria-pressed="{{ $tab === $value ? 'true' : 'false' }}" class="min-h-11 rounded-lg border px-4 text-sm {{ $tab === $value ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 dark:border-gray-600' }}">{{ $label }}</button>
            @endforeach
        </div>
        @if($tab === 'products')
            @if($this->currentSupplier->usesPrimaryPricing())
            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <button type="button" wire:click="selectPage" class="min-h-11 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-600">انتخاب همین صفحه</button>
                <span class="py-3 text-sm">{{ count($selected) }} انتخاب</span>
                <label class="text-sm">درصد اختلاف با شعبه اصلی
                    <input type="number" step="0.000000000001" min="-99.999999" max="999.999999" wire:model="percentage" placeholder="حفظ درصد فعلی" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 sm:w-48 dark:border-gray-600 dark:bg-gray-900">
                </label>
                <button type="button" wire:click="previewSync" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white disabled:opacity-50">پیش‌نمایش همگام‌سازی</button>
            </div>
            @error('percentage')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="text-xs leading-6 text-gray-500 dark:text-gray-400">مبالغ به تومان است. درصد خالی، درصد اختصاصی هر محصول را حفظ می‌کند. همگام‌سازی از شعبه اصلی انجام می‌شود؛ وضعیت فروش تغییر نمی‌کند.</p>
            @endif
            <div class="space-y-3">
                @forelse($this->offers as $offer)
                    @php($comparison = app(\App\Services\SupplierBranchCatalogService::class)->comparison($offer, $this->primary))
                    <article wire:key="offer-{{ $branchId }}-{{ $offer->id }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <label class="flex min-w-0 items-start gap-3">
                                <input type="checkbox" wire:model.live="selected" value="{{ $offer->id }}" class="mt-1 h-5 w-5 rounded border-gray-300 text-indigo-600" aria-label="انتخاب {{ $offer->product->title }}">
                                <span class="min-w-0"><span class="block break-words font-semibold">{{ $offer->product->title }}</span><span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $offer->product->sku }} · {{ $offer->product->category?->name }} · {{ $offer->is_active ? 'فروش فعال' : 'فروش غیرفعال' }}</span></span>
                            </label>
                            <span class="shrink-0 text-xs {{ $comparison['state'] === 'synced' ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-300' }}">{{ ['independent' => 'قیمت‌گذاری مستقل', 'synced' => 'همگام', 'drift' => 'نیازمند بررسی', 'no_base' => 'بدون قیمت مبنا'][$comparison['state']] }}{{ $comparison['state'] === 'independent' ? '' : ' · '.$comparison['percentage'].'٪' }}</span>
                        </div>
                        <details class="mt-3">
                            <summary class="min-h-11 cursor-pointer text-sm leading-10">مقایسه با شعبه اصلی</summary>
                            <div class="space-y-2">
                                @forelse($comparison['rows'] as $row)
                                    <div class="grid gap-2 rounded-lg bg-gray-50 p-3 text-xs sm:grid-cols-4 dark:bg-gray-900">
                                        <span>{{ $row['type_label'] }} · {{ $row['tier'] == 0 ? 'قیمت پایه' : 'پله '.$row['tier'] }} {{ $row['period_label'] }}</span>
                                        <span>اصلی: {{ $row['base'] === null ? '—' : number_format($row['base']) }}</span>
                                        <span>شعبه: {{ $row['price'] === null ? '—' : number_format($row['price']) }}</span>
                                        <span>اختلاف: {{ $row['difference'] === null ? '—' : number_format($row['difference']) }} {{ $row['percent'] === null ? '' : '('.$row['percent'].'٪)' }}</span>
                                    </div>
                                @empty<p class="py-2 text-sm text-gray-500">قیمتی ثبت نشده است.</p>@endforelse
                            </div>
                        </details>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" wire:click="openPricing({{ $offer->id }})" class="min-h-11 rounded-lg bg-indigo-600 px-3 text-sm text-white">قیمت‌گذاری و تنظیمات فروش</button>
                            @if($this->currentSupplier->usesPrimaryPricing())
                                <button type="button" wire:click="previewSync({{ $offer->id }})" wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-indigo-300 px-3 text-sm text-indigo-700 dark:text-indigo-300">بررسی و همگام‌سازی</button>
                            @endif
                            @if($offer->is_active)
                                <button type="button" wire:click="archiveOffer({{ $offer->id }})" wire:confirm="فروش این محصول در همین شعبه متوقف شود؟" wire:loading.attr="disabled" class="min-h-11 rounded-lg px-3 text-sm text-amber-700 dark:text-amber-300">توقف فروش</button>
                            @endif
                            <button type="button" wire:click="removeOffer({{ $offer->id }})" wire:confirm="محصول و قیمت‌هایش از لیست فروش همین شعبه حذف شود؟ خود محصول و سایر شعبه‌ها محفوظ می‌مانند." wire:loading.attr="disabled" class="min-h-11 rounded-lg px-3 text-sm text-red-600 dark:text-red-400">حذف از لیست فروش شعبه</button>
                        </div>
                    </article>
                @empty<div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm dark:border-gray-600">محصولی با این شرایط در لیست فروش نیست.</div>@endforelse
            </div>
            {{ $this->offers->links() }}
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">تأیید، محصول را به لیست فروش شعبه اضافه می‌کند؛ قیمت‌گذاری و انتشار را می‌توانید بعداً انجام دهید.</p>
            <div class="space-y-3">
                @forelse($this->transfers as $transfer)
                    <article wire:key="transfer-{{ $branchId }}-{{ $transfer->id }}" class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-800">
                        <div class="min-w-0"><h2 class="break-words font-semibold">{{ $transfer->sourceProduct?->title }}</h2><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $transfer->sourceProduct?->sku }} · {{ $transfer->sourceProduct?->category?->name }}</p></div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button type="button" wire:click="accept({{ $transfer->id }})" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white disabled:opacity-50">تأیید و افزودن</button>
                            <button type="button" wire:click="reject({{ $transfer->id }})" wire:confirm="پیشنهاد افزودن این محصول رد شود؟" wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-600">رد پیشنهاد</button>
                        </div>
                    </article>
                @empty<div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm dark:border-gray-600">محصولی در انتظار افزودن نیست.</div>@endforelse
            </div>
            {{ $this->transfers->links() }}
        @endif
        @error('preview')<p role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        @if($preview)
            <section x-data x-init="$el.scrollIntoView({ block: 'start', behavior: 'smooth' })" class="space-y-4 rounded-xl border-2 border-indigo-300 bg-white p-4 dark:border-indigo-700 dark:bg-gray-800" aria-label="پیش‌نمایش تغییر قیمت">
                <h2 class="font-bold">پیش‌نمایش قیمت‌ها</h2>
                <p class="text-sm">{{ $this->currentSupplier->branchLabel() }} · {{ $previewPercentage === null ? 'حفظ درصد اختصاصی محصولات' : 'درصد جدید: '.$previewPercentage.'٪' }}</p>
                @foreach($preview as $item)
                    <div class="space-y-2"><h3 class="text-sm font-semibold">{{ $item['title'] }}</h3>
                        @foreach($item['rows'] as $row)
                            <div class="grid gap-2 rounded-lg bg-gray-50 p-3 text-xs sm:grid-cols-3 dark:bg-gray-900">
                                <span>{{ $row['type_label'] }} · {{ $row['tier'] == 0 ? 'قیمت پایه' : 'پله '.$row['tier'] }} {{ $row['period_label'] }}</span>
                                <span>فعلی: {{ $row['price'] === null ? '—' : number_format($row['price']) }}</span>
                                <span>پس از تأیید: {{ $row['after'] === null ? 'بدون مبنا؛ نیازمند بررسی' : number_format($row['after']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
                <p class="text-xs leading-6 text-gray-500 dark:text-gray-400">ردیف‌های وابسته‌ای که قیمت مبنای آن‌ها حذف شده، حذف می‌شوند. خود محصول تغییر نمی‌کند.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="applySync" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white disabled:opacity-50">تأیید تغییر قیمت‌ها</button>
                    <button type="button" wire:click="closePreview" class="min-h-11 rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-600">انصراف</button>
                </div>
            </section>
        @endif
    @endif
</div>
