@php
    $displayExchangeRateBasis = !empty($displayCurrencyCode) && $displayCurrencyCode !== 'TOM'
        ? ($exchangeRateBasis ?? null)
        : null;
@endphp

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
    <div id="progressModal" role="dialog" aria-modal="true" aria-labelledby="progressTitle" aria-describedby="progressMessage" tabindex="-1" class="fixed inset-0 z-[9999] items-center justify-center bg-black/50 backdrop-blur-sm outline-none hidden">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md mx-4 transform transition-all">
            
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="mx-auto w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mb-4">
                    <svg id="progressIcon" class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                </div>
                <h3 id="progressTitle" class="text-xl font-bold text-gray-900">در حال تولید کاتالوگ</h3>
                <p id="progressMessage" role="status" aria-live="polite" aria-atomic="true" class="text-sm text-gray-500 mt-2">آماده‌سازی...</p>
            </div>

            <!-- Progress Bar -->
            <div class="relative">
                <!-- Background -->
                <div id="progressBar" role="progressbar" aria-label="درصد پیشرفت ساخت PDF" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" class="h-3 bg-gray-200 rounded-full overflow-hidden">
                    <!-- Progress Fill -->
                    <div id="progressFill" class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full transition-all duration-500 ease-out" style="width: 0%">
                    </div>
                </div>
                
                <!-- Percentage Text -->
                <div class="mt-3 flex items-center justify-between">
                <span id="progressPercent" aria-live="polite" aria-atomic="true" class="text-2xl font-bold text-gray-900">0%</span>
                    <span class="text-sm text-gray-500">لطفاً صبر کنید...</span>
                </div>
            </div>

            <!-- Success Message -->
            <div id="successMessage" role="status" aria-live="polite" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg items-center gap-2 hidden">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span class="text-sm text-green-800 font-medium">کاتالوگ با موفقیت آماده شد!</span>
            </div>
            <div id="errorMessage" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800 hidden" role="alert"></div>
        </div>
    </div>

    <script>
        // Progress Tracker با Vanilla JavaScript
        const ProgressTracker = {
            progress: 0,
            pollTimeoutId: null,
            pollDelayMs: 2000,
                returnFocusElement: null,
            progressEndpoint: @js(route('catalog.progress', $progressKey)),
            progressUrl: null,
            startedAt: 0,
            downloadStarted: false,
            pendingTimeoutMs: 15000,
            staleProgressTimeoutMs: @js((int) ini_get('max_execution_time') > 0
                ? (((int) ini_get('max_execution_time') + 10) * 1000)
                : 300000),

            elements: {
                modal: null,
                progressBar: null,
                progressFill: null,
                progressPercent: null,
                progressMessage: null,
                progressIcon: null,
                successMessage: null,
                errorMessage: null
            },

            init() {
                this.elements.modal = document.getElementById('progressModal');
                this.elements.progressBar = document.getElementById('progressBar');
                this.elements.progressFill = document.getElementById('progressFill');
                this.elements.progressPercent = document.getElementById('progressPercent');
                this.elements.progressMessage = document.getElementById('progressMessage');
                this.elements.progressIcon = document.getElementById('progressIcon');
                this.elements.successMessage = document.getElementById('successMessage');
                this.elements.errorMessage = document.getElementById('errorMessage');
            },

            startTracking() {
                if (!this.elements.modal) this.init();
                if (this.pollTimeoutId) clearTimeout(this.pollTimeoutId);

                this.returnFocusElement = document.activeElement;
                this.progressUrl = this.progressEndpoint;
                this.startedAt = Date.now();
                this.pollDelayMs = 2000;
                this.downloadStarted = false;
                this.progress = 0;
                this.elements.modal.classList.remove('hidden');
                this.elements.modal.classList.add('flex');
                this.elements.successMessage.classList.add('hidden');
                this.elements.errorMessage.classList.add('hidden');
                this.elements.progressIcon.classList.add('animate-spin');
                this.elements.modal.focus();

                this.fetchProgress();
            },

            async fetchProgress() {
                try {
                    const controller = new AbortController();
                    const requestTimeoutId = setTimeout(() => controller.abort(), 10000);
                    let response;
                    let data;
                    try {
                        response = await fetch(this.progressUrl, {
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json' },
                            signal: controller.signal,
                        });
                        if (response.ok) {
                            data = await response.json();
                        }
                    } finally {
                        clearTimeout(requestTimeoutId);
                    }

                    if (response.status === 401 || response.status === 404) {
                        this.showError('وضعیت ساخت PDF منقضی شده است. دوباره خروجی بگیرید.');
                        return;
                    }

                    if (!response.ok) throw new Error('PDF progress returned ' + response.status);

                    this.progress = Math.max(0, Math.min(100, Number(data.percent) || 0));
                    this.elements.progressMessage.textContent = data.message || 'در حال پردازش...';
                    this.updateUI();

                    const elapsedMs = Date.now() - this.startedAt;
                    const lastUpdateMs = Number(data.timestamp) > 0
                        ? Date.now() - Number(data.timestamp) * 1000
                        : null;

                    if (data.status === 'pending' && elapsedMs > this.pendingTimeoutMs) {
                        this.showError('ساخت PDF شروع نشد. دوباره تلاش کنید.');
                        return;
                    }

                    if (data.status === 'processing'
                        && lastUpdateMs !== null
                        && lastUpdateMs > this.staleProgressTimeoutMs) {
                        this.showError('ساخت PDF متوقف شد یا زمان اجرای سرور به پایان رسید. دوباره تلاش کنید.');
                        return;
                    }

                    if (data.status === 'failed') {
                        this.showError(data.message);
                        return;
                    }

                    if (data.status === 'completed' || this.progress >= 100) {
                        this.complete();
                        return;
                    }

                    this.pollDelayMs = Math.min(10000, Math.ceil(this.pollDelayMs * 1.5));

                    if (elapsedMs > this.staleProgressTimeoutMs + 5000) {
                        this.showError('ساخت PDF بیش از زمان مجاز طول کشید. بعداً دوباره تلاش کنید.');
                        return;
                    }
                } catch (error) {
                    console.error('❌ Error fetching progress:', error);
                    if (error && error.name === 'AbortError' && this.elements.progressMessage) {
                        this.elements.progressMessage.textContent = 'ارتباط کند است؛ دوباره تلاش می‌کنیم.';
                    }
                    this.pollDelayMs = Math.min(10000, Math.ceil(this.pollDelayMs * 1.5));
                    if (Date.now() - this.startedAt > 60000) {
                        this.showError('ارتباط با وضعیت ساخت PDF برقرار نشد. دوباره تلاش کنید.');
                        return;
                    }
                }

                if (this.progressUrl) {
                    this.pollTimeoutId = setTimeout(() => this.fetchProgress(), this.pollDelayMs);
                }
            },

            complete() {
                if (this.downloadStarted) return;
                this.downloadStarted = true;
                if (this.pollTimeoutId) clearTimeout(this.pollTimeoutId);
                this.elements.progressIcon.classList.remove('animate-spin');
                this.elements.successMessage.classList.remove('hidden');
                this.elements.successMessage.classList.add('flex');
                this.elements.successMessage.querySelector('span').textContent = 'کاتالوگ با موفقیت آماده شد!';
                setTimeout(() => this.stopTracking(), 8000);
            },

            showError(message) {
                if (!this.elements.modal) this.init();
                if (this.pollTimeoutId) clearTimeout(this.pollTimeoutId);
                this.progressUrl = null;
                this.elements.modal.classList.remove('hidden');
                this.elements.modal.classList.add('flex');
                this.elements.successMessage.classList.add('hidden');
                this.elements.progressIcon.classList.remove('animate-spin');
                this.elements.errorMessage.textContent = message || 'ساخت PDF ناموفق بود. دوباره تلاش کنید.';
                this.elements.errorMessage.classList.remove('hidden');
                setTimeout(() => this.stopTracking(), 6000);
            },

            updateUI() {
                if (this.elements.progressFill) {
                    this.elements.progressFill.style.width = this.progress + '%';
                }
                if (this.elements.progressPercent) {
                    this.elements.progressPercent.textContent = this.progress + '%';
                }
                if (this.elements.progressBar) {
                    this.elements.progressBar.setAttribute('aria-valuenow', String(this.progress));
                }
            },

            stopTracking() {
                if (this.pollTimeoutId) {
                    clearTimeout(this.pollTimeoutId);
                }
                this.pollTimeoutId = null;
                this.progressUrl = null;

                if (this.elements.modal) {
                    this.elements.modal.classList.remove('flex');
                    this.elements.modal.classList.add('hidden');
                }

                if (this.returnFocusElement?.isConnected && !this.returnFocusElement.disabled) {
                    this.returnFocusElement.focus();
                }
                this.returnFocusElement = null;
            }
        };

        window.startProgressTracking = () => ProgressTracker.startTracking();

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab' || !ProgressTracker.elements.modal) return;
            if (ProgressTracker.elements.modal.classList.contains('hidden')) return;

            event.preventDefault();
            ProgressTracker.elements.modal.focus();
        });

        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ status }) => {
                    if (!ProgressTracker.progressUrl) return;

                    ProgressTracker.showError(status === 503
                        ? 'ارتباط با سرور قطع شد. دوباره تلاش کنید.'
                        : 'ساخت PDF با خطای سرور متوقف شد. دوباره تلاش کنید.');
                });
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            ProgressTracker.init();
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
            <div class="flex flex-col gap-2 py-2 lg:flex-row lg:items-center lg:justify-between lg:gap-3 lg:py-0 lg:h-16">
                <div class="flex min-w-0 items-center gap-4">
                    <a href="{{ route('supplier.catalog.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors inline-flex items-center gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2">
                        @svg('heroicon-o-arrow-right', 'w-5 h-5')
                        <span class="text-sm font-medium">بازگشت</span>
                    </a>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <div>
                        <h1 class="max-w-[10rem] truncate text-lg font-bold text-gray-900 lg:max-w-xs" title="{{ $template->name }}">{{ $template->name }}</h1>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 lg:gap-3">
                    <button 
                        wire:click="clearCatalogCache"
                        wire:loading.attr="disabled"
                        @disabled($catalogSnapshotBusy)
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-orange-300 rounded-lg text-sm font-medium text-orange-700 hover:bg-orange-50 transition-colors disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2"
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
                        id="downloadPdf2Btn"
                        onclick="window.startProgressTracking()"
                        wire:click="downloadPdf2" 
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2">
                        @svg('heroicon-o-document-arrow-down', 'w-5 h-5 text-gray-500')
                        PDF(کاتالوگ)
                    </button>
                    <button 
                        id="downloadPdfBtn"
                        onclick="window.startProgressTracking()"
                        wire:click="downloadPdf" 
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2">
                        @svg('heroicon-o-document-arrow-down', 'w-5 h-5 text-gray-500')
                        PDF(لیست)
                    </button>
                    <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-indigo-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2">
                        @svg('heroicon-o-printer', 'w-5 h-5')
                        چاپ
                    </button>
                </div>
            </div>
        </div>
    </div>

    @error('pdfExport')
        <div class="max-w-6xl mx-auto mt-3 px-4 sm:px-6 lg:px-8" role="alert">
            <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm leading-6 text-red-800">{{ $message }}</div>
        </div>
    @enderror

    @if(($settings['price_display_currency'] ?? 'toman') === 'supplier' && !$displayCurrencyCode)
        <div class="mx-auto mt-4 max-w-6xl rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900" role="alert">
            ارز پایهٔ کاتالوگ قابل استفاده نیست؛ ارز پایه و نرخ معتبر همهٔ شعبه‌های انتخاب‌شده باید یکسان باشد. یک شعبه را انتخاب کنید، نرخ ارز شعب را همسان کنید یا نمایش تومان را در تنظیمات برگزینید.
        </div>
    @elseif($currencySnapshotWarning && ($showCoverPage || empty($displayExchangeRateBasis)))
        <div class="mx-auto mt-4 max-w-6xl rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900" role="alert">
            نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده یکسان نیست یا معتبر نیست؛ مبلغ فقط با نرخ ذخیره‌شدهٔ معتبر و هم‌ارز نمایش داده می‌شود و در غیر این صورت ناموجود است.
        </div>
    @endif

    @if(!$showCoverPage && !empty($displayExchangeRateBasis))
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('pdf.partials.exchange-rate-basis-summary', [
            'exchangeRateBasis' => $displayExchangeRateBasis,
            'currencySnapshotWarning' => $currencySnapshotWarning,
        ])
        </div>
    @endif

    @error('priceDisplayCurrency')
        <div class="mx-auto mt-4 max-w-6xl rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm leading-6 text-red-800" role="alert">{{ $message }}</div>
    @enderror

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
                        'exchangeRateBasis' => $displayExchangeRateBasis,
                        'currencySnapshotWarning' => false,
                    ])
                    @break
                
                @case('creative')
                    @include('pdf.supplier-catalog-cover-creative', [
                        'supplierName' => $supplierName,
                        'supplierLogoUrl' => $supplierLogoUrl ?? null,
                        'catalogTitle' => $template->title ?? null,
                        'supplier' => $supplier,
                        'contactPhones' => $settings['contact_phones'] ?? [],
                        'exchangeRateBasis' => $displayExchangeRateBasis,
                        'currencySnapshotWarning' => false,
                    ])
                    @break
                
                @case('corporate')
                    @include('pdf.supplier-catalog-cover-corporate', [
                        'supplierName' => $supplierName,
                        'supplierLogoUrl' => $supplierLogoUrl ?? null,
                        'catalogTitle' => $template->title ?? null,
                        'supplier' => $supplier,
                        'contactPhones' => $settings['contact_phones'] ?? [],
                        'exchangeRateBasis' => $displayExchangeRateBasis,
                        'currencySnapshotWarning' => false,
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
                            'exchangeRateBasis' => $displayExchangeRateBasis,
                            'currencySnapshotWarning' => false,
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
                            'exchangeRateBasis' => $displayExchangeRateBasis,
                            'currencySnapshotWarning' => false,
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
                        'exchangeRateBasis' => $displayExchangeRateBasis,
                        'currencySnapshotWarning' => false,
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
                            {{-- ✅ پشتیبانی از ساختار جدید: ['tier_index' => [price_type_ids]] --}}
                            @php
                                // تولید لیست قیمت‌ها برای نمایش
                                $displayPrices = [];
                                
                                if ($selectedPriceTypes === 'all') {
                                    // همه قیمت‌ها - نمایش همه کلیدهای موجود در prices
                                    foreach ($product['prices'] as $key => $priceData) {
                                        if (is_array($priceData) && ($priceData['price'] ?? 0) > 0) {
                                            $displayPrices[$key] = $priceData;
                                        }
                                    }
                                } elseif (is_array($selectedPriceTypes)) {
                                    // ساختار جدید: ['tier_index' => [price_type_ids]]
                                    $tierNames = [0 => 'زیرکارتن', 1 => 'کارتن', 2 => 'عمده', 3 => 'بار'];
                                    
                                    foreach ($selectedPriceTypes as $tierIndex => $priceTypeIds) {
                                        $tierName = $tierNames[$tierIndex] ?? "تیراژ {$tierIndex}";
                                        
                                        foreach ($priceTypeIds as $priceTypeId) {
                                            // قیمت نقدی یا گارانتی
                                            $key = "{$tierIndex}_{$priceTypeId}";
                                            if (isset($product['prices'][$key])) {
                                                $displayPrices[$key] = $product['prices'][$key];
                                            }
                                            
                                            // قیمت‌های چک (اگر نوع = check)
                                            $priceType = $priceTypesById[(int) $priceTypeId] ?? null;
                                            if (($priceType['code'] ?? null) === 'check') {
                                                // جستجو برای همه قیمت‌های چک این تیراژ
                                                foreach ($product['prices'] as $priceKey => $priceData) {
                                                    if (is_array($priceData) 
                                                        && isset($priceData['check_period_id'])
                                                        && $priceData['tier_index'] == $tierIndex
                                                        && $priceData['price_type_id'] == $priceTypeId
                                                    ) {
                                                        $displayPrices[$priceKey] = $priceData;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            @endphp
                            
                            @if($branchAwareCatalog && !empty($product['branch_prices']))
                                @php
                                    $branchDisplayRows = $catalogBranchPriceRows->build(
                                        $product['branch_prices'],
                                        $selectedPriceTypes,
                                        $priceTypesById,
                                    );
                                @endphp
                                @foreach($branchDisplayRows as $branchRow)
                                @php
                                    $priceData = $branchRow['price_data'];
                                    $priceValue = is_array($priceData) ? ($priceData['price'] ?? null) : null;
                                    $warrantyPrice = is_array($priceData) ? ($priceData['warranty_price'] ?? 0) : 0;
                                    $finalPrice = $priceValue;
                                    if ($includeWarranty && $warrantyPrice > 0 && $finalPrice !== null) {
                                        $finalPrice += $warrantyPrice;
                                    }
                                    $formattedPrice = $priceFormatter->format(
                                        $finalPrice,
                                        $displayCurrencyCode,
                                        is_array($priceData) ? ($priceData['catalog_display_currency'] ?? $priceData['foreign_price_applied_currency'] ?? null) : null,
                                        is_array($priceData) ? ($priceData['catalog_display_rate'] ?? $priceData['foreign_price_rate'] ?? null) : null,
                                        is_array($priceData) ? (bool) ($priceData['catalog_display_valid'] ?? true) : true,
                                    );
                                    $label = $branchRow['label'];
                                    $warrantyType = is_array($priceData) ? ($priceData['warranty_type'] ?? null) : null;
                                    $warrantyDuration = is_array($priceData) ? ($priceData['warranty_duration'] ?? null) : null;
                                    $warrantyLabel = '';
                                    if (!$includeWarranty && $warrantyType) {
                                        if ($warrantyType === 'test') {
                                            $warrantyLabel = ' (تست)';
                                        } elseif ($warrantyType === 'limited' && $warrantyDuration) {
                                            $warrantyLabel = " ({$warrantyDuration} ماه)";
                                        } elseif ($warrantyType === 'lifetime') {
                                            $warrantyLabel = ' (مادام‌العمر)';
                                        }
                                    }
                                @endphp
                                <div class="flex justify-between print:m-1 items-center px-3 py-[2px] overflow-hidden">
                                    <span class="text-[11px] print:text-[10px] text-gray-600 print:text-gray-800 truncate" title="{{ $label }}{{ $warrantyLabel }}">
                                        {{ $label }}{{ $warrantyLabel }}
                                    </span>
                                    <span class="text-[13px] print:text-[12px] font-bold text-gray-900 print:text-black flex-shrink-0" dir="ltr" title="{{ $formattedPrice ?? '-' }}">
                                        @if($formattedPrice)
                                            {{ $formattedPrice }}
                                        @else
                                            <span class="text-gray-300 print:m-1 print:text-gray-400 font-normal">---</span>
                                        @endif
                                    </span>
                                </div>
                                @endforeach
                            @else
                            @foreach($displayPrices as $key => $priceData)
                            @php
                                $priceValue = is_array($priceData) ? ($priceData['price'] ?? null) : $priceData;
                                $warrantyPrice = is_array($priceData) ? ($priceData['warranty_price'] ?? 0) : 0;
                                $tierIndex = is_array($priceData) ? ($priceData['tier_index'] ?? null) : null;
                                $priceTypeId = is_array($priceData) ? ($priceData['price_type_id'] ?? null) : null;
                                $checkPeriodLabel = is_array($priceData) ? ($priceData['check_period_label'] ?? null) : null;
                                $warrantyType = is_array($priceData) ? ($priceData['warranty_type'] ?? null) : null;
                                $warrantyDuration = is_array($priceData) ? ($priceData['warranty_duration'] ?? null) : null;
                                
                                // ✅ محاسبه قیمت نهایی (با یا بدون گارانتی)
                                $finalPrice = $priceValue;
                                if ($includeWarranty && $warrantyPrice > 0) {
                                    $finalPrice = $priceValue + $warrantyPrice;
                                }
                                $formattedPrice = $priceFormatter->format(
                                    $finalPrice,
                                    $displayCurrencyCode,
                                    is_array($priceData) ? ($priceData['catalog_display_currency'] ?? $priceData['foreign_price_applied_currency'] ?? null) : null,
                                    is_array($priceData) ? ($priceData['catalog_display_rate'] ?? $priceData['foreign_price_rate'] ?? null) : null,
                                    is_array($priceData) ? (bool) ($priceData['catalog_display_valid'] ?? true) : true,
                                );
                                
                                // تولید label
                                $tierNames = [0 => 'زیرکارتن', 1 => 'کارتن', 2 => 'عمده', 3 => 'بار'];
                                $tierName = $tierNames[$tierIndex] ?? "تیراژ {$tierIndex}";
                                
                                $priceType = $priceTypesById[(int) $priceTypeId] ?? null;
                                $priceTypeName = $priceType['name'] ?? "قیمت {$priceTypeId}";
                                
                                if ($checkPeriodLabel) {
                                    $label = "{$tierName} - {$checkPeriodLabel}";
                                } else {
                                    $label = "{$tierName} - {$priceTypeName}";
                                }
                                
                                // اضافه کردن توضیحات گارانتی به label (فقط اگر گارانتی فعال نباشد)
                                $warrantyLabel = '';
                                if (!$includeWarranty && $warrantyType) {
                                    if ($warrantyType === 'test') {
                                        $warrantyLabel = " (تست)";
                                    } elseif ($warrantyType === 'limited' && $warrantyDuration) {
                                        $warrantyLabel = " ({$warrantyDuration} ماه)";
                                    } elseif ($warrantyType === 'lifetime') {
                                        $warrantyLabel = " (مادام‌العمر)";
                                    }
                                }
                            @endphp
                            <div class="flex justify-between print:m-1 items-center px-3 py-[2px] overflow-hidden">
                                <span class="text-[11px] print:text-[10px] text-gray-600 print:text-gray-800 truncate" title="{{ $label }}{{ $warrantyLabel }}">
                                    {{ $label }}{{ $warrantyLabel }}
                                </span>
                                <span class="text-[13px] print:text-[12px] font-bold text-gray-900 print:text-black flex-shrink-0" dir="ltr" title="{{ $formattedPrice ?? '-' }}">
                                    @if($formattedPrice)
                                        {{ $formattedPrice }}
                                    @else
                                        <span class="text-gray-300 print:m-1 print:text-gray-400 font-normal">---</span>
                                    @endif
                                </span>
                            </div>
                            @endforeach
                            @endif
                            
                            {{-- ✅ نمایش اطلاعات گارانتی (اگر فعال باشد و محصول گارانتی داشته باشد) --}}
                            @if($includeWarranty && !empty($displayPrices))
                                @php
                                    // یافتن یک نمونه قیمت برای گرفتن اطلاعات گارانتی
                                    $samplePrice = collect($displayPrices)->first(fn($p) => isset($p['warranty_type']) && $p['warranty_type']);
                                @endphp
                                @if($samplePrice && isset($samplePrice['warranty_type']))
                                <div class="mt-2 pt-2 border-t border-gray-200 px-3">
                                    <div class="text-[10px] text-gray-600 flex items-center gap-1">
                                        <img src="{{ asset('images/icons/warranty-shield.png') }}" class="w-3 h-3" alt="گارانتی">
                                        <span class="font-medium text-purple-700">گارانتی:</span>
                                        @if($samplePrice['warranty_type'] === 'test')
                                            تست
                                        @elseif($samplePrice['warranty_type'] === 'limited')
                                            {{ $samplePrice['warranty_duration'] ?? 0 }} ماه
                                        @elseif($samplePrice['warranty_type'] === 'lifetime')
                                            مادام‌العمر
                                        @endif
                                    </div>
                                </div>
                                @endif
                            @endif
                        </div>

                    </div>
                    @endforeach
                </div>
            </div>

            @empty
            @if($catalogSnapshotBusy)
            <div class="mx-4 mt-8 rounded-xl border border-amber-300 bg-amber-50 px-5 py-8 text-center text-amber-950 print:hidden" role="status" aria-live="polite">
                <h3 class="text-base font-bold">پیش‌نمایش در حال آماده‌سازی است</h3>
                <p class="mt-2 text-sm leading-6">درخواست دیگری در حال ساخت همین اطلاعات است. چند ثانیه صبر کنید و دوباره بررسی کنید.</p>
                <button type="button" wire:click="retryCatalogSnapshot" wire:loading.attr="disabled" class="mt-4 min-h-11 rounded-lg bg-amber-700 px-5 py-2 text-sm font-bold text-white hover:bg-amber-800 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-800">
                    <span wire:loading.remove wire:target="retryCatalogSnapshot">بررسی دوباره</span>
                    <span wire:loading wire:target="retryCatalogSnapshot">در حال بررسی…</span>
                </button>
            </div>
            @elseif($previewDataExpired)
            <div class="mx-4 mt-8 rounded-xl border border-amber-300 bg-amber-50 px-5 py-8 text-center text-amber-950 print:hidden" role="status">
                <h3 class="text-base font-bold">پیش‌نمایش این کاتالوگ منقضی شده است</h3>
                <p class="mt-2 text-sm leading-6">برای بارگذاری دوبارهٔ محصولات، دکمهٔ «بروزرسانی داده‌ها» را بزنید.</p>
            </div>
            @else
            <div class="py-20 text-center flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-xl print:hidden mx-4 mt-8">
                <div class="bg-gray-100 p-4 rounded-full mb-4">
                    @svg('heroicon-o-inbox', 'w-10 h-10 text-gray-400')
                </div>
                <h3 class="text-lg font-bold text-gray-900">محصولی یافت نشد</h3>
                <p class="text-gray-500 mt-2">محصولی با تنظیمات فعلی (دسته‌بندی یا فیلترهای اعمال شده) برای نمایش وجود ندارد.</p>
            </div>
            @endif
            @endforelse

        </div>
    </div>
</div>
