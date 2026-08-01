<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800">بروزرسانی سریع قیمت‌ها (آپلود اکسل)</h2>
        <button wire:click="downloadTemplate" class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200 transition-colors shadow-sm flex items-center gap-2">
            <x-heroicon-o-document-arrow-down class="w-5 h-5" />
            <span>دانلود فایل محصولات فعال</span>
        </button>
    </div>

    @if(isset($pendingBatch) && empty($rowErrors))
    <!-- باکس فایل‌های در انتظار تایید (زمانی که خطایی در کار نباشد) -->
    <div class="relative overflow-hidden bg-white border-r-4 border-emerald-500 rounded-xl shadow-md p-5 sm:p-6 transition-all duration-300 hover:shadow-lg">
        <div class="relative flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
            <div class="flex items-start sm:items-center gap-4">
                <div class="flex-shrink-0 p-3 bg-emerald-100 text-emerald-600 rounded-full shadow-sm">
                    <x-heroicon-o-check-circle class="w-8 h-8" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">فایل شما با موفقیت پردازش شد</h3>
                    <p class="text-sm text-gray-600 mt-1 font-medium">
                        آپلود به اتمام رسیده و سیستم منتظر تایید نهایی شماست.
                    </p>
                    <div class="flex flex-wrap items-center gap-3 mt-3 text-xs text-gray-600">
                        <span class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-2.5 py-1.5 rounded-md shadow-sm">
                            <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                            تعداد رکورد: <span class="font-bold text-gray-800">{{ $pendingBatch->items()->count() }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-2.5 py-1.5 rounded-md shadow-sm">
                            <x-heroicon-o-clock class="w-4 h-4 text-gray-400" />
                            زمان آپلود: <span class="font-bold text-gray-800" dir="ltr">{{ jdate($pendingBatch->created_at)->format('Y/m/d H:i') }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="w-full sm:w-auto mt-2 sm:mt-0 border-t sm:border-t-0 border-gray-100 pt-4 sm:pt-0">
                <a href="{{ route('supplier.fast-pricing.review', ['batchId' => $pendingBatch->id]) }}" class="group relative flex w-full sm:w-auto items-center justify-center gap-2 px-6 py-3 text-sm font-bold text-emerald-800 transition-all bg-emerald-100 rounded-lg shadow-sm border border-emerald-200 hover:bg-emerald-600 hover:text-white hover:shadow-md focus:ring-4 focus:ring-emerald-200 overflow-hidden">
                    <span class="absolute inset-0 w-full h-full -mt-1 rounded-lg opacity-30 bg-gradient-to-b from-transparent via-transparent to-black group-hover:opacity-10 transition-opacity"></span>
                    <x-heroicon-o-eye class="relative w-5 h-5 transition-transform group-hover:scale-110" />
                    <span class="relative">مشاهده و تایید نهایی</span>
                    <span class="absolute top-0 right-0 flex w-3 h-3 -mt-1 -mr-1">
                        <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-emerald-400"></span>
                        <span class="relative inline-flex w-3 h-3 rounded-full bg-emerald-500"></span>
                    </span>
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- باکس نمایش خطاهای ردیف‌ها -->
    @if(!empty($rowErrors))
    <div class="bg-white p-6 rounded-xl shadow-sm border border-red-200">
        <div class="flex items-center gap-3 mb-4 text-red-600">
            <x-heroicon-o-exclamation-triangle class="w-8 h-8" />
            <h3 class="text-lg font-bold">گزارش خطاهای پردازش فایل اکسل</h3>
        </div>

        <p class="text-sm text-gray-600 mb-4 font-medium">
            فایل شما بررسی شد اما در برخی ردیف‌ها خطاهایی وجود داشت که نادیده گرفته شدند. لطفاً موارد زیر را بررسی کنید:
        </p>

        <div class="bg-red-50 p-4 rounded-lg mb-4 max-h-[450px] overflow-y-auto border border-red-100 divide-y divide-red-100">
            @foreach($rowErrors as $errorData)
            <div class="py-3 flex flex-col sm:flex-row gap-4 first:pt-0 last:pb-0">
                <!-- تصویر محصول -->
                <div class="w-12 h-12 shrink-0 rounded-lg bg-white border border-red-100 flex items-center justify-center overflow-hidden">
                    @if(!empty($errorData['image_url']))
                    <img src="{{ $errorData['image_url'] }}" alt="{{ $errorData['product_name'] }}" class="w-full h-full object-cover">
                    @else
                    <x-heroicon-o-photo class="w-5 h-5 text-gray-300" />
                    @endif
                </div>

                <!-- جزئیات خطا -->
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h4 class="font-bold text-gray-800 text-sm">{{ $errorData['product_name'] }}</h4>
                        <span class="text-[11px] text-red-700 font-bold px-2 py-0.5 bg-red-100 rounded-md">ردیف {{ $errorData['row'] }}</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 mt-1.5 text-xs text-red-700 font-medium">
                        @foreach($errorData['errors'] as $err)
                        <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endforeach
        </div>

        @if($successfulRows > 0 && $processedBatchId)
        <div class="flex flex-col sm:flex-row items-center justify-between mt-5 pt-4 border-t border-gray-100 gap-4">
            <p class="text-sm font-medium text-gray-700">
                <span class="inline-flex items-center justify-center min-w-[1.5rem] px-1.5 h-6 rounded-full bg-emerald-100 text-emerald-700 font-bold ml-1">{{ $successfulRows }}</span>
                ردیف بدون مشکل پردازش شدند و آماده تایید هستند.
            </p>
            <a href="{{ route('supplier.fast-pricing.review', ['batchId' => $processedBatchId]) }}" class="flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition-colors w-full sm:w-auto">
                <span>نادیده گرفتن خطاها و ادامه</span>
                <x-heroicon-o-arrow-left class="w-4 h-4" />
            </a>
        </div>
        @endif
    </div>
    @endif
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 shadow-sm">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <!-- استفاده از blade-heroicons طبق دستورالعمل -->
                <x-heroicon-o-information-circle class="h-6 w-6 text-blue-600" />
            </div>
            <div class="mr-3">
                <h3 class="text-sm font-medium text-blue-800">
                    راهنمای تکمیل فایل قیمت‌ها
                </h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>سلول خالی:</strong> اگر قیمت یک ستون را خالی بگذارید، آن قیمت <b>هیچ تغییری</b> نمی‌کند (مناسب برای آپدیت فقط یک نوع قیمت).</li>
                        <li><strong>عدد صفر (0):</strong> اگر در سلول قیمت عدد 0 وارد کنید، آن نوع قیمت برای کالا <b>غیرفعال (ناموجود)</b> می‌شود.</li>
                        <li><strong>اعداد مثبت:</strong> قیمت جدید برای کالا ثبت و آن نوع قیمت فعال می‌شود.</li>
                        <li>لطفاً از وارد کردن حروف یا نماد (مانند ریال، تومان، کاما) در سلول قیمت‌ها خودداری کنید.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 relative">
        <!-- نمایش مراحل کار به صورت بصری -->
        <div class="flex items-center gap-2 mb-6 text-sm font-medium text-gray-500 border-b border-gray-100 pb-4">
            <span class="flex items-center gap-1 {{ !$file ? 'text-indigo-600 font-bold' : 'text-gray-400' }}">
                <span class="w-6 h-6 rounded-full flex items-center justify-center border-2 {{ !$file ? 'border-indigo-600 bg-indigo-50' : 'border-gray-300' }}">۱</span>
                انتخاب فایل
            </span>
            <x-heroicon-o-chevron-left class="w-4 h-4" />
            <span class="flex items-center gap-1 {{ $file ? 'text-indigo-600 font-bold' : '' }}">
                <span class="w-6 h-6 rounded-full flex items-center justify-center border-2 {{ $file ? 'border-indigo-600 bg-indigo-50' : 'border-gray-300' }}">۲</span>
                ارسال و استخراج
            </span>
        </div>

        <form wire:submit.prevent="processFile">
            <div class="mb-6">
                <!-- حالت ۱: هنوز فایلی انتخاب نشده -->
                @if(!$file)
                <div class="flex items-center justify-center w-full">
                    <label class="flex flex-col items-center justify-center w-full h-64 border-2 border-indigo-200 border-dashed rounded-xl cursor-pointer bg-indigo-50/50 hover:bg-indigo-50 transition-all duration-200 group relative">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <div class="p-4 mb-4 rounded-full bg-indigo-100 group-hover:bg-indigo-200 transition-colors">
                                <x-heroicon-o-cloud-arrow-up class="w-10 h-10 text-indigo-600" />
                            </div>
                            <p class="mb-2 text-sm text-gray-600"><span class="font-bold text-indigo-600">برای انتخاب فایل کلیک کنید</span> یا فایل را اینجا بکشید</p>
                            <p class="text-xs text-gray-400 font-medium bg-white px-3 py-1 rounded-full shadow-sm mt-2 border border-gray-100">فقط فرمت XLSX (حداکثر ۵ مگابایت)</p>
                        </div>
                        <input type="file" wire:model="file" class="hidden" accept=".xlsx" />

                        <!-- لودینگ آپلود اولیه -->
                        <div wire:loading wire:target="file" class="absolute inset-0 bg-white/80 rounded-xl flex flex-col items-center justify-center backdrop-blur-sm z-10">
                            <svg class="animate-spin h-8 w-8 text-indigo-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-bold text-indigo-600">در حال خواندن فایل...</span>
                        </div>
                    </label>
                </div>
                @else
                <!-- حالت ۲: فایل با موفقیت انتخاب شده و آماده ارسال است -->
                <div class="p-6 border border-emerald-200 bg-emerald-50/50 rounded-xl flex flex-col sm:flex-row items-center justify-between gap-4 transition-all">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-emerald-100 rounded-lg text-emerald-600 shadow-sm">
                            <x-heroicon-o-document-chart-bar class="w-8 h-8" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-800" dir="ltr">{{ $file->getClientOriginalName() }}</h4>
                            <div class="flex items-center gap-2 mt-1">
                                    <span class="flex items-center text-xs font-medium text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded-md">
                                        <x-heroicon-o-check class="w-3 h-3 ml-1" />
                                        فایل آماده ارسال است
                                    </span>
                            </div>
                        </div>
                    </div>

                    <!-- دکمه حذف فایل انتخاب شده -->
                    <button type="button" wire:click="$set('file', null)" class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 bg-white border border-red-200 hover:bg-red-50 rounded-lg transition-colors shadow-sm">
                        <x-heroicon-o-trash class="w-4 h-4" />
                        انتخاب فایل دیگر
                    </button>
                </div>
                @endif

                <!-- خطاهای اعتبارسنجی فایل -->
                @error('file')
                <div class="flex items-center gap-1 mt-3 text-sm text-red-600 bg-red-50 p-3 rounded-lg border border-red-100">
                    <x-heroicon-o-exclamation-circle class="w-5 h-5 flex-shrink-0" />
                    <span class="font-medium">{{ $message }}</span>
                </div>
                @enderror
            </div>

            <!-- دکمه نهایی ارسال -->
            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit"
                        @if(!$file) disabled @endif
                        class="group relative flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-bold rounded-lg transition-all focus:ring-4 focus:outline-none
                    {{ $file ? 'text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-300 shadow-md hover:shadow-lg' : 'text-gray-500 bg-gray-200 cursor-not-allowed border border-gray-300' }}">

                    <span wire:loading.remove wire:target="processFile" class="flex items-center gap-1.5">
                        <x-heroicon-o-arrow-up-tray class="w-5 h-5 {{ $file ? 'transition-transform group-hover:-translate-y-1' : '' }}" />
                        آپلود و پردازش اطلاعات
                    </span>

                    <span wire:loading wire:target="processFile" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        در حال پردازش رکوردها...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
