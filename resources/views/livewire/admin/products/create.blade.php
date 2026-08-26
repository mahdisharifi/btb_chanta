<div>
    {{-- عنوان و breadcrumb وابسته به حالت --}}
    <x-admin.subheader
        :title="$isEdit ? 'ویرایش محصول' : 'ایجاد محصول جدید'"
        :breadcrumbs="['مدیریت محصولات' => route('admin.products.index'), ($isEdit ? 'ویرایش محصول' : 'ایجاد محصول') => '#']" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
            {{ $isEdit ? 'ویرایش محصول' : 'ایجاد محصول جدید' }}
        </h1>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">

        <!-- اطلاعات پایه -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2">اطلاعات اصلی</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">عنوان محصول *</label>
                    <input type="text" wire:model.live.debounce.500ms="title" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm" required>
                    @error('title') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نامک (Slug) *<button type="button" wire:click="resetSlugToAuto"
                                                                                                                        class="text-gray-400 hover:text-gray-600" title="بازتولید خودکار از عنوان">
                            <x-heroicon-o-arrow-path class="w-4 h-4" />
                        </button></label>
                    <input type="text" wire:model="slug" dir="ltr" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm text-left" required>
                    @error('slug') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- انتخابگر هوشمند دسته‌بندی -->
                <div x-data="{ open: false }" class="relative">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">دسته‌بندی (فقط دسته‌های نهایی مجاز هستند) *</label>

                    <div @click="open = !open" class="w-full border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-lg shadow-sm text-sm px-3 py-2 cursor-pointer flex justify-between items-center bg-white dark:bg-gray-900">
                        <span class="block truncate">{{ $selectedCategoryName ?: 'یک دسته‌بندی انتخاب کنید...' }}</span>
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>

                    <div x-show="open" @click.away="open = false" style="display: none;" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg">
                        <div class="p-2 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 rounded-t-lg">
                            <input type="text" wire:model.live.debounce.300ms="searchCategory" placeholder="جستجوی دسته‌بندی..." class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <ul class="max-h-60 overflow-y-auto">
                            @forelse($searchCategories as $cat)
                            <li wire:click="selectCategory({{ $cat->id }}, '{{ $cat->name }}')" @click="open = false" class="px-3 py-2.5 text-sm cursor-pointer hover:bg-indigo-50 dark:hover:bg-gray-700 dark:text-gray-300 border-b border-gray-50 dark:border-gray-700/50 last:border-0 transition-colors">
                                {{ $cat->name }}
                            </li>
                            @empty
                            <li class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                                @if(strlen($searchCategory) > 1)
                                دسته‌بندی با این نام یافت نشد.
                                @else
                                جهت جستجو، نام دسته‌بندی را تایپ کنید...
                                @endif
                            </li>
                            @endforelse
                        </ul>
                    </div>
                    @error('category_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- انتخاب تامین کننده -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تامین کننده *</label>
                    <select wire:model="supplier_id" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm" required>
                        <option value="">یک تامین کننده انتخاب کنید...</option>
                        @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">کد محصول (SKU)</label>
                    <input type="text" wire:model="sku" dir="ltr" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm text-left">
                    @error('sku') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">وضعیت محصول *</label>
                    <select wire:model="status" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm" required>
                        <option value="">انتخاب وضعیت...</option>
                        @foreach(\App\Enums\ProductStatus::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center mt-6 md:col-span-2">
                    <input id="is_active" type="checkbox" wire:model="is_active" class="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                    <label for="is_active" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">محصول فعال است و در سایت نمایش داده می‌شود</label>
                    @error('is_active') <span class="text-red-500 text-xs ml-2">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">توضیحات کوتاه</label>
                <textarea wire:model="short_description" rows="2" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm"></textarea>
                @error('short_description') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">توضیحات کامل</label>
                <textarea wire:model="full_description" rows="5" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm"></textarea>
                @error('full_description') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- بخش تیراژهای محصول -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-4 border-b dark:border-gray-600 pb-2">
                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                    <x-heroicon-o-cube class="w-5 h-5 text-indigo-600"/>
                    تیراژهای محصول
                </h2>
                <button type="button" 
                        wire:click="toggleQuantityTiers" 
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $showQuantityTiers ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                    @if($showQuantityTiers)
                        <x-heroicon-s-check-circle class="w-4 h-4"/>
                        فعال
                    @else
                        <x-heroicon-o-x-circle class="w-4 h-4"/>
                        غیرفعال
                    @endif
                </button>
            </div>

            @if($showQuantityTiers)
                <div class="space-y-3">
                    @forelse($quantity_tiers as $index => $tier)
                        @php
                            // بررسی وجود قیمت برای این تیراژ (فقط در حالت ویرایش و فقط قیمت‌های سیستم جدید)
                            $hasPrices = false;
                            if ($isEdit && $productId) {
                                $product = \App\Models\Product::find($productId);
                                $hasPrices = $product && $product->prices()
                                    ->where('tier_index', $index)
                                    ->whereNotNull('price_type_id') // فقط قیمت‌های سیستم جدید
                                    ->exists();
                            }
                        @endphp
                        <div wire:key="tier-{{ $index }}" class="flex items-center gap-3 p-4 rounded-lg border {{ $hasPrices ? 'bg-amber-50 dark:bg-amber-900/10 border-amber-300 dark:border-amber-700' : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700' }}">
                            @if($hasPrices)
                                <div class="flex-shrink-0">
                                    <x-heroicon-s-lock-closed class="w-5 h-5 text-amber-600 dark:text-amber-400"/>
                                </div>
                            @endif
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">نام تیراژ</label>
                                    <input type="text" 
                                           wire:model="quantity_tiers.{{ $index }}.name" 
                                           placeholder="مثال: تیراژ کم"
                                           {{ $hasPrices ? 'readonly' : '' }}
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm {{ $hasPrices ? 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed' : '' }}">
                                    @error("quantity_tiers.{$index}.name") 
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">حداقل تعداد</label>
                                    <input type="number" 
                                           wire:model="quantity_tiers.{{ $index }}.min_quantity" 
                                           min="0"
                                           placeholder="100"
                                           {{ $hasPrices ? 'readonly' : '' }}
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm {{ $hasPrices ? 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed' : '' }}">
                                    @error("quantity_tiers.{$index}.min_quantity") 
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">حداکثر تعداد</label>
                                    <input type="number" 
                                           wire:model="quantity_tiers.{{ $index }}.max_quantity" 
                                           min="0"
                                           placeholder="500"
                                           {{ $hasPrices ? 'readonly' : '' }}
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm {{ $hasPrices ? 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed' : '' }}">
                                    @error("quantity_tiers.{$index}.max_quantity") 
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                                    @enderror
                                </div>
                            </div>
                            @if($hasPrices)
                                <div class="flex-shrink-0">
                                    <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs font-medium rounded-lg">
                                        <x-heroicon-s-banknotes class="w-4 h-4"/>
                                        قیمت‌گذاری شده
                                    </span>
                                </div>
                            @else
                                <button type="button" 
                                        wire:click="removeQuantityTier({{ $index }})" 
                                        class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                    <x-heroicon-o-trash class="w-5 h-5"/>
                                </button>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-cube class="w-12 h-12 mx-auto mb-2 opacity-50"/>
                            <p class="text-sm">هنوز تیراژی تعریف نشده است.</p>
                        </div>
                    @endforelse

                    @if(count($quantity_tiers) < 5)
                        <button type="button" 
                                wire:click="addQuantityTier" 
                                class="w-full py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors flex items-center justify-center gap-2">
                            <x-heroicon-o-plus-circle class="w-5 h-5"/>
                            <span class="text-sm font-medium">افزودن تیراژ جدید</span>
                        </button>
                    @else
                        <p class="text-xs text-amber-600 dark:text-amber-400 text-center py-2">حداکثر 5 تیراژ قابل تعریف است.</p>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                    برای فعال کردن تنظیم تیراژها، دکمه بالا را کلیک کنید.
                </p>
            @endif
        </div>

        <!-- بخش ویژگی‌ها -->
        @if($categoryAttributes)
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2 flex items-center gap-2">
                <x-heroicon-o-adjustments-horizontal class="w-5 h-5 text-[#d4af37]"/>
                ویژگی‌های محصول
            </h2>

            <div class="space-y-4">
                @foreach($categoryAttributes as $attribute)
                @php
                    $selectedValues = $selectedAttributeValues[$attribute->id] ?? null;
                    $selectedCount = 0;
                    if (is_array($selectedValues)) {
                        $selectedCount = count(array_filter($selectedValues));
                    } elseif ($selectedValues) {
                        $selectedCount = 1;
                    }
                @endphp
                
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden" 
                     wire:key="attr-{{ $attribute->id }}"
                     x-data="{ 
                         search: '',
                         showWarning: false,
                         warningMessage: ''
                     }">
                    
                    <!-- Header -->
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">{{ $attribute->name }}</h4>
                            <div class="flex items-center gap-2">
                                @if($attribute->is_multiple)
                                <span class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-0.5 rounded">چند انتخابی</span>
                                @else
                                <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded">تک انتخابی</span>
                                @endif
                                @if($selectedCount > 0)
                                <span class="text-xs bg-[#d4af37]/10 text-[#d4af37] px-2 py-1 rounded font-medium">
                                    {{ $selectedCount }} انتخاب شده
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <!-- هشدار برای تک انتخابی -->
                        <div x-show="showWarning" 
                             x-transition
                             class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200 text-sm">
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                                <span x-text="warningMessage"></span>
                            </div>
                        </div>

                        <!-- مقادیر انتخاب‌شده (اولویت نمایش) -->
                        @if($selectedCount > 0)
                        <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">انتخاب‌های شما:</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($attribute->values as $value)
                                @php
                                    $selectedArr = $selectedAttributeValues[$attribute->id] ?? [];
                                    $isSelected = $attribute->is_multiple 
                                        ? in_array((string)$value->id, array_map('strval', (array)$selectedArr))
                                        : (string)($selectedAttributeValues[$attribute->id] ?? '') === (string)$value->id;
                                @endphp
                                @if($isSelected)
                                <span class="inline-flex items-center gap-1.5 bg-[#d4af37] text-white px-3 py-1.5 rounded-lg text-sm font-medium shadow-sm">
                                    {{ $value->value }}
                                    <button type="button" 
                                            wire:click="removeAttributeValue({{ $attribute->id }}, {{ $value->id }})"
                                            class="hover:bg-white/20 rounded-full p-0.5 transition-colors">
                                        <x-heroicon-s-x-mark class="w-3.5 h-3.5" />
                                    </button>
                                </span>
                                @endif
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- جستجو و افزودن مقدار جدید -->
                        <div class="mb-3 flex gap-2" 
                             x-on:attribute-value-added.window="if ($event.detail.attributeId === {{ $attribute->id }}) { search = ''; }">
                            <div class="relative flex-1">
                                <x-heroicon-o-magnifying-glass class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input type="text"
                                       x-model="search"
                                       x-on:input="$wire.set('newAttributeValues.{{ $attribute->id }}', search)"
                                       placeholder="جستجو یا مقدار جدید..."
                                       class="w-full pr-10 pl-3 py-2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg text-sm focus:ring-[#d4af37] focus:border-[#d4af37]">
                            </div>
                            <button type="button"
                                    x-show="search.length > 0"
                                    x-transition
                                    wire:click="saveNewAttributeValue({{ $attribute->id }})"
                                    x-on:click="setTimeout(() => { search = ''; }, 100)"
                                    class="px-4 py-2 bg-[#d4af37] text-black rounded-lg text-sm font-medium hover:bg-[#c49d2f] transition-colors flex items-center gap-1 whitespace-nowrap">
                                <x-heroicon-o-plus class="w-4 h-4" />
                                افزودن
                            </button>
                        </div>

                        <!-- لیست مقادیر (فقط موارد انتخاب نشده) -->
                        <div class="max-h-40 overflow-y-auto pr-1 space-y-2">
                            @foreach($attribute->values as $value)
                            @php
                                $normalizedValue = str_replace(['ي', 'ك'], ['ی', 'ک'], $value->value);
                                $selectedArr = $selectedAttributeValues[$attribute->id] ?? [];
                                $isSelected = $attribute->is_multiple 
                                    ? in_array((string)$value->id, array_map('strval', (array)$selectedArr))
                                    : (string)($selectedAttributeValues[$attribute->id] ?? '') === (string)$value->id;
                            @endphp
                            <!-- فقط مقادیر انتخاب نشده را نمایش بده -->
                            @if(!$isSelected)
                            <div x-show="!search || '{{ $normalizedValue }}'.toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/\u200C/g, ' ').includes(search.toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/\u200C/g, ' '))"
                                 class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded cursor-pointer transition-colors">
                                @if($attribute->is_multiple)
                                <input type="checkbox"
                                       {{ in_array((string)$value->id, array_map('strval', (array)($selectedAttributeValues[$attribute->id] ?? []))) ? 'checked' : '' }}
                                       wire:click="toggleAttributeValue({{ $attribute->id }}, {{ $value->id }})"
                                       class="text-[#d4af37] focus:ring-[#d4af37] border-gray-300 dark:border-gray-600 rounded">
                                @else
                                <input type="radio"
                                       {{ (string)($selectedAttributeValues[$attribute->id] ?? '') === (string)$value->id ? 'checked' : '' }}
                                       wire:click="toggleAttributeValue({{ $attribute->id }}, {{ $value->id }})"
                                       name="attr_{{ $attribute->id }}"
                                       @if($selectedCount > 0)
                                       onchange="showWarning = true; warningMessage = 'انتخاب قبلی حذف شد'; setTimeout(() => showWarning = false, 3000)"
                                       @else
                                       onchange="showWarning = false"
                                       @endif
                                       class="text-[#d4af37] focus:ring-[#d4af37] border-gray-300 dark:border-gray-600">
                                @endif
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $value->value }}</span>
                            </div>
                            @endif
                            @endforeach
                        </div>

                        @error('newAttributeValues.' . $attribute->id)
                        <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif



        {{-- SEO --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-[#d4af37]"/>
                    بهینه‌سازی موتور جستجو (SEO)
                </h3>
                <button type="button" wire:click="generateSeo"
                        class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-[#d4af37]/10 text-[#d4af37] hover:bg-[#d4af37]/20 transition">
                    <x-heroicon-o-sparkles class="w-4 h-4"/>
                    تولید خودکار
                </button>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">عنوان SEO</label>
                <input type="text" wire:model="seo_title" maxlength="60"
                       class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm"
                       placeholder="حداکثر ۶۰ کاراکتر">
                <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_title ?? '') }}/60</p>
                @error('seo_title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">توضیحات SEO</label>
                <textarea wire:model="seo_description" rows="3" maxlength="160"
                          class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm"
                          placeholder="حداکثر ۱۶۰ کاراکتر"></textarea>
                <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_description ?? '') }}/160</p>
                @error('seo_description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    کلمات کلیدی
                </label>

                <input type="text"
                       wire:model="seo_keywords"
                       maxlength="255"
                       class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm"
                       placeholder="با کاما جدا کنید: موبایل، گوشی، سامسونگ">

                <p class="text-xs text-gray-400 mt-1">
                    <span dir="ltr">{{ mb_strlen($seo_keywords ?? '') }}/255</span>
                </p>

                @error('seo_keywords')
                <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">آدرس Canonical</label>
                <input type="url" wire:model="seo_canonical_url" dir="ltr"
                       class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm text-left"
                       placeholder="https://...">
                @error('seo_canonical_url')
                <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                @if($seo_title || $seo_description)
                <div class="mt-4 p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    <p class="text-xs text-gray-400 mb-2 font-medium">پیش‌نمایش گوگل</p>
                    <p class="text-blue-600 text-sm font-medium truncate">{{ $seo_title ?: $title }}</p>
                    <p class="text-green-700 dark:text-green-500 text-xs truncate">{{ $seo_canonical_url ?: url('/products/'.($slug??'')) }}</p>
                    <p class="text-gray-600 dark:text-gray-400 text-xs mt-0.5 line-clamp-2">{{ $seo_description }}</p>
                </div>
                @endif


            </div>
        </div>

        <!-- تصاویر و ویدئوی محصول -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2">تصاویر و ویدئوی محصول</h2>

            {{-- نمایش رسانه‌های موجود فقط در حالت ویرایش --}}
            @if($isEdit && count($existingGalleries) > 0)
            @php
            $existingVideos = collect($existingGalleries)->filter(fn ($gallery) => ($gallery['file_type'] ?? 'image') === 'video');
            $existingImages = collect($existingGalleries)->filter(fn ($gallery) => ($gallery['file_type'] ?? 'image') === 'image');
            @endphp

            <div class="mb-6 space-y-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">رسانه‌های فعلی محصول</p>

                @if($existingVideos->count() > 0)
                <div class="flex flex-wrap gap-3">
                    @foreach($existingVideos as $gallery)
                    <div wire:key="existing-video-{{ $gallery['id'] }}" class="relative">
                        <video controls class="w-32 h-24 object-cover rounded-lg shadow-sm border-2 border-purple-500 bg-black">
                            <source
                                src="{{ $gallery['full_url'] ?? asset('storage/' . $gallery['file_path']) }}"
                                type="{{ $gallery['mime_type'] ?? 'video/mp4' }}"
                            >
                            مرورگر شما از پخش ویدئو پشتیبانی نمی‌کند.
                        </video>

                        <span class="absolute top-1 left-1 bg-purple-600 text-white text-[10px] px-1.5 py-0.5 rounded">
                            ویدئو
                        </span>

                        <button
                            type="button"
                            wire:click="markGalleryForDeletion({{ $gallery['id'] }})"
                            wire:confirm="این رسانه هنگام ذخیره حذف خواهد شد. مطمئن هستید؟"
                            class="absolute -top-2 -right-2 bg-red-600 hover:bg-red-700 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs shadow"
                        >
                            &times;
                        </button>
                    </div>
                    @endforeach
                </div>
                @endif

                @if(collect($existingGalleries)->where('file_type', 'image')->count() > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-3">
                    @foreach(collect($existingGalleries)->where('file_type', 'image')->values() as $index => $gallery)
                    <div class="relative group rounded-lg overflow-hidden border-2 {{ $gallery['is_primary'] ? 'border-[#d4af37]' : 'border-white/10' }}">
                        <img src="{{ $gallery['full_url'] }}" class="w-full h-28 object-cover">

                        {{-- برچسب تصویر اصلی --}}
                        @if($gallery['is_primary'])
                        <span class="absolute top-1 right-1 text-xs bg-[#d4af37] text-black font-bold px-2 py-0.5 rounded">اصلی</span>
                        @endif

                        <div class="absolute bottom-0 left-0 right-0 flex justify-between items-center p-1 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity">
                            {{-- دکمه‌های جابجایی --}}
                            <div class="flex gap-1">
                                <button type="button" wire:click="moveGalleryImage({{ $gallery['id'] }}, 'up')"
                                        @if($index === 0) disabled @endif
                                        class="p-1 rounded bg-white/10 hover:bg-white/20 disabled:opacity-30">
                                    <x-heroicon-s-chevron-right class="w-3 h-3 text-white" />
                                </button>
                                <button type="button" wire:click="moveGalleryImage({{ $gallery['id'] }}, 'down')"
                                        @if($index === collect($existingGalleries)->where('file_type','image')->count() - 1) disabled @endif
                                    class="p-1 rounded bg-white/10 hover:bg-white/20 disabled:opacity-30">
                                    <x-heroicon-s-chevron-left class="w-3 h-3 text-white" />
                                </button>
                            </div>

                            <div class="flex gap-1">
                                {{-- انتخاب به عنوان تصویر اصلی --}}
                                @if(!$gallery['is_primary'])
                                <button type="button" wire:click="setPrimaryGallery({{ $gallery['id'] }})"
                                        class="p-1 rounded bg-[#d4af37]/80 hover:bg-[#d4af37]">
                                    <x-heroicon-s-star class="w-3 h-3 text-black" />
                                </button>
                                @endif

                                {{-- حذف --}}
                                <button type="button" wire:click="markGalleryForDeletion({{ $gallery['id'] }})"
                                        class="p-1 rounded bg-red-500/80 hover:bg-red-500">
                                    <x-heroicon-s-trash class="w-3 h-3 text-white" />
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

            </div>
            @endif




            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- تصویر اصلی -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ $isEdit ? 'جایگزینی تصویر اصلی (اختیاری)' : 'تصویر اصلی محصول' }}
                    </label>
                    <input type="file" wire:model="primary_image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-700 dark:file:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-md">
                    <div wire:loading wire:target="primary_image" class="text-sm text-indigo-600 mt-2">در حال آپلود...</div>
                    @error('primary_image') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if ($primary_image)
                    <div class="mt-4">
                        <p class="text-xs text-green-600 mb-2">پیش‌نمایش تصویر اصلی جدید:</p>
                        <img src="{{ $primary_image->temporaryUrl() }}" class="w-32 h-32 object-cover border-2 border-green-500 rounded-lg shadow-sm">
                    </div>
                    @endif
                </div>

                <!-- گالری تصاویر -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ $isEdit ? 'افزودن به گالری (انتخاب چندتایی)' : 'گالری تصاویر (انتخاب چندتایی)' }}
                    </label>
                    <input type="file" wire:model="gallery_images" multiple accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-700 dark:file:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-md">
                    <div wire:loading wire:target="gallery_images" class="text-sm text-indigo-600 mt-2">در حال آپلود...</div>
                    @error('gallery_images.*') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if ($gallery_images)
                    <div class="mt-4">
                        <p class="text-xs text-green-600 mb-2">پیش‌نمایش گالری:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($gallery_images as $image)
                            <img src="{{ $image->temporaryUrl() }}" class="w-20 h-20 object-cover border-2 border-green-500 rounded-lg shadow-sm">
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                <!-- ویدئوی محصول -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        {{ $isEdit ? 'جایگزینی ویدئوی محصول (اختیاری)' : 'ویدئوی محصول (اختیاری)' }}
                    </label>

                    <input type="file"
                           wire:model="product_video"
                           accept="video/mp4,video/webm,video/ogg,video/quicktime"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 dark:file:bg-gray-700 dark:file:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-md">

                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        فرمت‌های مجاز: mp4، webm، ogg، mov - حداکثر حجم: ۵۰ مگابایت
                    </p>

                    <div wire:loading wire:target="product_video" class="text-sm text-purple-600 mt-2">در حال آپلود ویدئو...</div>
                    @error('product_video') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if ($product_video)
                    <div class="mt-4">
                        <p class="text-xs text-green-600 mb-2">پیش‌نمایش ویدئوی جدید:</p>
                        <video controls class="w-full max-w-md rounded-lg border-2 border-green-500 bg-black shadow-sm">
                            <source src="{{ $product_video->temporaryUrl() }}">
                            مرورگر شما از پخش ویدئو پشتیبانی نمی‌کند.
                        </video>
                    </div>
                    @endif

                    {{-- ✅ فیلد آپلود تصویر پیش‌نمایش (Poster) --}}
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            <span class="flex items-center gap-2">
                                <x-heroicon-o-photo class="w-5 h-5" />
                                تصویر پیش‌نمایش ویدئو (Thumbnail)
                            </span>
                        </label>

                        <input type="file"
                               wire:model="video_poster"
                               accept="image/jpeg,image/jpg,image/png,image/webp"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-600 dark:file:text-gray-300 border border-gray-200 dark:border-gray-600 rounded-md">

                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            این تصویر در گالری محصول به عنوان پیش‌نمایش ویدئو نمایش داده می‌شود
                        </p>

                        <div wire:loading wire:target="video_poster" class="text-sm text-indigo-600 mt-2">در حال آپلود تصویر...</div>
                        @error('video_poster') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                        @if ($video_poster)
                        <div class="mt-3">
                            <p class="text-xs text-green-600 mb-2">پیش‌نمایش thumbnail:</p>
                            <img src="{{ $video_poster->temporaryUrl() }}" 
                                 class="w-32 h-24 object-cover rounded-lg border-2 border-green-500 shadow-sm"
                                 alt="Video Poster Preview">
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 border-t border-gray-200 dark:border-gray-700 pt-6">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition-colors flex items-center justify-center min-w-[150px]">
                <span wire:loading.remove wire:target="save, primary_image, gallery_images, product_video, video_poster">{{ $isEdit ? 'ذخیره تغییرات' : 'ایجاد محصول' }}</span>
                <span wire:loading wire:target="save, primary_image, gallery_images, product_video, video_poster">در حال پردازش...</span>
            </button>
            <a href="{{ route('admin.products.index') }}" wire:navigate class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2 px-4 transition-colors">انصراف</a>
        </div>

    </form>


</div>
