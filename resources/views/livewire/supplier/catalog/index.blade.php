<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('مدیریت کاتالوگ‌ها') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 space-y-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if($isAdmin && !$adminHasSupplier)
                <!-- پیام برای ادمین بدون supplier -->
                <div class="bg-yellow-50 border-r-4 border-yellow-400 p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <x-heroicon-s-exclamation-triangle class="h-6 w-6 text-yellow-400" />
                        </div>
                        <div class="mr-3 flex-1">
                            <h3 class="text-lg font-bold text-yellow-800">
                                مشخصات تامین‌کننده ثبت نشده است
                            </h3>
                            <p class="mt-2 text-sm text-yellow-700">
                                برای استفاده از سیستم کاتالوگ، ابتدا باید مشخصات تامین‌کننده خود را ثبت کنید.
                            </p>
                            <div class="mt-4">
                                <a href="{{ route('supplier.profile.edit') }}" 
                                   class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-bold rounded-lg transition-colors">
                                    <x-heroicon-s-pencil class="w-4 h-4 ml-2" />
                                    ثبت مشخصات تامین‌کننده
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

                <!-- لیست کاتالوگ‌های قبلی -->
                <div class="xl:col-span-5 2xl:col-span-4">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl flex flex-col h-full border border-gray-100">
                        <div class="p-5 border-b border-gray-100 bg-white flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <x-heroicon-o-book-open class="w-6 h-6 text-blue-600" />
                                کاتالوگ‌های من
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded-full">
                                    {{ $templates->total() }} مورد
                                </span>
                                @if(!$showForm)
                                <button wire:click="createNew" 
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-colors">
                                    <x-heroicon-s-plus class="w-4 h-4" />
                                    جدید
                                </button>
                                @endif
                            </div>
                        </div>

                        <div class="p-5 flex-1 bg-gray-50/30">
                            @if($templates->count() > 0)
                            <div class="space-y-4">
                                @foreach($templates as $template)
                                @php
                                $hasPageBreak = $template->settings['separate_categories'] ?? false;
                                $isAllCats = ($template->settings['categories'] ?? 'all') === 'all';
                                @endphp
                                <div class="bg-white border border-gray-200 rounded-xl p-4 hover:border-blue-400 hover:shadow-md transition-all group relative overflow-hidden {{ $editingTemplateId == $template->id ? 'ring-2 ring-blue-500 border-blue-500' : '' }}">
                                    <!-- نوار رنگی کنار کارت برای زیبایی -->
                                    <div class="absolute right-0 top-0 bottom-0 w-1 transition-colors {{ $editingTemplateId == $template->id ? 'bg-blue-600' : 'bg-gray-200 group-hover:bg-blue-500' }}"></div>

                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h4 class="font-bold text-gray-900 text-md truncate pr-2">{{ $template->name }}</h4>
                                            <div class="flex items-center gap-1 text-xs text-gray-500 mt-1 pr-2">
                                                <x-heroicon-o-calendar class="w-3.5 h-3.5" />
                                                {{ jdate($template->created_at)->format('%d %B %Y') }}
                                            </div>
                                        </div>

                                        <!-- عملیات سریع -->
                                        <div class="flex items-center space-x-1 space-x-reverse opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                            <button wire:click="editTemplate({{ $template->id }})" title="ویرایش" class="text-gray-400 hover:text-blue-600 transition p-1.5 bg-gray-50 rounded-md hover:bg-blue-50">
                                                <x-heroicon-m-pencil class="w-4 h-4" />
                                            </button>
                                            <button wire:click="deleteTemplate({{ $template->id }})" wire:confirm="آیا از حذف این کاتالوگ اطمینان دارید؟" title="حذف" class="text-gray-400 hover:text-red-600 transition p-1.5 bg-gray-50 rounded-md hover:bg-red-50">
                                                <x-heroicon-m-trash class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    @if(!empty($template->description))
                                    <p class="text-sm text-gray-600 mb-4 line-clamp-2 pr-2 leading-relaxed">
                                        {{ $template->description }}
                                    </p>
                                    @endif

                                    <!-- برچسب‌های وضعیت -->
                                    <div class="flex flex-wrap gap-2 mb-4 pr-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium {{ $isAllCats ? 'bg-indigo-50 text-indigo-700' : 'bg-purple-50 text-purple-700' }}">
                                            <x-heroicon-m-squares-plus class="w-3.5 h-3.5" />
                                            {{ $isAllCats ? 'همه دسته‌ها' : 'دسته‌های انتخابی' }}
                                        </span>

                                        @if($hasPageBreak)
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700">
                                            <x-heroicon-m-document-minus class="w-3.5 h-3.5" />
                                            تفکیک صفحات
                                        </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center justify-end mt-4 pt-4 border-t border-gray-100 gap-2">
                                        @if(($template->settings['cover_theme'] ?? 'default') === 'custom')
                                        <a href="{{ route('supplier.catalog.custom-cover', $template->id) }}" class="inline-flex items-center justify-center px-4 py-2 bg-amber-50 text-sm font-bold text-amber-700 rounded-lg hover:bg-amber-600 hover:text-white transition-colors gap-2">
                                            <x-heroicon-m-paint-brush class="w-4 h-4" />
                                            تنظیمات کاور
                                        </a>
                                        @endif
                                        
                                        <a href="{{ route('supplier.catalog.preview', $template->id) }}" target="_blank" class="inline-flex items-center justify-center flex-1 px-4 py-2 bg-gray-50 text-sm font-bold text-gray-700 rounded-lg hover:bg-blue-600 hover:text-white transition-colors gap-2">
                                            <x-heroicon-m-eye class="w-4 h-4" />
                                            مشاهده و دریافت کاتالوگ
                                        </a>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <div class="mt-6">
                                {{ $templates->links(data: ['scrollTo' => false]) }}
                            </div>
                            @else
                            <div class="flex flex-col items-center justify-center py-12 px-4 text-center bg-white rounded-xl border border-dashed border-gray-300">
                                <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4">
                                    <x-heroicon-o-document-text class="h-8 w-8 text-blue-500" />
                                </div>
                                <h4 class="text-base font-bold text-gray-900 mb-1">کاتالوگی یافت نشد</h4>
                                <p class="text-sm text-gray-500 max-w-sm">
                                    شما هنوز هیچ الگوی کاتالوگی ایجاد نکرده‌اید. با استفاده از فرم سمت چپ اولین کاتالوگ خود را بسازید.
                                </p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- فرم ایجاد/ویرایش کاتالوگ (ستون راست/بزرگتر) -->
                @if($showForm)
                <div class="xl:col-span-7 2xl:col-span-8" 
                     x-data 
                     x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl p-6 border border-gray-100 sticky top-6">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="p-2 {{ $editingTemplateId ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600' }} rounded-lg">
                                    @if($editingTemplateId)
                                    <x-heroicon-o-pencil-square class="w-6 h-6" />
                                    @else
                                    <x-heroicon-o-document-plus class="w-6 h-6" />
                                    @endif
                                </div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    {{ $editingTemplateId ? 'ویرایش کاتالوگ' : 'ساخت کاتالوگ جدید' }}
                                </h3>
                            </div>
                            <!-- در بخش بالای فرم -->
                            @if($editingTemplateId)
                            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-center">
                                    <x-heroicon-s-information-circle class="w-5 h-5 text-blue-500 ml-2" />
                                    <span class="text-blue-700 font-medium">در حال ویرایش کاتالوگ</span>
                                    <span class="mr-auto text-blue-600 text-sm">(برای بازگشت به لیست از دکمه "بازگشت به لیست" استفاده کنید)</span>
                                </div>
                            </div>
                            @endif

                            <!-- پیام موفقیت -->
                            @if($showSuccessMessage)
                            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                                 class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg animate-fade-in">
                                <div class="flex items-center">
                                    <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 ml-2" />
                                    <span class="text-green-700 font-medium">{{ $successMessage }}</span>
                                    <button @click="show = false" class="mr-auto text-green-600 hover:text-green-800">
                                        <x-heroicon-s-x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            @endif




                        </div>

                        <form wire:submit.prevent="saveTemplate" class="space-y-6">
                            
                            <!-- انتخاب تامین‌کنندگان (فقط برای ادمین) -->
                            @if($isAdmin && $suppliers->count() > 0)
                            <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-200">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2 mb-3">
                                    <x-heroicon-o-building-storefront class="w-5 h-5 text-purple-500" />
                                    انتخاب تامین‌کنندگان
                                </h4>
                                
                                <div x-data="{ open: false }" class="relative w-full">
                                    <button @click="open = !open" type="button" class="w-full bg-white border border-gray-300 rounded-lg shadow-sm pl-3 pr-4 py-2.5 text-right cursor-pointer focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 sm:text-sm transition-shadow hover:shadow">
                                        <span class="block truncate font-medium text-gray-700">
                                            @if(empty($selectedSuppliers))
                                                همه تامین‌کنندگان (پیش‌فرض)
                                            @else
                                                <span class="text-purple-600 font-bold">{{ count($selectedSuppliers) }}</span> تامین‌کننده انتخاب شده
                                            @endif
                                        </span>
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <x-heroicon-m-chevron-up-down class="h-5 w-5 text-gray-400" />
                                        </span>
                                    </button>

                                    <div x-show="open" @click.away="open = false" x-transition class="absolute z-20 mt-1 w-full bg-white shadow-xl max-h-60 rounded-xl py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto sm:text-sm custom-scrollbar" style="display: none;">
                                        <div class="p-2 space-y-1">
                                            @foreach($suppliers as $supplier)
                                            <label class="flex items-center p-2.5 hover:bg-purple-50 rounded-lg cursor-pointer transition-colors group">
                                                <input type="checkbox" wire:model.live="selectedSuppliers" value="{{ $supplier->id }}" class="rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500 mr-2 w-4 h-4">
                                                <span class="mr-3 text-sm text-gray-700 group-hover:text-purple-700">
                                                    {{ $supplier->company_name }}
                                                    @if($supplier->brand_name)
                                                        <span class="text-xs text-gray-500">({{ $supplier->brand_name }})</span>
                                                    @endif
                                                </span>
                                            </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('selectedSuppliers')" class="mt-2" />
                                <p class="text-xs text-gray-500 mt-2">
                                    <x-heroicon-s-information-circle class="w-4 h-4 inline ml-1" />
                                    محصولات تامین‌کنندگان انتخاب شده در کاتالوگ نمایش داده می‌شوند. اگر هیچ تامین‌کننده‌ای انتخاب نشود، محصولات همه تامین‌کنندگان نمایش داده می‌شود.
                                </p>
                            </div>
                            @endif

                            <!-- نام و توضیحات -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <x-input-label for="name" value="نام کاتالوگ *" class="font-semibold" />
                                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full bg-gray-50 focus:bg-white transition-colors" placeholder="مثلا: کاتالوگ بهاره" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="description" value="توضیحات (اختیاری)" class="font-semibold" />
                                    <x-text-input wire:model="description" id="description" type="text" class="mt-1 block w-full bg-gray-50 focus:bg-white transition-colors" placeholder="توضیحات کوتاه برای نمایش در کاور..." />
                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                </div>
                            </div>

                            <!-- انتخاب دسته‌بندی‌ها با Alpine Dropdown -->
                            <div class="space-y-3 bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2">
                                    <x-heroicon-o-squares-2x2 class="w-5 h-5 text-gray-500" />
                                    انتخاب دسته‌بندی‌ها
                                </h4>

                                <div class="flex items-center space-x-2 space-x-reverse mb-2">
                                    <input type="checkbox" wire:model.live="selectAllCategories" id="selectAllCategories" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-4 h-4 cursor-pointer">
                                    <label for="selectAllCategories" class="text-sm text-gray-700 font-medium cursor-pointer">همه دسته‌بندی‌ها (فقط دارای قیمت در صورت خاموش بودن)</label>
                                </div>

                                <div x-data="{ open: false }" class="relative w-full md:w-2/3">
                                    <button @click="open = !open" type="button" class="w-full bg-white border border-gray-300 rounded-lg shadow-sm pl-3 pr-4 py-2.5 text-right cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-shadow hover:shadow">
                                        <span class="block truncate font-medium text-gray-700">
                                            @if($selectAllCategories)
                                                همه دسته‌بندی‌ها انتخاب شده است
                                            @elseif(count($selectedCategories) === 0)
                                                هیچ دسته‌بندی انتخاب نشده
                                            @else
                                                <span class="text-blue-600 font-bold">{{ count($selectedCategories) }}</span> دسته انتخاب شده
                                            @endif
                                        </span>
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <x-heroicon-m-chevron-up-down class="h-5 w-5 text-gray-400" />
                                        </span>
                                    </button>

                                    <div x-show="open" @click.away="open = false" x-transition class="absolute z-20 mt-1 w-full bg-white shadow-xl max-h-60 rounded-xl py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto sm:text-sm custom-scrollbar" style="display: none;">
                                        <div class="sticky top-0 z-20 bg-white px-3 py-2 border-b border-gray-100">
                                            <input type="text" wire:model.live.debounce.300ms="categorySearch" class="w-full text-sm border-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-gray-50" placeholder="جستجوی دسته‌بندی...">
                                        </div>

                                        <div class="p-2 space-y-1">
                                            @forelse($this->availableCategories as $id => $title)
                                            <label class="flex items-center p-2.5 hover:bg-blue-50 rounded-lg cursor-pointer transition-colors group">
                                                <input type="checkbox" wire:model.live="selectedCategories" value="{{ $id }}" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 mr-2 w-4 h-4">
                                                <span class="mr-3 text-sm text-gray-700 group-hover:text-blue-700">{{ $title }}</span>
                                            </label>
                                            @empty
                                            <div class="text-sm text-gray-500 p-4 text-center flex flex-col items-center">
                                                <x-heroicon-o-magnifying-glass class="w-6 h-6 text-gray-300 mb-2" />
                                                دسته‌بندی یافت نشد.
                                            </div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('selectedCategories')" class="mt-2" />
                            </div>

                            <!-- انتخاب تیراژها و قیمت‌ها -->
                            <div class="space-y-3 bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2">
                                    <x-heroicon-o-currency-dollar class="w-5 h-5 text-gray-500" />
                                    انتخاب تیراژها و قیمت‌ها برای نمایش
                                </h4>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                                    <div class="flex items-start gap-2">
                                        <x-heroicon-s-information-circle class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
                                        <p class="text-xs text-blue-700">
                                            برای هر تیراژ می‌توانید یک یا چند نوع قیمت (نقدی، 30 روزه، 60 روزه و...) انتخاب کنید. 
                                            <strong>توجه:</strong> انتخاب تیراژهای بیشتر فضای بیشتری در کاتالوگ اشغال می‌کند.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2 space-x-reverse mb-3">
                                    <input type="checkbox" wire:model.live="selectAllPrices" id="selectAllPrices" 
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-4 h-4 cursor-pointer">
                                    <label for="selectAllPrices" class="text-sm text-gray-700 font-medium cursor-pointer">
                                        همه تیراژها و همه قیمت‌ها
                                    </label>
                                </div>

                                @php
                                    $tiers = $this->availableTiers;
                                    $priceTypes = $this->availablePriceTypes;
                                @endphp

                                <div class="space-y-3">
                                    @foreach($tiers as $tierIndex => $tierName)
                                    @php
                                        $tierSelected = in_array($tierIndex, $selectedTiers);
                                        $tierPricesRaw = $selectedPrices[$tierIndex] ?? [];
                                        $tierPrices = is_array($tierPricesRaw) ? $tierPricesRaw : [];
                                    @endphp
                                    
                                    <div class="bg-white border-2 rounded-lg overflow-hidden transition-all {{ $tierSelected ? 'border-blue-300 shadow-sm' : 'border-gray-200' }}">
                                        {{-- Header تیراژ --}}
                                        <div class="p-3 bg-gray-50 border-b {{ $tierSelected ? 'bg-blue-50 border-blue-200' : 'border-gray-200' }}">
                                            <label class="flex items-center cursor-pointer group">
                                                <input type="checkbox" 
                                                       wire:click="toggleTier({{ $tierIndex }})"
                                                       {{ $tierSelected ? 'checked' : '' }}
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-5 h-5 cursor-pointer">
                                                <div class="mr-3 flex-1 flex items-center justify-between">
                                                    <div>
                                                        <span class="text-sm font-bold {{ $tierSelected ? 'text-blue-900' : 'text-gray-700' }}">
                                                            {{ $tierName }}
                                                        </span>
                                                        @if($tierIndex === 1)
                                                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full mr-2">مرجع</span>
                                                        @endif
                                                    </div>
                                                    @if($tierSelected && count($tierPrices) > 0)
                                                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">
                                                            {{ count($tierPrices) }} قیمت
                                                        </span>
                                                    @endif
                                                </div>
                                            </label>
                                        </div>
                                        
                                        {{-- انواع قیمت برای این تیراژ --}}
                                        @if($tierSelected)
                                        <div class="p-3 space-y-2">
                                            @foreach($priceTypes as $priceTypeId => $priceTypeLabel)
                                            @php
                                                $priceSelected = in_array($priceTypeId, $tierPrices);
                                                $priceType = \App\Models\PriceType::find($priceTypeId);
                                                $isCheckType = $priceType && $priceType->code === 'check';
                                            @endphp
                                            <div>
                                                <label class="flex items-center p-2 hover:bg-blue-50 rounded-lg cursor-pointer transition-colors group">
                                                    <input type="checkbox" 
                                                           wire:click="toggleTierPrice({{ $tierIndex }}, {{ $priceTypeId }})"
                                                           {{ $priceSelected ? 'checked' : '' }}
                                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 mr-2 w-4 h-4">
                                                    <span class="mr-2 text-sm {{ $priceSelected ? 'text-blue-700 font-medium' : 'text-gray-700' }}">
                                                        {{ $priceTypeLabel }}
                                                    </span>
                                                    @if($isCheckType && $priceSelected)
                                                        @php
                                                            $selectedCheckPeriodsForTier = $selectedCheckPeriods[$tierIndex] ?? [];
                                                            $checkPeriodsCount = count($selectedCheckPeriodsForTier);
                                                        @endphp
                                                        <span class="mr-auto text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                                            {{ $checkPeriodsCount }} دوره
                                                        </span>
                                                    @endif
                                                </label>
                                                
                                                {{-- ✅ دوره‌های چک (اگر نوع قیمت "چک" انتخاب شده باشد) --}}
                                                @if($isCheckType && $priceSelected)
                                                <div class="mr-6 mt-1 p-2 bg-gray-50 rounded-lg border border-gray-200">
                                                    <div class="text-xs text-gray-600 mb-2 font-medium">دوره‌های چک:</div>
                                                    <div class="space-y-1">
                                                        @foreach($this->availableCheckPeriods as $checkPeriod)
                                                        @php
                                                            $checkPeriodSelected = in_array($checkPeriod->id, $selectedCheckPeriodsForTier);
                                                        @endphp
                                                        <label class="flex items-center p-1.5 hover:bg-white rounded cursor-pointer transition-colors">
                                                            <input type="checkbox" 
                                                                   wire:click="toggleCheckPeriod({{ $tierIndex }}, {{ $checkPeriod->id }})"
                                                                   {{ $checkPeriodSelected ? 'checked' : '' }}
                                                                   class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 mr-2 w-3.5 h-3.5">
                                                            <span class="text-xs {{ $checkPeriodSelected ? 'text-green-700 font-medium' : 'text-gray-600' }}">
                                                                {{ $checkPeriod->display_label }}
                                                            </span>
                                                        </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                            @endforeach
                                        </div>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                                
                                @if(count($selectedTiers) > 0)
                                <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <x-heroicon-s-light-bulb class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                                        <p class="text-xs text-amber-700">
                                            <strong>تعداد تیراژهای انتخاب شده:</strong> {{ count($selectedTiers) }} تیراژ
                                            <br>
                                            انتخاب بیشتر از 2 تیراژ ممکن است فضای محدود در کاتالوگ را پر کند و خوانایی را کاهش دهد.
                                        </p>
                                    </div>
                                </div>
                                @endif
                                
                                {{-- ✅ نمایش قیمت‌های با گارانتی --}}
                                <div class="mt-3 p-4 bg-purple-50 border-2 border-purple-200 rounded-xl">
                                    <div class="flex items-start gap-3">
                                        <input type="checkbox" 
                                               wire:model="includeWarranty" 
                                               id="includeWarranty"
                                               class="mt-1 rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500 w-5 h-5 cursor-pointer">
                                        <div class="flex-1">
                                            <label for="includeWarranty" class="font-bold text-purple-900 cursor-pointer flex items-center gap-2">
                                                <x-heroicon-o-shield-check class="w-5 h-5 text-purple-600" />
                                                نمایش قیمت‌های با گارانتی
                                            </label>
                                            <p class="text-xs text-purple-700 mt-2 leading-relaxed">
                                                با فعال‌سازی این گزینه، قیمت محصولاتی که دارای گارانتی هستند به همراه 
                                                هزینه گارانتی در کاتالوگ نمایش داده می‌شوند.
                                                <br>
                                                <span class="text-purple-600 font-medium">نکته:</span> 
                                                هزینه گارانتی برای هر تیراژ و نوع قیمت ممکن است متفاوت باشد.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <x-input-error :messages="$errors->get('selectedPrices')" class="mt-2" />
                            </div>

                            <!-- تنظیمات خروجی و چاپ -->
                            <div class="space-y-3 bg-blue-50/30 p-4 rounded-xl border border-blue-100">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2">
                                    <x-heroicon-o-printer class="w-5 h-5 text-blue-500" />
                                    تنظیمات خروجی و چاپ
                                </h4>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-blue-300 transition-colors cursor-pointer" onclick="document.getElementById('separateCategories').click()">
                                    <div class="flex items-start gap-3">
                                        <div class="pt-0.5">
                                            <input type="checkbox" wire:model="separateCategories" id="separateCategories" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-4 h-4 cursor-pointer" onclick="event.stopPropagation()">
                                        </div>
                                        <div>
                                            <label for="separateCategories" class="text-sm text-gray-900 font-bold cursor-pointer">تفکیک دسته‌بندی‌ها (Page Break)</label>
                                            <p class="text-xs text-gray-500 mt-1">با انتخاب این گزینه، هر دسته‌بندی در کاتالوگ (PDF) در یک صفحه جدید و مجزا آغاز می‌شود.</p>
                                        </div>
                                    </div>
                                    <div class="hidden sm:block">
                                        <x-heroicon-o-document-duplicate class="w-8 h-8 text-gray-300" />
                                    </div>
                                </div>
                                <!-- صفحه اول (کاور) -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-blue-300 transition-colors cursor-pointer" onclick="document.getElementById('show_cover_page').click()">
                                    <div class="flex items-start gap-3">
                                        <div class="pt-0.5">
                                            <input type="checkbox" wire:model="show_cover_page" id="show_cover_page" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-4 h-4 cursor-pointer" onclick="event.stopPropagation()">
                                        </div>
                                        <div>
                                            <label for="show_cover_page" class="text-sm text-gray-900 font-bold cursor-pointer">صفحه اول (کاور)</label>
                                            <p class="text-xs text-gray-500 mt-1">با انتخاب این گزینه، صفحه اول کاتالوگ شامل مشخصات تامین‌کننده نمایش داده می‌شود.</p>
                                        </div>
                                    </div>
                                    <div class="hidden sm:block">
                                        <x-heroicon-o-document class="w-8 h-8 text-gray-300" />
                                    </div>
                                </div>
                                
                                <!-- صفحه جداگانه برای کاور -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 bg-white border border-gray-200 rounded-lg shadow-sm hover:border-blue-300 transition-colors cursor-pointer" onclick="document.getElementById('cover_page_break').click()">
                                    <div class="flex items-start gap-3">
                                        <div class="pt-0.5">
                                            <input type="checkbox" wire:model="cover_page_break" id="cover_page_break" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 w-4 h-4 cursor-pointer" onclick="event.stopPropagation()">
                                        </div>
                                        <div>
                                            <label for="cover_page_break" class="text-sm text-gray-900 font-bold cursor-pointer">صفحه جداگانه برای کاور</label>
                                            <p class="text-xs text-gray-500 mt-1">با انتخاب این گزینه، صفحه کاور در یک صفحه جداگانه نمایش داده می‌شود و محصولات از صفحه بعدی شروع می‌شوند.</p>
                                        </div>
                                    </div>
                                    <div class="hidden sm:block">
                                        <x-heroicon-o-document-plus class="w-8 h-8 text-gray-300" />
                                    </div>
                                </div>

                                <!-- کیفیت تصاویر -->
                                <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                    <div class="flex items-start gap-3 mb-3">
                                        <x-heroicon-o-photo class="w-5 h-5 text-blue-500 mt-0.5" />
                                        <div class="flex-1">
                                            <label class="text-sm text-gray-900 font-bold">کیفیت تصاویر در کاتالوگ</label>
                                            <p class="text-xs text-gray-500 mt-1">انتخاب کیفیت تصاویر تأثیر مستقیم بر حجم و سرعت تولید PDF دارد.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-2 mr-8">
                                        @foreach([
                                            'sm' => 'کوچک (Small) - سریع‌تر، حجم کمتر',
                                            'md' => 'متوسط (Medium) - متعادل',
                                            'lg' => 'بزرگ (Large) - کیفیت بهتر',
                                            'original' => 'اصلی (Original) - بهترین کیفیت، حجم بیشتر'
                                        ] as $value => $label)
                                        <label class="flex items-center p-2 hover:bg-blue-50 rounded-lg cursor-pointer transition-colors group">
                                            <input type="radio" wire:model="imageQuality" value="{{ $value }}" class="text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <span class="mr-3 text-sm text-gray-700 group-hover:text-blue-700">{{ $label }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                <!-- طرح کاور -->
                                <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                                    <div class="flex items-start gap-3 mb-3">
                                        <x-heroicon-o-sparkles class="w-5 h-5 text-purple-500 mt-0.5" />
                                        <div class="flex-1">
                                            <label class="text-sm text-gray-900 font-bold">طرح صفحه اول (کاور)</label>
                                            <p class="text-xs text-gray-500 mt-1">طرح ظاهری صفحه اول کاتالوگ را انتخاب کنید.</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 mr-8">
                                        @php $coverThemes = ['default' => ['name' => 'پیش‌فرض', 'desc' => 'طرح ساده و کلاسیک', 'preview' => 'bg-gray-100 border-2 border-gray-300'], 'modern' => ['name' => 'مدرن', 'desc' => 'گرادینت بنفش و آبی', 'preview' => 'bg-gradient-to-br from-purple-500 to-blue-600'], 'creative' => ['name' => 'خلاقانه', 'desc' => 'طرح تیره با اشکال', 'preview' => 'bg-slate-900'], 'corporate' => ['name' => 'رسمی', 'desc' => 'طرح سازمانی آبی', 'preview' => 'bg-white border-4 border-blue-900'], 'custom' => ['name' => 'سفارشی', 'desc' => 'طراحی کاملاً قابل تنظیم', 'preview' => 'bg-gradient-to-br from-amber-400 to-orange-500']]; @endphp
                                        @foreach($coverThemes as $value => $theme)
                                        <label class="relative flex flex-col rounded-lg border-2 overflow-hidden cursor-pointer transition-all {{ $coverTheme === $value ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300' }}">
                                            <div class="h-16 {{ $theme['preview'] }} flex items-center justify-center"><span class="text-white font-bold text-xs drop-shadow-md">{{ $theme['name'] }}</span></div>
                                            <div class="p-2"><div class="flex items-center gap-2"><input type="radio" wire:model="coverTheme" value="{{ $value }}" class="text-blue-600 focus:ring-blue-500"><span class="text-xs font-medium text-gray-900">{{ $theme['name'] }}</span></div><p class="mt-0.5 text-[10px] text-gray-500">{{ $theme['desc'] }}</p></div>
                                            @if($coverTheme === $value)<div class="absolute top-1 right-1"><svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg></div>@endif
                                        </label>
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('coverTheme')" class="mt-2" />
                                </div>

                                    <x-input-error :messages="$errors->get('imageQuality')" class="mt-2" />
                                </div>

                            </div>

                            <!-- تنظیمات مرتب‌سازی جدید و پیشرفته -->
                            <div class="space-y-3">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2">
                                    <x-heroicon-o-bars-arrow-down class="w-5 h-5 text-gray-500" />
                                    ترتیب نمایش محصولات
                                </h4>

                                <div class="bg-gray-50 p-5 rounded-xl border border-gray-200 space-y-6">
                                    <!-- اولویت اول -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 relative">
                                        <div class="absolute -right-2 top-0 bottom-0 w-1 bg-blue-500 rounded-l-full hidden md:block"></div>
                                        <div>
                                            <x-input-label for="sortLevel1" value="اولویت اول" class="text-gray-600" />
                                            <select wire:model.live="sortLevel1" id="sortLevel1" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm">
                                                @foreach($this->availableSortFields as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label for="sortLevel1Dir" value="جهت اولویت اول" class="text-gray-600" />
                                            <select wire:model="sortLevel1Dir" id="sortLevel1Dir" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm">
                                                <option value="asc">صعودی (الف تا ی / ارزان تا گران)</option>
                                                <option value="desc">نزولی (ی تا الف / گران تا ارزان)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- اولویت دوم -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 relative">
                                        <div class="absolute -right-2 top-0 bottom-0 w-1 bg-gray-300 rounded-l-full hidden md:block"></div>
                                        <div>
                                            <x-input-label for="sortLevel2" value="اولویت دوم" class="text-gray-600" />
                                            <select wire:model="sortLevel2" id="sortLevel2" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm">
                                                @foreach($this->availableSortFields as $key => $label)
                                                @if($key !== $sortLevel1)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                                @endif
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label for="sortLevel2Dir" value="جهت اولویت دوم" class="text-gray-600" />
                                            <select wire:model="sortLevel2Dir" id="sortLevel2Dir" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm">
                                                <option value="asc">صعودی (الف تا ی / ارزان تا گران)</option>
                                                <option value="desc">نزولی (ی تا الف / گران تا ارزان)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- شماره‌های تماس سفارشی (برای کاور) -->
                            <div class="space-y-3 bg-green-50/30 p-4 rounded-xl border border-green-100">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2">
                                    <x-heroicon-o-phone class="w-5 h-5 text-green-500" />
                                    شماره‌های تماس در کاور (حداکثر 3 شماره)
                                </h4>
                                
                                @foreach($contactPhones as $index => $phone)
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label :for="'phone_label_'.$index" value="عنوان" class="text-xs" />
                                            <x-text-input 
                                                wire:model="contactPhones.{{ $index }}.label" 
                                                :id="'phone_label_'.$index" 
                                                type="text" 
                                                class="mt-1 block w-full text-sm" 
                                                placeholder="مثلاً: فروش، پشتیبانی" />
                                        </div>
                                        <div>
                                            <x-input-label :for="'phone_number_'.$index" value="شماره تماس" class="text-xs" />
                                            <x-text-input 
                                                wire:model="contactPhones.{{ $index }}.number" 
                                                :id="'phone_number_'.$index" 
                                                type="text" 
                                                class="mt-1 block w-full text-sm" 
                                                placeholder="021-12345678" />
                                        </div>
                                    </div>
                                    
                                    @if($index > 0)
                                    <button type="button" wire:click="removeContactPhone({{ $index }})" 
                                            class="mt-5 p-2 text-red-500 hover:bg-red-50 rounded-lg transition">
                                        <x-heroicon-s-trash class="w-4 h-4" />
                                    </button>
                                    @endif
                                </div>
                                @endforeach
                                
                                @if(count($contactPhones) < 3)
                                <button type="button" wire:click="addContactPhone" 
                                        class="text-sm text-green-600 hover:text-green-700 font-medium flex items-center gap-1">
                                    <x-heroicon-s-plus-circle class="w-4 h-4" />
                                    افزودن شماره تماس
                                </button>
                                @endif
                                
                                <p class="text-xs text-gray-500 mt-2">
                                    <x-heroicon-s-information-circle class="w-4 h-4 inline ml-1" />
                                    این شماره‌ها در صفحه اول (کاور) کاتالوگ نمایش داده می‌شوند.
                                </p>
                            </div>
                            
                            <!-- مشخصات محصول در نام دسته‌بندی -->
                            <div class="space-y-3 bg-amber-50/30 p-4 rounded-xl border border-amber-100">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2">
                                    <x-heroicon-o-tag class="w-5 h-5 text-amber-500" />
                                    نمایش مشخصات در نام محصول
                                </h4>
                                
                                <!-- مشخصه عمومی -->
                                <div>
                                    <x-input-label for="globalAttribute" value="مشخصه عمومی (برای همه محصولات)" class="font-semibold" />
                                    <select wire:model.live="globalAttribute" id="globalAttribute" 
                                            class="mt-1 block w-full md:w-2/3 border-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-lg shadow-sm text-sm">
                                        <option value="">-- انتخاب نکنید --</option>
                                        @foreach($this->availableAttributes as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">
                                        این مشخصه برای همه محصولات نمایش داده می‌شود (به جز دسته‌بندی‌هایی که مشخصات اختصاصی دارند).
                                    </p>
                                </div>
                                
                                <!-- نمایش عنوان مشخصات -->
                                <div class="mt-3">
                                    <label for="showAttributeLabels" class="flex items-center cursor-pointer">
                                        <x-checkbox wire:model="showAttributeLabels" id="showAttributeLabels" />
                                        <span class="ms-3 text-sm text-gray-600">نمایش عنوان مشخصات</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1 ml-7">
                                        اگر فعال باشد: <span class="font-mono text-xs">(رنگ: قرمز)</span> | اگر غیرفعال باشد: <span class="font-mono text-xs">(قرمز)</span>
                                    </p>
                                </div>
                                
                                <!-- مشخصات اختصاصی دسته‌بندی‌ها -->
                                @if(!empty($this->categoriesToConfigureAttributes))
                                <div class="mt-4 pt-4 border-t border-amber-200">
                                    <h5 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                        <x-heroicon-o-squares-plus class="w-4 h-4" />
                                        مشخصات اختصاصی هر دسته‌بندی (حداکثر 2 مشخصه)
                                    </h5>
                                    
                                    <div class="space-y-3 max-h-96 overflow-y-auto">
                                        @foreach($this->categoriesToConfigureAttributes as $categoryId => $categoryName)
                                            @php
                                                $categoryAttrs = $this->categoryAttributes[$categoryId] ?? [];
                                            @endphp
                                            
                                            <div class="bg-white p-3 rounded-lg border border-gray-200">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-sm font-medium text-gray-700">{{ $categoryName }}</span>
                                                    @if(empty($categoryAttrs))
                                                        <button type="button" 
                                                                wire:click="addCategoryAttribute({{ $categoryId }})"
                                                                class="text-xs text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1">
                                                            <x-heroicon-s-plus-circle class="w-3.5 h-3.5" />
                                                            افزودن مشخصه
                                                        </button>
                                                    @endif
                                                </div>
                                                
                                                @if(!empty($categoryAttrs))
                                                <div class="space-y-2">
                                                    @foreach($categoryAttrs as $index => $attrId)
                                                    <div class="flex items-center gap-2">
                                                        <select wire:model="categoryAttributes.{{ $categoryId }}.{{ $index }}" 
                                                                class="flex-1 text-xs border-gray-300 focus:border-amber-500 focus:ring-amber-500 rounded-lg">
                                                            <option value="">-- انتخاب مشخصه --</option>
                                                            @foreach($this->availableAttributes as $id => $name)
                                                                @if($id != $globalAttribute)
                                                                    <option value="{{ $id }}">{{ $name }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                        
                                                        <button type="button" 
                                                                wire:click="removeCategoryAttribute({{ $categoryId }}, {{ $index }})"
                                                                class="p-1.5 text-red-500 hover:bg-red-50 rounded transition">
                                                            <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                                        </button>
                                                    </div>
                                                    @endforeach
                                                    
                                                    @if(count($categoryAttrs) < 2)
                                                    <button type="button" 
                                                            wire:click="addCategoryAttribute({{ $categoryId }})"
                                                            class="text-xs text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1 mt-1">
                                                        <x-heroicon-s-plus-circle class="w-3.5 h-3.5" />
                                                        افزودن مشخصه دوم
                                                    </button>
                                                    @endif
                                                </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    
                                    @if($selectAllCategories)
                                    <p class="text-xs text-blue-600 bg-blue-50 p-2 rounded mt-2">
                                        <x-heroicon-s-information-circle class="w-4 h-4 inline ml-1" />
                                        همه دسته‌بندی‌های موجود در محصولات شما نمایش داده شده‌اند.
                                    </p>
                                    @endif
                                </div>
                                @endif
                                
                                <p class="text-xs text-amber-700 bg-amber-50 p-2 rounded">
                                    <x-heroicon-s-light-bulb class="w-4 h-4 inline ml-1" />
                                    مثال: لوازم جانبی (رنگ: قرمز، سایز: L) - مشخصات در پرانتز نمایش داده می‌شوند.
                                </p>
                            </div>
                            
                            <!-- دکمه‌های عملیات بهبود یافته -->
                            <div class="flex items-center justify-end space-x-3 space-x-reverse pt-6 border-t border-gray-100">
                                <!-- دکمه لغو/بستن -->
                                <button type="button" wire:click="confirmCancel"
                                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors duration-200 flex items-center shadow-sm hover:shadow">
                                    <x-heroicon-s-x-mark class="w-4 h-4 ml-2" />
                                    {{ $editingTemplateId ? 'لغو' : 'بستن' }}
                                </button>

                                <!-- دکمه ذخیره -->
                                <button type="button" wire:click="saveTemplate"
                                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200 flex items-center shadow-sm hover:shadow">
                                    <x-heroicon-s-check class="w-4 h-4 ml-2" />
                                    @if($editingTemplateId)
                                    بروزرسانی کاتالوگ
                                    @else
                                    ایجاد کاتالوگ جدید
                                    @endif
                                </button>
                            </div>






                        </form>

                    </div>
                </div>
                @endif
                
                <!-- پیام خالی بودن لیست (زمانی که فرم باز نیست) -->
                @if(!$showForm && $templates->count() === 0)
                <div class="xl:col-span-7 2xl:col-span-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl p-12 border border-gray-100 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mb-4">
                                <x-heroicon-o-document-plus class="h-10 w-10 text-blue-500" />
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">هنوز کاتالوگی ایجاد نکرده‌اید</h3>
                            <p class="text-sm text-gray-500 mb-6 max-w-md">
                                با کلیک بر روی دکمه "جدید" اولین کاتالوگ خود را ایجاد کنید و محصولات خود را به صورت حرفه‌ای نمایش دهید.
                            </p>
                            <button wire:click="createNew" 
                                    class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors shadow-sm hover:shadow">
                                <x-heroicon-s-plus-circle class="w-5 h-5" />
                                ایجاد اولین کاتالوگ
                            </button>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
            @endif
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f8fafc;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
    <!-- JavaScript بهبود یافته -->
    <script>
        document.addEventListener('livewire:initialized', () => {
            // تأیید لغو ویرایش
            @this.on('confirm-cancel', () => {
                if (confirm('آیا از انصراف از ویرایش اطمینان دارید؟ تغییرات ذخیره نشده از بین خواهند رفت.')) {
                    @this.call('hideForm');
                }
            });

            // پنهان کردن پیام موفقیت
            @this.on('hide-success-message', () => {
                setTimeout(() => {
                    @this.set('showSuccessMessage', false);
                }, 3000);
            });

            // اسکرول به فرم هنگام ویرایش
            @this.on('scroll-to-form', () => {
                setTimeout(() => {
                    const formElement = document.querySelector('[wire\\:submit\\.prevent="saveTemplate"]');
                    if (formElement) {
                        formElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 100);
            });
        });
    </script>
</div>
