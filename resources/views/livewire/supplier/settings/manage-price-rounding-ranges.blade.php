<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                {{-- Header --}}
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">تنظیمات رُند کردن قیمت‌ها</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        در این صفحه می‌توانید رنج‌های قیمتی مختلف را تعریف کنید و برای هر رنج، نوع و مبلغ رُند کردن را مشخص کنید.
                    </p>
                </div>

                {{-- راهنمای کامل --}}
                <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-bold text-blue-900 mb-2 flex items-center">
                        <x-heroicon-o-information-circle class="w-5 h-5 ml-2" />
                        راهنمای استفاده
                    </h3>
                    <ul class="text-sm text-blue-800 space-y-2 mr-6 list-disc">
                        <li><strong>شروع از صفر:</strong> اولین رنج باید از قیمت صفر شروع شود.</li>
                        <li><strong>بدون شکاف:</strong> رنج‌ها باید بدون فاصله به هم متصل باشند. حداقل قیمت هر رنج خودکار محاسبه می‌شود (حداکثر رنج قبلی + ۱).</li>
                        <li><strong>رنج بی‌نهایت:</strong> آخرین رنج باید "حداکثر قیمت" خالی باشد تا تمام قیمت‌های بالاتر را پوشش دهد.</li>
                        <li><strong>مثال:</strong> رنج ۱ (از ۰ تا ۵۰۰٬۰۰۰ | رُند ۱۰۰ | به بالا) + رنج ۲ (از ۵۰۰٬۰۰۱ تا بی‌نهایت | رُند ۵٬۰۰۰ | به پایین)</li>
                        <li><strong>نوع رُند:</strong> 
                            <span class="font-semibold">به بالا</span> = همیشه بالاتر (۴۹٬۶۰۰ → ۵۰٬۰۰۰) | 
                            <span class="font-semibold">به پایین</span> = همیشه پایین‌تر (۴۹٬۶۰۰ → ۴۹٬۵۰۰) | 
                            <span class="font-semibold">نزدیک‌ترین</span> = به نزدیک‌ترین عدد
                        </li>
                        <li><strong>مبلغ مرجع:</strong> گام رُند کردن (۱۰۰، ۵۰۰، ۱٬۰۰۰، ۵٬۰۰۰ و...)</li>
                    </ul>
                </div>

                {{-- هشدار پوشش ناقص --}}
                @if($coverageWarning)
                    <div class="mb-6 bg-red-50 border border-red-300 rounded-lg p-4">
                        <div class="flex items-start">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-600 ml-2 mt-0.5" />
                            <div>
                                <h4 class="font-bold text-red-900">خطا در پوشش رنج‌ها</h4>
                                <p class="text-sm text-red-800 mt-1">{{ $coverageWarning }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- لیست رنج‌ها --}}
                <div class="space-y-4">
                    @foreach($ranges as $index => $range)
                        <div class="border border-gray-300 rounded-lg p-4 bg-gray-50" wire:key="range-{{ $index }}">
                            <div class="flex items-start justify-between mb-3">
                                <h4 class="font-bold text-gray-900">رنج {{ $index + 1 }}</h4>
                                @if(count($ranges) > 1)
                                    <button 
                                        type="button"
                                        wire:click="removeRange({{ $index }})"
                                        class="text-red-600 hover:text-red-800 transition"
                                        title="حذف رنج">
                                        <x-heroicon-o-trash class="w-5 h-5" />
                                    </button>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                {{-- حداقل قیمت --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        حداقل قیمت (تومان)
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="number" 
                                        wire:model.live="ranges.{{ $index }}.min_price"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $index > 0 ? 'bg-gray-100' : '' }}"
                                        placeholder="0"
                                        min="0"
                                        step="1"
                                        onwheel="this.blur()"
                                        readonly
                                    >
                                    @error("ranges.{$index}.min_price")
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                    @if($index > 0)
                                        <p class="text-xs text-gray-500 mt-1">خودکار (max قبلی + 1)</p>
                                    @endif
                                </div>

                                {{-- حداکثر قیمت --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        حداکثر قیمت (تومان)
                                        @if($index === count($ranges) - 1)
                                            <span class="text-xs text-gray-500">(خالی = بی‌نهایت)</span>
                                        @endif
                                    </label>
                                    <input 
                                        type="number" 
                                        wire:model.live="ranges.{{ $index }}.max_price"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="بی‌نهایت"
                                        min="0"
                                        step="1"
                                        onwheel="this.blur()"
                                    >
                                    @error("ranges.{$index}.max_price")
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- نوع رُند --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        نوع رُند کردن
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <select 
                                        wire:model.live="ranges.{{ $index }}.rounding_method"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach($this->roundingMethods as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error("ranges.{$index}.rounding_method")
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- مبلغ مرجع --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        مبلغ مرجع (گام)
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <select 
                                        wire:model.live="ranges.{{ $index }}.rounding_step"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach($this->allowedSteps as $step)
                                            <option value="{{ $step }}">{{ number_format($step) }} تومان</option>
                                        @endforeach
                                    </select>
                                    @error("ranges.{$index}.rounding_step")
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- وضعیت فعال --}}
                                <div class="flex items-end">
                                    <label class="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            wire:model.live="ranges.{{ $index }}.is_active"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <span class="mr-2 text-sm text-gray-700">فعال</span>
                                    </label>
                                </div>
                            </div>

                            {{-- نمایش نمونه --}}
                            @if(!empty($range['rounding_step']) && !empty($range['rounding_method']))
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    <p class="text-xs text-gray-600">
                                        <strong>نمونه:</strong>
                                        @php
                                            $samplePrice = $range['min_price'] + ($range['rounding_step'] * 2.3);
                                            $step = $range['rounding_step'];
                                            $method = $range['rounding_method'];
                                            
                                            if ($method === 'ceil') {
                                                $rounded = ceil($samplePrice / $step) * $step;
                                            } elseif ($method === 'floor') {
                                                $rounded = floor($samplePrice / $step) * $step;
                                            } else {
                                                $rounded = round($samplePrice / $step) * $step;
                                            }
                                        @endphp
                                        قیمت {{ number_format($samplePrice) }} تومان → {{ number_format($rounded) }} تومان
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- دکمه افزودن رنج --}}
                <div class="mt-4">
                    <button 
                        type="button"
                        wire:click="addRange"
                        class="inline-flex items-center px-4 py-2 bg-gray-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 transition">
                        <x-heroicon-o-plus class="w-4 h-4 ml-2" />
                        افزودن رنج جدید
                    </button>
                </div>

                {{-- دکمه‌های عملیات --}}
                <div class="mt-6 flex items-center justify-between border-t pt-6">
                    <div class="flex gap-3">
                        <button 
                            type="button"
                            wire:click="saveAll"
                            :disabled="$wire.coverageWarning !== null"
                            class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            <x-heroicon-o-check class="w-5 h-5 ml-2" />
                            ذخیره همه رنج‌ها
                        </button>

                        @if($hasChanges)
                            <button 
                                type="button"
                                wire:click="cancelChanges"
                                class="inline-flex items-center px-4 py-3 bg-gray-200 border border-gray-300 rounded-md font-semibold text-sm text-gray-700 uppercase tracking-widest hover:bg-gray-300 transition">
                                <x-heroicon-o-x-mark class="w-5 h-5 ml-2" />
                                انصراف
                            </button>
                        @endif
                    </div>

                    @if(!$hasChanges && $affectedPricesCount > 0)
                        <button 
                            type="button"
                            wire:click="confirmUpdateAll"
                            class="inline-flex items-center px-4 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 transition">
                            <x-heroicon-o-arrow-path class="w-5 h-5 ml-2" />
                            اعمال رُند به {{ number_format($affectedPricesCount) }} قیمت
                        </button>
                    @endif
                </div>

                {{-- تذکر مهم --}}
                <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <x-heroicon-o-light-bulb class="w-5 h-5 text-yellow-600 ml-2 mt-0.5" />
                        <div>
                            <h4 class="font-bold text-yellow-900">نکته مهم</h4>
                            <ul class="text-sm text-yellow-800 mt-1 space-y-1 mr-4 list-disc">
                                <li>پس از ذخیره رنج‌ها، دکمه "اعمال رُند به قیمت‌ها" ظاهر می‌شود.</li>
                                <li>این تنظیمات فقط بر قیمت‌های جدید یا ویرایش‌شده تأثیر می‌گذارد، مگر اینکه دکمه "اعمال رُند" را بزنید.</li>
                                <li>توصیه می‌شود این تنظیمات را <strong>قبل از افزودن کالاها</strong> انجام دهید.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal تأیید بروزرسانی همه --}}
    @if($showUpdateAllConfirm)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="cancelModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-right overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10 sm:ml-3">
                                <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-yellow-600" />
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:mr-4 sm:text-right">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    تأیید بروزرسانی همه قیمت‌ها
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        آیا مطمئن هستید که می‌خواهید <strong>{{ number_format($affectedPricesCount) }}</strong> قیمت را بر اساس رنج‌های جدید بروزرسانی کنید؟
                                    </p>
                                    <p class="text-sm text-red-600 mt-2">
                                        این عملیات غیرقابل بازگشت است!
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button 
                            type="button"
                            wire:click="updateAllPrices"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:mr-3 sm:w-auto sm:text-sm">
                            بله، بروزرسانی کن
                        </button>
                        <button 
                            type="button"
                            wire:click="cancelModal"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                            انصراف
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
