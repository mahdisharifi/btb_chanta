<div class="cover-wrapper">
    <div class="logo-area" >
        @if(!empty($supplierLogo) && file_exists($supplierLogo))
        <!-- استفاده از مسیر فیزیکی برای mPDF -->
        <img src="{{ $supplierLogo }}" alt="{{ $supplierName ?? 'لوگو' }}" style="max-width: 150px; max-height: 150px; object-fit: contain;">
        @elseif(!empty($supplierLogoUrl))
        <!-- استفاده از لینک وب برای پیش‌نمایش در مرورگر -->
        <img src="{{ $supplierLogoUrl }}" alt="{{ $supplierName ?? 'لوگو' }}" style="max-width: 150px; max-height: 150px; object-fit: contain;">
        @else
        <!-- در صورت نداشتن لوگو، نمایش یک آیکن یا نام تامین‌کننده -->
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
