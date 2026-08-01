<div>
    <x-admin.subheader title="ویرایش دسته‌بندی: {{ $category->name }}" />

    <div class="bg-white rounded-lg shadow p-6">
        <form wire:submit.prevent="update" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <x-input-label for="name" value="نام دسته‌بندی" />
                    <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <!-- Parent Category -->
                <div>
                    <x-input-label for="parent_id" value="دسته‌بندی والد" />
                    <select id="parent_id" wire:model="parent_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">بدون والد (دسته اصلی)</option>
                        @foreach($parentCategories as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
                </div>

                <!-- Sort Order -->
                <div>
                    <x-input-label for="sort_order" value="ترتیب نمایش" />
                    <x-text-input id="sort_order" type="number" class="mt-1 block w-full" wire:model="sort_order" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>

                <!-- Icon -->
                <div>
                    <x-input-label for="icon" value="آیکون (اختیاری)" />
                    <x-text-input id="icon" type="text" class="mt-1 block w-full" wire:model="icon" dir="ltr" />
                    <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                </div>

                <!-- Status -->
                <div class="md:col-span-2 flex items-center mt-4">
                    <label for="is_active" class="inline-flex items-center cursor-pointer">
                        <input id="is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" wire:model="is_active">
                        <span class="mr-2 text-sm text-gray-600">دسته‌بندی فعال باشد</span>
                    </label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <x-input-label for="description" value="توضیحات" />
                    <textarea id="description" wire:model="description" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>
            </div>

            <hr class="my-6 border-gray-200">

            <!-- بخش تخصیص ویژگی‌ها -->
            <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">ویژگی‌های اختصاصی دسته‌بندی</h3>

                @if($isLeafNode)
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden flex flex-col">

                    <!-- نمایش خطای احتمالی هنگام حذف -->
                    @error('attribute_remove')
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 m-4 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-heroicon-o-x-circle class="h-5 w-5 text-red-500" />
                            </div>
                            <div class="mr-3">
                                <p class="text-sm text-red-700">{{ $message }}</p>
                            </div>
                        </div>
                    </div>
                    @enderror

                    <!-- بخش ویژگی‌های انتخاب شده -->
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <p class="text-sm font-medium text-gray-700 mb-3">ویژگی‌های انتخاب شده:</p>
                        @php
                        // مپ کردن روی آرایه انتخاب‌شده‌ها برای حفظ ترتیب دقیق انتخاب
                        $selectedAttrModels = collect($selectedAttributes)
                        ->map(fn($id) => $allAttributes->firstWhere('id', $id))
                        ->filter();
                        @endphp


                        @if($selectedAttrModels->count() > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedAttrModels as $attribute)
                            <span class="inline-flex items-center pl-2 pr-3 py-1.5 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800 border border-indigo-200 shadow-sm transition-all">
                                <span>{{ $attribute->name }}</span>

                                <!-- نمایش نوع ویژگی -->
                                <span class="mr-1 text-[10px] text-indigo-600 bg-white px-1.5 py-0.5 rounded-full border border-indigo-200 shadow-sm">
                                    {{ method_exists($attribute->type, 'label') ? $attribute->type->label() : ($attribute->type->value ?? $attribute->type) }}
                                </span>

                                @if($attribute->unit)
                                    <span class="mr-1 text-xs text-indigo-500">({{ $attribute->unit }})</span>
                                @endif

                                <!-- نمایش تعداد محصولات متصل -->
                                @php $count = $attributeProductCounts[$attribute->id] ?? 0; @endphp
                                @if($count > 0)
                                    <a href="{{ route('admin.products.index', ['attribute_id' => $attribute->id]) }}"
                                       title="مشاهده محصولات"
                                       class="mr-3 ml-1 inline-flex items-center justify-center px-2 py-0.5 text-[11px] font-bold leading-none text-white bg-indigo-500 rounded-full hover:bg-indigo-600 transition-colors">
                                        {{ $count }} محصول
                                    </a>
                                @else
                                    <span class="mr-3 ml-1 text-[11px] text-indigo-400">بدون محصول</span>
                                @endif

                                <!-- دکمه حذف -->
                                <button type="button" wire:click="removeAttribute({{ $attribute->id }})" wire:loading.attr="disabled" class="mr-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-indigo-500 hover:text-red-600 hover:bg-red-100 focus:outline-none transition-colors">
                                    <x-heroicon-m-x-mark class="w-4 h-4" />
                                </button>
                            </span>
                            @endforeach
                        </div>
                        @else
                        <p class="text-sm text-gray-500 italic">هیچ ویژگی انتخاب نشده است.</p>
                        @endif
                    </div>

                    <!-- بخش جستجو و انتخاب ویژگی‌های جدید -->
                    <div x-data="{ search: '' }" class="p-4 bg-white">
                        <div class="flex flex-col sm:flex-row justify-between gap-4 mb-4">
                            <p class="text-sm text-gray-600 self-center">برای افزودن ویژگی روی آن کلیک کنید.</p>

                            <div class="relative w-full sm:w-72">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                </div>
                                <input x-model="search" type="text" class="block w-full rounded-md border-0 py-1.5 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="جستجوی ویژگی...">
                            </div>
                        </div>

                        @php
                        $unselectedAttrModels = $allAttributes->whereNotIn('id', $selectedAttributes);
                        @endphp

                        @if($unselectedAttrModels->count() > 0)
                        <div class="max-h-60 overflow-y-auto pr-2">
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                @foreach($unselectedAttrModels as $attribute)
                                <button
                                    type="button"
                                    wire:click="addAttribute({{ $attribute->id }})"
                                    x-show="search === '' || '{{ $attribute->name }}'.includes(search)"
                                    class="flex flex-col items-start justify-between p-3 border border-gray-200 rounded-lg hover:bg-indigo-50 hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition text-right w-full min-h-[70px]"
                                >
                                    <div class="flex justify-between items-start w-full">
                                        <span class="text-sm font-medium text-gray-800">{{ $attribute->name }}</span>
                                        <span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200 mr-2">
                                            {{ method_exists($attribute->type, 'label') ? $attribute->type->label() : ($attribute->type->value ?? $attribute->type) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between items-end w-full mt-2">
                                        <span class="text-xs text-gray-500">{{ $attribute->unit ? '('.$attribute->unit.')' : '' }}</span>

                                        @php $count = $attributeProductCounts[$attribute->id] ?? 0; @endphp
                                        <span class="text-[11px] px-1.5 py-0.5 rounded {{ $count > 0 ? 'text-indigo-600 bg-indigo-100 font-medium' : 'text-gray-400 bg-gray-50' }}">
                                            {{ $count > 0 ? $count . ' محصول' : 'بدون محصول' }}
                                        </span>
                                    </div>
                                </button>
                                @endforeach
                            </div>

                            <!-- حالت پیدا نشدن نتیجه در جستجو -->
                            <div x-show="search !== '' && !Array.from($el.previousElementSibling.children).some(el => el.style.display !== 'none')" class="text-center py-6" style="display: none;">
                                <p class="text-sm text-gray-500">ویژگی مورد نظر یافت نشد.</p>
                            </div>
                        </div>
                        @else
                        <div class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                            <p class="text-sm text-gray-500">تمامی ویژگی‌های موجود انتخاب شده‌اند.</p>
                        </div>
                        @endif
                    </div>
                </div>
                @else
                <div class="bg-yellow-50 border-r-4 border-yellow-400 p-4 rounded-md">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-yellow-500" />
                        </div>
                        <div class="mr-3">
                            <p class="text-sm text-yellow-700">
                                تخصیص ویژگی تنها برای <strong>دسته‌بندی‌های نهایی</strong> (بدون زیردسته) امکان‌پذیر است.
                            </p>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <hr class="my-6 border-gray-200">

            <!-- SEO Section -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">تنظیمات سئو (SEO)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="seo_title" value="عنوان سئو" />
                        <x-text-input id="seo_title" type="text" class="mt-1 block w-full" wire:model="seo_title" />
                        <x-input-error :messages="$errors->get('seo_title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="seo_keywords" value="کلمات کلیدی (با کاما جدا کنید)" />
                        <x-text-input id="seo_keywords" type="text" class="mt-1 block w-full" wire:model="seo_keywords" />
                        <x-input-error :messages="$errors->get('seo_keywords')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="seo_description" value="توضیحات سئو (Meta Description)" />
                        <textarea id="seo_description" wire:model="seo_description" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('seo_description')" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end mt-6 gap-4">
                <a href="{{ route('admin.categories.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                    انصراف
                </a>
                <x-primary-button>
                    ذخیره تغییرات
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
