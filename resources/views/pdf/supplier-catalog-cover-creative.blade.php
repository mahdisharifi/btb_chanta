<div class="creative-cover">
{{-- طرح رسمی و سازمانی برای کاور کاتالوگ --}}
<div class="creative-bg">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>

    <div class="cover-content">
        <div class="logo-section">
            @if(!empty($forPdf) && !empty($supplierLogoPath) && file_exists($supplierLogoPath))
                <img src="{{ $supplierLogoPath }}" alt="{{ $supplierName ?? 'لوگو' }}" class="creative-logo">
            @elseif(empty($forPdf) && !empty($supplierLogoUrl))
                <img src="{{ $supplierLogoUrl }}" alt="{{ $supplierName ?? 'لوگو' }}" class="creative-logo">
            @else
                <div class="creative-logo-placeholder">
                    {{ mb_substr($supplierName ?? 'L', 0, 1) }}
                </div>
            @endif
        </div>

        <div class="title-section">
            <h1 class="creative-company-name">{{ $supplier->company_name ?? $supplierName ?? 'تأمین‌کننده' }}</h1>
            
            @if(!empty($supplier->brand_name) && $supplier->brand_name !== ($supplier->company_name ?? $supplierName))
            <div class="creative-brand-name">{{ $supplier->brand_name }}</div>
            @endif

            <div class="creative-divider">
                <span class="divider-line"></span>
                <span class="divider-icon">*</span>
                <span class="divider-line"></span>
            </div>

            <div class="creative-catalog-title">
                {{ $catalogTitle ?? 'کاتالوگ محصولات' }}
            </div>
        </div>

        <div class="contact-cards">
            @if(!empty($contactPhones) && count($contactPhones) > 0)
                @foreach($contactPhones as $phone)
                    @if(!empty($phone['number']))
                    <div class="contact-card">
                        <div class="card-icon">تلفن</div>
                        <div class="card-content">
                            <div class="card-label">{{ $phone['label'] ?? 'تماس' }}</div>
                            <div class="card-value">{{ $phone['number'] }}</div>
                        </div>
                    </div>
                    @endif
                @endforeach
            @else
                @if(!empty($supplier->phone))
                <div class="contact-card">
                    <div class="card-icon">تلفن</div>
                    <div class="card-content">
                        <div class="card-label">تلفن تماس</div>
                        <div class="card-value">{{ $supplier->phone }}</div>
                    </div>
                </div>
                @endif

                @if(!empty($supplier->mobile))
                <div class="contact-card">
                    <div class="card-icon">موبایل</div>
                    <div class="card-content">
                        <div class="card-label">موبایل</div>
                        <div class="card-value">{{ $supplier->mobile }}</div>
                    </div>
                </div>
                @endif
            @endif

            @if(!empty($supplier->email))
            <div class="contact-card">
                <div class="card-icon">ایمیل</div>
                <div class="card-content">
                    <div class="card-label">ایمیل</div>
                    <div class="card-value">{{ $supplier->email }}</div>
                </div>
            </div>
            @endif

            @if(!empty($supplier->website))
            <div class="contact-card">
                <div class="card-icon">وب‌سایت</div>
                <div class="card-content">
                    <div class="card-label">وب‌سایت</div>
                    <div class="card-value">{{ $supplier->website }}</div>
                </div>
            </div>
            @endif

            @if(!empty($supplier->address))
            <div class="contact-card wide">
                <div class="card-icon">آدرس</div>
                <div class="card-content">
                    <div class="card-label">آدرس</div>
                    <div class="card-value">
                        @if(!empty($supplier->city)) {{ $supplier->city }} - @endif
                        {{ $supplier->address }}
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="creative-year">
            {{ jdate(now())->format('Y/m/d') }}
        </div>
    </div>
</div>

<style>
    .creative-cover {
        background: #0f172a;
        min-height: 750px;
        position: relative;
        overflow: hidden;
    }

    .creative-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        overflow: hidden;
    }

    .shape {
        position: absolute;
        border-radius: 50%;
        opacity: 0.15;
    }

    .shape-1 {
        width: 400px;
        height: 400px;
        background: linear-gradient(135deg, #ec4899, #8b5cf6);
        top: -100px;
        right: -100px;
    }

    .shape-2 {
        width: 300px;
        height: 300px;
        background: linear-gradient(135deg, #3b82f6, #06b6d4);
        bottom: 100px;
        left: -80px;
    }

    .shape-3 {
        width: 200px;
        height: 200px;
        background: linear-gradient(135deg, #f59e0b, #ef4444);
        top: 200px;
        left: 50px;
    }

    .shape-4 {
        width: 250px;
        height: 250px;
        background: linear-gradient(135deg, #10b981, #3b82f6);
        bottom: -50px;
        right: 100px;
    }

    .cover-content {
        position: relative;
        z-index: 1;
        padding: 40px;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-height: 750px;
    }

    .logo-section {
        margin-top: 50px;
        margin-bottom: 40px;
    }

    .creative-logo {
        max-width: 140px;
        max-height: 140px;
        object-fit: contain;
        background: white;
        padding: 20px;
        border-radius: 50%;
        box-shadow: 0 15px 50px rgba(0,0,0,0.5);
    }

    .creative-logo-placeholder {
        width: 140px;
        height: 140px;
        background: linear-gradient(135deg, #ec4899, #8b5cf6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 60px;
        font-weight: bold;
        color: white;
        box-shadow: 0 15px 50px rgba(0,0,0,0.5);
    }

    .title-section {
        text-align: center;
        margin-bottom: 40px;
    }

    .creative-company-name {
        font-size: 40px;
        font-weight: bold;
        color: white;
        margin: 0 0 10px 0;
    }

    .creative-brand-name {
        font-size: 22px;
        color: rgba(255,255,255,0.8);
        margin-bottom: 20px;
    }

    .creative-divider {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        margin: 25px 0;
    }

    .divider-line {
        width: 80px;
        height: 2px;
        background: linear-gradient(90deg, transparent, #ec4899, #8b5cf6, transparent);
    }

    .divider-icon {
        font-size: 24px;
        color: #ec4899;
    }

    .creative-catalog-title {
        font-size: 28px;
        color: white;
        font-weight: 500;
        padding: 15px 40px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
    }

    .contact-cards {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
        max-width: 600px;
        margin-top: 30px;
    }

    .contact-card {
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 12px;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 0 0 calc(50% - 8px);
        transition: all 0.3s ease;
    }

    .contact-card.wide {
        flex: 0 0 100%;
    }

    .card-icon {
        font-size: 11px;
        font-weight: bold;
        min-width: 50px;
        color: rgba(255,255,255,0.9);
    }

    .card-content {
        flex: 1;
    }

    .card-label {
        font-size: 10px;
        color: rgba(255,255,255,0.7);
        margin-bottom: 3px;
    }

    .card-value {
        font-size: 13px;
        color: white;
        font-weight: 500;
    }

    .creative-year {
        position: absolute;
        bottom: 30px;
        right: 30px;
        font-size: 48px;
        font-weight: bold;
        color: rgba(255,255,255,0.1);
    }

    @media print {
        .creative-cover {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
