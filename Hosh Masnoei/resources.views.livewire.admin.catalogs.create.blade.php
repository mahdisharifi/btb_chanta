<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">ایجاد کاتالوگ جدید</h1>
            <p class="mt-1 text-sm text-gray-600">
                از این بخش می‌توانید کاتالوگ عادی یا کاتالوگ تجمیعی ادمین ایجاد کنید.
            </p>
        </div>

        <a href="{{ route('admin.catalogs.index') }}"
           class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            بازگشت
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <form wire:submit.prevent="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- نام کاتالوگ --}}
                <div class="lg:col-span-2">
                    <x-input-label for="name" value="نام کاتالوگ" />
                    <x-text-input
                        id="name"
                        type="text"
                        wire:model.defer="name"
                        class="mt-2 block w-full"
                        placeholder="مثلاً کاتالوگ تجمیعی تابستان 1405"
                    />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                {{-- نوع کاتالوگ --}}
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">
                        نوع کاتالوگ
                    </label>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-gray-300">
                            <input
                                type="radio"
                                wire:model.live="is_global"
                                value="0"
                                class="mt-1 h-4 w-4 border-gray-300 text-black focus:ring-black"
                            >
                            <div>
                                <div class="font-semibold text-gray-900">کاتالوگ تأمین‌کننده‌ای</div>
                                <div class="mt-1 text-sm text-gray-600">
                                    این نوع کاتالوگ به یک تأمین‌کننده مشخص وابسته است.
                                </div>
                            </div>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-gray-300">
                            <input
                                type="radio"
                                wire:model.live="is_global"
                                value="1"
                                class="mt-1 h-4 w-4 border-gray-300 text-black focus:ring-black"
                            >
                            <div>
                                <div class="font-semibold text-gray-900">کاتالوگ تجمیعی ادمین</div>
                                <div class="mt-1 text-sm text-gray-600">
                                    این نوع کاتالوگ می‌تواند محصولات چند تأمین‌کننده را پوشش دهد.
                                </div>
                            </div>
                        </label>
                    </div>

                    <x-input-error :messages="$errors->get('is_global')" class="mt-2" />
                </div>

                {{-- انتخاب تامین‌کنندگان برای کاتالوگ تجمیعی --}}
                @if((bool) $is_global)
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700">
                        انتخاب تأمین‌کنندگان
                    </label>

                    <div class="rounded-xl border border-gray-200 p-4">
                        @if(isset($suppliers) && count($suppliers))
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($suppliers as $supplier)
                            <label class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2 hover:bg-gray-50">
                                <input
                                    type="checkbox"
                                    wire:model="selectedSupplierIds"
                                    value="{{ $supplier->id }}"
                                    class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black"
                                >
                                <span class="text-sm text-gray-800">
                                                {{ $supplier->name ?? ('تأمین‌کننده #' . $supplier->id) }}
                                            </span>
                            </label>
                            @endforeach
                        </div>
                        @else
                        <div class="text-sm text-red-600">
                            هیچ تأمین‌کننده‌ای برای انتخاب وجود ندارد.
                        </div>
                        @endif
                    </div>

                    <x-input-error :messages="$errors->get('selectedSupplierIds')" class="mt-2" />
                </div>
                @endif

                {{-- دسته‌بندی‌ها --}}
                <div class="lg:col-span-2">
                    <x-input-label for="selected_categories" value="دسته‌بندی‌های انتخابی" />
                    <textarea
                        id="selected_categories"
                        wire:model.defer="selected_categories"
                        rows="4"
                        class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-black focus:ring-black"
                        placeholder="در صورت نیاز، دسته‌بندی‌ها را به فرمت موردنظر کامپوننت ذخیره کنید"
                    ></textarea>
                    <p class="mt-2 text-xs text-gray-500">
                        این فیلد باید با ساختار واقعی ذخیره‌سازی پروژه هماهنگ شود
                        (مثلاً آرایه شناسه دسته‌ها).
                    </p>
                    <x-input-error :messages="$errors->get('selected_categories')" class="mt-2" />
                </div>

                {{-- تنظیمات --}}
                <div class="lg:col-span-2">
                    <x-input-label for="settings" value="تنظیمات کاتالوگ" />
                    <textarea
                        id="settings"
                        wire:model.defer="settings"
                        rows="6"
                        class="mt-2 block w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-black focus:ring-black"
                        placeholder='مثلاً ساختار JSON تنظیمات'
                    ></textarea>
                    <p class="mt-2 text-xs text-gray-500">
                        در صورتی که تنظیمات به‌صورت آرایه/JSON مدیریت می‌شود، بهتر است در لایه Livewire اعتبارسنجی و نرمال‌سازی شود.
                    </p>
                    <x-input-error :messages="$errors->get('settings')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-6">
                <a href="{{ route('admin.catalogs.index') }}"
                   class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    انصراف
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center rounded-lg bg-black px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-800"
                >
                    ذخیره کاتالوگ
                </button>
            </div>
        </form>
    </div>
</div>
