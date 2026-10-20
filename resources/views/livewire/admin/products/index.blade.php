<div class="p-6 space-y-6 relative">
    <!-- هدر بخش مدیریت محصولات -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
        <!-- سمت راست: عنوان و خلاصه آمار تجاری -->
        <div class="space-y-1">
            <h1 class="text-xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-2">
                <span class="w-2 h-5 bg-blue-600 rounded-sm"></span>
                مدیریت محصولات
            </h1>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                جستجو، کنترل موجودی، ویرایش قیمت‌ها، ویژگی‌های فنی (EAV) و مدیریت گروهی کالاها
            </p>
        </div>

        <div class="flex items-center gap-2 self-end lg:self-center">
            <!-- دکمه خروجی اکسل با افکت لودینگ -->
            <button wire:click="exportExcel"
                    wire:loading.attr="disabled"
                    class="whitespace-nowrap flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold transition-all duration-200 shadow-sm shadow-emerald-100 dark:shadow-none disabled:opacity-50">
                <!-- آیکون لودینگ فعال در هنگام اجرای پردازش خروجی -->
                <svg wire:loading wire:target="exportExcel" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <!-- آیکون خروجی اکسل در حالت عادی -->
                <svg wire:loading.remove wire:target="exportExcel" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>خروجی اکسل</span>
            </button>

            <!-- دکمه افزودن محصول جدید -->
            <a href="{{ route('admin.products.create') }}" class="whitespace-nowrap flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[11px] font-bold transition-all duration-200 shadow-sm shadow-blue-100 dark:shadow-none">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>افزودن محصول جدید</span>
            </a>
        </div>

    </div>

    <!-- پیام‌های موفقیت آمیز با افکت Fade In -->
    @if (session()->has('message'))
    <div class="p-3.5 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900 text-emerald-800 dark:text-emerald-300 rounded-xl text-[11px] font-semibold flex items-center gap-2 shadow-sm animate-fade-in">
        <svg class="w-4.5 h-4.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        {{ session('message') }}
    </div>
    @endif

    <!-- نوار فیلتر و جستجوی پیشرفته هماهنگ شده با استاندارد پروژه -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl p-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">

            <!-- جستجوی عنوان یا SKU -->
            <div class="relative w-full">
                <input wire:model.live.debounce.400ms="search" type="text" placeholder="جستجو در عنوان کالا، کد SKU..." class="w-full pr-9 pl-3 py-1.5 border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-xs rounded-lg text-slate-800 dark:text-white dark:bg-slate-800 placeholder:text-slate-400">
                <div class="absolute right-2.5 top-2.5 text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- دسته بندی‌ها -->
            <div class="w-full">
                <select wire:model.live="category_id" class="block w-full py-1.5 border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-xs rounded-lg text-slate-700 dark:text-slate-300 dark:bg-slate-800 cursor-pointer">
                    <option value="">همه دسته‌بندی‌ها</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- وضعیت کالا -->
            <div class="w-full">
                <select wire:model.live="status" class="block w-full py-1.5 border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-xs rounded-lg text-slate-700 dark:text-slate-300 dark:bg-slate-800 cursor-pointer">
                    <option value="">همه وضعیت‌ها</option>
                    @foreach($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- مرتب‌سازی کالاها -->
            <div class="w-full">
                <select wire:model.live="sort" class="block w-full py-1.5 border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-xs rounded-lg text-slate-700 dark:text-slate-300 dark:bg-slate-800 cursor-pointer">
                    <option value="latest">جدیدترین‌ها</option>
                    <option value="oldest">قدیمی‌ترین‌ها</option>
                    <option value="title_asc">عنوان (الفبا تا ی)</option>
                    <option value="title_desc">عنوان (ی تا الفبا)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- عملیات گروهی کاملا تعاملی و قدرتمند (Bulk Actions) -->
    <div class="grid gap-3 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">وضعیت تسک</label>
            <select id="filter-task-status" wire:model.live="taskStatus" class="w-full rounded-lg border-slate-300 text-xs">
                <option value="">همه</option>
                @foreach($taskStatuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">نوع تسک</label>
            <select id="filter-task-type" wire:model.live="taskType" class="w-full rounded-lg border-slate-300 text-xs">
                <option value="">همهٔ انواع</option>
                @foreach($taskTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">وضعیت قیمت‌گذاری</label>
            <select id="filter-pricing-status" wire:model.live="pricingStatus" class="w-full rounded-lg border-slate-300 text-xs">
                <option value="">همه</option>
                <option value="priced">قیمت‌گذاری‌شده</option>
                <option value="unpriced">قیمت‌گذاری‌نشده</option>
            </select>
        </div>
        <div>
            <label for="filter-primary-image" class="mb-1 block text-xs font-semibold text-slate-700">تصویر اصلی</label>
            <select id="filter-primary-image" wire:model.live="primaryImageStatus" class="w-full rounded-lg border-slate-300 text-xs">
                <option value="">همه</option>
                <option value="has">دارای تصویر اصلی</option>
                <option value="missing">بدون تصویر اصلی</option>
            </select>
        </div>
        <div>
            <label for="filter-gallery-image-counts" class="mb-1 block text-xs font-semibold text-slate-700">تعداد تصاویر گالری (بدون تصویر اصلی)</label>
            <select id="filter-gallery-image-counts" wire:model.live="galleryImageCounts" multiple size="4" class="w-full rounded-lg border-slate-300 text-xs">
                @foreach($galleryImageCountOptions as $count)
                    <option value="{{ $count }}">{{ number_format($count) }} تصویر</option>
                @endforeach
            </select>
            <p class="mt-1 text-[10px] text-slate-500">برای انتخاب چند مورد، Ctrl یا لمس چندگانه را استفاده کنید.</p>
        </div>
        <div>
            <label for="filter-short-description" class="mb-1 block text-xs font-semibold text-slate-700">توضیحات کوتاه</label>
            <select id="filter-short-description" wire:model.live="shortDescriptionStatus" class="w-full rounded-lg border-slate-300 text-xs">
                <option value="">همه</option>
                <option value="has">دارای توضیح</option>
                <option value="missing">بدون توضیح</option>
            </select>
        </div>
        <div>
            <label for="filter-full-description" class="mb-1 block text-xs font-semibold text-slate-700">توضیحات کامل</label>
            <select id="filter-full-description" wire:model.live="fullDescriptionStatus" class="w-full rounded-lg border-slate-300 text-xs">
                <option value="">همه</option>
                <option value="has">دارای توضیح</option>
                <option value="missing">بدون توضیح</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">نیاز به تکمیل</label>
            <select id="filter-completion-status" wire:model.live="completionStatus" class="w-full rounded-lg border-slate-300 text-xs">
                <option value="">همه</option>
                <option value="needs_completion">نیاز به تکمیل دارد</option>
                <option value="complete">تکمیل‌شده نسبت به میانگین دسته</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">تعداد موارد نیازمند تکمیل</label>
            <select id="filter-completion-counts" wire:model.live="completionCounts" multiple size="4" class="w-full rounded-lg border-slate-300 text-xs">
                @foreach($completionCountOptions as $count)
                    <option value="{{ $count }}">نیاز به {{ number_format($count) }} ویژگی</option>
                @endforeach
            </select>
            <p class="mt-1 text-[10px] text-slate-500">برای انتخاب چند تعداد، کلید Ctrl یا لمس چندگانه را استفاده کنید.</p>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">تعداد تأمین‌کنندگان دارای قیمت معتبر</label>
            <select id="filter-supplier-counts" wire:model.live="supplierCounts" multiple size="4" class="w-full rounded-lg border-slate-300 text-xs">
                @foreach($supplierCountOptions as $count)
                    <option value="{{ $count }}">{{ number_format($count) }} تأمین‌کننده</option>
                @endforeach
            </select>
            <p class="mt-1 text-[10px] text-slate-500">تعدادها با سایر فیلترها هماهنگ می‌شوند؛ برای انتخاب چند مورد Ctrl یا لمس چندگانه را استفاده کنید.</p>
        </div>
    </div>

    @if($canUndoBulkTaskCreation && $latestUndoableTaskBatch)
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
        <p>آخرین ایجاد گروهی شما: {{ number_format($latestUndoableTaskBatch->tasks_count) }} تسک ثبت شده؛ {{ number_format($latestUndoableTaskBatch->failed_count) }} مورد ناموفق.</p>
        <p class="w-full">بازگردانی فقط تسک‌های آزاد و پذیرفته‌نشده را لغو می‌کند؛ موارد پذیرفته‌شده یا در حال رسیدگی حفظ می‌شوند.</p>
        @if($latestUndoableTaskBatch->status === 'rolling_back')
            <p class="w-full">بازگردانی مرحله‌ای: {{ number_format($latestUndoableTaskBatch->rollback_processed_count) }} از {{ number_format($latestUndoableTaskBatch->tasks_count) }} تسک بررسی شده است.</p>
        @endif
        <button wire:click="undoLatestBulkTaskCreation" onclick="return confirm('{{ $latestUndoableTaskBatch->status === 'rolling_back' ? 'ادامهٔ بازگردانی تسک‌های باقی‌مانده؟' : 'تسک‌های آزاد این عملیات لغو شوند؟' }}')" wire:loading.attr="disabled" class="rounded-lg border border-amber-400 px-3 py-2 font-bold text-amber-900 hover:bg-amber-100 disabled:opacity-50 dark:text-amber-100 dark:hover:bg-amber-900/40">
            {{ $latestUndoableTaskBatch->status === 'rolling_back' ? 'ادامهٔ بازگردانی' : 'بازگردانی آخرین ایجاد گروهی' }}
        </button>
    </div>
    @endif

    @if($bulkTaskUndoResult !== '')
        <p role="status" aria-live="polite" class="rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">{{ $bulkTaskUndoResult }}</p>
    @endif

    @if(!empty($selectedProducts))
    <div class="p-3 bg-blue-50/70 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900 rounded-xl flex flex-wrap items-center justify-between gap-3 animate-fade-in">
        <div class="text-[11px] text-blue-800 dark:text-blue-300 font-bold">
            {{ count($selectedProducts) }} محصول انتخاب شده است.
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($hasDefaultFullCompletionRule && !$hasPendingBulkTaskRollback)
                <button wire:click="createDefaultTasksForSelected" onclick="return confirm('برای محصولات انتخاب‌شده‌ای که تسک فعال ندارند، تسک تکمیل کامل با قانون پیش‌فرض ساخته و منتشر شود؟')" wire:loading.attr="disabled" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[10px] font-bold transition-all disabled:opacity-50">
                    ایجاد تسک پیش‌فرض برای انتخاب‌شده‌ها
                </button>
            @elseif($hasPendingBulkTaskRollback)
                <span class="text-xs font-semibold text-amber-800">ابتدا بازگردانی گروهیِ نیمه‌تمام را کامل کنید.</span>
            @endif
            <button wire:click="bulkToggleActive(1)" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                فعالسازی نمایش گروهی
            </button>
            <button wire:click="bulkToggleActive(0)" class="px-2.5 py-1 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                غیرفعالسازی نمایش گروهی
            </button>
            <button wire:click="bulkSetStatus('active')" class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                وضعیت: فعال
            </button>
            <button wire:click="bulkSetStatus('out_of_stock')" class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                وضعیت: ناموجود
            </button>
            <button onclick="confirm('آیا از حذف گروهی محصولات انتخاب شده مطمئن هستید؟ این عملیات غیرقابل بازگشت است.') || event.stopImmediatePropagation()" wire:click="bulkDelete" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-[10px] font-bold transition-all shadow-sm">
                حذف گروهی
            </button>
        </div>
    </div>
    @endif

    <!-- جدول محصولات مدرن و فشرده -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="text-xs text-slate-600 dark:text-slate-300">{{ number_format($products->total()) }} محصول مطابق فیلترها</div>
        <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                تعداد در صفحه
                <select wire:model.live="perPage" class="rounded-lg border-slate-300 text-xs dark:bg-slate-800">
                    @foreach([15, 30, 50, 100] as $pageSize)
                        <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                    @endforeach
                </select>
            </label>
            @if($hasDefaultFullCompletionRule && !$hasPendingBulkTaskRollback)
                <button wire:click="createDefaultTasksForFilteredProducts" onclick="return confirm('برای همهٔ محصولات بدون تسک فعال در نتایج فیلترشده، تسک تکمیل کامل با قانون پیش‌فرض ساخته و منتشر شود؟')" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50">
                    ایجاد تسک پیش‌فرض برای همه نتایج فیلترشده
                </button>
            @elseif($hasPendingBulkTaskRollback)
                <span class="text-xs text-amber-700">ابتدا بازگردانی گروهیِ نیمه‌تمام را کامل کنید.</span>
            @else
                <span class="text-xs text-amber-700">برای تکمیل کامل ابتدا یک قانون پیش‌فرض فعال تعیین کنید.</span>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden relative">

        <!-- انیمیشن بارگذاری هوشمند (Livewire Loading State Overlay) -->
        <div wire:loading.delay class="absolute inset-0 bg-white/70 dark:bg-slate-900/70 backdrop-blur-[1px] z-10 flex items-center justify-center">
            <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-xl shadow-lg">
                <svg class="animate-spin h-4.5 w-4.5 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">در حال بروزرسانی لیست...</span>
            </div>
        </div>

        <div class="w-full overflow-x-auto lg:overflow-visible">
            <table class="w-full text-right border-collapse table-auto lg:table-fixed">
                <thead>
                <tr class="bg-slate-50/75 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-bold">
                    <th class="p-2.5 w-10 text-center align-middle">
                        <input wire:model.live="selectAll" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5 cursor-pointer">
                    </th>
                    <th class="p-2.5 w-16 text-center align-middle">تصویر</th>
                    <th class="p-2.5 align-middle">نام محصول / دسته‌بندی</th>
                    <th class="p-2.5 w-28 align-middle">کد کالا (SKU)</th>
                    <th class="p-2.5 w-28 text-center align-middle">وضعیت سیستم</th>
                    <th class="p-2.5 w-20 text-center align-middle">نمایش عمومی</th>
                    <th class="p-2.5 w-20 text-center align-middle">عملیات</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-[11px] text-slate-700 dark:text-slate-300">
                @forelse($products as $product)
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all duration-150">
                    <!-- چک باکس انتخاب -->
                    <td class="p-2.5 text-center align-middle">
                        <input wire:model.live="selectedProducts" type="checkbox" value="{{ $product->id }}" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5 cursor-pointer">
                    </td>

                    <!-- تصویر کالا -->
                    <td class="p-2.5 text-center align-middle">
                        @if($gallery = $product->galleries->first())
                        <img src="{{ $gallery->getVersionUrl('thumbnail') ?? $gallery->getVersionUrl('small') ?? $gallery->full_url }}" alt="{{ $product->title }}" loading="lazy" width="36" height="36" class="w-9 h-9 object-cover rounded-lg border border-slate-150 dark:border-slate-700 shadow-sm mx-auto">
                        @else
                        <div class="w-9 h-9 bg-slate-100 dark:bg-slate-800 rounded-lg flex items-center justify-center text-slate-400 mx-auto border border-dashed border-slate-250 dark:border-slate-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        @endif
                    </td>

                    <!-- اطلاعات کالا -->
                    <td class="p-2.5 align-middle">
                        <div class="font-bold text-slate-800 dark:text-white text-[11.5px] whitespace-normal leading-relaxed" title="{{ $product->title }}">
                            {{ $product->title }}
                        </div>
                        @php($canViewSupplierPrices = auth()->user()?->can('viewSupplierPrices', $product) ?? false)
                        @if($canViewSupplierPrices && $product->productSuppliers->isNotEmpty())
                            <details class="mt-2 rounded-lg border border-emerald-100 bg-emerald-50/60 p-2 text-[10px] dark:border-emerald-900 dark:bg-emerald-950/20">
                                <summary class="cursor-pointer font-semibold text-emerald-800 dark:text-emerald-300">مشاهده تأمین‌کنندگان و قیمت‌ها ({{ $product->productSuppliers->count() }})</summary>
                                <div class="mt-2 space-y-2">
                                    @foreach($product->productSuppliers as $productSupplier)
                                        @if($productSupplier->supplier)
                                            <div class="rounded-md border border-emerald-100 bg-white p-2 dark:border-emerald-900 dark:bg-slate-900">
                                                <p class="font-bold text-slate-800 dark:text-slate-100">{{ $productSupplier->supplier->brand_name ?: $productSupplier->supplier->company_name }}</p>
                                                <ul class="mt-1 space-y-1 text-slate-600 dark:text-slate-300">
                                                    @foreach($productSupplier->validPrices as $price)
                                                        <li>
                                                            {{ $price->priceType?->name ?? 'قیمت' }}
                                                            @php($tier = $product->quantity_tiers[$price->tier_index] ?? null)
                                                            @if($price->tier_index > 0 && $tier)
                                                                · {{ $tier['name'] ?? ('حداقل ' . number_format($tier['min_quantity'] ?? 0) . ' عدد') }}
                                                            @endif
                                                            @if($price->checkPeriod) · {{ $price->checkPeriod->label ?: $price->checkPeriod->days.' روزه' }} @endif
                                                            : <strong>{{ number_format($price->price) }} تومان</strong>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </details>
                        @endif
                        <div class="text-[9.5px] text-slate-400 mt-0.5 flex items-center gap-1">
                            <span class="inline-block w-1.5 h-1.5 bg-blue-500 rounded-full"></span>
                            <span>{{ $product->category->name ?? 'بدون دسته‌بندی' }}</span>
                        </div>
                    </td>

                    <!-- کد کالا SKU -->
                    <td class="p-2.5 align-middle font-mono text-[10px] text-slate-500 dark:text-slate-400">
                        @if($product->slug)
                            <a href="{{ route('products.show', ['product' => $product->slug]) }}" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline decoration-dotted underline-offset-2 hover:text-blue-900 dark:text-blue-300 dark:hover:text-blue-200" title="مشاهده صفحه محصول">
                                {{ $product->sku ?? '---' }}
                            </a>
                        @else
                            {{ $product->sku ?? '---' }}
                        @endif
                    </td>

                    <!-- وضعیت سیستم (بدج‌های مدرن و شکیل با پین رینگ زنده) -->
                    <td class="p-2.5 text-center align-middle">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-black border {{ match($product->status?->value ?? $product->status) {
                            'active' => 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900',
                            'pending_review' => 'text-amber-700 bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900',
                            'inactive' => 'text-rose-700 bg-rose-50 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900',
                            'out_of_stock' => 'text-orange-700 bg-orange-50 dark:bg-orange-950/20 border-orange-200 dark:border-orange-900',
                            default => 'text-slate-600 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700',
                        } }}">
                                    @if($product->status === 'active')
                                        <span class="w-1 h-1 bg-emerald-500 rounded-full animate-ping"></span>
                                    @endif
{{ App\Enums\ProductStatus::options()[$product->status->value ?? $product->status] ?? ($product->status->value ?? $product->status) }}
                                </span>
                    </td>

                    <!-- نمایش عمومی (توگل سوئیچ کاملا فشرده و سازگار با RTL) -->
                    <td class="p-2.5 text-center align-middle">
                        <button wire:click="toggleActive({{ $product->id }})" class="relative inline-flex items-center h-4.5 w-8 rounded-full transition-colors duration-200 focus:outline-none p-0.5 {{ $product->is_active ? 'bg-blue-600' : 'bg-slate-200 dark:bg-slate-700' }}" title="{{ $product->is_active ? 'نمایش داده می‌شود (جهت پنهان‌سازی کلیک کنید)' : 'پنهان است (جهت نمایش عمومی کلیک کنید)' }}">
                            <span class="inline-block w-3.5 h-3.5 transform rounded-full bg-white transition-transform duration-200 shadow-sm {{ $product->is_active ? '-translate-x-3.5' : 'translate-x-0' }}"></span>
                        </button>
                    </td>

                    <!-- عملیات کالا -->
                    <td class="p-2.5 text-center align-middle">
                        <div class="flex items-center justify-center gap-0.5">
                            @if(!$product->has_active_completion_task)
                                <a href="{{ route('admin.outsourcing.tasks.index', ['productId' => $product->id]) }}" class="rounded-md px-1.5 py-1 text-[9px] font-bold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950/30" title="افزودن تسک پیش‌فرض برای محصول">افزودن تسک</a>
                            @else
                                <span class="rounded-md bg-amber-50 px-1.5 py-1 text-[9px] font-semibold text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">تسک فعال</span>
                            @endif
                            <!-- دکمه ویرایش کالا -->
                            <a href="{{ route('admin.products.edit', $product->id) }}" class="p-1 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/20 rounded-md transition-all" title="ویرایش کالا">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>

                            <!-- دکمه حذف کالا -->
                            <button onclick="confirm('آیا از حذف این کالا مطمئن هستید؟ با تایید این بخش، تمام ویژگی‌ها و گالری تصاویر آن نیز حذف خواهد شد.') || event.stopImmediatePropagation()" wire:click="delete({{ $product->id }})" class="p-1 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-md transition-all" title="حذف کالا">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="p-8 text-center text-slate-400">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0V9a2 2 0 00-2-2H6a2 2 0 00-2 2v4.5m16 0h-1.5"></path>
                            </svg>
                            <span class="text-[11px] font-bold text-slate-500">هیچ محصولی با فیلترهای اعمال شده یافت نشد.</span>
                        </div>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <!-- پیجینیشن تجاری -->
        @if($products->hasPages())
        <div class="p-3 border-t border-slate-100 dark:border-slate-800">
            {{ $products->links() }}
        </div>
        @endif
    </div>
</div>
