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

    <form wire:submit.prevent="save" 
          class="space-y-6"
          x-data="{ 
              scrollToFirstError() {
                  // اسکرول به Error Summary
                  const errorSummary = document.getElementById('error-summary');
                  if (errorSummary) {
                      errorSummary.scrollIntoView({ behavior: 'smooth', block: 'center' });
                      return;
                  }
                  
                  // اگر Error Summary نبود، به اولین فیلد با خطا اسکرول کن
                  const firstError = document.querySelector('.text-red-500, .border-red-500');
                  if (firstError) {
                      firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                      // فوکوس روی input
                      const input = firstError.closest('div')?.querySelector('input, select, textarea');
                      if (input) {
                          setTimeout(() => input.focus(), 500);
                      }
                  }
              }
          }"
          @submit.prevent="$wire.save().then(() => {}).catch(() => { setTimeout(() => scrollToFirstError(), 100); })">

        <!-- اطلاعات پایه -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2">اطلاعات اصلی</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div id="field-title">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">عنوان محصول *</label>
                    <input type="text" 
                           wire:model.live.debounce.500ms="title" 
                           class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm @error('title') border-red-500 dark:border-red-600 @enderror" 
                           required>
                    @error('title') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div id="field-slug">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        نامک (Slug) *
                        <button type="button" wire:click="resetSlugToAuto"
                                class="text-gray-400 hover:text-gray-600" title="بازتولید خودکار از عنوان">
                            <x-heroicon-o-arrow-path class="w-4 h-4" />
                        </button>
                    </label>
                    <input type="text" 
                           wire:model="slug" 
                           dir="ltr" 
                           class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm text-left @error('slug') border-red-500 dark:border-red-600 @enderror" 
                           required>
                    @error('slug') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- انتخابگر هوشمند دسته‌بندی -->
                <div x-data="{ open: false }" class="relative" id="field-category_id">
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
        </div>

        <!-- بخش واحد بسته‌بندی و تیراژبندی خودکار -->
        <div id="field-packaging" 
             class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 
                    @error('packaging_unit_type') border-red-500 dark:border-red-600 @enderror 
                    @error('quantity_per_packaging') border-red-500 dark:border-red-600 @enderror">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2 flex items-center gap-2">
                <x-heroicon-o-cube class="w-5 h-5 text-indigo-600"/>
                واحد بسته‌بندی و تیراژبندی
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                {{-- نوع واحد بسته‌بندی --}}
                <div>
                    <x-input-label for="packaging_unit_type" value="واحد فروش" />
                    <select wire:model.live="packaging_unit_type" 
                            id="packaging_unit_type" 
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="carton">کارتن</option>
                        <option value="jean">جین (پارچه)</option>
                        <option value="jowab">جورب (جوراب)</option>
                        <option value="package">بسته</option>
                        <option value="bundle">شیرینگ</option>
                        <option value="roll">طاقه (پارچه)</option>
                        <option value="piece">عدد</option>
                    </select>
                    <x-input-error :messages="$errors->get('packaging_unit_type')" class="mt-2" />
                </div>
                
                {{-- تعداد در هر واحد --}}
                <div>
                    <x-input-label for="quantity_per_packaging" value="تعداد در هر واحد بسته‌بندی" />
                    <div class="relative">
                        <input type="number"
                               wire:model.live="quantity_per_packaging"
                               id="quantity_per_packaging"
                               placeholder="مثلاً: 4"
                               min="1"
                               max="999999"
                               onwheel="this.blur()"
                               class="mt-1 block w-full px-3 py-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                @if($packaging_unit_type === 'roll')
                                    متر
                                @elseif($packaging_unit_type === 'jowab')
                                    جفت
                                @else
                                    عدد
                                @endif
                            </span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        💡 مثال: 4 عدد در کارتن، 100 متر در طاقه، 6 عدد در شیرینگ
                    </p>
                    <x-input-error :messages="$errors->get('quantity_per_packaging')" class="mt-2" />
                </div>
            </div>
            
            {{-- نمایش تیراژبندی خودکار (فقط اگر واحد و تعداد مشخص شده و هنوز اعمال نشده) --}}
            @if($quantity_per_packaging > 0 && !$tiersApplied)
                <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg border border-indigo-200 dark:border-indigo-800 mt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-indigo-800 dark:text-indigo-300 flex items-center gap-2">
                            <x-heroicon-o-sparkles class="w-4 h-4"/>
                            پیشنهاد تیراژبندی خودکار
                        </h3>
                        <div class="flex gap-2">
                            <button type="button" 
                                    wire:click="applyAutoTiers"
                                    class="text-xs px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition-colors flex items-center gap-1">
                                <x-heroicon-s-check class="w-4 h-4"/>
                                اعمال این تیراژها
                            </button>
                        </div>
                    </div>
                    
                    {{-- نمایش پیش‌نمایش تیراژها --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
                        @php
                            $previewTiers = $this->generateAutoTiers();
                        @endphp
                        @foreach($previewTiers as $tier)
                            <div class="bg-white dark:bg-gray-800 p-2 rounded border border-indigo-100 dark:border-indigo-900">
                                <div class="font-medium text-indigo-700 dark:text-indigo-400">{{ $tier['name'] }}</div>
                                <div class="text-gray-500 dark:text-gray-400 mt-1">
                                    @if($packaging_unit_type === 'roll')
                                        {{ number_format($tier['min_quantity']) }} تا {{ $tier['max_quantity'] ? number_format($tier['max_quantity']) : '∞' }} متر
                                    @elseif($packaging_unit_type === 'jowab')
                                        {{ number_format($tier['min_quantity']) }} تا {{ $tier['max_quantity'] ? number_format($tier['max_quantity']) : '∞' }} جفت
                                    @else
                                        {{ number_format($tier['min_quantity']) }} تا {{ $tier['max_quantity'] ? number_format($tier['max_quantity']) : '∞' }} عدد
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-3 flex items-center gap-1">
                        <x-heroicon-o-information-circle class="w-4 h-4"/>
                        برای اعمال این تیراژها، روی دکمه "اعمال این تیراژها" کلیک کنید. سپس می‌توانید آنها را ویرایش یا تیراژ جدید اضافه کنید.
                    </p>
                </div>
            @elseif($quantity_per_packaging <= 0)
                <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-lg border border-amber-200 dark:border-amber-800 mt-4">
                    <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300 text-sm">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5"/>
                        <span>برای مشاهده پیشنهاد تیراژبندی خودکار، ابتدا <strong>تعداد در هر واحد بسته‌بندی</strong> را مشخص کنید.</span>
                    </div>
                </div>
            @endif
        </div>

        <!-- بخش تیراژهای محصول -->
        <div id="field-quantity_tiers" 
             class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700
                    @error('quantity_tiers') border-red-500 dark:border-red-600 @enderror
                    @error('quantity_tiers.*') border-red-500 dark:border-red-600 @enderror">
            <div class="mb-4 border-b dark:border-gray-600 pb-2">
                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                    <x-heroicon-o-cube class="w-5 h-5 text-indigo-600"/>
                    تیراژهای محصول
                    @if($tiersApplied)
                        <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-2 py-1 rounded">
                            فعال
                        </span>
                    @endif
                </h2>
            </div>

            @if(!$tiersApplied)
                {{-- راهنما: ابتدا تیراژبندی خودکار را اعمال کنید --}}
                <div class="text-center py-8">
                    <x-heroicon-o-arrow-up class="w-12 h-12 mx-auto mb-3 text-gray-400 dark:text-gray-600"/>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        برای شروع، ابتدا <strong class="text-indigo-600 dark:text-indigo-400">تیراژبندی خودکار</strong> را در بخش بالا اعمال کنید.
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                        پس از اعمال، می‌توانید تیراژها را ویرایش کنید یا تیراژ جدید اضافه کنید.
                    </p>
                </div>
            @else
                {{-- 📋 راهنمای تیراژها --}}
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                    <div class="flex items-start gap-3">
                        <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"/>
                        <div class="text-sm text-blue-800 dark:text-blue-200 space-y-2">
                            <p class="font-semibold">📌 قوانین تیراژها:</p>
                            <ul class="list-disc list-inside space-y-1 mr-2">
                                <li><strong>حداقل:</strong> 3 تیراژ الزامی است</li>
                                <li><strong>حداکثر:</strong> 4 تیراژ می‌توانید تعریف کنید</li>
                                <li><strong>تیراژ زیر کارتن:</strong> اگر کارتن = 1، این تیراژ غیرفعال می‌شود</li>
                                <li><strong>حداقل تعداد:</strong> خودکار محاسبه می‌شود (حداکثر تیراژ قبلی + 1)</li>
                                <li><strong>حداکثر تعداد:</strong> باید بزرگتر از حداقل تعداد باشد</li>
                                <li><strong>آخرین تیراژ:</strong> حداکثر تعداد باید خالی باشد (بدون محدودیت)</li>
                                <li><strong>عدم تداخل:</strong> تیراژها نباید با هم همپوشانی داشته باشند</li>
                            </ul>
                            <div class="mt-3 p-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded text-xs">
                                <strong>⚡ نکته:</strong> وقتی "حداکثر تعداد" یک تیراژ را تغییر می‌دهید، "حداقل تعداد" تیراژ بعدی خودکار به‌روزرسانی می‌شود.
                            </div>
                            <div class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-xs">
                                <strong>⚠️ هشدار:</strong> اگر تغییری باعث تداخل یا بی‌منطقی شود، تیراژهای بعدی خودکار حذف می‌شوند.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- نمایش تعداد تیراژ فعلی --}}
                <div class="flex items-center justify-between mb-3 px-2">
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        تعداد تیراژها: 
                        <strong class="text-gray-900 dark:text-gray-100">{{ count($quantity_tiers) }}</strong> 
                        <span class="text-xs">(حداقل 3، حداکثر 4)</span>
                    </span>
                    @if(count($quantity_tiers) < 3)
                        <span class="text-xs bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-2 py-1 rounded">
                            ⚠️ حداقل 3 تیراژ لازم است
                        </span>
                    @elseif(count($quantity_tiers) === 4)
                        <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 px-2 py-1 rounded">
                            ⚠️ حداکثر تعداد رسیده
                        </span>
                    @else
                        <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 px-2 py-1 rounded">
                            ✅ معتبر
                        </span>
                    @endif
                </div>

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
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        نام تیراژ
                                        <span class="text-xs text-gray-500">(ثابت)</span>
                                    </label>
                                    <input type="text" 
                                           wire:model="quantity_tiers.{{ $index }}.name" 
                                           placeholder="مثال: تیراژ کم"
                                           readonly
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-100 dark:text-gray-400 rounded-md shadow-sm text-sm bg-gray-100 cursor-not-allowed">
                                    @error("quantity_tiers.{$index}.name") 
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        حداقل تعداد
                                        <span class="text-xs text-gray-500">(خودکار)</span>
                                    </label>
                                    <input type="number" 
                                           wire:model="quantity_tiers.{{ $index }}.min_quantity" 
                                           min="0"
                                           placeholder="100"
                                           readonly
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-100 dark:text-gray-400 rounded-md shadow-sm text-sm bg-gray-100 cursor-not-allowed">
                                    @error("quantity_tiers.{$index}.min_quantity") 
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span> 
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">حداکثر تعداد</label>
                                    <input type="number" 
                                           wire:model.live="quantity_tiers.{{ $index }}.max_quantity" 
                                           wire:change="updateNextTierMin({{ $index }})"
                                           min="0"
                                           placeholder="500"
                                           onwheel="this.blur()"
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

                    @if(count($quantity_tiers) < 4)
                        <button type="button" 
                                wire:click="addQuantityTier" 
                                class="w-full py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors flex items-center justify-center gap-2">
                            <x-heroicon-o-plus-circle class="w-5 h-5"/>
                            <span class="text-sm font-medium">
                                افزودن تیراژ جدید 
                                @if(count($quantity_tiers) < 3)
                                    <span class="text-red-600 dark:text-red-400">({{ 3 - count($quantity_tiers) }} تیراژ دیگر لازم است)</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">(اختیاری - {{ 4 - count($quantity_tiers) }} تیراژ دیگر مجاز)</span>
                                @endif
                            </span>
                        </button>
                    @else
                        <div class="text-center py-3 px-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <p class="text-sm text-amber-700 dark:text-amber-300 flex items-center justify-center gap-2">
                                <x-heroicon-s-exclamation-triangle class="w-5 h-5"/>
                                حداکثر 4 تیراژ قابل تعریف است
                            </p>
                        </div>
                    @endif
                </div>
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
                @include('components.attribute-value-selector', ['attribute' => $attribute])
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

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">عنوان SEO</label>
                <input type="text" wire:model.live="seo_title" maxlength="60"
                       class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm"
                       placeholder="حداکثر ۶۰ کاراکتر">
                <p class="text-xs text-gray-400 mt-1">{{ mb_strlen($seo_title ?? '') }}/60</p>
                @error('seo_title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">توضیحات SEO</label>
                <textarea wire:model.live="seo_description" rows="3" maxlength="160"
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
            <div class="mt-4 rounded-xl border-2 border-blue-200 dark:border-blue-800 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 p-5 shadow-sm">
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
                        
                        @if($seoImage)
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

        <!-- بخش تولید توضیحات با هوش مصنوعی -->
        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 p-6 rounded-lg shadow-sm border-2 border-purple-200 dark:border-purple-800">
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
            <div class="mb-6"
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
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-5 h-5 text-indigo-600"/>
                    توضیحات کوتاه
                    <span class="text-xs text-gray-500">(خلاصه محصول - 2-3 جمله)</span>
                </label>
                <textarea wire:model="short_description" 
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
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                    <x-heroicon-o-document-duplicate class="w-5 h-5 text-indigo-600"/>
                    توضیحات تخصصی کامل
                    <span class="text-xs text-gray-500">(توضیحات جامع محصول)</span>
                </label>
                <textarea wire:model="full_description" 
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
                    <div class="relative group rounded-lg overflow-hidden border-2 {{ $gallery['is_primary'] ? 'border-[#d4af37]' : ($gallery['is_seo_image'] ? 'border-blue-500' : 'border-white/10') }}">
                        <img src="{{ $gallery['full_url'] }}" class="w-full h-28 object-cover">

                        {{-- برچسب تصویر اصلی --}}
                        @if($gallery['is_primary'])
                        <span class="absolute top-1 right-1 text-xs bg-[#d4af37] text-black font-bold px-2 py-0.5 rounded">اصلی</span>
                        @endif
                        
                        {{-- برچسب تصویر SEO --}}
                        @if($gallery['is_seo_image'] ?? false)
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
                                <button type="button" wire:click="toggleSeoImage({{ $gallery['id'] }})"
                                        class="p-1 rounded {{ ($gallery['is_seo_image'] ?? false) ? 'bg-blue-500' : 'bg-blue-500/50 hover:bg-blue-500/80' }}"
                                        title="تصویر ویژه برای Google (SEO)">
                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </button>

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
