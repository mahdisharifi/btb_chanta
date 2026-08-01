<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        <!-- هدر -->
        <div class="bg-indigo-50 border-b border-indigo-100 px-8 py-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="bg-indigo-600 p-3 rounded-lg text-white shadow-md">
                    <x-heroicon-o-document-text class="w-8 h-8" />
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-indigo-900">طراحی کاتالوگ هوشمند</h2>
                    <p class="text-indigo-600 mt-1 text-sm">شخصی‌سازی و دریافت کاتالوگ PDF محصولات</p>
                </div>
            </div>
        </div>

        <div class="p-8 space-y-8">

            <!-- بخش انتخاب قالب یا ایجاد جدید -->
            <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <x-heroicon-o-folder-open class="w-5 h-5 text-gray-500" />
                    شروع کار (بارگذاری قبلی یا کاتالوگ جدید)
                </label>
                <select wire:model.live="selectedCatalogId" class="w-full bg-white border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">-- طراحی یک کاتالوگ کاملاً جدید --</option>
                    @foreach($existingCatalogs as $catalog)
                    <option value="{{ $catalog->id }}">
                        {{ $catalog->name }} (ویرایش کاتالوگ ذخیره شده در {{ jdate($catalog->created_at)->format('Y/m/d') }})
                    </option>
                    @endforeach
                </select>
            </div>

            <form wire:submit.prevent="generate" class="space-y-8">

                <!-- عنوان کاتالوگ -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">عنوان کاتالوگ <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="مثال: کاتالوگ پاییزه محصولات">
                    @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- انتخاب دسته‌بندی‌ها -->
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-100 shadow-inner">
                        <div class="flex items-center gap-2 mb-4">
                            <x-heroicon-o-squares-2x2 class="w-5 h-5 text-indigo-500" />
                            <h3 class="font-bold text-gray-800">دسته‌بندی‌های مورد نظر</h3>
                        </div>

                        <div class="mb-3 pb-3 border-b border-gray-200">
                            <label class="flex items-center p-2 bg-indigo-50 border border-indigo-100 rounded-lg cursor-pointer hover:bg-indigo-100 transition-colors">
                                <input type="checkbox" wire:model.live="selectAllCategories" class="rounded border-indigo-400 text-indigo-600 shadow-sm focus:ring-indigo-500 w-5 h-5">
                                <span class="mr-3 text-sm font-bold text-indigo-800">انتخاب همه دسته‌بندی‌ها</span>
                            </label>
                        </div>

                        <div class="space-y-3 max-h-60 overflow-y-auto pl-2 custom-scrollbar">
                            @forelse($categories as $category)
                            <label class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition-colors">
                                <input type="checkbox" wire:model.live="selectedCategories" value="{{ $category->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 w-5 h-5">
                                <span class="mr-3 text-sm font-medium text-gray-700">{{ $category->name }}</span>
                            </label>
                            @empty
                            <p class="text-sm text-gray-500">دسته‌بندی فعالی یافت نشد.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- انتخاب انواع قیمت -->
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-100 shadow-inner">
                        <div class="flex items-center gap-2 mb-4">
                            <x-heroicon-o-currency-dollar class="w-5 h-5 text-green-500" />
                            <h3 class="font-bold text-gray-800">انواع قیمت برای نمایش</h3>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-60 overflow-y-auto pl-2 custom-scrollbar">
                            @foreach($priceTypes as $type)
                            <label class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-green-50 hover:border-green-200 transition-colors">
                                <input type="checkbox" wire:model="selectedPriceTypes" value="{{ $type->value }}" class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 w-5 h-5">
                                <span class="mr-3 text-sm font-medium text-gray-700">{{ $type->label() }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- تنظیمات مرتب‌سازی (Progressive Disclosure) -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-6 flex items-center gap-2 border-b pb-3">
                        <x-heroicon-o-bars-arrow-down class="w-5 h-5 text-gray-500" />
                        تنظیمات مرتب‌سازی
                    </h3>

                    <div class="space-y-4">
                        <!-- سطح اول (همیشه نمایش داده می‌شود) -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 flex flex-col md:flex-row md:items-center gap-4 transition-all">
                            <div class="flex items-center gap-2 min-w-[120px]">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs">1</span>
                                <label class="text-sm font-semibold text-gray-700">اولویت اول</label>
                            </div>
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <select wire:model.live="sortField1" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- مرتب‌سازی پیش‌فرض (بدون اولویت) --</option>
                                    @foreach($this->sortFields as $val => $label)
                                    <option value="{{ $val }}" @if(in_array($val, [$sortField2, $sortField3]) && $val !== '') disabled @endif>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="sortDir1" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if(!$sortField1) disabled @endif>
                                    <option value="asc">صعودی (قدیم به جدید / کم به زیاد / الف تا ی)</option>
                                    <option value="desc">نزولی (جدید به قدیم / زیاد به کم / ی تا الف)</option>
                                </select>
                            </div>
                        </div>

                        <!-- سطح دوم (فقط اگر سطح یک انتخاب شده باشد) -->
                        @if($sortField1)
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 flex flex-col md:flex-row md:items-center gap-4 transition-all animate-fade-in">
                            <div class="flex items-center gap-2 min-w-[120px]">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-700 font-bold text-xs">2</span>
                                <label class="text-sm font-semibold text-gray-700">اولویت دوم</label>
                            </div>
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <select wire:model.live="sortField2" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- انتخاب فیلد مرتب‌سازی بعدی --</option>
                                    @foreach($this->sortFields as $val => $label)
                                    <option value="{{ $val }}" @if(in_array($val, [$sortField1, $sortField3]) && $val !== '') disabled @endif>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="sortDir2" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if(!$sortField2) disabled @endif>
                                    <option value="asc">صعودی (قدیم به جدید / کم به زیاد / الف تا ی)</option>
                                    <option value="desc">نزولی (جدید به قدیم / زیاد به کم / ی تا الف)</option>
                                </select>
                            </div>
                        </div>
                        @endif

                        <!-- سطح سوم (فقط اگر سطح دوم انتخاب شده باشد) -->
                        @if($sortField2)
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 flex flex-col md:flex-row md:items-center gap-4 transition-all animate-fade-in">
                            <div class="flex items-center gap-2 min-w-[120px]">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 font-bold text-xs">3</span>
                                <label class="text-sm font-semibold text-gray-700">اولویت سوم</label>
                            </div>
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <select wire:model.live="sortField3" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- انتخاب فیلد مرتب‌سازی بعدی --</option>
                                    @foreach($this->sortFields as $val => $label)
                                    <option value="{{ $val }}" @if(in_array($val, [$sortField1, $sortField2]) && $val !== '') disabled @endif>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="sortDir3" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if(!$sortField3) disabled @endif>
                                    <option value="asc">صعودی (قدیم به جدید / کم به زیاد / الف تا ی)</option>
                                    <option value="desc">نزولی (جدید به قدیم / زیاد به کم / ی تا الف)</option>
                                </select>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- تنظیمات کاور -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                    <label class="flex items-center p-2 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="checkbox" wire:model="showCoverPage" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 w-5 h-5">
                        <div class="mr-4">
                            <span class="block text-sm font-bold text-gray-800">تولید صفحه کاور (Cover)</span>
                            <span class="block text-xs text-gray-500 mt-1">یک صفحه مجزا در ابتدای کاتالوگ حاوی نام کاتالوگ و تاریخ.</span>
                        </div>
                    </label>
                </div>

                <!-- دکمه ثبت -->
                <div class="flex items-center justify-end pt-4 border-t border-gray-100">
                    <button type="submit" class="inline-flex items-center px-8 py-3 bg-indigo-600 border border-transparent rounded-xl font-bold text-white shadow-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all disabled:opacity-50" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="generate" class="flex items-center gap-2">
                            <x-heroicon-o-eye class="w-5 h-5" />
                            ذخیره و مشاهده پیش‌نمایش کاتالوگ
                        </span>
                        <span wire:loading wire:target="generate" class="flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            در حال پردازش...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
