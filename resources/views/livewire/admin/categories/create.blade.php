{{-- resources/views/livewire/admin/categories/create.blade.php --}}
<div class="max-w-4xl mx-auto py-4 sm:py-6 space-y-4 sm:space-y-6 px-3 sm:px-0">
    <!-- هدر فرم با برندینگ مشکی/طلایی -->
    <div class="flex items-start sm:items-center justify-between gap-3 flex-col sm:flex-row">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-black flex items-center justify-center shrink-0 shadow-lg shadow-black/10 border border-amber-500/20">
                <x-heroicon-o-squares-plus class="w-5 h-5 sm:w-6 sm:h-6 text-amber-500" />
            </div>

            <div>
                <h1 class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900">
                    {{ $isEditMode ? 'ویرایش دسته‌بندی' : 'ایجاد دسته‌بندی جدید' }}
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 mt-1">
                    {{ $isEditMode ? 'اطلاعات دسته‌بندی را ویرایش کنید' : 'اطلاعات دسته‌بندی محصولات را وارد کنید' }}
                </p>
            </div>
        </div>

        <a href="{{ route('admin.categories.index') }}"
           wire:navigate
           class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-black transition self-end sm:self-auto group">
            <x-heroicon-o-arrow-uturn-right class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
            <span>بازگشت</span>
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- بخش اطلاعات عمومی -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-black" />
                <h2 class="font-semibold text-gray-900">اطلاعات عمومی</h2>
            </div>

            <div class="p-4 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        نام دسته‌بندی <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           wire:model="name"
                           placeholder="مثلا: لوازم برقی"
                           class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20 focus:ring-2 transition-all duration-200" />
                    @error('name')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        نامک (Slug)
                    </label>
                    <p class="text-xs text-gray-500 mb-2">در صورت خالی بودن خودکار ساخته می‌شود</p>
                    <input type="text"
                           wire:model="slug"
                           dir="ltr"
                           placeholder="electric-appliances"
                           class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20 focus:ring-2 transition-all duration-200" />
                    @error('slug')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        دسته‌بندی والد
                    </label>
                    <select wire:model.live="parent_id"
                            class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20 focus:ring-2 transition-all duration-200">
                        <option value="">— بدون والد (دسته اصلی) —</option>
                        @foreach($parentCategories as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        ترتیب نمایش <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           min="0"
                           wire:model="sort_order"
                           class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20 focus:ring-2 transition-all duration-200" />
                    @error('sort_order')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        توضیحات
                    </label>
                    <textarea wire:model="description"
                              rows="3"
                              placeholder="توضیح کوتاهی درباره این دسته‌بندی..."
                              class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20 focus:ring-2 transition-all duration-200"></textarea>
                    @error('description')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-black focus:ring-black/20">
                        <span class="text-sm font-medium text-gray-700 group-hover:text-black transition-colors">این دسته‌بندی فعال باشد</span>
                    </label>
                    @error('is_active')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- بخش آیکون دسته‌بندی -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                <x-heroicon-o-swatch class="w-5 h-5 text-black" />
                <h2 class="font-semibold text-gray-900">آیکون دسته‌بندی</h2>
            </div>

            <div class="p-4 sm:p-6">
                <div x-data="{ openIcons: false, q: '' }" class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            @if($icon)
                            <div class="w-12 h-12 rounded-2xl bg-black/5 flex items-center justify-center border border-amber-500/10">
                                @php
                                $allowedIcons = collect($icons ?? [])->map(fn ($name) => 'heroicon-o-' . $name)->all();
                                $resolvedIcon = in_array($icon, $allowedIcons, true) ? $icon : 'heroicon-o-squares-2x2';
                                @endphp
                                <x-dynamic-component :component="$resolvedIcon" class="w-5 h-5 text-black"/>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">آیکون انتخاب شده</div>
                                <div class="text-xs text-gray-500" dir="ltr">{{ $icon }}</div>
                            </div>
                            @else
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center">
                                <x-heroicon-o-swatch class="w-6 h-6 text-gray-400" />
                            </div>
                            <div class="text-sm text-gray-500">آیکونی انتخاب نشده</div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            @if($icon)
                            <button type="button"
                                    wire:click="clearIcon"
                                    class="px-3 py-2 text-sm rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition">
                                حذف
                            </button>
                            @endif

                            <button type="button"
                                    @click="openIcons = !openIcons"
                                    class="px-3 py-2 text-sm rounded-xl bg-black text-white hover:bg-gray-800 transition">
                                <span x-show="!openIcons">انتخاب آیکون</span>
                                <span x-show="openIcons">بستن لیست</span>
                            </button>
                        </div>
                    </div>

                    <div x-show="openIcons" x-cloak class="space-y-4" x-transition>
                        <input type="text"
                               x-model="q"
                               placeholder="جستجوی نام آیکون... (مثلا: cube)"
                               class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20 focus:ring-2" />

                        <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2 max-h-64 overflow-y-auto p-1 scrollbar-thin scrollbar-thumb-gray-200">
                            @foreach($icons as $iconName)
                            <button type="button"
                                    wire:click="selectIcon('{{ $iconName }}')"
                                    x-show="q === '' || '{{ $iconName }}'.toLowerCase().includes(q.toLowerCase())"
                                    class="p-3 rounded-xl border transition-all duration-150
                                           {{ $icon === 'heroicon-o-'.$iconName ? 'border-amber-500 bg-black text-amber-500 shadow-md' : 'border-gray-200 hover:border-black hover:bg-gray-50 text-gray-600 hover:text-black' }}">
                                <x-dynamic-component :component="'heroicon-o-'.$iconName" class="w-5 h-5 mx-auto" />
                            </button>
                            @endforeach
                        </div>

                        @error('icon')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- بخش تصویر دسته‌بندی -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                <x-heroicon-o-photo class="w-5 h-5 text-black" />
                <h2 class="font-semibold text-gray-900">تصویر دسته‌بندی</h2>
            </div>

            <div class="p-4 sm:p-6">
                @if($image)
                <div class="space-y-3">
                    <img src="{{ $image->temporaryUrl() }}" class="w-full max-w-sm rounded-2xl object-cover border border-gray-200 shadow-sm" alt="preview">
                    <button type="button"
                            wire:click="removeImage"
                            class="px-3 py-2 text-sm rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition">
                        حذف تصویر جدید
                    </button>
                </div>
                @elseif($existingImagePath)
                <div class="space-y-3">
                    <img src="{{ asset('storage/' . $existingImagePath) }}" class="w-full max-w-sm rounded-2xl object-cover border border-gray-200 shadow-sm" alt="existing-image">
                    <button type="button"
                            wire:click="removeImage"
                            class="px-3 py-2 text-sm rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition">
                        حذف تصویر فعلی
                    </button>
                </div>
                @else
                <label class="block border-2 border-dashed border-gray-300 rounded-2xl p-6 text-center cursor-pointer hover:border-black/40 hover:bg-gray-50/50 transition duration-200">
                    <input type="file" wire:model="image" accept="image/*" class="hidden">
                    <div class="space-y-2">
                        <div class="flex justify-center">
                            <x-heroicon-o-cloud-arrow-up class="w-10 h-10 text-gray-400" />
                        </div>
                        <div class="text-sm font-medium text-gray-700">برای آپلود کلیک کنید</div>
                        <div class="text-xs text-gray-500">JPG, PNG, WebP — حداکثر ۲ مگابایت</div>
                    </div>
                </label>
                @endif

                <div wire:loading wire:target="image" class="mt-3 text-sm text-gray-500 flex items-center gap-2">
                    <div class="animate-spin rounded-full h-4 w-4 border-2 border-gray-500 border-t-transparent"></div>
                    <span>در حال آپلود و پردازش تصویر...</span>
                </div>

                @error('image')
                <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- بخش ویژگی‌های دسته‌بندی (فقط برای برگ‌ها) -->
        @if($isLeafNode)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-black" />
                <h2 class="font-semibold text-gray-900">ویژگی‌های محصولات این دسته‌بندی</h2>
            </div>

            <div class="p-4 sm:p-6 space-y-6">
                <!-- بخش اول: ویژگی‌های انتخاب شده فعلی (رفع عیب بزرگ UX پروژه) -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        ویژگی‌های انتخاب شده
                    </h3>

                    @php
                        $selectedAttrModels = $allAttributes
                            ->whereIn('id', $selectedAttributes)
                            ->sortBy(fn ($attribute) => (int) data_get($attributeSettings, $attribute->id.'.display_order', 0))
                            ->values();
                    @endphp

                    <div class="flex flex-wrap gap-2 min-h-[45px] p-3 rounded-xl bg-gray-50 border border-gray-100">
                        {{-- در بخش ویژگی‌های انتخاب شده --}}
                        @forelse($selectedAttrModels as $selectedAttr)
                        @php
                        $hasProducts = ($attributeProductCounts[$selectedAttr->id] ?? 0) > 0;
                        @endphp
                        <div class="flex flex-wrap gap-2 bg-black text-white text-xs px-3 py-2 rounded-lg border border-amber-500/20 {{ $hasProducts ? 'opacity-70' : '' }}">
                            <span class="w-full font-medium">{{ $selectedAttr->name }}</span>
                            <label class="flex items-center gap-1 text-[11px] text-gray-200">
                                ترتیب
                                <input type="number" min="0" max="99999" onwheel="this.blur()" wire:model.live="attributeSettings.{{ $selectedAttr->id }}.display_order" class="w-16 rounded border-gray-500 bg-gray-800 px-1 py-0.5 text-center text-white">
                            </label>
                            <label class="flex items-center gap-1 text-[11px] text-gray-200">
                                <input type="checkbox" wire:model.live="attributeSettings.{{ $selectedAttr->id }}.is_required" class="rounded border-gray-400 text-amber-500 focus:ring-amber-400">
                                اجباری
                            </label>

                            @if($hasProducts)
                            <span class="text-[10px] bg-amber-500/20 px-1 rounded">{{ $attributeProductCounts[$selectedAttr->id] }}</span>
                            <x-heroicon-o-lock-closed class="w-3.5 h-3.5 text-amber-500" title="دارای محصول" />
                            @else
                            <button type="button" wire:click="removeAttribute({{ $selectedAttr->id }})" class="mr-auto text-gray-400 hover:text-red-400 transition-colors">
                                <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                            </button>
                            @endif
                        </div>
                        @empty
                        <span class="text-xs text-gray-400 my-auto">هیچ ویژگی انتخاب نشده است.</span>
                        @endforelse

                    </div>
                </div>

                <!-- بخش دوم: ابزار جستجو و انتخاب ویژگی جدید -->
                <div x-data="{ search: '' }" class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        افزودن ویژگی جدید
                    </h3>

                    <div class="relative">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                        </div>
                        <input type="text"
                               x-model="search"
                               placeholder="جستجوی در میان ویژگی‌ها..."
                               class="w-full pr-10 rounded-xl border-gray-200 focus:border-black focus:ring-black/20 focus:ring-2 text-sm transition-all duration-200">
                    </div>

                    @php
                    $unselectedAttrModels = $allAttributes->whereNotIn('id', $selectedAttributes);
                    @endphp

                    <!-- لیست ویژگی‌های در دسترس -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[220px] overflow-y-auto p-2 border border-gray-100 rounded-xl bg-gray-50/30 scrollbar-thin scrollbar-thumb-gray-200">
                        @forelse($unselectedAttrModels as $attribute)
                        <button type="button"
                                wire:click="addAttribute({{ $attribute->id }})"
                                x-show="search === '' || @js($attribute->name).toLowerCase().includes(search.toLowerCase())"
                                class="group rounded-xl border border-gray-200 bg-white p-3 text-right hover:border-black hover:shadow-sm transition-all duration-150">
                            <div class="flex items-center justify-between gap-3">
                                <div class="overflow-hidden">
                                    <div class="font-medium text-gray-900 text-sm truncate">{{ $attribute->name }}</div>
                                    @if(isset($attribute->type) && method_exists($attribute->type, 'label'))
                                    <div class="text-[10px] text-gray-400">{{ $attribute->type->label() }}</div>
                                    @endif
                                </div>

                                @if(($globalAttributeProductCounts[$attribute->id] ?? 0) > 0)
                                <span class="shrink-0 rounded-lg bg-gray-100 group-hover:bg-black group-hover:text-white px-2 py-1 text-[10px] text-gray-600 transition-colors">
        {{ $globalAttributeProductCounts[$attribute->id] }} محصول
    </span>
                                @else
                                <x-heroicon-o-plus class="w-4 h-4 text-gray-300 group-hover:text-black transition-colors" />
                                @endif
                            </div>
                        </button>
                        @empty
                        <div class="col-span-2 py-4 text-center">
                            <p class="text-xs text-gray-400">تمامی ویژگی‌ها انتخاب شده‌اند.</p>
                        </div>
                        @endforelse
                    </div>

                    <div x-show="search !== '' && !Array.from($el.previousElementSibling.querySelectorAll('button')).some(el => el.style.display !== 'none')"
                         class="text-center py-4 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                        <p class="text-xs text-gray-500 italic">ویژگی با عنوان «<span x-text="search" class="font-bold text-black"></span>» یافت نشد.</p>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @error('attribute_remove')
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">
            {{ $message }}
        </div>
        @enderror

        <!-- بخش اطلاعات سئو -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-globe-alt class="w-5 h-5 text-black" />
                    <div>
                        <h2 class="font-semibold text-gray-900">اطلاعات سئو (SEO)</h2>
                        <p class="text-xs text-gray-500 mt-1">
                            برای نمایش بهتر در نتایج جستجو، عنوان و توضیحات را کوتاه، واضح و تجاری بنویسید.
                        </p>
                    </div>
                </div>

                <button type="button"
                        wire:click="generateSeo"
                        class="inline-flex justify-center items-center gap-2 px-4 py-2 rounded-xl bg-black text-white text-sm hover:bg-gray-800 transition">
                    <x-heroicon-o-sparkles class="w-4 h-4 text-amber-400" />
                    تولید خودکار سئو
                </button>
            </div>

            <div class="p-4 sm:p-6 space-y-5">
                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
                    <div class="flex items-start gap-3">
                        <x-heroicon-o-light-bulb class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                        <div class="space-y-2">
                            <h3 class="text-sm font-semibold text-gray-900">راهنمای سریع سئو</h3>
                            <ul class="text-xs sm:text-sm text-gray-600 space-y-1 leading-6">
                                <li>عنوان سئو بهتر است حدود ۵۰ تا ۶۰ کاراکتر باشد.</li>
                                <li>توضیحات متا بهتر است حدود ۱۲۰ تا ۱۶۰ کاراکتر باشد.</li>
                                <li>نام دسته‌بندی و هدف خرید کاربر را در متن بیاورید.</li>
                                <li>از تکرار بیش از حد کلمات کلیدی خودداری کنید.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            حالت تولید محتوا
                        </label>
                        <select wire:model.live="seoTemplate"
                                class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20">
                            <option value="commercial">تجاری و فروش‌محور</option>
                            <option value="standard">عمومی و استاندارد</option>
                            <option value="technical">تخصصی و راهنمای انتخاب</option>
                        </select>
                        <p class="mt-2 text-xs text-gray-500">
                            این گزینه تعیین می‌کند متن سئو با چه لحنی ساخته شود.
                        </p>
                        @error('seoTemplate')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-end">
                        <label class="w-full flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 cursor-pointer">
<span>
<span class="block text-sm font-medium text-grayئو دوباره ساخته می‌شود.</span هنگام تغییر اطلاعات</span>
<span class="block text-xs text-gray-500 mt-1">با تغییر نام یا توضیحات، سئو دوباره ساخته می‌شود.</span>
                            </span>

                            <input type="checkbox"
                                   wire:model.live="autoGenerateSeo"
                                   class="rounded border-gray-300 text-black focus:ring-black/20">
                        </label>
                        @error('autoGenerateSeo')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                    <div>
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                عنوان سئو (SEO Title)
                            </label>
                            <span class="text-xs {{ mb_strlen((string) $seo_title) > 60 ? 'text-red-500' : 'text-gray-400' }}">
{{ mb_strlen((string) $seo_title) }}/60
</span>
                        </div>

                        <input type="text"
                               wire:model.live="seo_title"
                               placeholder="مثال: خرید لوازم برقی با بهترین قیمت"
                               class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20">

                        <p class="mt-2 text-xs text-gray-500">
                            بهتر است شامل نام دسته‌بندی و یک عبارت تجاری مثل خرید، قیمت یا راهنمای انتخاب باشد.
                        </p>

                        @error('seo_title')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            کلمات کلیدی
                        </label>

                        <input type="text"
                               wire:model="seo_keywords"
                               placeholder="مثال: گوشی, موبایل, خرید گوشی"
                               class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20">

                        <p class="mt-2 text-xs text-gray-500">
                            این بخش برای نظم داخلی و کمک به مدیریت محتوا مفید است؛ در SEO مدرن اهمیت مستقیم زیادی ندارد.
                        </p>

                        @error('seo_keywords')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                توضیحات سئو (Meta Description)
                            </label>
                            <span class="text-xs {{ mb_strlen((string) $seo_description) > 160 ? 'text-red-500' : 'text-gray-400' }}">
{{ mb_strlen((string) $seo_description) }}/160
</span>
                        </div>

                        <textarea wire:model.live="seo_description"
                                  rows="3"
                                  placeholder="خلاصه‌ای جذاب و کوتاه درباره این دسته‌بندی بنویسید..."
                                  class="w-full rounded-xl border-gray-200 focus:border-black focus:ring-black/20"></textarea>

                        <p class="mt-2 text-xs text-gray-500">
                            این متن باید کاربر را برای کلیک قانع کند؛ کوتاه، دقیق و بدون اغراق بنویسید.
                        </p>

                        @error('seo_description')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </div>




        <!-- دکمه‌های عملیاتی ادمین -->
        <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
            <a href="{{ route('admin.categories.index') }}"
               class="inline-flex justify-center items-center px-5 py-3 rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                انصراف
            </a>

            <button type="submit"
                    class="inline-flex justify-center items-center px-5 py-3 rounded-xl bg-black text-amber-500 font-semibold hover:bg-gray-900 border border-amber-500/20 shadow-md shadow-black/10 transition-all duration-200">
                {{ $isEditMode ? 'ذخیره تغییرات' : 'ثبت دسته‌بندی' }}
            </button>
        </div>
    </form>
</div>
