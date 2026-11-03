<div class="space-y-6" dir="rtl">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">مدیریت شعبه‌ها</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">برای هر شعبه، محصولات و تنظیمات مستقل نگهداری می‌شود.</p>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">افزودن شعبه</h2>
        <form wire:submit="createBranch" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="branch-name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">نام شعبه</label>
                <input id="branch-name" type="text" wire:model="branchName" maxlength="120" required
                    @error('branchName') aria-invalid="true" aria-describedby="branch-name-error" @enderror
                    class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    placeholder="برای نمونه: شعبهٔ غرب">
                @error('branchName') <p id="branch-name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="createBranch"
                class="min-h-11 rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="createBranch">ساخت شعبه</span>
                <span wire:loading wire:target="createBranch">در حال ساخت…</span>
            </button>
        </form>
        <p class="mt-3 text-xs leading-6 text-gray-500 dark:text-gray-400">پس از ساخت، فهرست محصولات شعبهٔ اصلی برای تأیید یا رد در دسترس قرار می‌گیرد.</p>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">شعبه‌های فروشگاه</h2>
        @error('primaryBranch') <p class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" role="alert">{{ $message }}</p> @enderror
        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">با تغییر شعبهٔ اصلی، ساختار محصولات مشترک دوباره تنظیم می‌شود و نسبت قیمت شعبه‌ها حفظ خواهد شد. تنظیمات و محصولات اختصاصی هر شعبه مستقل می‌مانند.</p>
        <ul class="mt-4 space-y-3">
            <li class="flex flex-col gap-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-950/30">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $this->primarySupplier->branchLabel() }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">شعبهٔ اصلی · محصولات فعلی فروشگاه</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">شهر: {{ ($branchCityNames[$this->primarySupplier->id] ?? '') ?: 'ثبت نشده' }}</p>
                </div>
                <form wire:submit="saveLocation({{ $this->primarySupplier->id }})" class="space-y-3">
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
            </li>
            @forelse($this->branches as $branch)
                <li wire:key="branch-{{ $branch->id }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <header class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-900/40 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $branch->branchLabel() }}</h3>
                                <span class="rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">شعبهٔ فرعی</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">شهر: {{ ($branchCityNames[$branch->id] ?? '') ?: 'ثبت نشده' }}</p>
                        </div>
                        <p class="inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <span class="font-semibold">{{ number_format($branch->pending_transfers_count) }}</span>
                            <span>محصول در انتظار بررسی</span>
                        </p>
                    </header>

                    <div class="grid gap-5 p-4 sm:p-5 xl:grid-cols-[minmax(0,1fr)_15rem]">
                        <form wire:submit="saveLocation({{ $branch->id }})" class="min-w-0 space-y-4">
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

                        <aside class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700 xl:border-r xl:border-t-0 xl:pr-5 xl:pt-0">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">عملیات شعبه</h4>
                            <form method="POST" action="{{ route('supplier.branches.switch') }}">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                <input type="hidden" name="return_to" value="{{ route('supplier.branches.transfers', absolute: false) }}">
                                <button type="submit" class="min-h-11 w-full rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">رفتن به شعبه</button>
                            </form>
                            <div>
                                <button type="button" wire:click="makePrimary({{ $branch->id }})"
                                    wire:confirm="ریشهٔ فروشگاه به این شعبه منتقل می‌شود و شعبهٔ اصلی فعلی به شعبهٔ عادی تبدیل می‌شود. قیمت‌های فعلی محصولات مشترک حفظ و درصد هر شعبه نسبت به مبنای جدید بازتنظیم می‌شود. تنظیمات هر شعبه مستقل می‌ماند. ادامه می‌دهید؟"
                                    wire:loading.attr="disabled" wire:target="makePrimary({{ $branch->id }})"
                                    @disabled(!$branch->has_shared_active_product)
                                    @unless($branch->has_shared_active_product) aria-describedby="primary-branch-requirement-{{ $branch->id }}" @endunless
                                    class="min-h-11 w-full rounded-lg border border-indigo-300 px-4 text-sm font-medium text-indigo-800 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-500 dark:border-indigo-800 dark:text-indigo-200 dark:hover:bg-indigo-950/30 dark:disabled:border-gray-700 dark:disabled:text-gray-500">تعیین به‌عنوان شعبهٔ اصلی</button>
                                @unless($branch->has_shared_active_product)
                                    <p id="primary-branch-requirement-{{ $branch->id }}" class="mt-1.5 text-xs leading-5 text-gray-500 dark:text-gray-400">برای جابه‌جایی، این شعبه باید حداقل یک محصول مشترک فعال داشته باشد.</p>
                                @endunless
                            </div>
                            <button type="button" wire:click="deleteBranch({{ $branch->id }})"
                                wire:confirm="این شعبه از فهرست فعال حذف می‌شود، درخواست‌های معلق آن لغو می‌شوند و محصولات و سوابق آن نگه داشته می‌شوند. ادامه می‌دهید؟"
                                wire:loading.attr="disabled" wire:target="deleteBranch({{ $branch->id }})"
                                class="min-h-11 w-full rounded-lg border border-red-300 px-4 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:opacity-60 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/30">حذف شعبه</button>
                        </aside>
                    </div>
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">هنوز شعبهٔ دیگری ایجاد نشده است.</li>
            @endforelse
        </ul>
    </section>
</div>
