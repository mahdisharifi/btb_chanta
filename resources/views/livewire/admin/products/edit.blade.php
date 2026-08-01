<div
    @show-category-change-confirmation.window="
        if (confirm('تغییر دسته‌بندی تمام ویژگی‌های تخصصی وارد شده را حذف می‌کند. آیا مطمئن هستید؟')) {
            $wire.applyCategoryChange(event.detail.categoryId, event.detail.categoryName);
        }
    "
>
    <x-admin.subheader title="ویرایش محصول" :breadcrumbs="['مدیریت محصولات' => route('admin.products.index'), 'ویرایش محصول' => '#']" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">ویرایش محصول: {{ $product->title }}</h1>
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نامک (Slug) *</label>
                    <input type="text" wire:model="slug" dir="ltr" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm text-left" required>
                    @error('slug') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- انتخابگر هوشمند دسته‌بندی -->
                <div x-data="{ open: false }" class="relative">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">دسته‌بندی (فقط دسته‌های نهایی مجاز هستند) *</label>

                    <div @click="open = !open" class="w-full border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-lg shadow-sm text-sm px-3 py-2 cursor-pointer flex justify-between items-center bg-white dark:bg-gray-900">
                        <span class="block truncate">{{ $selectedCategoryName ?? 'یک دسته‌بندی انتخاب کنید...' }}</span>
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
                                دسته‌بندی مجازی با این نام یافت نشد.
                                @else
                                جهت جستجو، نام دسته‌بندی را تایپ کنید...
                                @endif
                            </li>
                            @endforelse
                        </ul>
                    </div>
                    @error('category_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">کد محصول (SKU)</label>
                    <input type="text" wire:model="sku" dir="ltr" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm text-left">
                    @error('sku') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">وضعیت محصول *</label>
                    <select wire:model="status" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm" required>
                        @foreach(\App\Enums\ProductStatus::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center mt-6">
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

        <!-- ... کدهای قبلی فیلدهای محصول و دسته‌بندی ... -->

        <!-- بخش ویژگی‌ها -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-6">
                @if(count($categoryAttributes) > 0)
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">ویژگی‌های دسته‌بندی</h3>
                        <button type="button" wire:click="refreshAttributes" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">
                            به‌روزرسانی مقادیر
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($categoryAttributes as $attribute)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $attribute->name }}</label>

                            <select wire:model="attributeValues.{{ $attribute->id }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">انتخاب کنید...</option>
                                @foreach($attribute->values as $value)
                                <option value="{{ $value->id }}">{{ $value->value }}</option>
                                @endforeach
                            </select>

                            <!-- افزودن مقدار جدید -->
                            <div class="mt-2 flex gap-2">
                                <input type="text" wire:model="newAttributeValues.{{ $attribute->id }}" placeholder="مقدار جدید..." class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button" wire:click="saveNewAttributeValue({{ $attribute->id }})" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">افزودن</button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- بخش هشدار ویژگی‌های از دست رفته -->
            <div class="md:col-span-1">
                @if(count($lostAttributes) > 0)
                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-200 dark:border-red-800/30">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="mr-3">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-300">
                                ویژگی‌هایی که پس از ذخیره حذف خواهند شد
                            </h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-400">
                                <p>این مقادیر در دسته‌بندی جدید وجود ندارند:</p>
                                <ul class="list-disc pl-5 pr-5 mt-1 space-y-1">
                                    @foreach($lostAttributes as $lost)
                                    <li><strong>{{ $lost['name'] }}:</strong> {{ $lost['value'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- ... ادامه کدهای آپلود تصویر و دکمه ذخیره ... -->

        <!-- تصاویر محصول -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2">تصاویر محصول</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- تصویر اصلی -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">تغییر تصویر اصلی</label>
                    <input type="file" wire:model="new_primary_image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-700 dark:file:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-md">
                    <div wire:loading wire:target="new_primary_image" class="text-sm text-indigo-600 mt-2">در حال آپلود...</div>
                    @error('new_primary_image') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if ($new_primary_image)
                    <div class="mt-4">
                        <p class="text-xs text-green-600 mb-2">تصویر اصلی جدید:</p>
                        <img src="{{ $new_primary_image->temporaryUrl() }}" class="w-32 h-32 object-cover border-2 border-green-500 rounded-lg shadow-sm">
                    </div>
                    @else
                    @php
                    $primaryImage = $existingGallery->where('is_primary', true)->first();
                    @endphp
                    @if($primaryImage)
                    <div class="mt-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">تصویر اصلی فعلی:</p>
                        <img src="{{ Storage::url($primaryImage->file_path) }}" class="w-32 h-32 object-cover border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm">
                    </div>
                    @endif
                    @endif
                </div>

                <!-- گالری تصاویر -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">افزودن تصاویر گالری جدید</label>
                    <input type="file" wire:model="new_gallery_images" multiple accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-700 dark:file:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-md">
                    <div wire:loading wire:target="new_gallery_images" class="text-sm text-indigo-600 mt-2">در حال آپلود...</div>
                    @error('new_gallery_images.*') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if ($new_gallery_images)
                    <div class="mt-4">
                        <p class="text-xs text-green-600 mb-2">گالری جدید (ذخیره نشده):</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($new_gallery_images as $image)
                            <img src="{{ $image->temporaryUrl() }}" class="w-20 h-20 object-cover border-2 border-green-500 rounded-lg shadow-sm">
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- مدیریت گالری موجود -->
            @if(count($existingGallery) > 0)
            <div class="mt-8 border-t dark:border-gray-700 pt-6">
                <h3 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-4">مدیریت تصاویر گالری موجود</h3>
                <div class="flex flex-wrap gap-4">
                    @foreach($existingGallery as $image)
                    @if(!$image->is_primary)
                    <div class="relative group">
                        <img src="{{ Storage::url($image->file_path) }}" class="w-24 h-24 object-cover border dark:border-gray-600 rounded-lg shadow-sm">
                        <button type="button" wire:click="removeGalleryImage({{ $image->id }})" wire:confirm="آیا از حذف این تصویر مطمئن هستید؟" class="absolute top-1 right-1 bg-red-600 text-white rounded-md p-1.5 opacity-0 group-hover:opacity-100 transition-opacity" title="حذف تصویر">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    @endif
                    @endforeach

                    @if($existingGallery->where('is_primary', false)->count() === 0)
                    <p class="text-sm text-gray-500 dark:text-gray-400">عکسی در گالری (غیر از تصویر اصلی) وجود ندارد.</p>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <div class="flex items-center gap-4 border-t border-gray-200 dark:border-gray-700 pt-6">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition-colors flex items-center justify-center min-w-[150px]">
                <span wire:loading.remove wire:target="save, new_primary_image, new_gallery_images">بروزرسانی محصول</span>
                <span wire:loading wire:target="save, new_primary_image, new_gallery_images">در حال پردازش...</span>
            </button>
            <a href="{{ route('admin.products.index') }}" wire:navigate class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2 px-4 transition-colors">انصراف</a>
        </div>
    </form>
</div>
