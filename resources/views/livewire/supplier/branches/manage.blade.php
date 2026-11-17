<div class="space-y-6" dir="rtl">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">مدیریت شعبه‌ها</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">برای هر شعبه، محصولات و تنظیمات مستقل نگهداری می‌شود.</p>
    </div>



    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <details @if($errors->has('branchName') || $errors->has('branchProvinceIds.new') || $errors->has('branchCityIds.new') || $errors->has('newBranchAddress') || $errors->has('newBranchPostalCode')) open @endif>
            <summary class="flex min-h-12 cursor-pointer items-center justify-between gap-4 rounded-lg bg-gray-50 px-4 py-3 marker:text-indigo-600 dark:bg-gray-900/50">
                <span>
                    <span class="block font-semibold text-gray-900 dark:text-white">＋ افزودن شعبه</span>
                    <span class="mt-1 block text-xs text-gray-600 dark:text-gray-300">نام و محل شعبه را وارد کنید؛ جزئیات بعد از ساخت در کارت همان شعبه است.</span>
                </span>
                <span class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">افزودن شعبه</span>
            </summary>
            <form wire:submit="createBranch" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="min-w-0 flex-1">
                <label for="branch-name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">نام شعبه</label>
                <input id="branch-name" type="text" wire:model="branchName" maxlength="120" required
                    @error('branchName') aria-invalid="true" aria-describedby="branch-name-error" @enderror
                    class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    placeholder="برای نمونه: شعبهٔ غرب">
                @error('branchName') <p id="branch-name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="new-branch-province" class="mb-1 block text-sm font-medium">استان *</label>
                <select id="new-branch-province" wire:model.live="branchProvinceIds.new" required class="min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <option value="">انتخاب استان</option>
                    @foreach($this->iranProvinces as $id => $provinceName)
                        <option value="{{ $id }}">{{ $provinceName }}</option>
                    @endforeach
                </select>
                @error('branchProvinceIds.new') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="new-branch-city" class="mb-1 block text-sm font-medium">شهر *</label>
                @php($newProvinceId = (int) ($branchProvinceIds['new'] ?? 0))
                <select id="new-branch-city" wire:model="branchCityIds.new" wire:key="new-branch-city-{{ $newProvinceId }}" required @disabled(!$newProvinceId) class="min-h-11 w-full rounded-lg border-gray-300 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    <option value="">{{ $newProvinceId ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید' }}</option>
                    @foreach($this->availableCitiesByProvince[$newProvinceId] ?? [] as $id => $cityName)
                        <option value="{{ $id }}">{{ $cityName }}</option>
                    @endforeach
                </select>
                @error('branchCityIds.new') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="new-branch-address" class="mb-1 block text-sm font-medium">آدرس</label>
                <input id="new-branch-address" type="text" wire:model="newBranchAddress" maxlength="255" class="min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                @error('newBranchAddress') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="new-branch-postal" class="mb-1 block text-sm font-medium">کدپستی</label>
                <input id="new-branch-postal" type="text" wire:model="newBranchPostalCode" maxlength="20" inputmode="numeric" class="min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                @error('newBranchPostalCode') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="createBranch"
                class="min-h-11 rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="createBranch">ساخت شعبه</span>
                <span wire:loading wire:target="createBranch">در حال ساخت…</span>
            </button>
            </form>
            <p class="mt-3 text-xs leading-6 text-gray-500 dark:text-gray-400">پس از ساخت، فهرست محصولات شعبهٔ اصلی برای تأیید یا رد در دسترس قرار می‌گیرد.</p>
        </details>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <details @if($errors->has('pricingBranchId') || $errors->has('pricingCategoryId') || $errors->has('pricingProductId') || $errors->has('pricingPercentage')) open @endif>
            <summary class="flex min-h-12 cursor-pointer items-center justify-between gap-4 rounded-lg bg-gray-50 px-4 py-3 marker:text-indigo-600 dark:bg-gray-900/50">
                <span>
                    <span class="block font-semibold text-gray-900 dark:text-white">تنظیم درصد قیمت یک یا چند محصول</span>
                    <span class="mt-1 block text-xs text-gray-600 dark:text-gray-300">شعبه را انتخاب کنید؛ سپس درصد را برای همه، یک دسته یا یک محصول اعمال کنید.</span>
                </span>
                <span class="shrink-0 rounded-lg border border-indigo-300 px-4 py-2 text-sm font-semibold text-indigo-700 dark:text-indigo-200">تنظیم قیمت</span>
            </summary>
            <div class="mt-4 rounded-lg border border-indigo-100 bg-indigo-50 p-3 text-sm leading-6 text-indigo-950 dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-100">
                درصد اختصاصی فقط جای درصد قبلیِ همان محدوده را می‌گیرد. اگر یک محصول انتخاب شود، فقط همان محصول به قیمت‌گذاری درصدی برمی‌گردد و قیمت مستقل فعلی‌اش با قیمت محاسبه‌شده جایگزین می‌شود؛ محصولات دیگر و وضعیت فروششان تغییر نمی‌کند.
            </div>
        <form wire:submit="applyBranchPercentage" class="mt-4 grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,.9fr)_auto]">
            <div>
                <label for="pricing-branch" class="mb-1 block text-sm font-medium">۱. شعبه *</label>
                <select id="pricing-branch" wire:model.live="pricingBranchId" required class="min-h-11 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:text-white">
                    <option value="">انتخاب شعبه</option>
                    @foreach($this->branches->where('is_active', true) as $pricingBranch)
                        <option value="{{ $pricingBranch->id }}">{{ $pricingBranch->branchLabel() }}</option>
                    @endforeach
                </select>
                @error('pricingBranchId') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pricing-category" class="mb-1 block text-sm font-medium">۲. محدوده دسته‌بندی</label>
                <select id="pricing-category" wire:model.live="pricingCategoryId" class="min-h-11 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:text-white">
                    <option value="">همه دسته‌ها</option>
                    @foreach($this->pricingCategories as $pricingCategory)
                        <option value="{{ $pricingCategory->id }}">{{ $pricingCategory->name }}</option>
                    @endforeach
                </select>
                @error('pricingCategoryId') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pricing-product" class="mb-1 block text-sm font-medium">۳. محصول (اختیاری)</label>
                <select id="pricing-product" wire:model="pricingProductId" wire:key="pricing-product-{{ $pricingBranchId }}-{{ $pricingCategoryId }}" class="min-h-11 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:text-white">
                    <option value="">همه محصولات در محدوده</option>
                    @foreach($this->pricingProducts as $pricingProduct)
                        <option value="{{ $pricingProduct->id }}">{{ $pricingProduct->title }}</option>
                    @endforeach
                </select>
                @error('pricingProductId') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pricing-percentage" class="mb-1 block text-sm font-medium">۴. درصد افزایش یا کاهش *</label>
                <input id="pricing-percentage" type="number" wire:model="pricingPercentage" step="0.000000000001" min="-99.999999" max="999.999999" required dir="ltr" class="min-h-11 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:text-white">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">مثبت = افزایش؛ منفی = کاهش.</p>
                @error('pricingPercentage') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="applyBranchPercentage" class="min-h-11 rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="applyBranchPercentage">ذخیره درصد</span>
                <span wire:loading wire:target="applyBranchPercentage">در حال ذخیره…</span>
            </button>
        </form>
            @php($pricingProducts = $this->pricingProducts)
            @php($selectedPricingProduct = $pricingProductId !== '' ? $pricingProducts->firstWhere('id', (int) $pricingProductId) : null)
            @php($selectedPricingCategory = $pricingCategoryId !== '' ? $this->pricingCategories->firstWhere('id', (int) $pricingCategoryId) : null)
            <div role="status" aria-live="polite" class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm leading-6 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                @if($pricingBranchId === '')
                    ابتدا شعبه را انتخاب کنید تا محدوده‌ی اعمال مشخص شود.
                @elseif($pricingProductId !== '' && $selectedPricingProduct)
                    این محصول به قیمت‌گذاری درصدی وصل می‌شود و قیمتش با این درصد از روی شعبهٔ اصلی محاسبه خواهد شد؛ درصد و قیمت مستقل محصولات دیگر تغییر نمی‌کند.
                @elseif($pricingCategoryId !== '' && $selectedPricingCategory)
                    این درصد برای {{ number_format($pricingProducts->count()) }} محصول از دسته‌ی «{{ $selectedPricingCategory->name }}» در شعبه‌ی انتخاب‌شده اعمال می‌شود.
                @else
                    این درصد برای {{ number_format($pricingProducts->count()) }} محصول موجود در محدوده‌ی انتخاب‌شده اعمال می‌شود.
                @endif
                درصد جدید جایگزین درصد قبلی همان محدوده می‌شود و قیمت شعبه‌ی اصلی را تغییر نمی‌دهد.
            </div>
        </details>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">شعبه‌های فروشگاه</h2>
        @error('primaryBranch') <p class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" role="alert">{{ $message }}</p> @enderror
        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">شعبهٔ اصلی مبنای قیمت‌گذاری است. نشانی، محصولات و قیمت‌های شعبه‌های دیگر در محدودهٔ همان شعبه مدیریت می‌شوند.</p>
        <ul class="mt-4 space-y-3">
            <li class="flex flex-col gap-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-950/30">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $this->primarySupplier->branchLabel() }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">شعبهٔ اصلی · محصولات فعلی فروشگاه</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">شهر: {{ ($branchCityNames[$this->primarySupplier->id] ?? '') ?: 'ثبت نشده' }}</p>
                </div>
                @php($primarySupplierId = (int) $this->primarySupplier->id)
                <details @if($errors->has('branchProvinceIds.' . $primarySupplierId) || $errors->has('branchCityIds.' . $primarySupplierId) || $errors->has('branchAddresses.' . $primarySupplierId) || $errors->has('branchPostalCodes.' . $primarySupplierId)) open @endif>
                    <summary class="min-h-11 cursor-pointer rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm font-medium text-indigo-800 dark:border-indigo-800 dark:bg-gray-800 dark:text-indigo-200">ویرایش نشانی شعبهٔ اصلی</summary>
                <form wire:submit="saveLocation({{ $this->primarySupplier->id }})" class="mt-3 space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="min-w-0">
                            <label for="branch-province-{{ $this->primarySupplier->id }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">استان <span aria-hidden="true" class="text-red-600">*</span></label>
                            <select id="branch-province-{{ $this->primarySupplier->id }}" required wire:model.live="branchProvinceIds.{{ $this->primarySupplier->id }}"
                                @error('branchProvinceIds.' . $this->primarySupplier->id) aria-invalid="true" aria-describedby="branch-province-error-{{ $this->primarySupplier->id }}" @enderror
                                class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                <option value="">انتخاب استان</option>
                                @foreach($this->iranProvinces as $id => $provinceName)
                                    <option value="{{ $id }}">{{ $provinceName }}</option>
                                @endforeach
                            </select>
                            @error('branchProvinceIds.' . $this->primarySupplier->id) <p id="branch-province-error-{{ $this->primarySupplier->id }}" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="min-w-0">
                            <label for="branch-city-{{ $this->primarySupplier->id }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">شهر <span aria-hidden="true" class="text-red-600">*</span></label>
                            @php($primaryProvinceId = (int) ($branchProvinceIds[$this->primarySupplier->id] ?? 0))
                            <select id="branch-city-{{ $this->primarySupplier->id }}" required wire:model="branchCityIds.{{ $this->primarySupplier->id }}" @disabled(!$primaryProvinceId)
                                @error('branchCityIds.' . $this->primarySupplier->id) aria-invalid="true" aria-describedby="branch-city-error-{{ $this->primarySupplier->id }}" @enderror
                                class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800">
                                <option value="">{{ $primaryProvinceId ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید' }}</option>
                                @foreach($this->availableCitiesByProvince[$primaryProvinceId] ?? [] as $id => $cityName)
                                    <option value="{{ $id }}">{{ $cityName }}</option>
                                @endforeach
                            </select>
                            @error('branchCityIds.' . $this->primarySupplier->id) <p id="branch-city-error-{{ $this->primarySupplier->id }}" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="min-w-0">
                            <label for="branch-address-{{ $this->primarySupplier->id }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">نشانی (اختیاری)</label>
                            <input id="branch-address-{{ $this->primarySupplier->id }}" type="text" maxlength="255" wire:model="branchAddresses.{{ $this->primarySupplier->id }}"
                                @error('branchAddresses.' . $this->primarySupplier->id) aria-invalid="true" aria-describedby="branch-address-error-{{ $this->primarySupplier->id }}" @enderror
                                class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="نشانی">
                            @error('branchAddresses.' . $this->primarySupplier->id) <p id="branch-address-error-{{ $this->primarySupplier->id }}" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="min-w-0">
                            <label for="branch-postal-{{ $this->primarySupplier->id }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">کدپستی (اختیاری)</label>
                            <input id="branch-postal-{{ $this->primarySupplier->id }}" type="text" maxlength="20" inputmode="numeric" wire:model="branchPostalCodes.{{ $this->primarySupplier->id }}"
                                @error('branchPostalCodes.' . $this->primarySupplier->id) aria-invalid="true" aria-describedby="branch-postal-error-{{ $this->primarySupplier->id }}" @enderror
                                class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="کدپستی">
                            @error('branchPostalCodes.' . $this->primarySupplier->id) <p id="branch-postal-error-{{ $this->primarySupplier->id }}" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveLocation({{ $this->primarySupplier->id }})"
                        class="min-h-11 w-full rounded-lg border border-indigo-300 px-4 text-sm font-medium text-indigo-800 hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-60 dark:border-indigo-800 dark:text-indigo-200 dark:hover:bg-indigo-900/40 sm:w-auto">ذخیرهٔ نشانی شعبهٔ اصلی</button>
                </form>
                </details>
            </li>
            @forelse($this->branches as $branch)
                <li wire:key="branch-{{ $branch->id }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-900/40 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $branch->branchLabel() }}</h3>
                                <span class="rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">شعبهٔ فرعی</span>
                                @if($activeSupplierId === (int) $branch->id)
                                    <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200">در حال مشاهده</span>
                                @endif
                            </div>
                            @php($branchCity = ($branchCityNames[$branch->id] ?? '') ?: ($branch->city ?? ''))
                            @php($branchPlace = collect([$branch->province, $branchCity])->filter()->unique()->implode('، '))
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                {{ $branchPlace ?: 'موقعیت ثبت نشده' }}{{ filled($branch->address) ? ' · '.$branch->address : '' }}
                            </p>
                        </div>
                        <p class="inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <span class="font-semibold">{{ number_format($branch->pending_transfers_count) }}</span>
                            <span>محصول در انتظار بررسی</span>
                        </p>
                    </header>

                    @php($branchCurrency = match(strtoupper((string) $branch->pricing_currency)) {'TOM' => 'تومان', 'USD' => 'دلار آمریکا', 'CNY' => 'یوان چین', 'AED' => 'درهم امارات', default => $branch->pricing_currency ?: 'تومان'})
                    @php($branchModeLabel = $branch->usesPrimaryPricing() ? 'درصدی نسبت به شعبهٔ اصلی' : 'قیمت‌گذاری مستقل')
                    @php($branchModeDetail = $branch->usesPrimaryPricing()
                        ? 'درصد پیش‌فرض: '.rtrim(rtrim(number_format((float) ($branch->branch_price_percentage ?? 0), 6, '.', ''), '0'), '.').'٪'
                        : 'قیمت هر محصول جداگانه تنظیم می‌شود')
                    <div class="grid grid-cols-1 gap-3 border-b border-gray-200 p-4 dark:border-gray-700 sm:grid-cols-3 sm:px-5">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/60">
                            <p class="text-xs text-gray-500 dark:text-gray-400">محصولات</p>
                            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ number_format($branch->product_suppliers_count) }} محصول</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/60">
                            <p class="text-xs text-gray-500 dark:text-gray-400">واحد قیمت</p>
                            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $branchCurrency }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/60">
                            <p class="text-xs text-gray-500 dark:text-gray-400">روش قیمت‌گذاری</p>
                            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $branchModeLabel }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $branchModeDetail }}</p>
                        </div>
                    </div>

                    <div class="grid gap-5 p-4 sm:p-5 xl:grid-cols-[minmax(0,1fr)_15rem]">
                        <details class="min-w-0" @if($errors->has('branchProvinceIds.' . $branch->id) || $errors->has('branchCityIds.' . $branch->id) || $errors->has('branchAddresses.' . $branch->id) || $errors->has('branchPostalCodes.' . $branch->id)) open @endif>
                        <summary class="min-h-11 cursor-pointer rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">ویرایش نشانی و اطلاعات شعبه</summary>
                        <form wire:submit="saveLocation({{ $branch->id }})" class="mt-4 min-w-0 space-y-4">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">نشانی و اطلاعات شعبه</h4>
                            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                                <div class="min-w-0">
                                    <label for="branch-province-{{ $branch->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">استان <span aria-hidden="true" class="text-red-600">*</span></label>
                                    <select id="branch-province-{{ $branch->id }}" required wire:model.live="branchProvinceIds.{{ $branch->id }}"
                                        @error('branchProvinceIds.' . $branch->id) aria-invalid="true" aria-describedby="branch-province-error-{{ $branch->id }}" @enderror
                                        class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                        <option value="">انتخاب استان</option>
                                        @foreach($this->iranProvinces as $id => $provinceName)
                                            <option value="{{ $id }}">{{ $provinceName }}</option>
                                        @endforeach
                                    </select>
                                    @error('branchProvinceIds.' . $branch->id) <p id="branch-province-error-{{ $branch->id }}" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div class="min-w-0">
                                    <label for="branch-city-{{ $branch->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">شهر <span aria-hidden="true" class="text-red-600">*</span></label>
                                    @php($branchProvinceId = (int) ($branchProvinceIds[$branch->id] ?? 0))
                                    <select id="branch-city-{{ $branch->id }}" required wire:model="branchCityIds.{{ $branch->id }}" @disabled(!$branchProvinceId)
                                        @error('branchCityIds.' . $branch->id) aria-invalid="true" aria-describedby="branch-city-error-{{ $branch->id }}" @enderror
                                        class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800">
                                        <option value="">{{ $branchProvinceId ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید' }}</option>
                                        @foreach($this->availableCitiesByProvince[$branchProvinceId] ?? [] as $id => $cityName)
                                            <option value="{{ $id }}">{{ $cityName }}</option>
                                        @endforeach
                                    </select>
                                    @error('branchCityIds.' . $branch->id) <p id="branch-city-error-{{ $branch->id }}" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div class="min-w-0">
                                    <label for="branch-address-{{ $branch->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">نشانی <span class="font-normal text-gray-500">(اختیاری)</span></label>
                                    <input id="branch-address-{{ $branch->id }}" type="text" maxlength="255" wire:model="branchAddresses.{{ $branch->id }}"
                                        @error('branchAddresses.' . $branch->id) aria-invalid="true" aria-describedby="branch-address-error-{{ $branch->id }}" @enderror
                                        class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="نشانی کامل شعبه">
                                    @error('branchAddresses.' . $branch->id) <p id="branch-address-error-{{ $branch->id }}" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                                </div>
                                <div class="min-w-0">
                                    <label for="branch-postal-{{ $branch->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">کدپستی <span class="font-normal text-gray-500">(اختیاری)</span></label>
                                    <input id="branch-postal-{{ $branch->id }}" type="text" maxlength="20" inputmode="numeric" wire:model="branchPostalCodes.{{ $branch->id }}"
                                        @error('branchPostalCodes.' . $branch->id) aria-invalid="true" aria-describedby="branch-postal-error-{{ $branch->id }}" @enderror
                                        class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="کدپستی">
                                    @error('branchPostalCodes.' . $branch->id) <p id="branch-postal-error-{{ $branch->id }}" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">استان و شهر برای ثبت نشانی الزامی هستند.</p>
                                <button type="submit" wire:loading.attr="disabled" wire:target="saveLocation({{ $branch->id }})"
                                    class="min-h-11 rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-wait disabled:opacity-60">ذخیرهٔ نشانی</button>
                            </div>
                        </form>
                        </details>

                        <aside class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700 xl:border-r xl:border-t-0 xl:pr-5 xl:pt-0">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">عملیات شعبه</h4>
                            <form method="POST" action="{{ route('supplier.branches.switch') }}">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                <input type="hidden" name="return_to" value="{{ route('supplier.branches.transfers', absolute: false) }}#branch-products">
                                <button type="submit" class="min-h-11 w-full rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">محصولات</button>
                            </form>
                            <form method="POST" action="{{ route('supplier.branches.switch') }}">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                <input type="hidden" name="return_to" value="{{ route('supplier.branches.transfers', absolute: false) }}#branch-pricing-settings">
                                <button type="submit" class="min-h-11 w-full rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700">تنظیم قیمت</button>
                            </form>
                            @if($activeSupplierId !== (int) $branch->id)
                                <form method="POST" action="{{ route('supplier.branches.switch') }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <input type="hidden" name="return_to" value="{{ route('supplier.branches.index', absolute: false) }}">
                                    <button type="submit" class="min-h-11 w-full rounded-lg border border-indigo-300 px-4 text-sm font-medium text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-200 dark:hover:bg-indigo-900/40">نمایش این شعبه</button>
                                </form>
                            @endif
                            <button type="button" wire:click="reviewBranchDeletion({{ $branch->id }})"
                                wire:loading.attr="disabled" wire:target="reviewBranchDeletion({{ $branch->id }})"
                                class="min-h-11 w-full rounded-lg border border-red-300 px-4 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:opacity-60 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/30">حذف شعبه</button>
                        </aside>
                    </div>
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">هنوز شعبهٔ دیگری ایجاد نشده است.</li>
            @endforelse
        </ul>
    </section>

    @if($this->archivedBranches->isNotEmpty())
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <details>
                <summary class="min-h-11 cursor-pointer rounded-lg bg-gray-50 px-4 py-3 text-sm font-semibold dark:bg-gray-900">شعبه‌های غیرفعال یا حذف نرم‌شده ({{ number_format($this->archivedBranches->count()) }})</summary>
                <div class="mt-3">
                    <p class="text-sm text-gray-500">نام این شعبه‌ها مانع ایجاد شعبهٔ فعال جدید نیست. برای پاک‌سازی کامل، ابتدا وضعیت قیمت‌ها را بررسی کنید.</p>
            @foreach($this->archivedBranches as $archivedBranch)
                <div wire:key="archived-branch-{{ $archivedBranch->id }}" class="mt-3 flex items-center justify-between gap-3 border-t pt-3">
                    <span>{{ $archivedBranch->branchLabel() }} — {{ $archivedBranch->trashed() ? 'حذف نرم شده' : 'غیرفعال' }}</span>
                    <button type="button" wire:click="reviewBranchDeletion({{ $archivedBranch->id }})" class="min-h-11 rounded-lg border border-red-300 px-4 text-sm text-red-700">بررسی و حذف کامل</button>
                </div>
            @endforeach
                </div>
            </details>
        </section>
    @endif

    @if($deletingBranchId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4" wire:keydown.escape.window="cancelBranchDeletion">
            <section role="dialog" aria-modal="true" aria-labelledby="branch-deletion-title" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                <h2 id="branch-deletion-title" class="text-lg font-bold">حذف کامل شعبهٔ {{ $branchDeletionSummary['name'] }}</h2>
                <ul class="mt-4 list-inside list-disc space-y-2 text-sm leading-6">
                    <li>{{ $branchDeletionSummary['products'] }} محصول و تصاویر و مشخصات آن‌ها حفظ می‌شوند؛ فقط ارتباط مالکیت آن‌ها با شعبه برداشته می‌شود.</li>
                    <li>{{ $branchDeletionSummary['offers'] }} مورد از لیست فروش همین شعبه حذف می‌شود.</li>
                    <li>{{ $branchDeletionSummary['prices'] }} ردیف قیمت فعال یا غیرفعال و {{ $branchDeletionSummary['priced_offers'] }} مورد دارای مبلغ ارزی یا قیمت خرید نیاز به تعیین تکلیف دارد.</li>
                    <li>{{ $branchDeletionSummary['pending_transfers'] }} درخواست انتقال معلق و سوابق انتقال مربوط به شعبه حذف می‌شود.</li>
                    <li>تنظیمات اختصاصی شعبه، قالب‌های کاتالوگ، سوابق قیمت و عملیات قیمت‌گذاری وابسته حذف می‌شوند.</li>
                    <li>محصولات، لیست فروش و قیمت‌های شعبه‌های دیگر حفظ می‌شوند.</li>
                </ul>
                <p class="mt-4 text-sm font-semibold text-red-700">حذف کامل قابل بازگردانی از داخل برنامه نیست.</p>
                @if($branchDeletionSummary['external_period_references'] > 0)
                    <p role="alert" class="mt-3 text-sm leading-6 text-red-600">{{ $branchDeletionSummary['external_period_references'] }} وابستگی به دوره‌های چک این شعبه در قیمت یا تنظیمات شعبه‌های دیگر وجود دارد. پیش از حذف شعبه باید آن‌ها را تعیین تکلیف کنید.</p>
                @endif
                @if($branchDeletionSummary['prices'] > 0 || $branchDeletionSummary['priced_offers'] > 0)
                    <label class="mt-4 flex items-start gap-3 text-sm leading-6">
                        <input type="checkbox" wire:model="removeBranchPrices" class="mt-1 rounded border-gray-300 text-red-600">
                        قیمت‌های فعال و غیرفعال، مبالغ ارزی و خرید و سوابق قیمت همین شعبه حذف شوند. خود محصولات حذف نشوند.
                    </label>
                    <p class="mt-2 text-xs leading-6 text-gray-500">اگر می‌خواهید قیمت‌ها را نگه دارید یا منتقل کنید، انصراف دهید و ابتدا از لیست فروش شعبه آن‌ها را تعیین تکلیف کنید.</p>
                @endif
                @error('branchDeletion') <p role="alert" class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button type="button" wire:click="cancelBranchDeletion" class="min-h-11 rounded-lg border px-4 text-sm">انصراف</button>
                    <button type="button" wire:click="confirmBranchDeletion" wire:loading.attr="disabled" wire:target="confirmBranchDeletion" class="min-h-11 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">تأیید و حذف کامل شعبه</button>
                </div>
            </section>
        </div>
    @endif
</div>
