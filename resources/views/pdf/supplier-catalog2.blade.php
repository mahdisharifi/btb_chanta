@php
    // Resolve settings: اگر source_catalog_id دارد، از تنظیمات کاتالوگ source استفاده کن
    $resolvedSettings = $settings ?? [];
    if (isset($settings['custom_cover']['source_catalog_id']) && $settings['custom_cover']['source_catalog_id']) {
        $sourceCatalogId = $settings['custom_cover']['source_catalog_id'];
        $sourceCatalog = \App\Models\CatalogTemplate::find($sourceCatalogId);
        
        if ($sourceCatalog && isset($sourceCatalog->settings['custom_cover'])) {
            // استفاده از تنظیمات کاتالوگ source
            $resolvedSettings['custom_cover'] = $sourceCatalog->settings['custom_cover'];
        }
    }
    $settings = $resolvedSettings;
@endphp

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $template->name ?? 'کاتالوگ محصولات' }}</title>
    <style>
        body {
            font-family: 'vazir', sans-serif;
            background-color: #ffffff;
            color: #1f2937;
            margin: 0;
            padding: 15px;
            direction: rtl;
        }
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .grid-table tr {
            page-break-inside: avoid;
        }

        .product-cell {
            width: 25%;
            border: 1px solid #9ca3af;
            padding: 8px;
            vertical-align: top;
            text-align: center;
            page-break-inside: avoid;
        }

        .img-container {
            width: 100%;
            height: 130px;
            margin-bottom: 10px;
            text-align: center;
        }

        .no-image {
            height: 100%;
            color: #9ca3af;
            font-size: 11px;
            padding-top: 50px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }

        .title {
            font-size: 12px;
            font-weight: bold;
            color: #000000;
            height: 36px;
            overflow: hidden;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .price-table {
            width: 100%;
            border-collapse: collapse;
        }

        .price-table td {
            padding: 3px 0;
            border: none;
        }

        .price-label {
            font-size: 10px;
            color: #4b5563;
            text-align: right;
            white-space: nowrap;
        }


        .price-value {
            font-size: 12px;
            font-weight: bold;
            color: #000000;
            text-align: left;
            direction: ltr;
        }

        .price-empty {
            color: #9ca3af;
            font-weight: normal;
        }

        .empty-state {
            padding: 50px 0;
            text-align: center;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            margin: 20px;
        }

        /* عنوان دسته - با جدول جداگانه و avoid برای جلوگیری از جدا افتادن از محصولات */
        .category-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .category-title {
            background-color: #f3f4f6;
            padding: 10px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 4px;
            border-right: 4px solid #3b82f6;
            text-align: right;
        }

        .empty-cell {
            width: 25%;
            border: none;
            padding: 8px;
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
</head>
<body>

{{-- فراخوانی کاور در صورت فعال بودن تنظیمات --}}
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
        ])
        @break
    
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
        ])
@endswitch

{{-- page break بین کاور و محصولات --}}
@php
    $coverPageBreak = true; // پیش‌فرض
    
    // اگر صفحه جداگانه غیرفعال است، کاور و محصولات در یک صفحه باشند
    if(isset($separateCategories) && !$separateCategories) {
        $coverPageBreak = false;
    }
    // بررسی در تنظیمات table builder
    elseif(isset($settings['custom_cover_table']['settings']['page_break'])) {
        $coverPageBreak = $settings['custom_cover_table']['settings']['page_break'];
    }
    // بررسی در تنظیمات کاور قدیمی
    elseif(isset($settings['custom_cover']['page_break'])) {
        $coverPageBreak = $settings['custom_cover']['page_break'];
    }
@endphp

@if($coverPageBreak)
<pagebreak />
@endif
@endif

@forelse($groupedProducts as $categoryName => $products)

{{-- جداکننده صفحه بین دسته‌ها (به جز اولین دسته) --}}
@if($separateCategories && !$loop->first)
<pagebreak />
@endif

{{-- bookmark فقط وقتی دسته‌بندی فعال است --}}
@if($separateCategories)
<bookmark content="{{ $categoryName }}" level="0" />
@endif

{{--
عنوان دسته + ردیف اول محصولات داخل یک بلاک avoid قرار می‌گیرد
تا عنوان هرگز در انتهای صفحه تنها نماند (Orphan prevention)
--}}
@php
$productChunks = array_chunk($products, 4);
$firstChunk = $productChunks[0] ?? [];
$restChunks = array_slice($productChunks, 1);
@endphp

{{-- بلاک اول: عنوان دسته (در صورت فعال بودن) + اولین ردیف محصولات --}}
<table class="grid-table" style="page-break-inside: avoid; margin-bottom: 0;">
    @if($separateCategories)
    <tr>
        <td colspan="4" style="border: none; padding: 0 0 10px 0;">
            <div class="category-title">{{ $categoryName }}</div>
        </td>
    </tr>
    @endif

    @if(count($firstChunk) > 0)
    <tr>
        @foreach($firstChunk as $product)
        @include('pdf.partials.product-cell', ['product' => $product])
        @endforeach

        @for($i = count($firstChunk); $i < 4; $i++)
        <td class="empty-cell"></td>
        @endfor
    </tr>
    @endif
</table>

{{-- بقیه ردیف‌ها --}}
@if(count($restChunks) > 0)
<table class="grid-table">
    @foreach($restChunks as $rowProducts)
    <tr>
        @foreach($rowProducts as $product)
        @include('pdf.partials.product-cell', ['product' => $product])
        @endforeach

        @for($i = count($rowProducts); $i < 4; $i++)
        <td class="empty-cell"></td>
        @endfor
    </tr>
    @endforeach
</table>
@endif

@empty
<div class="empty-state">
    <h3 style="font-size: 18px; font-weight: bold; color: #111827;">محصولی یافت نشد</h3>
    <p style="color: #6b7280; margin-top: 10px;">محصولی با تنظیمات فعلی برای نمایش وجود ندارد.</p>
</div>
@endforelse

</body>
</html>
