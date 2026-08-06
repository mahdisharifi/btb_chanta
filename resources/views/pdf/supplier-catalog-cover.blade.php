<div class="cover-wrapper">
    <div class="logo-area">
        @if(!empty($forPdf) && !empty($supplierLogoPath) && file_exists($supplierLogoPath))
            {{-- محیط PDF: مسیر فیزیکی، بدون درخواست HTTP و Loopback --}}
            <img src="{{ $supplierLogoPath }}" alt="{{ $supplierName ?? 'لوگو' }}" style="max-width: 150px; max-height: 150px; object-fit: contain;">
        @elseif(empty($forPdf) && !empty($supplierLogoUrl))
            {{-- محیط مرورگر (پیش‌نمایش): URL عمومی --}}
            <img src="{{ $supplierLogoUrl }}" alt="{{ $supplierName ?? 'لوگو' }}" style="max-width: 150px; max-height: 150px; object-fit: contain;">
        @else
            <div style="font-size: 24px; font-weight: bold; color: #4b5563;">
                {{ $supplierName ?? 'بدون لوگو' }}
            </div>
        @endif


        @if(!empty($logoSrc))
        <div class="logo-area">
            <img src="{{ $logoSrc }}" alt="لوگو" />
        </div>
        @endif

    </div>



    <div class="company-name">
        {{ $supplier->company_name ?? $supplierName ?? 'تأمین‌کننده' }}
    </div>

    @if(!empty($supplier->brand_name) && $supplier->brand_name !== ($supplier->company_name ?? $supplierName))
    <div class="brand-name">
        {{ $supplier->brand_name }}
    </div>
    @endif

    <div class="divider"></div>

    <div class="catalog-title-box">
        {{ $catalogTitle ?? 'کاتالوگ جامع محصولات' }}
    </div>

    <br />

    <div class="info-grid">
        @if(!empty($contactPhones) && count($contactPhones) > 0)
            {{-- نمایش شماره‌های تماس سفارشی --}}
            @foreach($contactPhones as $phone)
                @if(!empty($phone['number']))
                <div class="info-item">
                    <span class="info-label">{{ $phone['label'] ?? 'تماس' }}:</span>
                    <span class="info-value">{{ $phone['number'] }}</span>
                </div>
                @endif
            @endforeach
        @else
            {{-- نمایش شماره‌های پیش‌فرض از supplier --}}
            @if(!empty($supplier->phone))
            <div class="info-item">
                <span class="info-label">تلفن تماس:</span>
                <span class="info-value">{{ $supplier->phone }}</span>
            </div>
            @endif

            @if(!empty($supplier->mobile))
            <div class="info-item">
                <span class="info-label">موبایل:</span>
                <span class="info-value">{{ $supplier->mobile }}</span>
            </div>
            @endif
        @endif

        @if(!empty($supplier->email))
        <div class="info-item">
            <span class="info-label">ایمیل:</span>
            <span class="info-value">{{ $supplier->email }}</span>
        </div>
        @endif

        @if(!empty($supplier->website))
        <div class="info-item">
            <span class="info-label">وب‌سایت:</span>
            <span class="info-value">{{ $supplier->website }}</span>
        </div>
        @endif

        @if(!empty($supplier->address))
        <div class="info-item full-width">
            <span class="info-label">آدرس:</span>
            <span class="info-value">
                    @if(!empty($supplier->city)) {{ $supplier->city }} - @endif
                    {{ $supplier->address }}
                </span>
        </div>
        @endif
    </div>

</div>
