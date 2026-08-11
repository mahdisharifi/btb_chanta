<div class="min-h-screen bg-gray-50 py-8" dir="rtl">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- هدر --}}
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">طراح کاور کاتالوگ</h1>
                    <p class="mt-1 text-sm text-gray-500">کاتالوگ: {{ $catalogTemplate->name }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('supplier.catalog.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        <x-heroicon-o-arrow-right class="w-5 h-5 ml-2" />
                        بازگشت
                    </a>
                </div>
            </div>
        </div>

        {{-- پیام‌ها --}}
        @if (session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {{-- ستون تنظیمات --}}
            <div class="lg:col-span-2 space-y-6">
                
                {{-- مدیریت کاور --}}
                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-indigo-600" />
                        مدیریت کاور
                    </h3>
                    
                    @if($sourceCatalogId)
                        <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <x-heroicon-o-link class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-blue-900">وابسته به: {{ $this->sourceCatalogName }}</p>
                                    <button type="button" wire:click="detachFromDefault" class="mt-2 text-xs text-blue-800 underline hover:text-blue-900">
                                        جدا کردن
                                    </button>
                                </div>
                            </div>
                        </div>
                    @elseif($isDefaultTemplate)
                        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <x-heroicon-o-check-badge class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <p class="text-sm font-medium text-green-900">کاور پیش‌فرض</p>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    @if(!$sourceCatalogId)
                        <div class="border-t border-indigo-200 pt-4">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" wire:model.live="isDefaultTemplate" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="text-sm font-medium text-gray-900">تنظیم به عنوان پیش‌فرض</span>
                                    <p class="text-xs text-gray-600 mt-0.5">کاتالوگ‌های جدید از این تنظیمات استفاده خواهند کرد.</p>
                                </div>
                            </label>
                        </div>
                    @endif
                </div>
                
                {{-- تنظیمات کلی --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <x-heroicon-o-swatch class="w-5 h-5 text-indigo-500" />
                        تنظیمات کلی
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">رنگ پس‌زمینه کاور</label>
                            <div class="flex items-center gap-3">
                                <input type="color" wire:model.live="coverSettings.background_color" class="w-20 h-10 rounded border border-gray-300 cursor-pointer">
                                <input type="text" wire:model.live="coverSettings.background_color" class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">فاصله از لبه (px)</label>
                            <input type="number" wire:model.live="coverSettings.padding" min="0" max="50" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="col-span-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="coverSettings.page_break" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm font-medium text-gray-700">صفحه جداگانه برای کاور</span>
                            </label>
                        </div>
                    </div>
                    
                    {{-- تنظیمات فونت پیش‌فرض --}}
                    <div class="mt-6 border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">فونت پیش‌فرض</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">اندازه فونت</label>
                                <input type="number" wire:model.live="coverSettings.default_font_size" min="8" max="72" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">رنگ فونت</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="coverSettings.default_font_color" class="w-12 h-8 rounded border border-gray-300 cursor-pointer">
                                    <input type="text" wire:model.live="coverSettings.default_font_color" class="flex-1 rounded-md border-gray-300 shadow-sm text-xs" placeholder="#ffffff">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">وزن فونت</label>
                                <select wire:model.live="coverSettings.default_font_weight" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="normal">معمول</option>
                                    <option value="bold">ضخیم</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="coverSettings.use_custom_font_per_cell" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs font-medium text-gray-700">امکان تنظیم فونت جداگانه برای هر سلول</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                {{-- مدیریت ردیف‌ها --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <x-heroicon-o-table-cells class="w-5 h-5 text-indigo-500" />
                            ردیف‌های جدول
                        </h3>
                        <button type="button" wire:click="addRow" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm">
                            <x-heroicon-o-plus class="w-4 h-4 ml-2" />
                            افزودن ردیف
                        </button>
                    </div>
                    
                    @if(empty($rows))
                        <div class="text-center py-8 text-gray-500">
                            <x-heroicon-o-table-cells class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            <p>هیچ ردیفی وجود ندارد.</p>
                            <p class="text-sm mt-1">روی "افزودن ردیف" کلیک کنید.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($rows as $index => $row)
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 transition">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-medium text-gray-700">ردیف {{ $index + 1 }}</span>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                {{ $row['columns'] }} ستون
                                            </span>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                ارتفاع: {{ $row['height'] }}{{ $row['height_unit'] }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" wire:click="moveRowUp({{ $index }})" class="p-1 text-gray-400 hover:text-indigo-600" title="بالا">
                                                <x-heroicon-o-chevron-up class="w-5 h-5" />
                                            </button>
                                            <button type="button" wire:click="moveRowDown({{ $index }})" class="p-1 text-gray-400 hover:text-indigo-600" title="پایین">
                                                <x-heroicon-o-chevron-down class="w-5 h-5" />
                                            </button>
                                            <button type="button" wire:click="editRow({{ $index }})" class="p-1 text-indigo-600 hover:text-indigo-800">
                                                <x-heroicon-o-pencil-square class="w-5 h-5" />
                                            </button>
                                            <button type="button" wire:click="deleteRow({{ $index }})" class="p-1 text-red-600 hover:text-red-800" onclick="return confirm('حذف شود؟')">
                                                <x-heroicon-o-trash class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid gap-2" style="grid-template-columns: repeat({{ $row['columns'] }}, 1fr);">
                                        @foreach($row['cells'] as $cellIndex => $cell)
                                            <div class="bg-gray-50 rounded p-3 text-xs">
                                                <div class="font-medium text-gray-700 mb-1">
                                                    @if($cell['content_type'] === 'logo') لوگو
                                                    @elseif($cell['content_type'] === 'company_name') نام شرکت
                                                    @elseif($cell['content_type'] === 'brand_name') نام برند
                                                    @elseif($cell['content_type'] === 'catalog_title') عنوان کاتالوگ
                                                    @elseif($cell['content_type'] === 'contact') اطلاعات تماس
                                                    @elseif($cell['content_type'] === 'date') تاریخ
                                                    @elseif($cell['content_type'] === 'spacer') فضای خالی
                                                    @else متن: {{ Str::limit($cell['content_value'], 20) }}
                                                    @endif
                                                </div>
                                                <div class="text-gray-500">فونت: {{ $cell['font_size'] }}px</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                
                {{-- دکمه ذخیره --}}
                <div class="flex justify-end">
                    <button type="button" wire:click="save" class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">
                        ذخیره تنظیمات
                    </button>
                </div>
            </div>
            
            {{-- ستون پیش‌نمایش --}}
            <div class="lg:col-span-1">
                <div class="sticky top-6">
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">پیش‌نمایش زنده</h3>
                        <div class="aspect-[210/297] border-2 border-gray-200 rounded-lg overflow-hidden" style="background-color: {{ $coverSettings['background_color'] }}; padding: {{ $coverSettings['padding'] }}px;">
                            <div class="h-full flex flex-col" style="font-family: 'IRANSans', Tahoma, Arial, sans-serif;">
                                @forelse($rows as $row)
                                    <div style="height: {{ $row['height_unit'] === 'auto' ? 'auto' : $row['height'] . $row['height_unit'] }}; min-height: 20px; background-color: {{ $row['background_color'] ?: 'transparent' }}; vertical-align: {{ $row['vertical_align'] }}; display: flex; flex-direction: column; justify-content: {{ $row['vertical_align'] === 'top' ? 'flex-start' : ($row['vertical_align'] === 'bottom' ? 'flex-end' : 'center') }};">
                                        <div class="h-full grid gap-1" style="grid-template-columns: repeat({{ $row['columns'] }}, 1fr);">
                                            @foreach($row['cells'] as $cell)
                                                <div style="background-color: {{ $cell['background_color'] ?: 'transparent' }}; padding: {{ $cell['padding'] }}px; border-radius: {{ $cell['border_radius'] }}px; text-align: {{ $cell['text_align'] }}; color: {{ $cell['font_color'] }}; font-size: {{ $cell['font_size'] }}px; font-weight: {{ $cell['font_weight'] }}; display: flex; align-items: center; justify-content: {{ $cell['text_align'] === 'left' ? 'flex-start' : ($cell['text_align'] === 'right' ? 'flex-end' : 'center') }};">
                                                    @php
                                                        $previewUser = auth()->user();
                                                        $previewSupplier = $previewUser ? $previewUser->supplier : null;
                                                    @endphp
                                                    @if($cell['content_type'] === 'logo')
                                                        @php
                                                            $logoWidth = $cell['logo_width'] ?? 100;
                                                            $logoHeight = $cell['logo_height'] ?? 100;
                                                            $logoUrl = $previewSupplier && $previewSupplier->logo ? asset('storage/' . $previewSupplier->logo) : null;
                                                        @endphp
                                                        @if($logoUrl)
                                                            <img src="{{ $logoUrl }}" alt="لوگو" style="width: {{ $logoWidth }}px; height: {{ $logoHeight }}px; object-fit: contain; border-radius: {{ $cell['border_radius'] ?? 0 }}px;">
                                                        @else
                                                            <div style="width: {{ $logoWidth }}px; height: {{ $logoHeight }}px; background: white; border-radius: {{ $cell['border_radius'] ?? 0 }}px; display: flex; align-items: center; justify-content: center;">
                                                                <span style="font-size: {{ min($logoWidth, $logoHeight) * 0.5 }}px; font-weight: bold; color: {{ $coverSettings['background_color'] }};">{{ mb_substr($previewSupplier->company_name ?? 'L', 0, 1) }}</span>
                                                            </div>
                                                        @endif
                                                    @elseif($cell['content_type'] === 'company_name')
                                                        <span>{{ $previewSupplier->company_name ?? 'نام شرکت' }}</span>
                                                    @elseif($cell['content_type'] === 'brand_name')
                                                        <span>{{ $previewSupplier->brand_name ?? '' }}</span>
                                                    @elseif($cell['content_type'] === 'catalog_title')
                                                        <span style="background: {{ $cell['background_color'] ?: '#fff' }}; padding: {{ $cell['padding'] }}px {{ $cell['padding'] * 2 }}px; border-radius: {{ $cell['border_radius'] }}px;">{{ $catalogTemplate->title ?? 'کاتالوگ محصولات' }}</span>
                                                    @elseif($cell['content_type'] === 'contact')
                                                        <div style="font-size: {{ $cell['font_size'] * 0.8 }}px;">
                                                            @foreach($catalogTemplate->contact_phones ?? [] as $phone)
                                                                @if(!empty($phone['number']))
                                                                    <div>{{ $phone['label'] ?? '' }}: {{ $phone['number'] }}</div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @elseif($cell['content_type'] === 'date')
                                                        <span>{{ jdate(now())->format('Y/m/d') }}</span>
                                                    @elseif($cell['content_type'] === 'text')
                                                        <span>{{ $cell['content_value'] }}</span>
                                                    @elseif($cell['content_type'] === 'spacer')
                                                        <div style="height: {{ $cell['font_size'] }}px;"></div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <div class="flex items-center justify-center h-full text-gray-400 text-xs">
                                        هنوز ردیفی اضافه نشده
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 text-center mt-2">پیش‌نمایش واقعی</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Modal ویرایش ردیف --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" wire:ignore.self>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" wire:click="cancelEdit"></div>
            
            <div class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                <div class="bg-white px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $editingRowIndex !== null ? 'ویرایش ردیف' : 'افزودن ردیف جدید' }}
                        </h3>
                        <button type="button" wire:click="cancelEdit" class="text-gray-400 hover:text-gray-600">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>
                </div>
                
                <div class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                    {{-- تنظیمات ردیف --}}
                    <div class="grid grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ارتفاع</label>
                            <input type="number" wire:model.live="rowForm.height" min="20" max="500" class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">واحد</label>
                            <select wire:model.live="rowForm.height_unit" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="px">پیکسل (px)</option>
                                <option value="mm">میلی‌متر (mm)</option>
                                <option value="auto">خودکار</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تعداد ستون</label>
                            <input type="number" wire:model.live="rowForm.columns" min="1" max="4" class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">تراز عمودی</label>
                            <select wire:model.live="rowForm.vertical_align" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="top">بالا</option>
                                <option value="middle">وسط</option>
                                <option value="bottom">پایین</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">رنگ پس‌زمینه ردیف (اختیاری)</label>
                        <input type="color" wire:model.live="rowForm.background_color" class="w-20 h-10 rounded border border-gray-300 cursor-pointer">
                    </div>
                    
                    <div class="border-t pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-gray-900">سلول‌ها</h4>
                            @if(count($rowForm['cells']) < 4)
                                <button type="button" wire:click="addCell" class="text-sm text-indigo-600 hover:text-indigo-800">
                                    + افزودن سلول
                                </button>
                            @endif
                        </div>
                        
                        @foreach($rowForm['cells'] as $cellIndex => $cell)
                            <div class="border border-gray-200 rounded-lg p-4 mb-3">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-700">سلول {{ $cellIndex + 1 }}</span>
                                        {{-- دکمه‌های جابجایی سلول --}}
                                        <div class="flex items-center gap-1">
                                            <button type="button" wire:click="moveCellLeft({{ $cellIndex }})" class="p-1 text-gray-400 hover:text-indigo-600 @if($cellIndex === 0) opacity-30 @endif" @if($cellIndex === 0) disabled @endif title="جابجایی به چپ">
                                                <x-heroicon-o-chevron-left class="w-4 h-4" />
                                            </button>
                                            <button type="button" wire:click="moveCellRight({{ $cellIndex }})" class="p-1 text-gray-400 hover:text-indigo-600 @if($cellIndex === count($rowForm['cells']) - 1) opacity-30 @endif" @if($cellIndex === count($rowForm['cells']) - 1) disabled @endif title="جابجایی به راست">
                                                <x-heroicon-o-chevron-right class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                    @if(count($rowForm['cells']) > 1)
                                        <button type="button" wire:click="removeCell({{ $cellIndex }})" class="text-sm text-red-600 hover:text-red-800">
                                            حذف
                                        </button>
                                    @endif
                                </div>
                                
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">نوع محتوا</label>
                                        <select wire:model.live="rowForm.cells.{{ $cellIndex }}.content_type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                            <option value="text">متن دلخواه</option>
                                            <option value="logo">لوگو</option>
                                            <option value="company_name">نام شرکت</option>
                                            <option value="brand_name">نام برند</option>
                                            <option value="catalog_title">عنوان کاتالوگ</option>
                                            <option value="contact">اطلاعات تماس</option>
                                            <option value="date">تاریخ</option>
                                            <option value="spacer">فضای خالی</option>
                                        </select>
                                    </div>
                                    
                                    @if($cell['content_type'] === 'text')
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700 mb-1">متن</label>
                                            <input type="text" wire:model.live="rowForm.cells.{{ $cellIndex }}.content_value" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                    @endif
                                    
                                    @if($cell['content_type'] === 'logo')
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">عرض لوگو (px)</label>
                                            <input type="number" wire:model.live="rowForm.cells.{{ $cellIndex }}.logo_width" min="30" max="300" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">ارتفاع لوگو (px)</label>
                                            <input type="number" wire:model.live="rowForm.cells.{{ $cellIndex }}.logo_height" min="30" max="300" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                    @endif
                                    
                                    @if($coverSettings['use_custom_font_per_cell'])
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">اندازه فونت</label>
                                            <input type="number" wire:model.live="rowForm.cells.{{ $cellIndex }}.font_size" min="8" max="72" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">رنگ فونت</label>
                                            <div class="flex items-center gap-2">
                                                <input type="color" wire:model.live="rowForm.cells.{{ $cellIndex }}.font_color" class="w-10 h-8 rounded border border-gray-300 cursor-pointer">
                                                <input type="text" wire:model.live="rowForm.cells.{{ $cellIndex }}.font_color" class="flex-1 rounded-md border-gray-300 shadow-sm text-xs">
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">وزن فونت</label>
                                            <select wire:model.live="rowForm.cells.{{ $cellIndex }}.font_weight" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                <option value="normal">معمول</option>
                                                <option value="bold">ضخیم</option>
                                            </select>
                                        </div>
                                    @endif
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">تراز متن</label>
                                        <select wire:model.live="rowForm.cells.{{ $cellIndex }}.text_align" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                            <option value="left">چپ</option>
                                            <option value="center">وسط</option>
                                            <option value="right">راست</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">پس‌زمینه</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" wire:model.live="rowForm.cells.{{ $cellIndex }}.background_color" class="w-10 h-8 rounded border border-gray-300 cursor-pointer">
                                            <input type="text" wire:model.live="rowForm.cells.{{ $cellIndex }}.background_color" class="flex-1 rounded-md border-gray-300 shadow-sm text-xs">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">فاصله (padding)</label>
                                        <input type="number" wire:model.live="rowForm.cells.{{ $cellIndex }}.padding" min="0" max="50" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">گردی گوشه‌ها</label>
                                        <input type="number" wire:model.live="rowForm.cells.{{ $cellIndex }}.border_radius" min="0" max="50" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" wire:click="cancelEdit" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        انصراف
                    </button>
                    <button type="button" wire:click="saveRow" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        ذخیره ردیف
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
