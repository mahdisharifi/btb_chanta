<div class="corporate-cover">

{{-- طرح رسمی و سازمانی برای کاور کاتالوگ --}}<div class="cover-top-bar">
        <div class="top-bar-content">
            @if(!empty($forPdf) && !empty($supplierLogoPath) && file_exists($supplierLogoPath))
                <img src="{{ $supplierLogoPath }}" alt="لوگو" class="top-logo">
            @elseif(empty($forPdf) && !empty($supplierLogoUrl))
                <img src="{{ $supplierLogoUrl }}" alt="لوگو" class="top-logo">
            @endif
            <div class="top-info">
                <div class="company-header">{{ $supplier->company_name ?? $supplierName ?? '' }}</div>
                @if(!empty($supplier->brand_name))
                <div class="brand-header">{{ $supplier->brand_name }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="cover-main">
        <div class="main-content">
            <div class="catalog-title-wrapper">
                <div class="title-decoration top"></div>
                <h1 class="catalog-main-title">{{ $catalogTitle ?? 'کاتالوگ محصولات' }}</h1>
                <div class="title-decoration bottom"></div>
            </div>

            <div class="year-badge">
                <span class="year-label">سال انتشار</span>
                <span class="year-value">{{ jdate(now())->format('Y/m/d') }}</span>
            </div>
        </div>
    </div>

    <div class="cover-bottom">
        <table class="info-table">
            <tbody>
                @if(!empty($contactPhones) && count($contactPhones) > 0)
                    @foreach($contactPhones as $phone)
                        @if(!empty($phone['number']))
                        <tr>
                            <td class="info-label-cell">{{ $phone['label'] ?? 'تماس' }}</td>
                            <td class="info-value-cell">{{ $phone['number'] }}</td>
                        </tr>
                        @endif
                    @endforeach
                @else
                    @if(!empty($supplier->phone))
                    <tr>
                        <td class="info-label-cell">تلفن تماس</td>
                        <td class="info-value-cell">{{ $supplier->phone }}</td>
                    </tr>
                    @endif

                    @if(!empty($supplier->mobile))
                    <tr>
                        <td class="info-label-cell">موبایل</td>
                        <td class="info-value-cell">{{ $supplier->mobile }}</td>
                    </tr>
                    @endif
                @endif

                @if(!empty($supplier->email))
                <tr>
                    <td class="info-label-cell">ایمیل</td>
                    <td class="info-value-cell">{{ $supplier->email }}</td>
                </tr>
                @endif

                @if(!empty($supplier->website))
                <tr>
                    <td class="info-label-cell">وب‌سایت</td>
                    <td class="info-value-cell">{{ $supplier->website }}</td>
                </tr>
                @endif

                @if(!empty($supplier->address))
                <tr>
                    <td class="info-label-cell">آدرس</td>
                    <td class="info-value-cell">
                        @if(!empty($supplier->city)) {{ $supplier->city }} - @endif
                        {{ $supplier->address }}
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<style>
    .corporate-cover {
        background: white;
        min-height: 750px;
        display: flex;
        flex-direction: column;
        border: 8px solid #1e3a8a;
    }

    .cover-top-bar {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        padding: 30px 40px;
        border-bottom: 4px solid #fbbf24;
    }

    .top-bar-content {
        display: flex;
        align-items: center;
        gap: 25px;
    }

    .top-logo {
        max-width: 100px;
        max-height: 100px;
        object-fit: contain;
        background: white;
        padding: 10px;
        border-radius: 10px;
    }

    .top-info {
        flex: 1;
    }

    .company-header {
        font-size: 32px;
        font-weight: bold;
        color: white;
        margin-bottom: 5px;
    }

    .brand-header {
        font-size: 18px;
        color: rgba(255,255,255,0.9);
    }

    .cover-main {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 60px 40px;
        background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    }

    .main-content {
        text-align: center;
    }

    .catalog-title-wrapper {
        margin-bottom: 40px;
    }

    .title-decoration {
        height: 3px;
        background: linear-gradient(90deg, transparent, #1e3a8a, transparent);
        margin: 20px 0;
    }

    .catalog-main-title {
        font-size: 48px;
        font-weight: bold;
        color: #1e3a8a;
        margin: 0;
        padding: 0 40px;
    }

    .year-badge {
        display: inline-flex;
        flex-direction: column;
        background: #fbbf24;
        padding: 20px 40px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
    }

    .year-label {
        font-size: 12px;
        color: #78350f;
        margin-bottom: 5px;
    }

    .year-value {
        font-size: 28px;
        font-weight: bold;
        color: #78350f;
    }

    .cover-bottom {
        background: #f8fafc;
        padding: 30px 40px;
        border-top: 2px solid #e5e7eb;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
    }

    .info-table td {
        padding: 10px 15px;
        border-bottom: 1px solid #e5e7eb;
    }

    .info-label-cell {
        width: 120px;
        font-weight: bold;
        color: #1e3a8a;
        background: #eff6ff;
    }

    .info-value-cell {
        color: #111827;
    }

    @media print {
        .corporate-cover {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
