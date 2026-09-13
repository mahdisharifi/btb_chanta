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
    <title>کاتالوگ محصولات {{ $supplierName }}</title>
    <style>
        body {
            font-family: 'vazir', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            color: #333;
            direction: rtl;
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

        /* استایل‌های لیست محصولات */
        .category-title {
            background-color: #f3f4f6;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 15px;
            border-radius: 6px;
            border-right: 4px solid #3b82f6;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #e5e7eb; padding: 10px 8px; text-align: center; vertical-align: middle; }
        th { background-color: #f9fafb; font-weight: bold; color: #374151; }
        .product-name { font-weight: bold; color: #111827; }
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
    
    {{-- page break برای صفحه کاور --}}
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
        <div style="page-break-after: always; break-after: page;"></div>
    @endif

@endif

{{-- حلقه نمایش دسته‌بندی‌ها و محصولات --}}
@foreach($groupedProducts as $category => $products)
<!--<div class="category-title">{{ $category }}</div>-->

@php
    // ✅ تعیین ستون‌های قیمت برای نمایش (با پشتیبانی از قیمت‌های چک)
    $priceColumns = [];
    
    if ($selectedPriceTypes === 'all') {
        // همه تیراژها با همه انواع قیمت
        $allTiers = [
            0 => 'زیرکارتن',
            1 => 'کارتن',
            2 => 'عمده',
            3 => 'بار',
        ];
        $allPriceTypes = \App\Enums\PriceType::cases();
        
        foreach ($allTiers as $tierIndex => $tierName) {
            foreach ($allPriceTypes as $priceType) {
                // اگر نوع قیمت "چک" است، باید دوره‌های چک را هم نمایش دهیم
                if ($priceType->value === 2) { // 2 = چک
                    // یافتن همه قیمت‌های چک موجود در محصولات
                    $checkPeriodsUsed = [];
                    foreach ($products as $product) {
                        foreach ($product['prices'] as $key => $priceData) {
                            if (is_array($priceData) && isset($priceData['check_period_id'])) {
                                $checkPeriodId = $priceData['check_period_id'];
                                if (!isset($checkPeriodsUsed[$checkPeriodId])) {
                                    $checkPeriodsUsed[$checkPeriodId] = [
                                        'id' => $checkPeriodId,
                                        'days' => $priceData['check_period_days'] ?? 0,
                                        'label' => $priceData['check_period_label'] ?? "چک {$checkPeriodId}",
                                    ];
                                }
                            }
                        }
                    }
                    
                    // مرتب‌سازی بر اساس روز
                    usort($checkPeriodsUsed, fn($a, $b) => $a['days'] <=> $b['days']);
                    
                    // ایجاد یک ستون برای هر دوره چک
                    foreach ($checkPeriodsUsed as $period) {
                        $priceColumns[] = [
                            'key' => "{$tierIndex}_{$priceType->value}_{$period['id']}",
                            'label' => "{$tierName} - {$period['label']}",
                            'tier_index' => $tierIndex,
                            'price_type_id' => $priceType->value,
                            'check_period_id' => $period['id'],
                        ];
                    }
                } else {
                    // قیمت نقدی یا گارانتی
                    $priceColumns[] = [
                        'key' => "{$tierIndex}_{$priceType->value}",
                        'label' => "{$tierName} - {$priceType->label()}",
                        'tier_index' => $tierIndex,
                        'price_type_id' => $priceType->value,
                    ];
                }
            }
        }
    } elseif (is_array($selectedPriceTypes)) {
        // ساختار: ['tier_index' => [price_type_ids]]
        $tierNames = [
            0 => 'زیرکارتن',
            1 => 'کارتن', 
            2 => 'عمده',
            3 => 'بار',
        ];
        
        foreach ($selectedPriceTypes as $tierIndex => $priceTypeIds) {
            $tierName = $tierNames[$tierIndex] ?? "تیراژ {$tierIndex}";
            
            foreach ($priceTypeIds as $priceTypeId) {
                $priceType = \App\Enums\PriceType::tryFrom($priceTypeId);
                $priceTypeLabel = $priceType ? $priceType->label() : "قیمت {$priceTypeId}";
                
                // ✅ اگر نوع قیمت "چک" است، باید دوره‌های چک را هم نمایش دهیم
                if ($priceTypeId === 2) { // 2 = چک
                    // یافتن همه قیمت‌های چک موجود در محصولات برای این تیراژ
                    $checkPeriodsUsed = [];
                    foreach ($products as $product) {
                        foreach ($product['prices'] as $key => $priceData) {
                            if (is_array($priceData) 
                                && isset($priceData['check_period_id'])
                                && $priceData['tier_index'] == $tierIndex
                                && $priceData['price_type_id'] == $priceTypeId
                            ) {
                                $checkPeriodId = $priceData['check_period_id'];
                                if (!isset($checkPeriodsUsed[$checkPeriodId])) {
                                    $checkPeriodsUsed[$checkPeriodId] = [
                                        'id' => $checkPeriodId,
                                        'days' => $priceData['check_period_days'] ?? 0,
                                        'label' => $priceData['check_period_label'] ?? "چک {$checkPeriodId}",
                                    ];
                                }
                            }
                        }
                    }
                    
                    // مرتب‌سازی بر اساس روز
                    usort($checkPeriodsUsed, fn($a, $b) => $a['days'] <=> $b['days']);
                    
                    // ایجاد یک ستون برای هر دوره چک
                    foreach ($checkPeriodsUsed as $period) {
                        $priceColumns[] = [
                            'key' => "{$tierIndex}_{$priceTypeId}_{$period['id']}",
                            'label' => "{$tierName} - {$period['label']}",
                            'tier_index' => $tierIndex,
                            'price_type_id' => $priceTypeId,
                            'check_period_id' => $period['id'],
                        ];
                    }
                } else {
                    // قیمت نقدی یا گارانتی
                    $priceColumns[] = [
                        'key' => "{$tierIndex}_{$priceTypeId}",
                        'label' => "{$tierName} - {$priceTypeLabel}",
                        'tier_index' => $tierIndex,
                        'price_type_id' => $priceTypeId,
                    ];
                }
            }
        }
    }
@endphp

<table>
    <thead>
    <tr>
        <th style="width: 40px;">ردیف</th>
        <th style="width: 70px;">تصویر</th>
        <th>نام محصول</th>
        @foreach($priceColumns as $column)
        <th style="font-size: 10px;">{{ $column['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($products as $index => $product)
    <tr>
        <td>{{ $index + 1 }}</td>
        <td>
            @php
            $imageExists = false;
            $imgPath = '';
            if(isset($product['image']) && !empty($product['image'])) {
            $imgPath = storage_path('app/public/' . ltrim($product['image'], '/'));
            if(file_exists($imgPath)) {
            $imageExists = true;
            }
            }
            @endphp

            @if($imageExists)
            <img src="{{ $imgPath }}" width="60" height="60" style="border-radius: 4px;" alt="product" />
            @else
            <span style="color: #9ca3af; font-size: 10px;">بدون تصویر</span>
            @endif
        </td>
        <td class="product-name">
            <div>{{ $product['title'] }}</div>
            @if(!empty($product['attribute_value']))
                <div style="font-size: 10px; color: #6b7280; margin-top: 4px; direction: ltr !important; unicode-bidi: bidi-override;">
                    {!! $product['attribute_value'] !!}
                </div>
            @endif
            
            {{-- ✅ نمایش گارانتی زیر نام کالا --}}
            @php
                // بررسی آیا این محصول گارانتی دارد
                $productHasWarranty = false;
                $productWarrantyType = null;
                $productWarrantyDuration = null;
                
                foreach ($priceColumns as $column) {
                    $priceKey = $column['key'];
                    $priceData = $product['prices'][$priceKey] ?? null;
                    $warrantyType = is_array($priceData) ? ($priceData['warranty_type'] ?? null) : null;
                    $warrantyDuration = is_array($priceData) ? ($priceData['warranty_duration'] ?? null) : null;
                    
                    if ($warrantyType || $warrantyDuration) {
                        $productHasWarranty = true;
                        $productWarrantyType = $warrantyType;
                        $productWarrantyDuration = $warrantyDuration;
                        break;
                    }
                }
            @endphp
            
            @if(($includeWarranty ?? false) && $productHasWarranty)
                <div style="margin-top: 4px;">
                    <img src="{{ public_path('images/icons/warranty-shield.png') }}" width="12" height="12" style="vertical-align: middle; margin-left: 4px;">
                    <span style="font-size: 9px; color: #7c3aed; font-weight: bold;">
                        گارانتی:
                        @if($productWarrantyType === 'test')
                            تست
                        @elseif($productWarrantyType === 'limited' && $productWarrantyDuration)
                            {{ $productWarrantyDuration }} ماه
                        @elseif($productWarrantyType === 'lifetime')
                            مادام‌العمر
                        @endif
                    </span>
                </div>
            @endif
        </td>
        @foreach($priceColumns as $column)
        <td style="font-size: 11px;">
            @php
                // ✅ کلید قیمت: tier_index + _ + price_type_id [+ _ + check_period_id]
                $priceKey = $column['key'];
                $priceData = $product['prices'][$priceKey] ?? null;
                $priceValue = is_array($priceData) ? ($priceData['price'] ?? null) : $priceData;
                $warrantyPrice = is_array($priceData) ? ($priceData['warranty_price'] ?? 0) : 0;
                $warrantyType = is_array($priceData) ? ($priceData['warranty_type'] ?? null) : null;
                $warrantyDuration = is_array($priceData) ? ($priceData['warranty_duration'] ?? null) : null;
                
                // ✅ محاسبه قیمت نهایی (با یا بدون گارانتی)
                $finalPrice = $priceValue;
                if (($includeWarranty ?? false) && $warrantyPrice > 0) {
                    $finalPrice = $priceValue + $warrantyPrice;
                }
            @endphp
            
            @if($finalPrice && $finalPrice > 0)
                <div>{{ number_format($finalPrice) }}</div>
            @else
            <span style="color: #9ca3af;">---</span>
            @endif
        </td>
        @endforeach
    </tr>
    @endforeach
    </tbody>
</table>

@if($separateCategories && !$loop->last)
<pagebreak />
@endif
@endforeach

</body>
</html>
