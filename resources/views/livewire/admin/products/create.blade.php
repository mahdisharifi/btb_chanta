<div x-data @product-form-step-changed.window="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })" data-product-create-guide data-product-tour-media-required="{{ $supplierDraftMode ? 'true' : 'false' }}" data-product-tour-welcome-description="{{ $supplierDraftMode ? 'ابتدا سه تصویر محصول را اضافه می‌کنیم و جابه‌جایی آن‌ها با کشیدن و رهاکردن را تمرین می‌کنیم؛ سپس به مشخصات محصول، دسته‌بندی و ادامه فرم می‌رویم.' : '' }}">
    {{-- عنوان و breadcrumb وابسته به حالت --}}
    @unless($supplierDraftMode)
        <x-admin.subheader
            :title="$isEdit ? 'ویرایش محصول' : 'ایجاد محصول جدید'"
            :breadcrumbs="['مدیریت محصولات' => route('admin.products.index'), ($isEdit ? 'ویرایش محصول' : 'ایجاد محصول') => '#']" />
    @endunless

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
        <h1 data-product-tour-welcome class="text-2xl font-bold text-gray-800 dark:text-gray-200">
            {{ $isEdit ? 'ویرایش محصول' : 'ایجاد محصول جدید' }}
        </h1>
        @if($supplierDraftMode)
            <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">با پایان مرحلهٔ اول، محصول ذخیره و آمادهٔ قیمت‌گذاری می‌شود. تکمیل مرحلهٔ دوم اختیاری است.</p>
        @endif
        </div>
        @if($supplierDraftMode && !$isEdit)
            <x-interactive-guide-launcher />
        @endif
    </div>

    {{-- 🚨 Error Summary: نمایش تمام خطاها در یک جا --}}
    @if ($errors->any())
        <div x-data="{ show: true }"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             id="error-summary"
             class="bg-red-50 dark:bg-red-900/20 border-2 border-red-400 dark:border-red-600 rounded-lg p-5 mb-6 shadow-lg">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <x-heroicon-s-exclamation-circle class="w-8 h-8 text-red-600 dark:text-red-400"/>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-red-900 dark:text-red-100 mb-2 flex items-center gap-2">
                        ⚠️ خطاهای فرم ({{ $errors->count() }} مورد)
                    </h3>
                    <p class="text-sm text-red-800 dark:text-red-200 mb-3">
                        لطفاً خطاهای زیر را برطرف کنید و دوباره تلاش کنید:
                    </p>
                    <ul class="space-y-2">
                        @foreach ($errors->all() as $index => $error)
                            <li class="flex items-start gap-2 text-sm">
                                <span class="flex-shrink-0 inline-flex items-center justify-center w-5 h-5 bg-red-600 dark:bg-red-700 text-white text-xs font-bold rounded-full">
                                    {{ $index + 1 }}
                                </span>
                                <span class="text-red-800 dark:text-red-200">{{ $error }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <button type="button"
                        @click="show = false"
                        class="flex-shrink-0 text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 transition-colors">
                    <x-heroicon-s-x-mark class="w-6 h-6"/>
                </button>
            </div>
        </div>
    @endif

    @include('livewire.products.partials.form-steps')
    @include('livewire.products.partials.saved-first-step')

    @if($formStep === 3)
        <div class="mb-4 flex flex-wrap gap-4">
            <button type="button" wire:click="previousFormStep" class="text-indigo-600 underline">بازگشت به اطلاعات تکمیلی</button>
            <a href="{{ route('supplier.catalog.index') }}" wire:navigate class="text-indigo-600 underline">دریافت کاتالوگ</a>
        </div>
        <livewire:supplier.products.edit-price :product="\App\Models\Product::findOrFail($productId)" :key="'wizard-pricing-'.$productId" />
    @else
    <form novalidate wire:submit.prevent="submitProductForm" class="space-y-6">

        @if($formStep === 1)
        <div data-product-tour-image-count="{{ ($primary_image ? 1 : 0) + count($gallery_images) }}" data-product-tour-drag-version="{{ $galleryDragVersion }}" id="field-media" data-product-tour-step="media-overview" data-product-tour-order="1" data-product-tour-target="h2" data-product-tour-title="تصاویر و ویدئوی محصول" data-product-tour-description="رسانه‌ها به مشتری کمک می‌کنند کالا را بشناسد. افزودن حداقل یک تصویر در فرم تأمین‌کننده الزامی و ویدئو اختیاری است؛ می‌توانید ویدئو و تصویر بندانگشتی آن را هم اضافه کنید." class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            @php
                $effectiveUploadedSeoImage = $uploadedSeoImage ?? (collect($existingGalleries)->contains(fn ($image) => $image['is_seo_image'] ?? false) ? null : $primary_image?->getFilename());
            @endphp
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
                <div x-data="{ dragged: null }" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-3">
                    @foreach(collect($existingGalleries)->where('file_type', 'image')->values() as $index => $gallery)
                    <div wire:key="sortable-existing-{{ $gallery['id'] }}" draggable="true" @dragstart.stop="dragged = {{ $index }}; $event.dataTransfer.setData('text/plain', '{{ $index }}'); $event.dataTransfer.effectAllowed = 'move'" @dragend.stop="dragged = null" @dragover.prevent="$event.dataTransfer.dropEffect = 'move'" @drop.prevent.stop="if (dragged !== null) { $wire.reorderExistingImages(dragged, {{ $index }}); dragged = null }" :class="dragged === {{ $index }} ? 'opacity-40' : ''" class="relative group cursor-grab active:cursor-grabbing rounded-lg overflow-hidden border-2 {{ $gallery['is_primary'] ? 'border-[#d4af37]' : ($gallery['is_seo_image'] ? 'border-blue-500' : 'border-white/10') }}">
                        <img src="{{ $gallery['full_url'] }}" class="w-full h-28 object-cover">

                        {{-- برچسب تصویر اصلی --}}
                        @if($gallery['is_primary'])
                        <span class="absolute top-1 right-1 text-xs bg-[#d4af37] text-black font-bold px-2 py-0.5 rounded">اصلی</span>
                        @endif

                        {{-- برچسب تصویر SEO --}}
                        @if(!$supplierDraftMode && ($gallery['is_seo_image'] ?? false))
                        <span class="absolute top-1 {{ ($gallery['is_primary'] ?? false) ? 'right-12' : 'right-1' }} text-xs bg-blue-500 text-white font-bold px-2 py-0.5 rounded flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            SEO
                        </span>
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
                                        class="p-1 rounded bg-[#d4af37]/80 hover:bg-[#d4af37]"
                                        title="تصویر اصلی">
                                    <x-heroicon-s-star class="w-3 h-3 text-black" />
                                </button>
                                @endif

                                {{-- انتخاب به عنوان تصویر SEO --}}
                                @unless($supplierDraftMode)
                                <button type="button" wire:click="toggleSeoImage({{ $gallery['id'] }})"
                                        class="p-1 rounded {{ ($gallery['is_seo_image'] ?? false) ? 'bg-blue-500' : 'bg-blue-500/50 hover:bg-blue-500/80' }}"
                                        title="تصویر ویژه برای Google (SEO)">
                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </button>

                                @endunless
                                {{-- حذف --}}
                                <button type="button" wire:click="markGalleryForDeletion({{ $gallery['id'] }})"
                                        class="p-1 rounded bg-red-500/80 hover:bg-red-500"
                                        title="حذف">
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




            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-8">
                <div class="md:col-span-2">
                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-300 mb-3">تصویر اول، تصویر اصلی محصول است. افزودن ویدئو و تصویر پیش‌نمایش آن اختیاری است. @if($supplierDraftMode) افزودن حداقل یک تصویر الزامی است. @endif</p>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                        <div data-product-tour-step="gallery-images" data-product-tour-order="2" data-product-tour-target="label[for=product_images]" data-product-tour-title="تصاویر محصول" data-product-tour-description="تصاویر را یکجا اضافه کنید. اولین تصویر اصلی است و می‌توانید تصویر اصلی را از همین بخش تغییر دهید.">
                            <label for="product_images" class="flex min-h-28 sm:min-h-32 w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 p-2 sm:p-3 text-indigo-600 hover:bg-indigo-50 dark:border-gray-600 dark:hover:bg-gray-700/50 focus-within:ring-2 focus-within:ring-indigo-500">
                                <svg aria-hidden="true" class="h-7 w-7 sm:h-9 sm:w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h4l2-3h6l2 3h4v13H3z"/><circle cx="12" cy="13" r="4"/></svg>
                                <span class="text-xs sm:text-sm text-center">افزودن تصاویر</span>
                                <input id="product_images" type="file" wire:model="incomingImages" multiple accept="image/*" class="sr-only">
                            </label>
                        </div>

                        <div data-product-tour-step="product-video" data-product-tour-order="5" data-product-tour-target="label[for=product_video]" data-product-tour-title="ویدئوی محصول" data-product-tour-description="افزودن ویدئو اختیاری است. فرمت‌های MP4، WebM، OGG و MOV پذیرفته می‌شوند و حجم فایل نباید از ۵۰ مگابایت بیشتر باشد.">
                            <label for="product_video" class="flex min-h-28 sm:min-h-32 w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 p-2 sm:p-3 text-indigo-600 hover:bg-indigo-50 dark:border-gray-600 dark:hover:bg-gray-700/50 focus-within:ring-2 focus-within:ring-indigo-500">
                                <svg aria-hidden="true" class="h-7 w-7 sm:h-9 sm:w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="6" width="14" height="12" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m16 10 6-4v12l-6-4z"/></svg>
                                <span class="text-xs sm:text-sm text-center">{{ $product_video ? 'تغییر ویدئو' : 'افزودن ویدئو' }}</span>
                                <input id="product_video" type="file" wire:model="product_video" accept="video/mp4,video/webm,video/ogg,video/quicktime" class="sr-only">
                            </label>
                        </div>

                        <div data-product-tour-step="video-poster" data-product-tour-order="6" data-product-tour-target="label[for=video_poster]" data-product-tour-title="تصویر بندانگشتی ویدئو" data-product-tour-description="این تصویر به‌عنوان پیش‌نمایش ویدئو در گالری استفاده می‌شود. فقط وقتی ویدئو دارید لازم است آن را انتخاب کنید؛ تصویر JPG، PNG یا WebP تا ۲ مگابایت باشد.">
                            <label for="video_poster" class="flex min-h-28 sm:min-h-32 w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 p-2 sm:p-3 text-indigo-600 hover:bg-indigo-50 dark:border-gray-600 dark:hover:bg-gray-700/50 focus-within:ring-2 focus-within:ring-indigo-500">
                                <svg aria-hidden="true" class="h-7 w-7 sm:h-9 sm:w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 17 5-5 4 4 3-3 6 6"/></svg>
                                <span class="text-xs sm:text-sm text-center">{{ $video_poster ? 'تغییر تصویر پیش‌نمایش' : 'تصویر پیش‌نمایش ویدئو' }}</span>
                                <input id="video_poster" type="file" wire:model="video_poster" accept="image/jpeg,image/jpg,image/png,image/webp" class="sr-only">
                            </label>
                        </div>
                    </div>

                    <p class="mt-2 text-[11px] leading-5 text-gray-500 dark:text-gray-400">هر تصویر حداکثر ۲ مگابایت باشد. فرمت ویدئو: MP4، WebM، OGG یا MOV تا ۵۰ مگابایت. تصویر پیش‌نمایش ویدئو در گالری محصول نمایش داده می‌شود.</p>

                    <div data-product-gallery-sortable x-data="{ dragged: null }" class="mt-4 flex flex-wrap gap-3 scroll-mt-6">
                        @if($primary_image)
                        <div wire:key="uploaded-primary-{{ $primary_image->getFilename() }}" draggable="true" @dragstart.stop="dragged = 0; $event.dataTransfer.setData('text/plain', '0'); $event.dataTransfer.effectAllowed = 'move'" @dragend.stop="dragged = null" @dragover.prevent="$event.dataTransfer.dropEffect = 'move'" @drop.prevent.stop="if (dragged !== null) { $wire.reorderUploadedImages(dragged, 0); dragged = null }" :class="dragged === 0 ? 'opacity-40' : ''" class="relative w-32 cursor-grab active:cursor-grabbing rounded-xl border-2 border-indigo-500 overflow-hidden">
                            <img src="{{ $primary_image->temporaryUrl() }}" alt="تصویر اصلی محصول" class="h-32 w-full object-cover">
                            <span class="block bg-indigo-600 text-white text-xs text-center py-2">تصویر اصلی</span>
                            @unless($supplierDraftMode)
                            <button type="button" wire:click="toggleUploadedSeoImage('{{ $primary_image->getFilename() }}')" aria-pressed="{{ $effectiveUploadedSeoImage === $primary_image->getFilename() ? 'true' : 'false' }}" class="w-full py-2 text-xs {{ $effectiveUploadedSeoImage === $primary_image->getFilename() ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700' }}">{{ $effectiveUploadedSeoImage === $primary_image->getFilename() ? '✓ تصویر ویژه سئو' : 'انتخاب برای سئو' }}</button>
                            @endunless
                            <button type="button" wire:click="removeUploadedImage" aria-label="حذف تصویر اصلی" class="absolute top-1 left-1 rounded bg-red-600 text-white px-2 py-1">×</button>
                        </div>
                        @endif
                        @foreach($gallery_images as $index => $image)
                        <div wire:key="uploaded-gallery-{{ $image->getFilename() }}" draggable="true" @dragstart.stop="dragged = {{ $index + ($primary_image ? 1 : 0) }}; $event.dataTransfer.setData('text/plain', '{{ $index + ($primary_image ? 1 : 0) }}'); $event.dataTransfer.effectAllowed = 'move'" @dragend.stop="dragged = null" @dragover.prevent="$event.dataTransfer.dropEffect = 'move'" @drop.prevent.stop="if (dragged !== null) { $wire.reorderUploadedImages(dragged, {{ $index + ($primary_image ? 1 : 0) }}); dragged = null }" :class="dragged === {{ $index + ($primary_image ? 1 : 0) }} ? 'opacity-40' : ''" class="relative w-32 cursor-grab active:cursor-grabbing rounded-xl border border-gray-300 overflow-hidden">
                            <img src="{{ $image->temporaryUrl() }}" alt="تصویر محصول" class="h-32 w-full object-cover">
                            <button type="button" wire:click="selectUploadedPrimary({{ $index }})" class="w-full bg-indigo-50 text-indigo-700 text-xs py-2">انتخاب به‌عنوان تصویر اصلی</button>
                            @unless($supplierDraftMode)
                            <button type="button" wire:click="toggleUploadedSeoImage('{{ $image->getFilename() }}')" aria-pressed="{{ $effectiveUploadedSeoImage === $image->getFilename() ? 'true' : 'false' }}" class="w-full py-2 text-xs {{ $effectiveUploadedSeoImage === $image->getFilename() ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700' }}">{{ $effectiveUploadedSeoImage === $image->getFilename() ? '✓ تصویر ویژه سئو' : 'انتخاب برای سئو' }}</button>
                            @endunless
                            <button type="button" wire:click="removeUploadedImage({{ $index }})" aria-label="حذف تصویر" class="absolute top-1 left-1 rounded bg-red-600 text-white px-2 py-1">×</button>
                        </div>
                        @endforeach
                    </div>
                    <div wire:loading wire:target="incomingImages" role="status" class="text-sm text-indigo-600 mt-2">در حال بارگذاری تصاویر...</div>
                    @error('incomingImages.*') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    @error('primary_image') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    @error('gallery_images') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    @error('gallery_images.*') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- وضعیت و پیش‌نمایش رسانه‌های بارگذاری‌شده -->
                <div class="md:col-span-2">
                    <div wire:loading wire:target="video_poster" class="text-sm text-indigo-600 mt-2">در حال آپلود تصویر پیش‌نمایش...</div>
                    @error('video_poster') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    @if ($video_poster)
                    <div class="mt-3 flex items-center gap-3">
                        <img src="{{ $video_poster->temporaryUrl() }}" class="h-16 w-24 object-cover rounded-lg border-2 border-green-500 shadow-sm" alt="پیش‌نمایش تصویر ویدئو">
                        <span class="text-xs text-green-600">تصویر پیش‌نمایش ویدئو آماده است.</span>
                    </div>
                    @endif

                    <div wire:loading wire:target="product_video" class="text-sm text-purple-600 mt-2">در حال آپلود ویدئو...</div>
                    @error('product_video') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if ($product_video)
                    <div class="mt-3">
                        <p class="text-xs text-green-600 mb-2">پیش‌نمایش ویدئوی جدید:</p>
                        <video controls class="aspect-video w-full max-w-md rounded-lg border-2 border-green-500 bg-black shadow-sm">
                            <source src="{{ $product_video->temporaryUrl() }}">
                            مرورگر شما از پخش ویدئو پشتیبانی نمی‌کند.
                        </video>
                    </div>
                    @endif
                </div>
            </div>
        </div>


        <!-- اطلاعات پایه -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2">اطلاعات اصلی</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div id="field-title" data-product-tour-step="title" data-product-tour-order="10" data-product-tour-target="#title" data-product-tour-title="نام محصول" data-product-tour-description="نامی را بنویس که مشتری‌ها برای پیدا کردن محصول می‌شناسند. این فیلد برای ثبت محصول الزامی است.">
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نام محصول (الزامی)</label>
                    <input id="title" type="text"
                           wire:model.live.debounce.500ms="title"
                           class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm @error('title') border-red-500 dark:border-red-600 @enderror"
                           required>
                    @error('title') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                @if($supplierDraftMode)
                    {{-- نامک تأمین‌کننده از عنوان ساخته و هنگام ثبت ذخیره می‌شود؛ برای ساده‌ماندن فرم نمایش داده نمی‌شود. --}}
                    <input id="slug" type="hidden" wire:model="slug">
                @else
                    <div id="field-slug" data-product-tour-step="slug" data-product-tour-order="20" data-product-tour-target="#slug" data-product-tour-title="نامک صفحه (Slug)" data-product-tour-description="نامک از عنوان محصول ساخته می‌شود و در نشانی صفحه قرار می‌گیرد. با دکمهٔ کنار عنوان می‌توانید آن را دوباره از روی عنوان بسازید؛ نامک باید یکتا بماند.">
                        <div class="mb-1 flex items-center gap-2">
                            <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            نامک (Slug) (الزامی)
                            </label>
                            <button type="button" wire:click="resetSlugToAuto"
                                    class="text-gray-400 hover:text-gray-600" title="بازتولید خودکار از عنوان" aria-label="بازتولید خودکار نامک از عنوان">
                                <x-heroicon-o-arrow-path class="w-4 h-4" />
                            </button>
                        </div>
                        <input id="slug" type="text"
                               wire:model="slug"
                               dir="ltr"
                               class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm text-left @error('slug') border-red-500 dark:border-red-600 @enderror"
                               required>
                        @error('slug') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                @endif

                <!-- انتخابگر هوشمند دسته‌بندی -->
                <div x-data="{ open: false }" class="relative" id="field-category_id" data-product-tour-step="category" data-product-tour-order="30" data-product-tour-target="#category-select" data-product-tour-title="انتخاب دسته‌بندی" data-product-tour-description="یک دستهٔ نهایی را جست‌وجو و انتخاب کنید. با انتخاب دسته، ویژگی‌های مخصوص همان دسته به فرم اضافه می‌شوند. اگر دسته را عوض کنید، انتخاب ویژگی‌های قبلی پاک می‌شود." data-product-tour-category-selected="{{ $category_id ? 'true' : 'false' }}">
                    <label id="category-label" for="category-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">دسته‌بندی (فقط دسته‌های نهایی مجاز هستند) (الزامی)</label>

                    <button type="button"
                            id="category-select"
                            x-ref="categoryTrigger"
                            aria-labelledby="category-label category-current"
                            aria-controls="category-options"
                            :aria-expanded="open.toString()"
                            @click="open = !open"
                            @keydown.escape="open = false"
                            class="w-full border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-lg shadow-sm text-sm px-3 py-2 cursor-pointer flex justify-between items-center bg-white dark:bg-gray-900 text-right">
                        <span id="category-current" class="block truncate">{{ $selectedCategoryName ?: 'یک دسته‌بندی انتخاب کنید...' }}</span>
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="open" @click.away="open = false" @keydown.escape.stop.prevent="open = false; $nextTick(() => $refs.categoryTrigger.focus())" style="display: none;" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg">
                        <div class="p-2 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 rounded-t-lg">
                            <input type="text" wire:model.live.debounce.300ms="searchCategory" aria-label="جستجوی دسته‌بندی" placeholder="جستجوی دسته‌بندی..." class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <ul id="category-options" class="max-h-60 overflow-y-auto">
                            @forelse($searchCategories as $cat)
                            <li>
                                <button type="button" wire:click="selectCategory({{ $cat->id }}, @js($cat->name))" @click="open = false" class="w-full text-right px-3 py-2.5 text-sm cursor-pointer hover:bg-indigo-50 dark:hover:bg-gray-700 dark:text-gray-300 border-b border-gray-50 dark:border-gray-700/50 last:border-0 transition-colors">
                                    {{ $cat->name }}
                                </button>
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

                @unless($supplierDraftMode)
                    <!-- انتخاب تامین کننده -->
                    <div data-product-tour-step="supplier" data-product-tour-order="40" data-product-tour-target="select" data-product-tour-title="تأمین‌کننده" data-product-tour-description="این فیلد فقط در فرم مدیریت نمایش داده می‌شود؛ در پنل تأمین‌کننده، محصول به‌طور خودکار به حساب و شعبهٔ فعال شما وصل می‌شود.">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تامین کننده (الزامی)</label>
                        <select wire:model="supplier_id" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm" required>
                            <option value="">یک تامین کننده انتخاب کنید...</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                @endunless

                @unless($supplierDraftMode)
                    <div data-product-tour-step="status" data-product-tour-order="60" data-product-tour-target="select" data-product-tour-title="وضعیت محصول" data-product-tour-description="این انتخاب برای فرم مدیریت است. پیشنهاد تأمین‌کننده در این مسیر مستقل از انتشار عمومی، به‌صورت پیش‌نویس ذخیره می‌شود.">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">وضعیت محصول (الزامی)</label>
                        <select wire:model="status" class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm" required>
                            <option value="">انتخاب وضعیت...</option>
                            @foreach(\App\Enums\ProductStatus::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div data-product-tour-step="active" data-product-tour-order="70" data-product-tour-target="#is_active" data-product-tour-title="نمایش عمومی محصول" data-product-tour-description="این گزینه فقط در فرم مدیریت وجود دارد. محصول پیشنهادی تأمین‌کننده تا زمان بررسی و تأیید، فعال و عمومی نمی‌شود." class="flex items-center mt-6 md:col-span-2">
                        <input id="is_active" type="checkbox" wire:model="is_active" class="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                        <label for="is_active" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">محصول فعال است و در سایت نمایش داده می‌شود</label>
                        @error('is_active') <span class="text-red-500 text-xs ml-2">{{ $message }}</span> @enderror
                    </div>
                @endunless
            </div>
        </div>

        <!-- بخش واحد بسته‌بندی و تیراژبندی خودکار -->
        @include('livewire.products.partials.packaging-tiers')
        @else


        <!-- بخش ویژگی‌ها -->
        @if($categoryAttributes)
        <div data-product-tour-step="category-attributes" data-product-tour-order="200" data-product-tour-target="h2" data-product-tour-title="ویژگی‌های دسته‌بندی" data-product-tour-description="ویژگی‌های این بخش با دسته‌ای که انتخاب کردید ساخته شده‌اند. هر کارت را جداگانه مرور کنید؛ بعضی ویژگی‌ها یک انتخاب دارند و بعضی چند انتخابی هستند."
             class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2 flex items-center gap-2">
                <x-heroicon-o-adjustments-horizontal class="w-5 h-5 text-[#d4af37]"/>
                ویژگی‌های محصول
            </h2>

            <div class="space-y-4">
                @foreach($categoryAttributes as $attribute)
                <div wire:key="product-guide-attribute-{{ $attribute->id }}"
                     data-product-tour-step="category-attribute-{{ $attribute->id }}"
                     data-product-tour-order="{{ 210 + $loop->index }}"
                     data-product-tour-target="[data-product-tour-field-control]"
                     data-product-tour-label="[data-product-tour-field-label]"
                     data-product-tour-title="ویژگی: {{ $attribute->name }}"
                     data-product-tour-description="{{ $attribute->is_multiple ? 'برای این ویژگی می‌توانید چند مقدار انتخاب کنید.' : 'برای این ویژگی یک مقدار انتخاب کنید.' }} مقادیر موجود را جست‌وجو کنید؛ اگر مورد مناسب نیست، مقدار تازه را بنویسید و افزودن را بزنید.">
                    @include('components.attribute-value-selector', ['attribute' => $attribute])
                </div>
                @endforeach
            </div>
        </div>
        @endif



        {{-- SEO --}}
        @unless($supplierDraftMode)
        <div id="field-seo" data-product-tour-step="seo-overview" data-product-tour-order="300" data-product-tour-target="h3" data-product-tour-title="تنظیمات نمایش در گوگل (SEO)" data-product-tour-description="این فیلدها عنوان و خلاصه‌ای را کنترل می‌کنند که موتورهای جست‌وجو می‌توانند برای محصول نمایش دهند. همهٔ فیلدهای SEO اختیاری‌اند و پیش‌نمایش پایین بخش، نتیجه را نشان می‌دهد." class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-[#d4af37]"/>
                    بهینه‌سازی موتور جستجو (SEO)
                </h3>
                <button type="button" data-product-tour-step="seo-generate" data-product-tour-order="310" data-product-tour-title="تولید خودکار SEO" data-product-tour-description="این دکمه عنوان، توضیحات، کلمات کلیدی و نشانی اصلی را از اطلاعاتی که تا اینجا وارد کرده‌اید می‌سازد. بعد از تولید، می‌توانید همه را ویرایش کنید." wire:click="generateSeo"
                        class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-[#d4af37]/10 text-[#d4af37] hover:bg-[#d4af37]/20 transition">
                    <x-heroicon-o-sparkles class="w-4 h-4"/>
                    تولید خودکار
                </button>
            </div>

            {{-- راهنمای تصویر SEO --}}
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="flex-1 text-xs text-blue-900 dark:text-blue-100">
                        <p class="font-semibold mb-1">💡 تصویر ویژه برای نتایج جستجوی Google</p>
                        <p class="leading-relaxed">
                            در بخش گالری تصاویر، روی آیکون 🔍 کلیک کنید تا آن تصویر در نتایج جستجوی Google نمایش داده شود.
                            اگر تصویر SEO انتخاب نکنید، به صورت خودکار از تصویر اصلی استفاده می‌شود.
                        </p>
                    </div>
                </div>
            </div>

            <div data-product-tour-step="seo-title" data-product-tour-order="320" data-product-tour-target="input" data-product-tour-title="عنوان SEO" data-product-tour-description="عنوانی کوتاه برای نتیجهٔ جست‌وجو است و حداکثر ۶۰ نویسه می‌پذیرد. اگر خالی بماند، عنوان محصول جای آن استفاده می‌شود.">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">عنوان SEO</label>
                <input type="text" wire:model.live="seo_title" maxlength="60"
                       class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm"
                       placeholder="حداکثر ۶۰ کاراکتر">
                <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_title ?? '') }}/60</p>
                @error('seo_title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div data-product-tour-step="seo-description" data-product-tour-order="330" data-product-tour-target="textarea" data-product-tour-title="توضیحات SEO" data-product-tour-description="خلاصه‌ای برای نتیجهٔ جست‌وجو بنویسید؛ حداکثر ۱۶۰ نویسه. این متن با توضیحات کامل محصول فرق دارد.">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">توضیحات SEO</label>
                <textarea wire:model.live="seo_description" rows="3" maxlength="160"
                          class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm"
                          placeholder="حداکثر ۱۶۰ کاراکتر"></textarea>
                <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_description ?? '') }}/160</p>
                @error('seo_description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div data-product-tour-step="seo-keywords" data-product-tour-order="340" data-product-tour-target="input" data-product-tour-title="کلمات کلیدی" data-product-tour-description="عبارت‌های مرتبط را با ویرگول جدا کنید. این فیلد اختیاری است و سقف آن ۲۵۵ نویسه است.">
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

            <div data-product-tour-step="seo-canonical" data-product-tour-order="350" data-product-tour-target="input[type=url]" data-product-tour-title="نشانی Canonical" data-product-tour-description="در حالت عادی این نشانی خودکار از نامک محصول ساخته می‌شود. فقط وقتی صفحهٔ اصلی محصول نشانی دیگری دارد، URL کامل و یکتا را وارد کنید.">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 flex items-center gap-2">
                    <span>آدرس Canonical (اختیاری)</span>
                    <span class="relative group">
                        <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 cursor-help" />
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-72 z-10">
                            <div class="bg-gray-900 text-white text-xs rounded-lg p-3 shadow-xl">
                                <p class="font-semibold mb-1">💡 Canonical URL چیست؟</p>
                                <p class="mb-2">URL اصلی و یکتا محصول برای جلوگیری از duplicate content در Google</p>
                                <p class="text-amber-300 text-[10px]">⚠️ در صورت خالی گذاشتن، سیستم به صورت خودکار از URL محصول استفاده می‌کند</p>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                            </div>
                        </div>
                    </span>
                </label>
                <input type="url" wire:model.live="seo_canonical_url" dir="ltr"
                       class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm text-left"
                       placeholder="https://example.com/products/sample-product">

                {{-- نمایش Canonical پیش‌فرض --}}
                <div class="mt-2 flex items-start gap-2 text-xs">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <span class="text-gray-500 dark:text-gray-400">Canonical پیش‌فرض:</span>
                        <code class="text-green-600 dark:text-green-400 font-mono bg-green-50 dark:bg-green-900/20 px-2 py-0.5 rounded ml-1">
                            {{ $seo_canonical_url ?: url('/products/'.($slug ?: 'product-slug')) }}
                        </code>
                    </div>
                </div>

                @error('seo_canonical_url')
                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            {{-- پیش‌نمایش گوگل - بهبود یافته با تصویر --}}
            @if($seo_title || $seo_description || $title)
            <div data-product-tour-step="seo-preview" data-product-tour-order="360" data-product-tour-target="h3" data-product-tour-title="پیش‌نمایش گوگل" data-product-tour-description="این پیش‌نمایش تقریبی عنوان، نشانی و توضیحات را کنار هم نشان می‌دهد. Google ممکن است متن نهایی را کوتاه یا بازنویسی کند." class="mt-4 rounded-xl border-2 border-blue-200 dark:border-blue-800 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-white dark:bg-gray-700 flex items-center justify-center shadow-sm">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h2v-2h-2v2zm0-4h2V7h-2v6z" fill="currentColor" class="text-blue-600"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">پیش‌نمایش در نتایج جستجوی گوگل</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">این نمایش تقریبی است - Google ممکن است متن را کوتاه کند</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
                    {{-- Layout دو ستونی: متن + تصویر --}}
                    <div class="flex gap-4">
                        {{-- ستون چپ: متن --}}
                        <div class="flex-1 min-w-0">
                            {{-- عنوان (Title) --}}
                            <div class="mb-1">
                                <h3 class="text-blue-600 dark:text-blue-400 text-lg font-medium hover:underline cursor-pointer line-clamp-1">
                                    {{ $seo_title ?: ($title ?: 'عنوان محصول شما') }}
                                </h3>
                            </div>

                            {{-- URL (Canonical) --}}
                            <div class="mb-2 flex items-center gap-2">
                                <span class="text-green-700 dark:text-green-500 text-sm font-medium">
                                    {{ parse_url($seo_canonical_url ?: url('/products/'.($slug ?: 'product-slug')), PHP_URL_HOST) }}
                                </span>
                                <span class="text-gray-400">›</span>
                                <span class="text-gray-600 dark:text-gray-400 text-xs truncate">
                                    {{ $seo_canonical_url ?: url('/products/'.($slug ?: 'product-slug')) }}
                                </span>
                            </div>

                            {{-- توضیحات (Description) --}}
                            <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed line-clamp-2">
                                {{ $seo_description ?: 'توضیحات محصول شما در اینجا نمایش داده می‌شود. این متن در نتایج جستجوی Google ظاهر می‌شود.' }}
                            </p>
                        </div>

                        {{-- ستون راست: تصویر SEO --}}
                        @php
                            // محاسبه تصویر SEO: is_seo_image > is_primary > first image
                            $seoImage = collect($existingGalleries ?? [])
                                ->where('file_type', 'image')
                                ->sortByDesc(fn($g) => ($g['is_seo_image'] ?? false) ? 2 : (($g['is_primary'] ?? false) ? 1 : 0))
                                ->first();
                        @endphp

                        @php
                            $uploadedSeoPreview = collect(array_filter([$primary_image, ...$gallery_images]))->first(fn ($image) => $image->getFilename() === $effectiveUploadedSeoImage);
                        @endphp
                        @if($uploadedSeoPreview)
                        <div class="flex-shrink-0"><img src="{{ $uploadedSeoPreview->temporaryUrl() }}" alt="پیش‌نمایش تصویر سئو" class="w-28 h-28 object-cover rounded-lg"></div>
                        @elseif($seoImage)
                        <div class="flex-shrink-0">
                            <div class="w-28 h-28 rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700">
                                <img src="{{ $seoImage['full_url'] ?? '' }}"
                                     alt="تصویر SEO"
                                     class="w-full h-full object-cover"
                                     loading="lazy"
                                     wire:key="seo-preview-{{ $seoImage['id'] ?? 'default' }}">
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- نکات سئو --}}
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="flex items-center gap-2 text-xs {{ mb_strlen($seo_title ?: '') >= 50 && mb_strlen($seo_title ?: '') <= 60 ? 'text-green-600' : 'text-amber-600' }}">
                        @if(mb_strlen($seo_title ?: '') >= 50 && mb_strlen($seo_title ?: '') <= 60)
                            <x-heroicon-s-check-circle class="w-4 h-4" />
                            <span>عنوان در محدوده ایده‌آل است</span>
                        @elseif(mb_strlen($seo_title ?: '') > 60)
                            <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
                            <span>عنوان خیلی طولانی است (Google کوتاه می‌کند)</span>
                        @else
                            <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
                            <span>عنوان کوتاه است (بهتر است 50-60 کاراکتر باشد)</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 text-xs {{ mb_strlen($seo_description ?: '') >= 120 && mb_strlen($seo_description ?: '') <= 160 ? 'text-green-600' : 'text-amber-600' }}">
                        @if(mb_strlen($seo_description ?: '') >= 120 && mb_strlen($seo_description ?: '') <= 160)
                            <x-heroicon-s-check-circle class="w-4 h-4" />
                            <span>توضیحات در محدوده ایده‌آل است</span>
                        @elseif(mb_strlen($seo_description ?: '') > 160)
                            <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
                            <span>توضیحات خیلی طولانی است</span>
                        @else
                            <x-heroicon-s-exclamation-triangle class="w-4 h-4" />
                            <span>توضیحات کوتاه است (بهتر است 120-160 کاراکتر باشد)</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>

        @endunless

        <!-- بخش تولید توضیحات با هوش مصنوعی -->
        <div id="field-ai-description" data-product-tour-step="ai-overview" data-product-tour-order="400" data-product-tour-target="h2" data-product-tour-title="ساخت توضیحات با کمک هوش مصنوعی" data-product-tour-description="این بخش خودش به سرویس هوش مصنوعی وصل نمی‌شود. مشخصات را آماده می‌کند تا پرامپت را کپی کنید، در ابزار دلخواهتان بفرستید و متن نهایی را در دو فیلد پایین وارد کنید." class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 p-6 rounded-lg shadow-sm border-2 border-purple-200 dark:border-purple-800">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-sparkles class="w-6 h-6 text-purple-600"/>
                    تولید توضیحات با هوش مصنوعی
                </h2>
                <span class="text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 px-3 py-1 rounded-full font-medium">
                    AI-Powered
                </span>
            </div>

            <!-- راهنمای استفاده -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"/>
                    <div class="text-sm text-blue-900 dark:text-blue-100 space-y-2">
                        <p class="font-semibold">📌 نحوه استفاده:</p>
                        <ol class="list-decimal list-inside space-y-1 mr-2">
                            <li>مشخصات محصول را در بخش‌های بالا تکمیل کنید</li>
                            <li>روی دکمه "کپی پرامپت" کلیک کنید</li>
                            <li>پرامپت در کلیپبورد ذخیره می‌شود</li>
                            <li>به ChatGPT یا هوش مصنوعی دلخواه خود بروید و پرامپت را Paste کنید</li>
                            <li>توضیحات تولید شده را در فیلدهای زیر وارد کنید</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- دکمه کپی پرامپت -->
            <div data-product-tour-step="ai-copy-prompt" data-product-tour-order="410" data-product-tour-target="button" data-product-tour-title="کپی پرامپت" data-product-tour-description="با این دکمه، پرامپتی شامل نام، دسته، بسته‌بندی و ویژگی‌های انتخاب‌شده در کلیپ‌بورد کپی می‌شود. آن را در ابزار هوش مصنوعی دلخواهتان بچسبانید؛ سپس متن پاسخ را خودتان در فیلدهای توضیحات وارد کنید." class="mb-6"
                 x-data="{
                     copied: false,
                     async copyPrompt() {
                         try {
                             const prompt = await $wire.generateAIPrompt();
                             await navigator.clipboard.writeText(prompt);
                             this.copied = true;
                             setTimeout(() => this.copied = false, 3000);
                         } catch (error) {
                             console.error('خطا در کپی کردن:', error);
                             alert('خطا در کپی کردن پرامپت. لطفاً دوباره تلاش کنید.');
                         }
                     }
                 }">
                <button type="button"
                        @click="copyPrompt()"
                        class="w-full py-4 px-6 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center gap-3 group">
                    <svg x-show="!copied" class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <svg x-show="copied" x-cloak class="w-6 h-6 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span x-text="copied ? 'کپی شد! ✓' : '📋 کپی پرامپت هوش مصنوعی'"></span>
                </button>

                <p class="text-xs text-center text-gray-500 dark:text-gray-400 mt-2">
                    پرامپت شامل تمام مشخصات، دسته‌بندی، و ویژگی‌های محصول است
                </p>
            </div>

            <!-- فیلد توضیحات کوتاه -->
            <div data-product-tour-step="short-description" data-product-tour-order="420" data-product-tour-target="textarea" data-product-tour-title="توضیحات کوتاه" data-product-tour-description="خلاصهٔ دو یا سه‌جمله‌ای محصول را بنویسید. این متن در کارت‌ها و فهرست‌ها نشان داده می‌شود و حداکثر ۲۰۰ نویسه دارد." class="mb-6">
                <label for="short_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-5 h-5 text-indigo-600"/>
                    توضیحات کوتاه
                    <span class="text-xs text-gray-500">(خلاصه محصول - 2-3 جمله)</span>
                </label>
                <textarea id="short_description" wire:model="short_description"
                          rows="3"
                          placeholder="خلاصه‌ای از محصول که در کارت‌ها و لیست‌ها نمایش داده می‌شود..."
                          class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-lg shadow-sm text-sm"></textarea>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-xs text-gray-500">
                        💡 نکته: این متن در کارت محصول و لیست‌ها نمایش داده می‌شود
                    </span>
                    <span class="text-xs {{ mb_strlen($short_description ?? '') > 200 ? 'text-red-600' : 'text-gray-400' }}">
                        {{ mb_strlen($short_description ?? '') }}/200 کاراکتر
                    </span>
                </div>
                @error('short_description')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <!-- فیلد توضیحات کامل -->
            <div data-product-tour-step="full-description" data-product-tour-order="430" data-product-tour-target="textarea" data-product-tour-title="توضیحات کامل" data-product-tour-description="جزئیات، کاربردها و مزایای محصول را بنویسید. این متن در صفحهٔ جزئیات محصول نمایش داده می‌شود.">
                <label for="full_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                    <x-heroicon-o-document-duplicate class="w-5 h-5 text-indigo-600"/>
                    توضیحات تخصصی کامل
                    <span class="text-xs text-gray-500">(توضیحات جامع محصول)</span>
                </label>
                <textarea id="full_description" wire:model="full_description"
                          rows="8"
                          placeholder="توضیحات کامل و تخصصی محصول شامل ویژگی‌ها، کاربردها، مزایا و..."
                          class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-lg shadow-sm text-sm"></textarea>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-xs text-gray-500">
                        💡 نکته: این متن در صفحه جزئیات محصول نمایش داده می‌شود
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ mb_strlen($full_description ?? '') }} کاراکتر
                    </span>
                </div>
                @error('full_description')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <!-- نمونه خروجی (اگر توضیحات پر شده) -->
            @if(!empty($short_description) || !empty($full_description))
            <div class="mt-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-sm font-semibold text-green-800 dark:text-green-300 mb-2 flex items-center gap-2">
                    <x-heroicon-s-check-circle class="w-5 h-5"/>
                    پیش‌نمایش توضیحات
                </p>
                @if(!empty($short_description))
                <div class="mb-3">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">توضیحات کوتاه:</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $short_description }}</p>
                </div>
                @endif
                @if(!empty($full_description))
                <div>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">توضیحات کامل:</p>
                    <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed max-h-40 overflow-y-auto">
                        {!! nl2br(e($full_description)) !!}
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>

        <!-- تصاویر و ویدئوی محصول -->
        @endif

        <div data-product-tour-step="submit-draft" data-product-tour-order="600" data-product-tour-target="button[type=submit]" data-product-tour-title="ثبت پیشنهاد برای بررسی" data-product-tour-description="دکمهٔ ذخیره، محصول را به‌صورت پیش‌نویس برای بررسی می‌فرستد؛ آن را همان لحظه عمومی نمی‌کند. راهنما خودش چیزی ثبت نمی‌کند—وقتی آماده بودید، دکمه را بزنید." class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 border-t border-gray-200 dark:border-gray-700 pt-6">
            @if($formStep === 1)
                @include('livewire.products.partials.continue-button')
            @else
                @include('livewire.products.partials.back-button')
            <button type="submit" wire:loading.attr="disabled" wire:target="submitProductForm, save, incomingImages, primary_image, gallery_images, product_video, video_poster" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition-colors flex items-center justify-center min-w-[150px] disabled:opacity-50">
                <span wire:loading.remove wire:target="submitProductForm, save, incomingImages, primary_image, gallery_images, product_video, video_poster">{{ $supplierDraftMode ? 'ذخیره و ادامه به قیمت‌گذاری' : ($isEdit ? 'ذخیره تغییرات' : 'ایجاد محصول') }}</span>
                <span wire:loading wire:target="submitProductForm, save, incomingImages, primary_image, gallery_images, product_video, video_poster">در حال پردازش...</span>
            </button>
            @endif
            <a href="{{ $supplierDraftMode ? route('supplier.products.index') : route('admin.products.index') }}" wire:navigate class="text-center text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2.5 px-4 transition-colors">{{ $supplierDraftMode ? 'بازگشت به محصولات' : 'انصراف' }}</a>
        </div>

    </form>
    @endif


</div>
