@if($showCoverPage)
@switch($coverTheme ?? 'default')
    @case('modern')
        @include('pdf.supplier-catalog-cover-modern', [
            'supplierName'     => $supplierName,
            'supplierLogoUrl'  => $supplierLogoUrl ?? null,
            'supplierLogoPath' => $supplierLogoPath ?? null,
            'forPdf'           => $forPdf ?? true,
            'catalogTitle'     => $template->title ?? null,
            'supplier'         => $supplier ?? null,
            'contactPhones'    => $contactPhones ?? [],
            'exchangeRateBasis' => $exchangeRateBasis ?? null,
            'currencySnapshotWarning' => $currencySnapshotWarning ?? false,
        ])
        @break
    
    @case('creative')
        @include('pdf.supplier-catalog-cover-creative', [
            'supplierName'     => $supplierName,
            'supplierLogoUrl'  => $supplierLogoUrl ?? null,
            'supplierLogoPath' => $supplierLogoPath ?? null,
            'forPdf'           => $forPdf ?? true,
            'catalogTitle'     => $template->title ?? null,
            'supplier'         => $supplier ?? null,
            'contactPhones'    => $contactPhones ?? [],
            'exchangeRateBasis' => $exchangeRateBasis ?? null,
            'currencySnapshotWarning' => $currencySnapshotWarning ?? false,
        ])
        @break
    
    @case('corporate')
        @include('pdf.supplier-catalog-cover-corporate', [
            'supplierName'     => $supplierName,
            'supplierLogoUrl'  => $supplierLogoUrl ?? null,
            'supplierLogoPath' => $supplierLogoPath ?? null,
            'forPdf'           => $forPdf ?? true,
            'catalogTitle'     => $template->title ?? null,
            'supplier'         => $supplier ?? null,
            'contactPhones'    => $contactPhones ?? [],
            'exchangeRateBasis' => $exchangeRateBasis ?? null,
            'currencySnapshotWarning' => $currencySnapshotWarning ?? false,
        ])
        @break
    
    @case('custom_default')
    @case('custom')
        {{-- بررسی کدام سیستم استفاده شده --}}
        @if(isset($settings['custom_cover_table']))
            @include('pdf.supplier-catalog-cover-table', [
                'supplierName'     => $supplierName,
                'supplierLogoUrl'  => $supplierLogoUrl ?? null,
                'supplierLogoPath' => $supplierLogoPath ?? null,
                'forPdf'           => $forPdf ?? true,
                'catalogTitle'     => $template->title ?? null,
                'supplier'         => $supplier ?? null,
                'contactPhones'    => $contactPhones ?? [],
                'exchangeRateBasis' => $exchangeRateBasis ?? null,
                'currencySnapshotWarning' => $currencySnapshotWarning ?? false,
                'settings'         => $settings ?? [],
            ])
        @else
            @include('pdf.supplier-catalog-cover-custom', [
                'supplierName'     => $supplierName,
                'supplierLogoUrl'  => $supplierLogoUrl ?? null,
                'supplierLogoPath' => $supplierLogoPath ?? null,
                'forPdf'           => $forPdf ?? true,
                'catalogTitle'     => $template->title ?? null,
                'supplier'         => $supplier ?? null,
                'contactPhones'    => $contactPhones ?? [],
                'exchangeRateBasis' => $exchangeRateBasis ?? null,
                'currencySnapshotWarning' => $currencySnapshotWarning ?? false,
                'settings'         => $settings ?? [],
            ])
        @endif
        @break
    
    @default
        @include('pdf.supplier-catalog-cover', [
            'supplierName'     => $supplierName,
            'supplierLogoUrl'  => $supplierLogoUrl ?? null,
            'supplierLogoPath' => $supplierLogoPath ?? null,
            'forPdf'           => $forPdf ?? true,
            'catalogTitle'     => $template->title ?? null,
            'supplier'         => $supplier ?? null,
            'contactPhones'    => $contactPhones ?? [],
            'exchangeRateBasis' => $exchangeRateBasis ?? null,
            'currencySnapshotWarning' => $currencySnapshotWarning ?? false,
        ])
@endswitch

{{-- page break بین کاور و محصولات --}}
@php
    $coverPageBreak = true; // پیش‌فرض
    
    // بررسی در تنظیمات کاتالوگ (اولویت اول)
    if(isset($settings['cover_page_break'])) {
        $coverPageBreak = $settings['cover_page_break'];
    }
    // بررسی در تنظیمات کاور قدیمی
    elseif(isset($settings['custom_cover']['page_break'])) {
        $coverPageBreak = $settings['custom_cover']['page_break'];
    }
@endphp

@if($coverPageBreak)
<pagebreak />
@endif

@else
    @include('pdf.partials.exchange-rate-basis-summary', [
        'exchangeRateBasis' => $exchangeRateBasis ?? null,
        'currencySnapshotWarning' => $currencySnapshotWarning ?? false,
    ])
@endif

