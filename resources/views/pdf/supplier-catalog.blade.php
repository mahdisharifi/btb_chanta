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

    $catalogFont = $settings['catalog_font'] ?? $settings['custom_cover_table']['settings']['catalog_font'] ?? 'vazir';
@endphp

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>کاتالوگ محصولات {{ $supplierName }}</title>
    <style>
        @include('pdf.partials.catalog-cover-styles')

        /* استایل‌های بخش کاور */

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
        .product-list { font-size: 12px; color: #333; width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .product-list tr { page-break-inside: avoid; }
        .product-list th, .product-list td { padding: 10px 8px; text-align: center; vertical-align: middle; }
        .product-list th, .product-list td { border: 1px solid #e5e7eb; }
        .product-list th { background-color: #f9fafb; font-weight: bold; color: #374151; }
        .product-name { font-weight: bold; color: #111827; }
    </style>
</head>
<body>
{{-- فراخوانی کاور در صورت فعال بودن تنظیمات --}}
@include('pdf.partials.catalog-cover-page')

@if(($supplier->type ?? null) === 'supplier')
<div style="text-align: right; color: #4b5563; font-size: 10px; margin: 0 0 10px;">
    {{ $supplier->company_name }} — {{ $supplier->branchLabel() }}
</div>
@endif

{{-- حلقه نمایش دسته‌بندی‌ها و محصولات --}}
@foreach($groupedProducts as $category => $products)
<!--<div class="category-title">{{ $category }}</div>-->

@php
    // ✅ تعیین ستون‌های قیمت برای نمایش (با پشتیبانی از قیمت‌های چک)
    $priceColumns = [];
    $tierNames = [0 => 'زیرکارتن', 1 => 'کارتن', 2 => 'عمده', 3 => 'کل موجودی'];
    $selectedTypesByTier = is_array($selectedPriceTypes) ? $selectedPriceTypes : [];
    if ($selectedPriceTypes === 'all') {
        $selectablePriceTypeIds = array_keys(array_filter(
            $priceTypesById,
            static fn (array $priceType): bool => ($priceType['code'] ?? null) !== 'warranty'
        ));
        $selectedTypesByTier = array_fill_keys([0, 1, 2, 3], $selectablePriceTypeIds);
    }

    foreach ($selectedTypesByTier as $tierIndex => $priceTypeIds) {
        $tierName = $tierNames[$tierIndex] ?? "تیراژ {$tierIndex}";

        foreach ((array) $priceTypeIds as $priceTypeId) {
            $priceTypeId = (int) $priceTypeId;
            $priceType = $priceTypesById[$priceTypeId] ?? null;
            if (!$priceType || ($priceType['code'] ?? null) === 'warranty') {
                continue;
            }

            $priceTypeLabel = $priceType['name'] ?? "قیمت {$priceTypeId}";

            if (($priceType['code'] ?? null) === 'check') {
                $checkPeriodsUsed = [];
                foreach ($products as $product) {
                    foreach (($product['prices'] ?? []) as $priceData) {
                        if (is_array($priceData)
                            && isset($priceData['check_period_id'])
                            && (int) ($priceData['tier_index'] ?? -1) === (int) $tierIndex
                            && (int) ($priceData['price_type_id'] ?? 0) === $priceTypeId
                        ) {
                            $checkPeriodId = (int) $priceData['check_period_id'];
                            $checkPeriodsUsed[$checkPeriodId] ??= [
                                'id' => $checkPeriodId,
                                'days' => $priceData['check_period_days'] ?? 0,
                                'label' => $priceData['check_period_label'] ?? "چک {$checkPeriodId}",
                            ];
                        }
                    }
                }

                usort($checkPeriodsUsed, static fn (array $a, array $b): int => $a['days'] <=> $b['days']);
                foreach ($checkPeriodsUsed as $period) {
                    $priceColumns[] = [
                        'key' => "{$tierIndex}_{$priceTypeId}_{$period['id']}",
                        'label' => "{$tierName} - {$period['label']}",
                        'tier_index' => $tierIndex,
                        'price_type_id' => $priceTypeId,
                        'check_period_id' => $period['id'],
                    ];
                }
                continue;
            }

            $priceColumns[] = [
                'key' => "{$tierIndex}_{$priceTypeId}",
                'label' => "{$tierName} - {$priceTypeLabel}",
                'tier_index' => $tierIndex,
                'price_type_id' => $priceTypeId,
            ];
        }
    }
@endphp

<table class="product-list">
    <thead>
    <tr>
        <th style="width: 40px;">ردیف</th>
        <th style="width: 70px;">تصویر</th>
        <th>نام محصول</th>
        @if($branchAwareCatalog ?? false)
        <th>قیمت‌های شعبه‌ها</th>
        @else
        @foreach($priceColumns as $column)
        <th style="font-size: 10px;">{{ $column['label'] }}</th>
        @endforeach
        @endif
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
            @if(!empty($product['attribute_value_pdf']))
                <div dir="rtl" style="font-size: 10px; color: #6b7280; margin-top: 4px; direction: rtl;">
                    {{ $product['attribute_value_pdf'] }}
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
        @if($branchAwareCatalog ?? false)
        <td style="font-size: 10px; text-align: right;">
            @foreach($catalogBranchPriceRows->build($product['branch_prices'] ?? [], $selectedPriceTypes, $priceTypesById) as $branchRow)
                @php
                    $priceData = $branchRow['price_data'];
                    $priceValue = is_array($priceData) ? ($priceData['price'] ?? null) : null;
                    $warrantyPrice = is_array($priceData) ? ($priceData['warranty_price'] ?? 0) : 0;
                    $finalPrice = $priceValue;
                    if (($includeWarranty ?? false) && $warrantyPrice > 0 && $finalPrice !== null) {
                        $finalPrice += $warrantyPrice;
                    }
                    $formattedPrice = $priceFormatter->format(
                        $finalPrice,
                        $displayCurrencyCode,
                        is_array($priceData) ? ($priceData['catalog_display_currency'] ?? $priceData['foreign_price_applied_currency'] ?? null) : null,
                        is_array($priceData) ? ($priceData['catalog_display_rate'] ?? $priceData['foreign_price_rate'] ?? null) : null,
                        is_array($priceData) ? (bool) ($priceData['catalog_display_valid'] ?? true) : true,
                    );
                @endphp
                <div style="display: flex; justify-content: space-between; gap: 8px; border-bottom: 1px solid #f3f4f6; padding: 2px 0;">
                    <span>{{ $branchRow['label'] }}</span>
                    <strong><bdi dir="ltr">{{ $formattedPrice ?: '---' }}</bdi></strong>
                </div>
            @endforeach
        </td>
        @else
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
                $formattedPrice = $priceFormatter->format(
                    $finalPrice,
                    $displayCurrencyCode,
                    is_array($priceData) ? ($priceData['catalog_display_currency'] ?? $priceData['foreign_price_applied_currency'] ?? null) : null,
                    is_array($priceData) ? ($priceData['catalog_display_rate'] ?? $priceData['foreign_price_rate'] ?? null) : null,
                    is_array($priceData) ? (bool) ($priceData['catalog_display_valid'] ?? true) : true,
                );
            @endphp
            
            @if($formattedPrice)
                <div>{{ $formattedPrice }}</div>
            @else
            <span style="color: #9ca3af;">---</span>
            @endif
        </td>
        @endforeach
        @endif
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
