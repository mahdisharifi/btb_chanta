<div class="min-h-screen bg-gray-50 text-gray-800 font-sans" dir="rtl">

    <!-- Notification Toast -->
    <div id="notification-toast" class="fixed top-4 right-4 z-[10000] hidden">
        <div class="bg-white rounded-lg shadow-lg border p-4 max-w-sm">
            <div class="flex items-center gap-3">
                <div id="notification-icon" class="flex-shrink-0"></div>
                <div>
                    <p id="notification-message" class="text-sm font-medium text-gray-900"></p>
                </div>
                <button onclick="document.getElementById('notification-toast').classList.add('hidden')" class="mr-auto text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (event) => {
                const toast = document.getElementById('notification-toast');
                const message = document.getElementById('notification-message');
                const icon = document.getElementById('notification-icon');
                
                const data = event[0] || event;
                message.textContent = data.message || 'عملیات با موفقیت انجام شد.';
                
                // تنظیم آیکون بر اساس نوع
                if (data.type === 'success') {
                    icon.innerHTML = '<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                    toast.querySelector('.border').classList.remove('border-red-200', 'border-yellow-200');
                    toast.querySelector('.border').classList.add('border-green-200');
                } else if (data.type === 'error') {
                    icon.innerHTML = '<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                    toast.querySelector('.border').classList.remove('border-green-200', 'border-yellow-200');
                    toast.querySelector('.border').classList.add('border-red-200');
                } else {
                    icon.innerHTML = '<svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                    toast.querySelector('.border').classList.remove('border-green-200', 'border-red-200');
                    toast.querySelector('.border').classList.add('border-yellow-200');
                }
                
                toast.classList.remove('hidden');
                
                // پنهان کردن خودکار بعد از 5 ثانیه
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 5000);
            });
        });
    </script>

    <!-- Progress Modal -->
    <div id="progressModal" class="fixed inset-0 z-[9999] items-center justify-center bg-black/50 backdrop-blur-sm hidden">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md mx-4 transform transition-all">
            
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="mx-auto w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mb-4">
                    <svg id="progressIcon" class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">در حال تولید کاتالوگ</h3>
                <p id="progressMessage" class="text-sm text-gray-500 mt-2">آماده‌سازی...</p>
            </div>

            <!-- Progress Bar -->
            <div class="relative">
                <!-- Background -->
                <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
                    <!-- Progress Fill -->
                    <div id="progressBar" class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full transition-all duration-500 ease-out" style="width: 0%">
                    </div>
                </div>
                
                <!-- Percentage Text -->
                <div class="mt-3 flex items-center justify-between">
                    <span id="progressPercent" class="text-2xl font-bold text-gray-900">0%</span>
                    <span class="text-sm text-gray-500">لطفاً صبر کنید...</span>
                </div>
            </div>

            <!-- Success Message -->
            <div id="successMessage" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg items-center gap-2 hidden">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span class="text-sm text-green-800 font-medium">کاتالوگ با موفقیت آماده شد!</span>
            </div>
        </div>
    </div>

    <script>
        // Progress Tracker با Vanilla JavaScript
        const ProgressTracker = {
            isDownloading: false,
            progress: 0,
            message: 'آماده‌سازی...',
            intervalId: null,
            progressKey: '{{ $progressKey }}',

            elements: {
                modal: null,
                progressBar: null,
                progressPercent: null,
                progressMessage: null,
                progressIcon: null,
                successMessage: null
            },

            init() {
                this.elements.modal = document.getElementById('progressModal');
                this.elements.progressBar = document.getElementById('progressBar');
                this.elements.progressPercent = document.getElementById('progressPercent');
                this.elements.progressMessage = document.getElementById('progressMessage');
                this.elements.progressIcon = document.getElementById('progressIcon');
                this.elements.successMessage = document.getElementById('successMessage');
            },

            startTracking() {
                console.log('🚀 Progress tracking started with key:', this.progressKey);
                if (!this.elements.modal) this.init();
                
                this.isDownloading = true;
                this.progress = 0;
                this.message = 'آماده‌سازی...';
                
                // نمایش modal
                this.elements.modal.classList.remove('hidden');
                this.elements.modal.classList.add('flex');
                this.elements.successMessage.classList.add('hidden');
                this.elements.progressIcon.classList.add('animate-spin');
                
                // شروع polling هر 500ms
                this.intervalId = setInterval(() => {
                    this.fetchProgress();
                }, 500);
            },

            async fetchProgress() {
                try {
                    const response = await fetch(`/catalog/progress/${this.progressKey}`);

                    if (response.ok) {
                        const data = await response.json();
                        console.log('📈 Progress:', data.percent + '% -', data.message);
                        
                        this.progress = data.percent || 0;
                        this.message = data.message || 'در حال پردازش...';
                        
                        // بروزرسانی UI
                        this.updateUI();
                        
                        // اگر به 100% رسید، بعد از 1.5 ثانیه بسته شود
                        if (this.progress >= 100) {
                            console.log('✅ Progress completed!');
                            this.elements.progressIcon.classList.remove('animate-spin');
                            this.elements.successMessage.classList.remove('hidden');
                            this.elements.successMessage.classList.add('flex');
                            
                            clearInterval(this.intervalId);
                            setTimeout(() => {
                                this.stopTracking();
                            }, 1500);
                        }
                    }
                } catch (error) {
                    console.error('❌ Error fetching progress:', error);
                    // در صورت خطا، بعد از 3 ثانیه modal را ببند
                    setTimeout(() => {
                        this.stopTracking();
                    }, 3000);
                }
            },

            updateUI() {
                if (this.elements.progressBar) {
                    this.elements.progressBar.style.width = this.progress + '%';
                }
                if (this.elements.progressPercent) {
                    this.elements.progressPercent.textContent = this.progress + '%';
                }
                if (this.elements.progressMessage) {
                    this.elements.progressMessage.textContent = this.message;
                }
            },

            stopTracking() {
                console.log('🛑 Progress tracking stopped');
                if (this.intervalId) {
                    clearInterval(this.intervalId);
                }
                this.isDownloading = false;
                this.progress = 0;
                
                // مخفی کردن modal
                if (this.elements.modal) {
                    this.elements.modal.classList.remove('flex');
                    this.elements.modal.classList.add('hidden');
                }
            }
        };

        // تابع global برای شروع tracking
        window.startProgressTracking = function() {
            ProgressTracker.startTracking();
        };

        // Initialize وقتی صفحه لود شد
        document.addEventListener('DOMContentLoaded', () => {
            ProgressTracker.init();
            console.log('✅ Progress Tracker initialized');
        });
    </script>

    <!-- تنظیمات اختصاصی چاپ -->
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 0.5cm;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                background-color: white !important;
            }
            .print-card {
                height: auto !important;
                page-break-inside: avoid;
            }
            .print-img-container {
                height: 120px !important;
            }
        }
        /* استایل‌های بخش کاور */
        .cover-wrapper { text-align: center; margin-top: 80px; }
        .logo-area { margin-bottom: 30px; }
        .logo-area img { max-width: 180px; max-height: 180px; }
        .company-name { font-size: 32px; font-weight: bold; color: #111827; margin-bottom: 10px; }
        .brand-name { font-size: 20px; color: #4b5563; margin-bottom: 30px; }
        .divider { border-top: 2px solid #e5e7eb; width: 60%; margin: 30px auto; }
        .catalog-title-box { margin: 40px auto; padding: 25px; background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 12px; font-size: 26px; font-weight: bold; color: #1e40af; width: 70%; }
        .info-grid { width: 80%; margin: 40px auto 0; border: 1px solid #e5e7eb; border-radius: 12px; padding: 25px; background: #f9fafb; text-align: right; }
        .info-item { margin-bottom: 12px; }
        .info-label { font-weight: bold; color: #374151; display: inline-block; width: 100px; }
        .info-value { color: #111827; }
    </style>

    <!-- نوار ابزار (در زمان چاپ مخفی می‌شود) -->
    <div class="print:hidden sticky top-0 z-50 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <a href="{{ route('supplier.catalog.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors inline-flex items-center gap-1">
                        @svg('heroicon-o-arrow-right', 'w-5 h-5')
                        <span class="text-sm font-medium">بازگشت</span>
                    </a>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900 truncate max-w-xs" title="{{ $template->name }}">{{ $template->name }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button 
                        wire:click="clearCatalogCache"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-orange-300 rounded-lg text-sm font-medium text-orange-700 hover:bg-orange-50 transition-colors disabled:opacity-50"
                        title="اگر اطلاعات کاتالوگ به‌روز نیست، این دکمه را بزنید">
                        @svg('heroicon-o-arrow-path', 'w-5 h-5 text-orange-500')
                        <span wire:loading.remove wire:target="clearCatalogCache">بروزرسانی داده‌ها</span>
                        <span wire:loading wire:target="clearCatalogCache" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            در حال بروزرسانی...
                        </span>
                    </button>
                    <button 
                        onclick="window.startProgressTracking()"
                        wire:click="downloadPdf2" 
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        @svg('heroicon-o-document-arrow-down', 'w-5 h-5 text-gray-500')
                        PDF(کاتالوگ)
                    </button>
                    <button 
                        onclick="window.startProgressTracking()"
                        wire:click="downloadPdf" 
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        @svg('heroicon-o-document-arrow-down', 'w-5 h-5 text-gray-500')
                        PDF(لیست)
                    </button>
                    <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                        @svg('heroicon-o-printer', 'w-5 h-5')
                        چاپ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- محتوای اصلی کاتالوگ -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 print:p-0 print:m-0 print:max-w-full print:w-full">
        {{-- اضافه کردن در ابتدای فایل بعد از <body> --}}
        @if($showCoverPage)
            @switch($settings['cover_theme'] ?? 'default')
                @case('modern')
                    @include('pdf.supplier-catalog-cover-modern', [
                        'supplierName' => $supplierName,
                        'supplierLogoUrl' => $supplierLogoUrl ?? null,
                        'catalogTitle' => $template->title ?? null,
                        'supplier' => $supplier,
                        'contactPhones' => $settings['contact_phones'] ?? [],
                    ])
                    @break
                
                @case('creative')
                    @include('pdf.supplier-catalog-cover-creative', [
                        'supplierName' => $supplierName,
                        'supplierLogoUrl' => $supplierLogoUrl ?? null,
                        'catalogTitle' => $template->title ?? null,
                        'supplier' => $supplier,
                        'contactPhones' => $settings['contact_phones'] ?? [],
                    ])
                    @break
                
                @case('corporate')
                    @include('pdf.supplier-catalog-cover-corporate', [
                        'supplierName' => $supplierName,
                        'supplierLogoUrl' => $supplierLogoUrl ?? null,
                        'catalogTitle' => $template->title ?? null,
                        'supplier' => $supplier,
                        'contactPhones' => $settings['contact_phones'] ?? [],
                    ])
                    @break
                
                @case('custom')
                    {{-- بررسی کدام سیستم استفاده شده --}}
                    @if(isset($settings['custom_cover_table']))
                        @include('pdf.supplier-catalog-cover-table', [
                            'supplierName' => $supplierName,
                            'supplierLogoUrl' => $supplierLogoUrl ?? null,
                            'supplierLogoPath' => $supplierLogoPath ?? null,
                            'catalogTitle' => $template->title ?? null,
                            'supplier' => $supplier,
                            'contactPhones' => $settings['contact_phones'] ?? [],
                            'settings' => $settings,
                            'forPdf' => false,
                        ])
                    @else
                        @include('pdf.supplier-catalog-cover-custom', [
                            'supplierName' => $supplierName,
                            'supplierLogoUrl' => $supplierLogoUrl ?? null,
                            'catalogTitle' => $template->title ?? null,
                            'supplier' => $supplier,
                            'contactPhones' => $settings['contact_phones'] ?? [],
                            'settings' => $settings,
                        ])
                    @endif
                    @break
                
                @default
                    @include('pdf.supplier-catalog-cover', [
                        'supplierName' => $supplierName,
                        'supplierLogo' => $supplierLogoUrl ?? null,
                        'supplierLogoUrl' => $supplierLogoUrl ?? null,
                        'catalogTitle' => $template->title ?? null,
                        'supplier' => $supplier,
                        'contactPhones' => $settings['contact_phones'] ?? [],
                    ])
            @endswitch
            
            {{-- page break برای کاور در preview/print --}}
            @php
                $coverPageBreak = true; // پیش‌فرض
                
                // بررسی در تنظیمات کاتالوگ
                if(isset($settings['cover_page_break'])) {
                    $coverPageBreak = $settings['cover_page_break'];
                }
                // بررسی در تنظیمات کاور قدیمی
                elseif(isset($settings['custom_cover']['page_break'])) {
                    $coverPageBreak = $settings['custom_cover']['page_break'];
                }
            @endphp
            
            @if($coverPageBreak)
                <div class="break-after-page"></div>
            @endif
        @endif
        <div class="bg-white print:bg-transparent">

            {{-- تغییر نام از $productsData به $groupedProducts (مطابق با کلاس) --}}
            @forelse($groupedProducts as $categoryName => $products)

            <!-- مدیریت شکست صفحات (Page Break) -->
            <div class="{{ $separateCategories && !$loop->first ? 'break-before-page ' : '' }}">

                <!-- گرید محصولات -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 print:grid-cols-4 mt-4 print:mt-0">
                    @foreach($products as $product)

                    <!-- کارت محصول -->
                    <div class="print-card flex flex-col border border-gray-200 bg-white hover:shadow-md transition-shadow print:border-gray-400 print:rounded-none print:shadow-none min-w-0 -mt-px -mr-px relative z-0 print:z-10 hover:z-20">

                        <!-- تصویر محصول -->
                        <div class="print-img-container aspect-square w-full relative bg-white flex items-center justify-center overflow-hidden shrink-0 pt-2 print:pt-1">
                            @if(!empty($product['image']))
                            @php
                            $rawImg = $product['image'];
                            if (str_starts_with($rawImg, 'http')) {
                            $imgSrc = $rawImg;
                            } else {
                            $cleanPath = str_replace('public/', '', ltrim($rawImg, '/'));
                            $imgSrc = asset('storage/' . $cleanPath);
                            }
                            @endphp
                            <img src="{{ $imgSrc }}" alt="{{ $product['title'] }}" class="object-contain w-full h-full p-2 print:p-1">
                            @else
                            <div class="w-full h-full flex flex-col items-center justify-center text-gray-300 bg-gray-50/50">
                                @svg('heroicon-o-photo', 'w-10 h-10 mb-1')
                                <span class="text-[10px]">بدون تصویر</span>
                            </div>
                            @endif
                        </div>

                        <!-- عنوان محصول -->
                        <div class="text-center w-full print:m-1 px-2 min-h-[40px] print:min-h-[34px] flex flex-col items-center justify-center shrink-0">
                            <h3 class="text-[13px] print:text-[12px] font-bold text-gray-800 leading-tight print:text-black line-clamp-2" title="{{ $product['title'] }}">
                                {{ $product['title'] }}
                            </h3>
                            @if(!empty($product['attribute_value']))
                                <div class="text-[10px] text-gray-500 mt-1 line-clamp-1">
                                    {{ $product['attribute_value'] }}
                                </div>
                            @endif
                        </div>

                        <!-- قیمت‌ها -->
                        <div class="w-full pb-2 print:pb-1 shrink-0">
                            {{-- منطق پیچیده حذف شد، مستقیما روی آرایه اصلی کلاس حلقه میزنیم --}}
                            @foreach($selectedPriceTypes as $priceType)
                            @php
                                $priceData = $product['prices'][$priceType] ?? null;
                                $priceValue = is_array($priceData) ? ($priceData['price'] ?? null) : $priceData;
                                $warrantyMonths = is_array($priceData) ? ($priceData['warranty_months'] ?? null) : null;
                                
                                $label = $priceLabels[$priceType] ?? $priceType;
                                if($priceType === 'warranty' && $warrantyMonths) {
                                    $label .= " ({$warrantyMonths} ماه)";
                                }
                            @endphp
                            <div class="flex justify-between print:m-1 items-center px-3 py-[2px] overflow-hidden">
                                    <span class="text-[11px] print:text-[10px] text-gray-600 print:text-gray-800 truncate" title="{{ $label }}">
                                        @if($priceValue)
                                            {{ $label }}                                        
                                        @else
                                            <span class="text-gray-300 print:m-1 print:text-gray-400 font-normal">---</span>
                                        @endif



                                        
                                    </span>
                                <span class="text-[13px] print:text-[12px] font-bold text-gray-900 print:text-black flex-shrink-0" dir="ltr" title="{{ $priceValue ? number_format($priceValue) : '-' }}">
                                        @if($priceValue)
                                            {{ number_format($priceValue) }}
                                        @else
                                            <span class="text-gray-300 print:m-1 print:text-gray-400 font-normal">---</span>
                                        @endif
                                    </span>
                            </div>
                            @endforeach
                        </div>

                    </div>
                    @endforeach
                </div>
            </div>

            @empty
            <div class="py-20 text-center flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-xl print:hidden mx-4 mt-8">
                <div class="bg-gray-100 p-4 rounded-full mb-4">
                    @svg('heroicon-o-inbox', 'w-10 h-10 text-gray-400')
                </div>
                <h3 class="text-lg font-bold text-gray-900">محصولی یافت نشد</h3>
                <p class="text-gray-500 mt-2">محصولی با تنظیمات فعلی (دسته‌بندی یا فیلترهای اعمال شده) برای نمایش وجود ندارد.</p>
            </div>
            @endforelse

        </div>
    </div>
</div>
