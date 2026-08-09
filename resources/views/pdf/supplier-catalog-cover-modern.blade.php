<div class="cover-wrapper modern-cover">

{{-- طرح مدرن و مینیمال برای کاور کاتالوگ --}}
<div class="cover-header">
        @if(!empty($forPdf) && !empty($supplierLogoPath) && file_exists($supplierLogoPath))
            <img src="{{ $supplierLogoPath }}" alt="{{ $supplierName ?? 'لوگو' }}" class="logo-main">
        @elseif(empty($forPdf) && !empty($supplierLogoUrl))
            <img src="{{ $supplierLogoUrl }}" alt="{{ $supplierName ?? 'لوگو' }}" class="logo-main">
        @else
            <div class="logo-placeholder">
                {{ mb_substr($supplierName ?? 'لوگو', 0, 1) }}
            </div>
        @endif
    </div>

    <div class="cover-body">
        <h1 class="company-title">{{ $supplier->company_name ?? $supplierName ?? 'تأمین‌کننده' }}</h1>
        
        @if(!empty($supplier->brand_name) && $supplier->brand_name !== ($supplier->company_name ?? $supplierName))
        <h2 class="brand-title">{{ $supplier->brand_name }}</h2>
        @endif

        <div class="catalog-badge">
            <span class="badge-text">{{ $catalogTitle ?? 'کاتالوگ جامع محصولات' }}</span>
        </div>
    </div>

    <div class="cover-footer">
        <div class="contact-grid">
            @if(!empty($contactPhones) && count($contactPhones) > 0)
                @foreach($contactPhones as $phone)
                    @if(!empty($phone['number']))
                    <div class="contact-item">
                        <span class="contact-icon">تلفن:</span>
                        <div class="contact-info">
                            <span class="contact-label">{{ $phone['label'] ?? 'تماس' }}</span>
                            <span class="contact-value">{{ $phone['number'] }}</span>
                        </div>
                    </div>
                    @endif
                @endforeach
            @else
                @if(!empty($supplier->phone))
                <div class="contact-item">
                    <span class="contact-icon">تلفن:</span>
                    <div class="contact-info">
                        <span class="contact-label">تلفن تماس</span>
                        <span class="contact-value">{{ $supplier->phone }}</span>
                    </div>
                </div>
                @endif

                @if(!empty($supplier->mobile))
                <div class="contact-item">
                    <span class="contact-icon">موبایل:</span>
                    <div class="contact-info">
                        <span class="contact-label">موبایل</span>
                        <span class="contact-value">{{ $supplier->mobile }}</span>
                    </div>
                </div>
                @endif
            @endif

            @if(!empty($supplier->email))
            <div class="contact-item">
                <span class="contact-icon">ایمیل:</span>
                <div class="contact-info">
                    <span class="contact-label">ایمیل</span>
                    <span class="contact-value">{{ $supplier->email }}</span>
                </div>
            </div>
            @endif

            @if(!empty($supplier->website))
            <div class="contact-item">
                <span class="contact-icon">وب:</span>
                <div class="contact-info">
                    <span class="contact-label">وب‌سایت</span>
                    <span class="contact-value">{{ $supplier->website }}</span>
                </div>
            </div>
            @endif

            @if(!empty($supplier->address))
            <div class="contact-item full-width">
                <span class="contact-icon">آدرس:</span>
                <div class="contact-info">
                    <span class="contact-label">آدرس</span>
                    <span class="contact-value">
                        @if(!empty($supplier->city)) {{ $supplier->city }} - @endif
                        {{ $supplier->address }}
                    </span>
                </div>
            </div>
            @endif
        </div>
        
        <div class="date-badge">
            {{ jdate(now())->format('Y/m/d') }}
        </div>
    </div>
</div>

<style>
    .modern-cover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 750px;
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border-radius: 0;
        position: relative;
        overflow: hidden;
    }

    .modern-cover::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: rotate 20s linear infinite;
    }

    .cover-header {
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .logo-main {
        max-width: 160px;
        max-height: 160px;
        object-fit: contain;
        filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));
        background: white;
        padding: 20px;
        border-radius: 20px;
    }

    .logo-placeholder {
        width: 160px;
        height: 160px;
        background: white;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 80px;
        font-weight: bold;
        color: #667eea;
        margin: 0 auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }

    .cover-body {
        text-align: center;
        position: relative;
        z-index: 1;
        padding: 40px 0;
    }

    .company-title {
        font-size: 42px;
        font-weight: bold;
        color: white;
        margin: 0 0 15px 0;
        text-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }

    .brand-title {
        font-size: 24px;
        color: rgba(255,255,255,0.9);
        margin: 0 0 30px 0;
        font-weight: normal;
    }

    .catalog-badge {
        display: inline-block;
        background: white;
        padding: 20px 50px;
        border-radius: 50px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }

    .badge-icon {
        font-size: 28px;
        margin-left: 10px;
    }

    .badge-text {
        font-size: 22px;
        font-weight: bold;
        color: #667eea;
    }

    .cover-footer {
        position: relative;
        z-index: 1;
    }

    .contact-grid {
        background: white;
        border-radius: 20px;
        padding: 30px;
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    .contact-item {
        flex: 0 0 calc(50% - 10px);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .contact-item.full-width {
        flex: 0 0 100%;
    }

    .contact-icon {
        font-size: 11px;
        font-weight: bold;
        width: 60px;
        text-align: right;
        color: #667eea;
    }

    .contact-info {
        flex: 1;
    }

    .contact-label {
        display: block;
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 2px;
    }

    .contact-value {
        font-size: 14px;
        color: #111827;
        font-weight: 500;
    }

    .date-badge {
        position: absolute;
        bottom: 10px;
        left: 10px;
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
    }

    @media print {
        .modern-cover {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
