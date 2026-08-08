
<div class="mx-auto max-w-4xl space-y-6">
    <x-admin.subheader :title="$isEdit ? 'ویرایش کاتالوگ' : 'ایجاد کاتالوگ'" />

    @if (session('message'))
    <x-alert type="success" :message="session('message')" />
    @endif

    <x-admin.card>
        <form wire:submit="save" class="space-y-6">
            <div>
                <x-input-label for="name" value="نام کاتالوگ" />
                <x-text-input
                    id="name"
                    wire:model.defer="name"
                    type="text"
                    class="mt-1 block w-full"
                    dir="rtl"
                />
                <x-input-error for="name" class="mt-2" />
            </div>

            <div>
                <x-input-label value="انتخاب تأمین‌کنندگان" />
                <p class="mt-1 text-sm text-gray-500">
                    اگر فقط یک تأمین‌کننده انتخاب شود، مقدار <code>supplier_id</code> نیز برای سازگاری با داده‌های قبلی ذخیره می‌شود.
                    در انتخاب چندگانه، کاتالوگ به‌صورت تجمیعی ذخیره خواهد شد.
                </p>

                <div class="mt-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($suppliers as $supplier)
                    <label class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2 hover:bg-gray-50">
                        <input
                            type="checkbox"
                            wire:model="supplier_ids"
                            value="{{ $supplier->id }}"
                            class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                        >
                        <span class="text-sm text-gray-800">{{ $supplier->company_name  }}</span>
                    </label>
                    @empty
                    <div class="col-span-full text-sm text-gray-500">
                        هیچ تأمین‌کننده‌ای برای انتخاب وجود ندارد.
                    </div>
                    @endforelse
                </div>

                <x-input-error for="supplier_ids" class="mt-2" />
                <x-input-error for="supplier_ids.*" class="mt-2" />
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-gray-900">دسته‌بندی‌های کاتالوگ</h3>
                </div>

                <label class="mb-4 flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input
                        type="checkbox"
                        wire:model.live="selectAllCategories"
                        class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                    >
                    انتخاب همه دسته‌بندی‌ها
                </label>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($categories as $category)
                    <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            wire:model.live="selectedCategories"
                            value="{{ $category->id }}"
                            class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                        >
                        <span>{{ $category->name }}</span>
                    </label>
                    @endforeach
                </div>

                @error('selectedCategories')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-gray-900">نوع قیمت‌های قابل نمایش</h3>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($priceTypes as $type)
                    <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            wire:model.live="selectedPriceTypes"
                            value="{{ $type->value }}"
                            class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                        >
                        <span>{{ $type->label() }}</span>
                    </label>
                    @endforeach
                </div>

                @error('selectedPriceTypes')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-gray-900">مرتب‌سازی محصولات</h3>
                </div>

                <div class="space-y-4">
                    @foreach([1, 2, 3] as $level)
                    @if($level === 1 || ${'sortField' . ($level - 1)})
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                فیلد مرتب‌سازی سطح {{ $level }}
                            </label>

                            <select
                                wire:model.live="sortField{{ $level }}"
                                class="w-full rounded-md border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500"
                            >
                                <option value="">بدون مرتب‌سازی</option>

                                @foreach($sortFields as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            @error('sortField' . $level)
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                جهت مرتب‌سازی سطح {{ $level }}
                            </label>

                            <select
                                wire:model.live="sortDir{{ $level }}"
                                class="w-full rounded-md border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500"
                            >
                                <option value="asc">صعودی</option>
                                <option value="desc">نزولی</option>
                            </select>

                            @error('sortDir' . $level)
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <label class="flex items-center justify-between gap-4">
        <span>
            <span class="block text-sm font-semibold text-gray-900">نمایش صفحه کاور</span>
            <span class="mt-1 block text-sm text-gray-500">در صورت فعال بودن، کاتالوگ با صفحه کاور تولید می‌شود.</span>
        </span>

                    <input
                        type="checkbox"
                        wire:model.live="showCoverPage"
                        class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                    >
                </label>

                @error('showCoverPage')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- کیفیت تصاویر --}}
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-gray-900">کیفیت تصاویر</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        انتخاب کیفیت بالاتر باعث افزایش حجم و زمان تولید PDF می‌شود.
                    </p>
                </div>

                <div class="space-y-3">
                    @foreach($imageQualities as $value => $label)
                    <label class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50 cursor-pointer
                        {{ $imageQuality === $value ? 'border-amber-500 bg-amber-50' : '' }}">
                        <input
                            type="radio"
                            wire:model.live="imageQuality"
                            value="{{ $value }}"
                            class="text-amber-600 focus:ring-amber-500"
                        >
                        <div class="flex-1">
                            <span class="block text-sm font-medium text-gray-900">{{ $label }}</span>
                            @if($value === 'sm')
                                <span class="text-xs text-gray-500">توصیه می‌شود برای کاتالوگ‌های بزرگ</span>
                            @elseif($value === 'original')
                                <span class="text-xs text-orange-600">⚠️ ممکن است زمان بیشتری طول بکشد</span>
                            @endif
                        </div>
                    </label>
                    @endforeach
                </div>

                @error('imageQuality')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.catalogs.index') }}"
                   class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    بازگشت
                </a>






                <x-primary-button type="submit">
                    {{ $isEdit ? 'ذخیره تغییرات' : 'ایجاد کاتالوگ' }}
                </x-primary-button>
            </div>
        </form>
    </x-admin.card>
</div>
