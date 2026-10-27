<div class="container mx-auto px-4 py-6 max-w-7xl">
    {{-- ═══════════════════════════════════════════════════════════
         HEADER
         ═══════════════════════════════════════════════════════════ --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <x-heroicon-o-photo class="w-7 h-7 text-indigo-600" />
                مدیریت اسلایدر اصلی
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                مدیریت اسلایدرهای صفحه اصلی سایت
            </p>
        </div>
        <button wire:click="create"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            <x-heroicon-o-plus class="w-5 h-5" />
            اسلایدر جدید
        </button>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         SLIDER LIST
         ═══════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if($sliders->isEmpty())
            <div class="p-12 text-center">
                <x-heroicon-o-photo class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-700 mb-2">هیچ اسلایدری وجود ندارد</h3>
                <p class="text-sm text-gray-500 mb-4">برای شروع یک اسلایدر جدید ایجاد کنید</p>
                <button wire:click="create"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    <x-heroicon-o-plus class="w-5 h-5" />
                    اسلایدر جدید
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">ترتیب</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">پیش‌نمایش</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">عنوان</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">لینک</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">وضعیت</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($sliders as $slider)
                            <tr class="hover:bg-gray-50 transition">
                                {{-- ترتیب --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <span class="text-sm font-medium text-gray-700">{{ $slider->sort_order }}</span>
                                        <div class="flex flex-col gap-0.5">
                                            @if(!$loop->first)
                                                <button wire:click="updateOrder({{ $slider->id }}, 'up')"
                                                        class="p-0.5 text-gray-400 hover:text-indigo-600 transition">
                                                    <x-heroicon-s-chevron-up class="w-3 h-3" />
                                                </button>
                                            @endif
                                            @if(!$loop->last)
                                                <button wire:click="updateOrder({{ $slider->id }}, 'down')"
                                                        class="p-0.5 text-gray-400 hover:text-indigo-600 transition">
                                                    <x-heroicon-s-chevron-down class="w-3 h-3" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- پیش‌نمایش --}}
                                <td class="px-4 py-3">
                                    <div class="relative w-24 h-12 rounded-lg overflow-hidden"
                                         style="{{ $slider->bg_gradient ?: 'background-color: ' . $slider->bg_color }}">
                                        @if($slider->image_path)
                                            <img src="{{ $slider->image_url }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                            <div class="absolute inset-0 bg-black/30"></div>
                                        @endif
                                        <div class="w-full h-full flex items-center justify-center text-[10px] font-bold px-2"
                                             style="color: {{ $slider->text_color }}">
                                            {{ Str::limit($slider->title, 20) }}
                                        </div>
                                    </div>
                                </td>

                                {{-- عنوان --}}
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 line-clamp-1">{{ $slider->title }}</p>
                                        @if($slider->subtitle)
                                            <p class="text-xs text-gray-500 line-clamp-1 mt-0.5">{{ $slider->subtitle }}</p>
                                        @endif
                                    </div>
                                </td>

                                {{-- لینک --}}
                                <td class="px-4 py-3">
                                    @if($slider->link_url)
                                        <a href="{{ $slider->link_url }}" target="_blank" rel="noopener noreferrer"
                                           class="text-xs text-indigo-600 hover:text-indigo-700 line-clamp-1">
                                            {{ $slider->link_url }}
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- وضعیت --}}
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="toggleActive({{ $slider->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition
                                                   {{ $slider->is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                        @if($slider->is_active)
                                            <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                                            فعال
                                        @else
                                            <x-heroicon-s-x-circle class="w-3.5 h-3.5" />
                                            غیرفعال
                                        @endif
                                    </button>
                                </td>

                                {{-- عملیات --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button wire:click="edit({{ $slider->id }})"
                                                class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                                title="ویرایش">
                                            <x-heroicon-o-pencil class="w-4 h-4" />
                                        </button>
                                        <button wire:click="confirmDelete({{ $slider->id }})"
                                                class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition"
                                                title="حذف">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $sliders->links() }}
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         CREATE/EDIT MODAL
         ═══════════════════════════════════════════════════════════ --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                {{-- Backdrop --}}
                <div x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"
                     @click="$wire.closeModal()"></div>

                {{-- Modal --}}
                <div x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block w-full max-w-3xl my-8 overflow-hidden text-right align-middle transition-all transform bg-white shadow-xl rounded-2xl">

                    <form wire:submit.prevent="save">
                        {{-- Header --}}
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-bold text-gray-800">
                                {{ $sliderId ? 'ویرایش اسلایدر' : 'اسلایدر جدید' }}
                            </h3>
                        </div>

                        {{-- Body --}}
                        <div class="px-6 py-4 max-h-[70vh] overflow-y-auto space-y-4">
                            {{-- عنوان --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    عنوان <span class="text-red-500">*</span>
                                </label>
                                <input type="text" wire:model="title"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            {{-- زیرعنوان --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">زیرعنوان</label>
                                <textarea wire:model="subtitle" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                                @error('subtitle') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            {{-- توضیحات --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                                <textarea wire:model="description" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                                @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="rounded-xl border border-gray-200 p-4 space-y-3">
                                    <label for="slider-desktop-image" class="block text-sm font-medium text-gray-700">تصویر اسلایدر دسکتاپ</label>
                                    <input id="slider-desktop-image" type="file" wire:model="desktopUpload" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600">
                                    <p class="text-xs text-gray-500">JPG، PNG یا WebP؛ حداکثر ۶ مگابایت و ۶۰۰۰×۴۰۰۰ پیکسل.</p>
                                    @error('desktopUpload') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    @if($desktopUpload)
                                        <img src="{{ $desktopUpload->temporaryUrl() }}" alt="پیش‌نمایش تصویر دسکتاپ" class="h-28 w-full rounded-lg object-cover">
                                    @elseif($existingImagePath && ! $removeDesktopImage)
                                        <img src="{{ asset('storage/' . $existingImagePath) }}" alt="تصویر فعلی اسلایدر دسکتاپ" class="h-28 w-full rounded-lg object-cover">
                                    @endif
                                    @if($existingImagePath && ! $desktopUpload)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" wire:model.live="removeDesktopImage" class="rounded border-gray-300">
                                            <span>حذف تصویر دسکتاپ (اسلاید بدون تصویر می‌شود)</span>
                                        </label>
                                    @endif
                                </div>

                                <div class="rounded-xl border border-gray-200 p-4 space-y-3">
                                    <label for="slider-mobile-image" class="block text-sm font-medium text-gray-700">تصویر جایگزین موبایل (اختیاری)</label>
                                    <input id="slider-mobile-image" type="file" wire:model="mobileUpload" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600">
                                    <p class="text-xs text-gray-500">اگر انتخاب نشود، تصویر دسکتاپ در موبایل هم استفاده می‌شود.</p>
                                    @error('mobileUpload') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    @if($mobileUpload)
                                        <img src="{{ $mobileUpload->temporaryUrl() }}" alt="پیش‌نمایش تصویر موبایل" class="h-28 w-full rounded-lg object-cover">
                                    @elseif($existingMobileImagePath && ! $removeMobileImage)
                                        <img src="{{ asset('storage/' . $existingMobileImagePath) }}" alt="تصویر فعلی اسلایدر موبایل" class="h-28 w-full rounded-lg object-cover">
                                    @endif
                                    @if($existingMobileImagePath)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" wire:model="removeMobileImage" class="rounded border-gray-300">
                                            <span>حذف تصویر موبایل فعلی</span>
                                        </label>
                                    @endif
                                </div>
                            </div>

                            {{-- لینک --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس لینک</label>
                                    <input type="text" wire:model="link_url" placeholder="/products یا https://example.com/page"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    <p class="mt-1 text-xs text-gray-500">برای صفحات همین سایت از مسیرهایی مثل ‎/products‎ و برای سایت‌های دیگر از آدرس http/https استفاده کنید.</p>
                                    @error('link_url') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">متن دکمه</label>
                                    <input type="text" wire:model="link_text" placeholder="مشاهده بیشتر"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    @error('link_text') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- گرادینت (با نمونه‌های پیشنهادی) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">گرادینت پس‌زمینه</label>
                                <input type="text" wire:model="bg_gradient"
                                       placeholder="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm font-mono">
                                @error('bg_gradient') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                
                                <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <button type="button" @click="$wire.bg_gradient = 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);'"
                                            class="h-10 rounded-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></button>
                                    <button type="button" @click="$wire.bg_gradient = 'background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);'"
                                            class="h-10 rounded-lg" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);"></button>
                                    <button type="button" @click="$wire.bg_gradient = 'background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);'"
                                            class="h-10 rounded-lg" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);"></button>
                                    <button type="button" @click="$wire.bg_gradient = 'background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);'"
                                            class="h-10 rounded-lg" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);"></button>
                                </div>
                            </div>

                            {{-- رنگ متن و موقعیت --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">رنگ متن</label>
                                    <div class="flex gap-2">
                                        <input type="color" wire:model="text_color" class="h-10 w-16 rounded border border-gray-300">
                                        <input type="text" wire:model="text_color" placeholder="#ffffff"
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent font-mono text-sm">
                                    </div>
                                    @error('text_color') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">موقعیت متن</label>
                                    <select wire:model="text_position"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                        <option value="left">چپ</option>
                                        <option value="center">وسط</option>
                                        <option value="right">راست</option>
                                    </select>
                                    @error('text_position') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- ترتیب و وضعیت --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ترتیب نمایش</label>
                                    <input type="number" wire:model="sort_order" min="0" max="999"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    @error('sort_order') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت</label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="is_active"
                                               class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-2 focus:ring-indigo-500">
                                        <span class="text-sm text-gray-700">فعال</span>
                                    </label>
                                </div>
                            </div>

                            {{-- تاریخ شروع و پایان --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ شروع (اختیاری)</label>
                                    <input type="date" wire:model="starts_at"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    @error('starts_at') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ پایان (اختیاری)</label>
                                    <input type="date" wire:model="ends_at"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    @error('ends_at') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-end gap-3">
                            <button type="button" wire:click="closeModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                انصراف
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                                {{ $sliderId ? 'بروزرسانی' : 'ایجاد' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         DELETE CONFIRMATION MODAL
         ═══════════════════════════════════════════════════════════ --}}
    @if($deleteConfirm)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('deleteConfirm') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div x-show="show" class="fixed inset-0 bg-gray-900 bg-opacity-75" @click="$wire.cancelDelete()"></div>
                <div x-show="show" class="inline-block w-full max-w-md p-6 my-8 text-right align-middle bg-white shadow-xl rounded-2xl">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-600" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">حذف اسلایدر</h3>
                            <p class="text-sm text-gray-500">آیا مطمئن هستید؟</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button wire:click="cancelDelete"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            انصراف
                        </button>
                        <button wire:click="delete"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                            حذف
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════════
     TOAST NOTIFICATIONS
     ═══════════════════════════════════════════════════════════ --}}
@script
<script>
    $wire.on('slider-saved', (event) => {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { message: event.message, type: 'success' }
        }));
    });

    $wire.on('slider-deleted', (event) => {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { message: event.message, type: 'success' }
        }));
    });

    $wire.on('slider-toggled', (event) => {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { message: event.message, type: 'success' }
        }));
    });

    $wire.on('slider-reordered', (event) => {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { message: event.message, type: 'success' }
        }));
    });

    $wire.on('slider-error', (event) => {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { message: event.message, type: 'error' }
        }));
    });
</script>
@endscript
