<div class="space-y-6">
    <!-- Subheader -->
    <x-admin.subheader title="قیمت‌گذاری سریع">
        <div class="flex items-center gap-4">
            <x-text-input
                wire:model.live.debounce.300ms="search"
                placeholder="جستجو در محصولات..."
                class="w-64"
            />

            <div class="flex items-center bg-white rounded-lg border border-gray-200 p-1">
                <button
                    wire:click="setViewMode('table')"
                    class="p-2 rounded {{ $viewMode === 'table' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-400 hover:text-gray-600' }}"
                    title="نمایش جدولی"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                </button>
                <button
                    wire:click="setViewMode('grid')"
                    class="p-2 rounded {{ $viewMode === 'grid' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-400 hover:text-gray-600' }}"
                    title="نمایش شبکه‌ای"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                </button>
            </div>
        </div>
    </x-admin.subheader>

    <!-- Content -->
    @if($viewMode === 'table')
    <!-- Table View -->
    <x-card class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="p-4 text-sm font-medium text-gray-600">محصول</th>
                    <th class="p-4 text-sm font-medium text-gray-600 w-24">وضعیت</th>
                    <th class="p-4 text-sm font-medium text-gray-600 w-32">موجودی</th>
                    @foreach(App\Enums\PriceType::cases() as $type)
                    <th class="p-4 text-sm font-medium text-gray-600 w-40">{{ $type->label() }} (تومان)</th>
                    @endforeach
                    <th class="p-4 text-sm font-medium text-gray-600 w-24">عملیات</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($products as $ps)
                <tr wire:key="row-{{ $ps->product_id }}" class="hover:bg-gray-50/50 transition-colors">
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0">
                                @if($ps->product?->primaryImage?->file_path)
                                <img src="{{ asset('storage/' . $ps->product->primaryImage->file_path) }}" alt="{{ $ps->product?->title }}" class="w-full h-full object-cover">
                                @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                    </svg>
                                </div>
                                @endif
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">{{ $ps->product?->title ?? 'محصول نامعتبر' }}</h4>
                                <p class="text-xs text-gray-500 mt-1">کد: {{ $ps->product_id }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="p-4">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.defer="pricingData.{{ $ps->product_id }}.is_active" class="sr-only peer" @if(!$ps->product) disabled @endif>
                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                        </label>
                    </td>
                    <td class="p-4">
                        <x-text-input
                            type="number"
                            wire:model.defer="pricingData.{{ $ps->product_id }}.stock_quantity"
                            class="w-full text-center"
                            dir="ltr"
                            :disabled="!$ps->product"
                        />
                    </td>
                    @foreach(App\Enums\PriceType::cases() as $type)
                    <td class="p-4">
                        <x-text-input
                            type="number"
                            wire:model.defer="pricingData.{{ $ps->product_id }}.prices.{{ $type->value }}.price"
                            class="w-full text-center"
                            placeholder="قیمت..."
                            dir="ltr"
                            :disabled="!$ps->product"
                        />
                    </td>
                    @endforeach
                    <td class="p-4">
                        <x-primary-button wire:click="save({{ $ps->product_id }})" class="!px-3 !py-2 w-full justify-center" :disabled="!$ps->product">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </x-primary-button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="p-8 text-center text-gray-500">
                        هیچ محصولی یافت نشد.
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    @else
    <!-- Grid View -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($products as $ps)
        <x-card wire:key="grid-{{ $ps->product_id }}" class="flex flex-col">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-20 h-20 rounded-xl bg-gray-100 overflow-hidden flex-shrink-0">
                    @if($ps->product?->primaryImage?->file_path)
                    <img src="{{ asset('storage/' . $ps->product->primaryImage->file_path) }}" alt="{{ $ps->product?->title }}" class="w-full h-full object-cover">
                    @else
                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                    </div>
                    @endif
                </div>
                <div class="flex-1">
                    <h4 class="text-base font-bold text-gray-900 mb-1 line-clamp-2">{{ $ps->product?->title ?? 'محصول نامعتبر' }}</h4>
                    <p class="text-sm text-gray-500 mb-3">کد: {{ $ps->product_id }}</p>

                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.defer="pricingData.{{ $ps->product_id }}.is_active" class="sr-only peer" @if(!$ps->product) disabled @endif>
                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                        <span class="mr-3 text-sm font-medium text-gray-700">فعال در فروشگاه</span>
                    </label>
                </div>
            </div>

            <div class="space-y-4 flex-1">
                <div>
                    <x-input-label value="موجودی انبار" />
                    <x-text-input
                        type="number"
                        wire:model.defer="pricingData.{{ $ps->product_id }}.stock_quantity"
                        class="w-full mt-1"
                        dir="ltr"
                        :disabled="!$ps->product"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    @foreach(App\Enums\PriceType::cases() as $type)
                    <div>
                        <x-input-label value="{{ $type->label() }} (تومان)" />
                        <x-text-input
                            type="number"
                            wire:model.defer="pricingData.{{ $ps->product_id }}.prices.{{ $type->value }}.price"
                            class="w-full mt-1"
                            dir="ltr"
                            :disabled="!$ps->product"
                        />
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100">
                <x-primary-button wire:click="save({{ $ps->product_id }})" class="w-full justify-center" :disabled="!$ps->product">
                    ذخیره تغییرات
                </x-primary-button>
            </div>
        </x-card>
        @empty
        <div class="col-span-full p-8 text-center text-gray-500 bg-white rounded-xl border border-gray-200">
            هیچ محصولی یافت نشد.
        </div>
        @endforelse
    </div>
    @endif

    <!-- Pagination -->
    <div class="mt-6">
        {{ $products->links() }}
    </div>
</div>

