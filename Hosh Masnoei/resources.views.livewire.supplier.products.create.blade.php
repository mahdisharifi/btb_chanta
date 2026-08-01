<div>
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">ایجاد محصول جدید</h1>
            <p class="mt-1 text-sm text-yellow-600 dark:text-yellow-500">توجه: محصول جدید شما پس از ثبت، مستقیما به عنوان <strong>پیش‌نویس</strong> ذخیره خواهد شد.</p>
        </div>
        <a href="{{ route('supplier.products.index') }}" wire:navigate class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            &larr; بازگشت به محصولات
        </a>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">

        <!-- اطلاعات پایه -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2">اطلاعات اصلی</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- عنوان محصول -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">عنوان محصول *</label>
                    <input type="text" wire:model="title" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm" required>
                    @error('title') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- انتخابگر هوشمند دسته‌بندی -->
                <div x-data="{ open: false }" class="relative">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">دسته‌بندی (فقط دسته‌های نهایی) *</label>

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

        <!-- بخش ویژگی‌ها -->
        @if($category_id)
        <div class="mt-6">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">ویژگی‌های دسته‌بندی</h3>
                    <button type="button" wire:click="refreshAttributes" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">
                        به‌روزرسانی مقادیر
                    </button>
                </div>

                @if(count($categoryAttributes) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
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
                @else
                <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                    هیچ ویژگی برای این دسته‌بندی تعریف نشده است.
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- تصاویر محصول -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2">تصاویر محصول</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- تصویر اصلی -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">تصویر اصلی محصول</label>
                    <input type="file" wire:model="primary_image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-700 dark:file:text-gray-300 border border-gray-200 dark:border-gray-700 rounded-md">
                    <div wire:loading wire:target="primary_image" class="text-sm text-indigo-600 mt-2">در حال آپلود...</div>
                    @error('primary_image') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if ($primary_image)
                    <div class="mt-4">
                        <p class="text-xs text-green-600 mb-2">پیش‌نمایش تصویر اصلی:</p>
                        <img src="{{ $primary_image->temporaryUrl() }}" class="w-32 h-32 object-cover border-2 border-green-500 rounded-lg shadow-sm">
                    </div>
                    @endif
                </div>

                <!-- گالری تصاویر -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">گالری تصاویر (انتخاب چندتایی)</label>
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
            </div>
        </div>

        <div class="flex items-center gap-4 border-t border-gray-200 dark:border-gray-700 pt-6">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-8 rounded-lg shadow-sm transition-colors flex items-center justify-center min-w-[150px]">
                <span wire:loading.remove wire:target="save, primary_image, gallery_images">ذخیره به عنوان پیش‌نویس</span>
                <span wire:loading wire:target="save, primary_image, gallery_images">در حال پردازش...</span>
            </button>
            <a href="{{ route('supplier.products.index') }}" wire:navigate class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2 px-4 transition-colors">انصراف</a>
        </div>
    </form>
</div>
