        <div id="field-packaging" data-product-tour-step="packaging" data-product-tour-order="80" data-product-tour-target="h2" data-product-tour-tiers-applied="{{ $tiersApplied ? 'true' : 'false' }}" data-product-tour-title="بسته‌بندی و واحد فروش" data-product-tour-description="این بخش مشخص می‌کند محصول با چه واحدی فروخته می‌شود و هر بسته چند واحد دارد. همین اطلاعات برای ساخت تیراژهای فروش استفاده می‌شود."
             class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700
                    @error('packaging_unit_type') border-red-500 dark:border-red-600 @enderror
                    @error('quantity_per_packaging') border-red-500 dark:border-red-600 @enderror">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4 border-b dark:border-gray-600 pb-2 flex items-center gap-2">
                <x-heroicon-o-cube class="w-5 h-5 text-indigo-600"/>
                واحد بسته‌بندی و تیراژبندی
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                {{-- نوع واحد بسته‌بندی --}}
                <div data-product-tour-step="packaging-unit" data-product-tour-order="90" data-product-tour-target="#packaging_unit_type" data-product-tour-title="واحد فروش" data-product-tour-description="واحد متناسب با محصول را انتخاب کنید: کارتن، جین، جفت، بسته، شیرینگ، طاقه یا عدد. برای طاقه تعداد بر حسب متر و برای جوراب بر حسب جفت تفسیر می‌شود.">
                    <x-input-label for="packaging_unit_type" value="واحد فروش (الزامی)" />
                    <select wire:model="packaging_unit_type"
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
                <div data-product-tour-step="packaging-quantity" data-product-tour-order="100" data-product-tour-target="#quantity_per_packaging" data-product-tour-title="تعداد در هر واحد بسته‌بندی" data-product-tour-description="تعداد داخل یک واحد فروش را وارد کنید؛ مثلاً ۴ عدد در هر کارتن یا ۱۰۰ متر در هر طاقه. پس از وارد کردن تعداد، پیشنهاد تیراژبندی ظاهر می‌شود.">
                    <x-input-label for="quantity_per_packaging" value="تعداد در هر واحد بسته‌بندی" />
                    <div class="relative">
                        <input type="number"
                               wire:model.blur="quantity_per_packaging"
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
                            <button type="button" data-product-tour-step="auto-tiers" data-product-tour-order="110" data-product-tour-title="پیشنهاد خودکار تیراژها" data-product-tour-description="پیشنهاد فقط بعد از تأیید شما اعمال می‌شود. با زدن این دکمه سه سطح اولیه ساخته می‌شود؛ سپس راهنما هر سطح و نحوهٔ تنظیم سقف آن را توضیح می‌دهد."
                                    wire:click="applyAutoTiers"
                                    class="text-xs px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition-colors flex items-center gap-1">
                                <x-heroicon-s-check class="w-4 h-4"/>
                                اعمال این تیراژها
                            </button>
                        </div>
                    </div>

                    @php
                        $previewTiers = [];
                    @endphp
                    {{-- نمایش پیش‌نمایش تیراژها --}}
                    @if($previewTiers)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
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

                    @endif
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-3 flex items-center gap-1">
                        <x-heroicon-o-information-circle class="w-4 h-4"/>
                        برای اعمال این تیراژها، روی دکمه "اعمال این تیراژها" کلیک کنید. سپس می‌توانید آنها را ویرایش یا تیراژ جدید اضافه کنید.
                    </p>
                </div>
            @elseif($quantity_per_packaging <= 0)
                <div data-product-tour-step="auto-tiers" data-product-tour-order="110" data-product-tour-target="#quantity_per_packaging" data-product-tour-title="پیشنهاد خودکار تیراژها" data-product-tour-description="ابتدا تعداد داخل هر واحد بسته‌بندی را وارد کنید. سپس دکمهٔ اعمال پیشنهاد را بزنید تا سه سطح اولیهٔ خرید ساخته شوند." class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-lg border border-amber-200 dark:border-amber-800 mt-4">
                    <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300 text-sm">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5"/>
                        <span>برای مشاهده پیشنهاد تیراژبندی خودکار، ابتدا <strong>تعداد در هر واحد بسته‌بندی</strong> را مشخص کنید.</span>
                    </div>
                </div>
            @endif
        </div>

        <!-- بخش تیراژهای محصول -->
        <div id="field-quantity_tiers" data-product-tour-step="tiers-overview" data-product-tour-order="120" data-product-tour-target="h2" data-product-tour-title="تیراژهای ساخته‌شده و قواعد فروش" data-product-tour-description="با اعمال پیشنهاد، سه سطح خرید ساخته شده‌اند. نام و حداقل تعداد هر سطح به‌صورت خودکار تعیین می‌شود؛ سقف‌ها را طوری تنظیم کنید که بازه‌ها هم‌پوشانی نداشته باشند و سقف آخر خالی بماند تا بدون محدودیت باشد."
             class="bg-white dark:bg-gray-800 p-3 sm:p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700
                    @error('quantity_tiers') border-red-500 dark:border-red-600 @enderror
                    @error('quantity_tiers.*') border-red-500 dark:border-red-600 @enderror">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b dark:border-gray-600 pb-2">
                <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                    <x-heroicon-o-cube class="w-5 h-5 text-indigo-600"/>
                    تیراژهای محصول
                    @if($tiersApplied)
                        <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-2 py-1 rounded">
                            فعال
                        </span>
                    @endif
                </h2>
                @if($tiersApplied)
                    <button type="button"
                            wire:click="clearAutoTiers"
                            wire:confirm="با غیرفعال‌کردن، تیراژهای فعلی حذف می‌شوند و برای ثبت محصول باید دوباره پیشنهاد تیراژ را اعمال کنید. ادامه می‌دهید؟"
                            wire:loading.attr="disabled"
                            wire:target="clearAutoTiers"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-red-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700">
                        غیرفعال‌کردن تیراژبندی
                    </button>
                @endif
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
                <details class="group mb-3 rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20">
                    <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-blue-800 dark:text-blue-200 sm:text-sm">راهنمای تیراژ و قوانین فروش</summary>
                    <div class="p-3 pt-1 sm:p-4">
                    <div class="flex items-start gap-3">
                        <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"/>
                        <div class="text-sm text-blue-800 dark:text-blue-200 space-y-2">
                            <p class="font-semibold">📌 قوانین تیراژها:</p>
                            <ul class="list-disc list-inside space-y-1 mr-2">
                                <li><strong>حداقل:</strong> 3 تیراژ الزامی است</li>
                                <li><strong>حداکثر:</strong> 4 تیراژ می‌توانید تعریف کنید</li>
                                <li><strong>تیراژ زیر کارتن:</strong> اگر کارتن = 1، این تیراژ غیرفعال می‌شود</li>
                                <li><strong>حداقل تعداد:</strong> خودکار محاسبه می‌شود (حداکثر تیراژ قبلی + 1)</li>
                                <li><strong>حداکثر تعداد:</strong> برای تیراژهای میانی باید بزرگتر از حداقل تعداد باشد</li>
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

                    <p class="px-3 pb-3 text-xs text-blue-800 dark:text-blue-200">
                                            @if(count($quantity_tiers) < 4)
                                                برای تیراژ آخر خالی بگذارید (بدون سقف)؛ برای افزودن تیراژ بعدی ابتدا سقف را وارد کنید.
                                            @else
                                                این آخرین تیراژ است؛ خالی بگذارید (بدون سقف).
                                            @endif
                    </p>
                </details>

                {{-- نمایش تعداد تیراژ فعلی --}}
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
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
                            تعداد مجاز
                        </span>
                    @endif
                </div>

                <div class="space-y-2 sm:space-y-3">
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
                        <div wire:key="tier-{{ $index }}" data-product-tour-step="tier-{{ $index }}" data-product-tour-order="{{ 130 + $index }}" data-product-tour-target="#quantity-tier-max-{{ $index }}" data-product-tour-title="تنظیم تیراژ {{ $index + 1 }}" data-product-tour-description="نام و حداقل تعداد این سطح خودکار محاسبه می‌شود. سقف تعداد را تنظیم کنید تا بازه‌ها پیوسته و بدون تداخل باشند؛ برای آخرین تیراژ، سقف را خالی بگذارید." class="flex items-center gap-1.5 p-2 sm:gap-3 sm:p-4 rounded-lg border {{ $hasPrices ? 'bg-amber-50 dark:bg-amber-900/10 border-amber-300 dark:border-amber-700' : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700' }}">
                            @if($hasPrices)
                                <div class="flex-shrink-0">
                                    <x-heroicon-s-lock-closed class="w-5 h-5 text-amber-600 dark:text-amber-400"/>
                                </div>
                            @endif
                            <div class="min-w-0 flex-1 grid grid-cols-3 gap-2 sm:gap-3">
                                <div>
                                    <label class="block text-[11px] sm:text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        نام تیراژ
                                        <span class="hidden sm:inline text-xs text-gray-500">(ثابت)</span>
                                    </label>
                                    <input type="text"
                                           aria-label="نام تیراژ {{ $index + 1 }}"
                                           wire:model="quantity_tiers.{{ $index }}.name"
                                           placeholder="مثال: تیراژ کم"
                                           readonly
                                           class="min-w-0 w-full px-2 py-2 sm:px-3 sm:py-2 text-xs sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-100 dark:text-gray-400 rounded-md shadow-sm bg-gray-100 cursor-not-allowed">
                                    @error("quantity_tiers.{$index}.name")
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] sm:text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        حداقل تعداد
                                        <span class="hidden sm:inline text-xs text-gray-500">(خودکار)</span>
                                    </label>
                                    <input id="quantity-tier-min-{{ $index }}" type="number"
                                           aria-label="حداقل تعداد {{ $tier['name'] }}"
                                           wire:model="quantity_tiers.{{ $index }}.min_quantity"
                                           min="0"
                                           placeholder="100"
                                           readonly
                                           class="min-w-0 w-full px-2 py-2 sm:px-3 sm:py-2 text-xs sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-100 dark:text-gray-400 rounded-md shadow-sm bg-gray-100 cursor-not-allowed">
                                    @error("quantity_tiers.{$index}.min_quantity")
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div>
                                    <label for="quantity-tier-max-{{ $index }}" class="block text-[11px] sm:text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">حداکثر تعداد</label>
                                    <input id="quantity-tier-max-{{ $index }}" type="number"
                                           wire:model.blur="quantity_tiers.{{ $index }}.max_quantity"
                                           min="0"
                                           placeholder="{{ $loop->last ? 'بدون سقف' : '500' }}"
                                           onwheel="this.blur()"
                                           @readonly($hasPrices || $index === 0)
                                           class="min-w-0 w-full px-2 py-2 sm:px-3 sm:py-2 text-xs sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm {{ $hasPrices ? 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed' : '' }}">
                                    @error('quantity_tiers')
                                        <span class="text-red-600 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span>
                                    @enderror
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
                            @elseif($index === 0)
                                <span class="shrink-0 p-2 text-gray-400" title="بازهٔ زیر کارتن از تعداد پایهٔ بسته‌بندی محاسبه می‌شود">
                                    <x-heroicon-s-lock-closed class="w-5 h-5"/>
                                    <span class="sr-only">تیراژ زیر کارتن ثابت است</span>
                                </span>
                            @else
                                <button type="button"
                                        wire:click="removeQuantityTier({{ $index }})"
                                        aria-label="حذف {{ $tier['name'] }}"
                                        class="shrink-0 p-2 sm:p-2.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors focus-visible:ring-2 focus-visible:ring-red-500">
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
                        <button type="button" data-product-tour-step="add-tier" data-product-tour-order="150" data-product-tour-title="افزودن تیراژ" data-product-tour-description="تا چهار تیراژ می‌توانید داشته باشید. برای افزودن سطح بعدی ابتدا سقف تیراژ آخر را وارد کنید؛ حداقل تعداد سطح جدید خودکار تنظیم می‌شود."
                                wire:click="addQuantityTier"
                                @disabled(! $this->canAddQuantityTier())
                                class="w-full py-2 sm:py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-gray-300">
                            <x-heroicon-o-plus-circle class="w-5 h-5"/>
                            <span class="text-sm font-medium">
                                @if(! $this->canAddQuantityTier())
                                    برای افزودن تیراژ، ابتدا سقف تیراژ آخر را وارد کنید
                                @else
                                    افزودن تیراژ جدید
                                    @if(count($quantity_tiers) < 3)
                                    <span class="text-red-600 dark:text-red-400">({{ 3 - count($quantity_tiers) }} تیراژ دیگر لازم است)</span>
                                    @else
                                    <span class="text-gray-500 dark:text-gray-400">(اختیاری - {{ 4 - count($quantity_tiers) }} تیراژ دیگر مجاز)</span>
                                    @endif
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

