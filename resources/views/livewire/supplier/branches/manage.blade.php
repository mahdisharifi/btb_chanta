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
                    class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    placeholder="برای نمونه: شعبهٔ غرب">
                @error('branchName') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
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
            <li class="flex flex-col gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-950/30 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $this->primarySupplier->branchLabel() }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">شعبهٔ اصلی · محصولات فعلی فروشگاه</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">شهر: {{ $this->primarySupplier->city ?: 'ثبت نشده' }}</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label for="branch-city-{{ $this->primarySupplier->id }}" class="sr-only">شهر شعبهٔ اصلی</label>
                        <input id="branch-city-{{ $this->primarySupplier->id }}" type="text" maxlength="120"
                            wire:model.defer="branchCities.{{ $this->primarySupplier->id }}" placeholder="شهر شعبهٔ اصلی"
                            class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white sm:w-44">
                    </div>
                    <button type="button" wire:click="saveCity({{ $this->primarySupplier->id }})" wire:loading.attr="disabled"
                        class="min-h-11 rounded-lg border border-indigo-300 px-4 text-sm font-medium text-indigo-800 hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-60 dark:border-indigo-800 dark:text-indigo-200 dark:hover:bg-indigo-900/40">ذخیرهٔ شهر</button>
                </div>
            </li>
            @forelse($this->branches as $branch)
                <li wire:key="branch-{{ $branch->id }}" class="flex flex-col gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $branch->branchLabel() }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ number_format($branch->pending_transfers_count) }} محصول در انتظار بررسی</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">شهر: {{ $branch->city ?: 'ثبت نشده' }}</p>
                    </div>
                    <div class="grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-2 xl:min-w-[38rem]">
                        <div class="min-w-0 flex-1">
                            <label for="branch-city-{{ $branch->id }}" class="sr-only">شهر {{ $branch->branchLabel() }}</label>
                            <input id="branch-city-{{ $branch->id }}" type="text" maxlength="120"
                                wire:model.defer="branchCities.{{ $branch->id }}" placeholder="شهر شعبه"
                                class="min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white sm:w-44">
                        </div>
                        <button type="button" wire:click="saveCity({{ $branch->id }})" wire:loading.attr="disabled"
                            class="min-h-11 rounded-lg border border-indigo-300 px-4 text-sm font-medium text-indigo-800 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-60 dark:border-indigo-800 dark:text-indigo-200 dark:hover:bg-indigo-900/40">ذخیرهٔ شهر</button>
                        <form method="POST" action="{{ route('supplier.branches.switch') }}" class="flex">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                            <input type="hidden" name="return_to" value="{{ route('supplier.branches.transfers', absolute: false) }}">
                            <button type="submit" class="min-h-11 w-full rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 sm:w-auto">رفتن به شعبه</button>
                        </form>
                        <div class="min-w-0">
                            <button type="button" wire:click="makePrimary({{ $branch->id }})"
                                wire:confirm="ریشهٔ فروشگاه به این شعبه منتقل می‌شود و شعبهٔ اصلی فعلی به شعبهٔ عادی تبدیل می‌شود. قیمت‌های فعلی محصولات مشترک حفظ و درصد هر شعبه نسبت به مبنای جدید بازتنظیم می‌شود. تنظیمات هر شعبه مستقل می‌ماند. ادامه می‌دهید؟"
                                wire:loading.attr="disabled" wire:target="makePrimary({{ $branch->id }})"
                                @disabled(!$branch->has_shared_active_product)
                                @unless($branch->has_shared_active_product) aria-describedby="primary-branch-requirement-{{ $branch->id }}" @endunless
                                class="min-h-11 w-full rounded-lg border border-indigo-300 px-4 text-sm font-semibold text-indigo-800 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-800 dark:text-indigo-200 dark:hover:bg-indigo-950/30">تعیین به‌عنوان شعبهٔ اصلی</button>
                            @unless($branch->has_shared_active_product)
                                <p id="primary-branch-requirement-{{ $branch->id }}" class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">برای جابه‌جایی، این شعبه باید حداقل یک محصول مشترک فعال داشته باشد.</p>
                            @endunless
                        </div>
                        <button type="button" wire:click="deleteBranch({{ $branch->id }})"
                            wire:confirm="این شعبه از فهرست فعال حذف می‌شود، درخواست‌های معلق آن لغو می‌شوند و محصولات و سوابق آن نگه داشته می‌شوند. ادامه می‌دهید؟"
                            wire:loading.attr="disabled" wire:target="deleteBranch({{ $branch->id }})"
                            class="min-h-11 rounded-lg border border-red-300 px-4 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:opacity-60 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/30">حذف شعبه</button>
                    </div>
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">هنوز شعبهٔ دیگری ایجاد نشده است.</li>
            @endforelse
        </ul>
    </section>
</div>
