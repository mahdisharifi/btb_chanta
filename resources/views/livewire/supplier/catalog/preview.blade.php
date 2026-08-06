<div class="min-h-screen bg-gray-50 text-gray-800 font-sans" dir="rtl">

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
                    <button wire:click="downloadPdf2" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        @svg('heroicon-o-document-arrow-down', 'w-5 h-5 text-gray-500')
                        PDF(کاتالوگ)
                    </button>
                    <button wire:click="downloadPdf" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
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
        @include('pdf.supplier-catalog-cover', [
        'supplierName' => $supplierName,
        'supplierLogo' => $supplierLogoUrl ?? null,
        'supplierLogoUrl' => $supplierLogoUrl ?? null,
        'catalogTitle' => $template->title ?? null,
        'supplier' => $supplier,
        ])
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
                        <div class="text-center w-full print:m-1 px-2 h-[36px] print:h-[30px] flex items-center justify-center shrink-0">
                            <h3 class="text-[13px] print:text-[12px] font-bold text-gray-800 leading-tight print:text-black line-clamp-2" title="{{ $product['title'] }}">
                                {{ $product['title'] }}
                                @if(!empty($product['attribute_value']))
                                    <span class="block text-[10px] text-gray-500 font-normal mt-0.5">
                                        ({{ $product['attribute_value'] }})
                                    </span>
                                @endif
                            </h3>
                        </div>

                        <!-- قیمت‌ها -->
                        <div class="w-full pb-2 print:pb-1 shrink-0">
                            {{-- منطق پیچیده حذف شد، مستقیما روی آرایه اصلی کلاس حلقه میزنیم --}}
                            @foreach($selectedPriceTypes as $priceType)
                            @php
                            $priceValue = $product['prices'][$priceType] ?? null;
                            @endphp
                            <div class="flex justify-between print:m-1 items-center px-3 py-[2px] overflow-hidden">
                                    <span class="text-[11px] print:text-[10px] text-gray-600 print:text-gray-800 truncate" title="{{ $priceLabels[$priceType] ?? $priceType }}">
                                        {{ $priceLabels[$priceType] ?? $priceType }}
                                    </span>
                                <span class="text-[13px] print:text-[12px] font-bold text-gray-900 print:text-black flex-shrink-0" dir="ltr" title="{{ $priceValue ? number_format($priceValue) : '-' }}">
                                        @if($priceValue)
                                            {{ number_format($priceValue) }}
                                        @else
                                            <span class="text-gray-300 print:m-1 print:text-gray-400 font-normal">-</span>
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
