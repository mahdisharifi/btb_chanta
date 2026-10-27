<div>
    {{-- هدر صفحه --}}
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">قیمت‌گذاری و موجودی</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                محصول: <span class="font-semibold text-gray-600 dark:text-gray-300">{{ $product->name ?? $product->title }}</span>
            </p>
        </div>
        <a href="{{ route('supplier.product-selection') }}" class="text-sm text-blue-600 hover:text-blue-800 transition-colors">
            &larr; بازگشت به انتخاب محصول
        </a>
    </div>

    {{-- فرم اصلی --}}
    <x-card>
        <form wire:submit="save">
            <div class="p-6">

                {{-- بخش اول: اطلاعات عرضه --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">اطلاعات عرضه</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            شناسه محصول، تعداد موجودی و وضعیت نمایش محصول شما برای این کالا.
                        </p>
                    </div>

                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="sku" value="شناسه انحصاری شما (SKU)" />
                            <x-text-input wire:model="sku" id="sku" type="text" class="mt-1 block w-full" placeholder="مثلا: PROD-12345" />
                            <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="stock_quantity" value="تعداد موجودی" />
                            <x-text-input wire:model="stock_quantity" id="stock_quantity" type="number" class="mt-1 block w-full" placeholder="تعداد" />
                            <x-input-error :messages="$errors->get('stock_quantity')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="is_active" class="flex items-center cursor-pointer">
                                <x-checkbox wire:model="is_active" id="is_active" />
                                <span class="ms-3 text-sm text-gray-600 dark:text-gray-300">این محصول را برای فروش فعال کن</span>
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-7">
                                در صورت فعال بودن، این محصول با قیمت‌های شما به مشتریان نمایش داده می‌شود.
                            </p>
                        </div>
                    </div>
                </div>

                <hr class="my-8 border-gray-200 dark:border-gray-700">

                {{-- بخش دوم: لیست قیمت‌ها --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6">
                    <div class="md:col-span-1">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">لیست قیمت‌ها</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            قیمت‌های مختلف را بر اساس نوع فروش وارد کنید. تنها فیلدهای دارای قیمت ذخیره خواهند شد.
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            @if(class_exists(\App\Enums\PriceType::class))
                            @foreach (\App\Enums\PriceType::cases() as $priceType)
                            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-100 dark:border-gray-700">
                                <x-input-label for="price-{{ $priceType->value }}">
                                    {{ $priceType->label() }}
                                </x-input-label>

                                <div class="relative mt-2">
                                    <x-text-input
                                        wire:model.live.debounce.500ms="prices.{{ $priceType->value }}.price"
                                        id="price-{{ $priceType->value }}"
                                        type="number"
                                        class="block w-full pe-16"
                                        placeholder="0"
                                    />
                                    <div class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3">
                                        <span class="text-gray-500 sm:text-sm">تومان</span>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('prices.' . $priceType->value . '.price')" class="mt-2" />

                                {{-- فیلد حداقل تعداد --}}
                                <div class="mt-3">
                                    <x-input-label for="min-qty-{{ $priceType->value }}" value="حداقل تعداد سفارش" class="text-xs" />
                                    <x-text-input
                                        wire:model="prices.{{ $priceType->value }}.min_quantity"
                                        id="min-qty-{{ $priceType->value }}"
                                        type="number"
                                        class="block w-full mt-1"
                                    />
                                </div>

                                {{-- فیلد تعداد ماه گارانتی (فقط برای قیمت WARRANTY) --}}
                                @if($priceType->value === 'warranty')
                                <div class="mt-3">
                                    <x-input-label for="warranty-months-{{ $priceType->value }}" value="تعداد ماه گارانتی" class="text-xs" />
                                    <x-text-input
                                        wire:model="prices.{{ $priceType->value }}.warranty_months"
                                        id="warranty-months-{{ $priceType->value }}"
                                        type="number"
                                        class="block w-full mt-1"
                                        placeholder="مثلا: 12, 18, 24"
                                    />
                                    <x-input-error :messages="$errors->get('prices.' . $priceType->value . '.warranty_months')" class="mt-1" />
                                    <p class="text-xs text-gray-500 mt-1">این عدد در کاتالوگ نمایش داده می‌شود</p>
                                </div>
                                @endif
                            </div>
                            @endforeach
                            @else
                            <p class="text-red-500 text-sm sm:col-span-2">
                                خطا: ENUM مربوط به انواع قیمت یافت نشد.
                            </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- فوتر فرم --}}
            <div class="flex items-center justify-end gap-x-4 bg-gray-50 dark:bg-gray-800/50 px-6 py-4 rounded-b-lg mt-6">
                <x-primary-button>
                    ذخیره و ثبت در محصولات من
                </x-primary-button>
            </div>
        </form>
    </x-card>
</div>
