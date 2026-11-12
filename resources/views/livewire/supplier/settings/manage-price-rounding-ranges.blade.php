<div data-price-rounding-ranges-guide
     data-product-tour-welcome-title="راهنمای تنظیم بازه‌های رُندکردن قیمت"
     data-product-tour-welcome-description="در این صفحه مشخص می‌کنید هر بازهٔ قیمت با چه روشی و چه گامی رُند شود. راهنما فقط صفحه را توضیح می‌دهد؛ هیچ بازه‌ای را ذخیره نمی‌کند و قیمت‌های فعلی را تغییر نمی‌دهد."
     data-product-tour-welcome-hint="تغییرات فرم تا زدن «ذخیره همه رنج‌ها» ذخیره نمی‌شوند. اعمال روی قیمت‌های موجود، مرحله‌ای جداگانه و نیازمند تأیید خودتان است."
     class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                {{-- Header --}}
                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 data-product-tour-welcome class="text-2xl font-bold text-gray-900">تنظیمات رُند کردن قیمت‌ها</h2>
                        <p class="mt-2 text-sm text-gray-600">
                            برای بازه‌های مختلف قیمت، روش و گام رُندکردن جداگانه تعیین کنید.
                        </p>
                    </div>
                    <x-interactive-guide-launcher />
                </div>

                {{-- راهنمای کامل --}}
                <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 data-product-tour-step="price-rounding-overview"
                        data-product-tour-order="10"
                        data-product-tour-title="منطق بازه‌های قیمت"
                        data-product-tour-description="قیمت هر محصول با قاعدهٔ بازهٔ فعالِ منطبق بررسی می‌شود. اگر بازهٔ فعالی پیدا نشود، سامانه به روش رُندکردن قدیمی حساب برمی‌گردد. نکتهٔ مرزی: موتور، سقفِ پرشده را شامل بازه نمی‌داند، اما حداقل بازهٔ بعدی با سقف قبلی + ۱ ساخته می‌شود؛ بنابراین قیمت دقیقاً برابر با سقف ممکن است از روش قدیمی پیروی کند."
                        data-product-tour-target="self"
                        class="font-bold text-blue-900 mb-2 flex items-center">
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
                    <div data-product-tour-step="price-rounding-coverage-warning"
                         data-product-tour-order="{{ 19 + (count($ranges) * 10) }}"
                         data-product-tour-title="پیام پوشش بازه‌ها"
                         data-product-tour-description="این هشدار می‌گوید کدام شرط هنوز برقرار نیست. شروع از صفر، نداشتن فاصله میان بازه‌ها و خالی‌بودن سقف آخرین بازه را بررسی کنید؛ تا رفع هشدار ذخیره را انجام ندهید."
                         class="mb-6 bg-red-50 border border-red-300 rounded-lg p-4">
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
                        <div data-price-rounding-range-card="{{ $index }}" class="border border-gray-300 rounded-lg p-4 bg-gray-50" wire:key="range-{{ $index }}">
                            <div class="flex items-start justify-between mb-3">
                                <h4 class="font-bold text-gray-900">رنج {{ $index + 1 }}</h4>
                                @if(count($ranges) > 1)
                                    <button 
                                        type="button"
                                        data-product-tour-step="price-rounding-range-{{ $index }}-remove"
                                        data-product-tour-order="{{ 26 + ($index * 10) }}"
                                        data-product-tour-title="حذف بازه {{ $index + 1 }}"
                                        data-product-tour-description="این دکمه بازه را از فرم فعلی حذف می‌کند؛ حذف تا وقتی «ذخیره همه رنج‌ها» را نزنید در پایگاه داده ثبت نمی‌شود. همیشه دست‌کم یک بازه لازم است."
                                        wire:click="removeRange({{ $index }})"
                                        class="text-red-600 hover:text-red-800 transition"
                                        title="حذف رنج">
                                        <x-heroicon-o-trash class="w-5 h-5" />
                                    </button>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                                {{-- حداقل قیمت --}}
                                <div data-product-tour-step="price-rounding-range-{{ $index }}-min"
                                     data-product-tour-order="{{ 20 + ($index * 10) }}"
                                     data-product-tour-title="حداقل قیمت بازه {{ $index + 1 }}"
                                     data-product-tour-description="@if($index === 0) اولین بازه از صفر تومان شروع می‌شود و این مقدار خودکار است؛ آن را نمی‌توان دستی تغییر داد. @else حداقل این بازه خودکار از سقف بازهٔ قبلی به‌علاوهٔ یک تومان ساخته می‌شود. اگر سقف قبلی خالی باشد، ابتدا همان سقف را وارد کنید تا این بازه از صفر شروع نشود. @endif"
                                     data-product-tour-target="input"
                                     data-product-tour-label="label">
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
                                        data-price-rounding-min-input
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
                                <div data-product-tour-step="price-rounding-range-{{ $index }}-max"
                                     data-product-tour-order="{{ 21 + ($index * 10) }}"
                                     data-product-tour-title="حداکثر قیمت بازه {{ $index + 1 }}"
                                     data-product-tour-description="@if($index === count($ranges) - 1) این آخرین بازه است؛ خالی‌گذاشتن سقف یعنی بازه تا بی‌نهایت ادامه دارد. اگر بازهٔ دیگری می‌سازید، این سقف را پر کنید؛ حداقل بازهٔ بعدی خودکار یک تومان بیشتر می‌شود. در محاسبه، مبلغی که دقیقاً برابر سقف باشد داخل این بازه نیست و به روش قدیمی برمی‌گردد. @else سقف این بازه را وارد کنید تا حداقل بازهٔ بعدی خودکار یک تومان بالاتر تنظیم شود. موتور، خود سقف پرشده را داخل بازه حساب نمی‌کند؛ پس قیمت دقیقاً برابر سقف از روش قدیمی پیروی می‌کند. @endif"
                                     data-product-tour-target="input"
                                     data-product-tour-label="label">
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
                                        data-price-rounding-max-input
                                    >
                                    @error("ranges.{$index}.max_price")
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- نوع رُند --}}
                                <div data-product-tour-step="price-rounding-range-{{ $index }}-method"
                                     data-product-tour-order="{{ 22 + ($index * 10) }}"
                                     data-product-tour-title="روش رُندکردن بازه {{ $index + 1 }}"
                                     data-product-tour-description="«به بالا» قیمت را به گام بعدی می‌برد، «به پایین» به گام قبلی می‌آورد و «نزدیک‌ترین» نزدیک‌ترین گام را انتخاب می‌کند. این انتخاب فقط برای قیمت‌های همین بازه است."
                                     data-product-tour-target="select"
                                     data-product-tour-label="label">
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
                                <div data-product-tour-step="price-rounding-range-{{ $index }}-step"
                                     data-product-tour-order="{{ 23 + ($index * 10) }}"
                                     data-product-tour-title="مبلغ مرجع بازه {{ $index + 1 }}"
                                     data-product-tour-description="این مبلغ اندازهٔ هر گام رُندکردن است؛ گزینه‌هایی از ۱۰۰ تا ۵۰٬۰۰۰ تومان دارید. برای نمونه، گام ۱٬۰۰۰ یعنی نتیجه روی مضرب‌های ۱٬۰۰۰ قرار می‌گیرد."
                                     data-product-tour-target="select"
                                     data-product-tour-label="label">
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
                                <div data-product-tour-step="price-rounding-range-{{ $index }}-active"
                                     data-product-tour-order="{{ 24 + ($index * 10) }}"
                                     data-product-tour-title="فعال‌بودن بازه {{ $index + 1 }}"
                                     data-product-tour-description="فقط بازه‌های فعال در روش بازه‌ای قیمت را رُند می‌کنند. اگر بازه را غیرفعال کنید یا هیچ بازهٔ فعالی قیمت را پوشش ندهد، روش رُندکردن قدیمی حساب به کار می‌رود."
                                     data-product-tour-target="input"
                                     data-product-tour-label="label"
                                     class="flex items-end">
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
                                <div data-product-tour-step="price-rounding-range-{{ $index }}-example"
                                     data-product-tour-order="{{ 25 + ($index * 10) }}"
                                     data-product-tour-title="نمونهٔ نتیجهٔ رُندکردن"
                                     data-product-tour-description="این پیش‌نمایش نمونه‌ای برای فهم روش و گام انتخاب‌شده است، نه قیمت واقعی یکی از محصولات. با تغییر روش یا مبلغ مرجع، نتیجهٔ نمونه هم عوض می‌شود."
                                     data-product-tour-target="p"
                                     class="mt-3 pt-3 border-t border-gray-200">
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
                        data-product-tour-step="price-rounding-add-range"
                        data-product-tour-order="{{ 20 + (count($ranges) * 10) }}"
                        data-product-tour-title="افزودن بازهٔ تازه"
                        data-product-tour-description="این دکمه یک بازهٔ جدید به فرم اضافه می‌کند. پیش از افزودن، برای بازهٔ فعلی سقف بگذارید؛ در غیر این صورت حداقل بازهٔ تازه از صفر می‌آید. پس از افزودن، راهنما فیلدهای بازهٔ جدید را هم جداگانه نشان می‌دهد."
                        wire:click="addRange"
                        data-price-rounding-add-range
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
                            data-product-tour-step="price-rounding-save"
                            data-product-tour-order="{{ 21 + (count($ranges) * 10) }}"
                            data-product-tour-title="ذخیرهٔ تنظیمات بازه‌ها"
                            data-product-tour-description="این دکمه شرط‌های صفحه را بررسی و بازه‌ها را ذخیره می‌کند: شروع از صفر، پیوستگی بر اساس سقف قبلی + ۱ و خالی‌بودن سقف آخر. ذخیره به‌تنهایی قیمت‌های قبلی را بازنویسی نمی‌کند. برای قیمت دقیقاً برابر با یک سقفِ پرشده، نکتهٔ مرزی ابتدای راهنما را در نظر بگیرید."
                            wire:click="saveAll"
                            :disabled="$wire.coverageWarning !== null"
                            class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            <x-heroicon-o-check class="w-5 h-5 ml-2" />
                            ذخیره همه رنج‌ها
                        </button>

                        @if($hasChanges)
                            <button 
                                type="button"
                                data-product-tour-step="price-rounding-cancel-changes"
                                data-product-tour-order="{{ 22 + (count($ranges) * 10) }}"
                                data-product-tour-title="لغو تغییرات ذخیره‌نشده"
                                data-product-tour-description="اگر هنوز رنج‌ها را ذخیره نکرده‌اید، این دکمه تنظیمات را از نو از نسخهٔ ذخیره‌شده بارگذاری می‌کند و تغییرات فعلی فرم را کنار می‌گذارد."
                                wire:click="cancelChanges"
                                class="inline-flex items-center px-4 py-3 bg-gray-200 border border-gray-300 rounded-md font-semibold text-sm text-gray-700 uppercase tracking-widest hover:bg-gray-300 transition">
                                <x-heroicon-o-x-mark class="w-5 h-5 ml-2" />
                                انصراف
                            </button>
                        @endif
                    </div>

                    {{-- این عملیات بعد از ذخیرهٔ رنج‌ها در دسترس است؛ حتی وقتی فعلاً
                         قیمت چکی برای بروزرسانی وجود ندارد، دکمه باید دیده شود تا
                         کاربر علت صفر بودن تعداد را به‌صورت واضح ببیند. --}}
                    <button 
                            type="button"
                            data-product-tour-step="price-rounding-apply-existing"
                            data-product-tour-order="{{ 23 + (count($ranges) * 10) }}"
                            data-product-tour-title="اعمال روی قیمت‌های موجود"
                            data-product-tour-description="این دکمه فقط پنجرهٔ تأیید را باز می‌کند؛ هنوز قیمتی تغییر نمی‌کند. با تأیید جداگانه، قیمت‌های چکیِ دارای مبنای نقدی و درصد محاسبه دوباره محاسبه می‌شوند. این کار از ذخیرهٔ تنظیمات جداست و برگشت خودکار ندارد."
                            wire:click="confirmUpdateAll"
                            wire:loading.attr="disabled"
                            data-price-rounding-update-open
                            class="inline-flex items-center px-4 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 transition">
                            <x-heroicon-o-arrow-path class="w-5 h-5 ml-2" />
                            اعمال رُند به {{ number_format($affectedPricesCount) }} قیمت
                    </button>
                </div>

                {{-- تذکر مهم --}}
                <div data-product-tour-step="price-rounding-final-note"
                     data-product-tour-order="{{ 30 + (count($ranges) * 10) }}"
                     data-product-tour-title="تفاوت قیمت‌های جدید و موجود"
                     data-product-tour-description="پس از ذخیره، قاعده روی قیمت‌های جدید یا ویرایش‌شده اثر می‌گذارد. قیمت‌های چکیِ از قبل ثبت‌شده فقط پس از زدن دکمهٔ اعمال و تأیید پنجرهٔ بعدی دوباره محاسبه می‌شوند. راهنما هیچ‌کدام از این دو عملیات را به‌جای شما انجام نمی‌دهد."
                     class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
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
        <div data-price-rounding-update-modal class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="cancelModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-right overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div data-product-tour-step="price-rounding-update-modal"
                         data-product-tour-order="{{ 24 + (count($ranges) * 10) }}"
                         data-product-tour-title="پنجرهٔ بازبینی عملیات"
                         data-product-tour-description="این پنجره تعداد قیمت‌های چکی‌ای را نشان می‌دهد که قرار است دوباره محاسبه شوند. تغییر قیمت فقط با انتخاب یکی از دو دکمهٔ پایین انجام می‌شود. اگر فقط می‌خواهید بدون تغییر از پنجره خارج شوید، انصراف را انتخاب کنید."
                         data-product-tour-target="#modal-title"
                         class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
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
                            data-product-tour-step="price-rounding-update-confirm"
                            data-product-tour-order="{{ 26 + (count($ranges) * 10) }}"
                            data-product-tour-title="تأیید بازنویسی قیمت‌ها"
                            data-product-tour-description="این انتخاب قیمت‌های چکیِ دارای مبنای نقدی و درصد محاسبه را با درصدها و قواعد فعلی دوباره محاسبه و ذخیره می‌کند و عملیات برگشت خودکار ندارد. فقط اگر واقعاً می‌خواهید قیمت‌های ثبت‌شده تغییر کنند، این دکمه را بزنید. راهنما هیچ‌گاه آن را خودکار اجرا نمی‌کند."
                            data-price-rounding-update-confirm
                            wire:click="updateAllPrices"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:mr-3 sm:w-auto sm:text-sm">
                            بله، بروزرسانی کن
                        </button>
                        <button 
                            type="button"
                            data-product-tour-step="price-rounding-update-cancel"
                            data-product-tour-order="{{ 25 + (count($ranges) * 10) }}"
                            data-product-tour-title="بستن پنجره بدون تغییر قیمت"
                            data-product-tour-description="با انصراف، پنجره بسته می‌شود و قیمت‌های فعلی دست‌نخورده می‌مانند. تنظیمات بازه‌ای که قبلاً ذخیره کرده‌اید باقی می‌مانند."
                            data-price-rounding-update-cancel
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
