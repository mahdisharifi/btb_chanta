<td class="product-cell">
    {{-- تصویر محصول --}}
    <div class="img-container">
        @php
        $imageExists = false;
        $imgPath = '';
        if(!empty($product['image'])) {
        $cleanPath = ltrim(str_replace('public/', '', $product['image']), '/');
        $imgPath = storage_path('app/public/' . $cleanPath);
        if(file_exists($imgPath)) {
        $imageExists = true;
        }
        }
        @endphp

        @if($imageExists)
        <img src="{{ $imgPath }}" width="110" height="110" style="border-radius: 4px;" alt="{{ $product['title'] }}">
        @else
        <div class="no-image">بدون تصویر</div>
        @endif
    </div>

    {{-- عنوان محصول --}}
    <div class="title">
        {{ $product['title'] }}
    </div>
    
    {{-- مشخصات محصول (در خط جداگانه) --}}
    @if(!empty($product['attribute_value']))
        <div style="font-size: 10px; color: #6b7280; margin-top: 4px; text-align: center; direction: ltr !important; unicode-bidi: bidi-override;">
            {!! $product['attribute_value'] !!}
        </div>
    @endif

    {{-- لیست قیمت‌ها --}}
    <table class="price-table">
        @php
            // ✅ تولید لیست قیمت‌های قابل نمایش (با پشتیبانی از ساختار جدید)
            $displayPrices = [];
            $hasWarrantyForProduct = false;
            $sampleWarrantyType = null;
            $sampleWarrantyDuration = null;
            
            $selectedTypesByTier = is_array($selectedPriceTypes) ? $selectedPriceTypes : [];
            if ($selectedPriceTypes === 'all') {
                $selectablePriceTypeIds = array_keys(array_filter(
                    $priceTypesById,
                    static fn (array $priceType): bool => ($priceType['code'] ?? null) !== 'warranty'
                ));
                $selectedTypesByTier = array_fill_keys([0, 1, 2, 3], $selectablePriceTypeIds);
            }

            if ($selectedTypesByTier !== []) {
                // ساختار جدید: ['tier_index' => [price_type_ids]]
                $tierNames = [0 => 'زیرکارتن', 1 => 'کارتن', 2 => 'عمده', 3 => 'بار'];
                
                foreach ($selectedTypesByTier as $tierIndex => $priceTypeIds) {
                    $tierName = $tierNames[$tierIndex] ?? "تیراژ {$tierIndex}";
                    
                    foreach ($priceTypeIds as $priceTypeId) {
                        $priceType = $priceTypesById[$priceTypeId] ?? null;
                        $isCheckType = ($priceType['code'] ?? null) === 'check';

                        if (!$isCheckType) {
                            // نقدی و قیمت‌های غیرچکی از کلید قیمت پایه خوانده می‌شوند.
                            $key = "{$tierIndex}_{$priceTypeId}";
                            if (isset($product['prices'][$key])) {
                                $displayPrices[$key] = $product['prices'][$key];
                            } else {
                                $displayPrices[$key] = [
                                    'price' => null,
                                    'tier_index' => $tierIndex,
                                    'price_type_id' => $priceTypeId,
                                    'check_period_label' => null,
                                    'warranty_type' => null,
                                    'warranty_duration' => null,
                                    'warranty_price' => 0,
                                ];
                            }
                        } else {
                            // جستجو برای همه قیمت‌های چک این تیراژ
                            foreach ($product['prices'] as $priceKey => $priceData) {
                                if (is_array($priceData) 
                                    && isset($priceData['check_period_id'])
                                    && $priceData['tier_index'] == $tierIndex
                                    && $priceData['price_type_id'] == $priceTypeId
                                ) {
                                    $displayPrices[$priceKey] = $priceData;
                                }
                            }
                        }
                    }
                }
            }
        @endphp
        
        @if(!empty($product['branch_prices']))
        @php
            $branchDisplayRows = $catalogBranchPriceRows->build(
                $product['branch_prices'],
                $selectedPriceTypes,
                $priceTypesById,
            );
        @endphp
        @foreach($branchDisplayRows as $branchRow)
        @php
            $priceData = $branchRow['price_data'];
            $priceValue = is_array($priceData) ? ($priceData['price'] ?? null) : null;
            $warrantyPrice = is_array($priceData) ? ($priceData['warranty_price'] ?? 0) : 0;
            $warrantyType = is_array($priceData) ? ($priceData['warranty_type'] ?? null) : null;
            $warrantyDuration = is_array($priceData) ? ($priceData['warranty_duration'] ?? null) : null;
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
            $label = $branchRow['label'];

            if ($warrantyType || $warrantyDuration) {
                $hasWarrantyForProduct = true;
                $sampleWarrantyType ??= $warrantyType;
                $sampleWarrantyDuration ??= $warrantyDuration;
            }
        @endphp
        <tr>
            <td class="price-label">{{ $label }}</td>
            <td class="price-value">
                @if($formattedPrice)
                    {{ $formattedPrice }}
                @else
                    <span class="price-empty">---</span>
                @endif
            </td>
        </tr>
        @endforeach
        @endif

        @if(empty($product['branch_prices']))
        @foreach($displayPrices as $key => $priceData)
        @php
            $priceValue = is_array($priceData) ? ($priceData['price'] ?? null) : $priceData;
            $warrantyPrice = is_array($priceData) ? ($priceData['warranty_price'] ?? 0) : 0;
            $tierIndex = is_array($priceData) ? ($priceData['tier_index'] ?? null) : null;
            $priceTypeId = is_array($priceData) ? ($priceData['price_type_id'] ?? null) : null;
            $checkPeriodLabel = is_array($priceData) ? ($priceData['check_period_label'] ?? null) : null;
            $warrantyType = is_array($priceData) ? ($priceData['warranty_type'] ?? null) : null;
            $warrantyDuration = is_array($priceData) ? ($priceData['warranty_duration'] ?? null) : null;
            
            // ✅ محاسبه قیمت نهایی
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
            
            // ✅ تشخیص گارانتی برای محصول
            if ($warrantyType || $warrantyDuration) {
                $hasWarrantyForProduct = true;
                $sampleWarrantyType = $warrantyType;
                $sampleWarrantyDuration = $warrantyDuration;
            }
            
            // تولید label
            $tierNames = [0 => 'زیرکارتن', 1 => 'کارتن', 2 => 'عمده', 3 => 'بار'];
            $tierName = $tierNames[$tierIndex] ?? "تیراژ {$tierIndex}";
            
            $priceTypeName = $priceTypesById[$priceTypeId]['name'] ?? "قیمت {$priceTypeId}";
            
            if ($checkPeriodLabel) {
                $label = "{$tierName} - {$checkPeriodLabel}";
            } else {
                $label = "{$tierName} - {$priceTypeName}";
            }
        @endphp
        <tr>
            <td class="price-label">
                {{ $label }}
            </td>
            <td class="price-value">
                @if($formattedPrice)
                    {{ $formattedPrice }}
                @else
                    <span class="price-empty">---</span>
                @endif
            </td>
        </tr>
        @endforeach
        @endif
        
        {{-- ✅ نمایش اطلاعات گارانتی بعد از همه قیمت‌ها --}}
        @if(($includeWarranty ?? false) && $hasWarrantyForProduct)
        <tr>
            <td colspan="2" style="text-align: center; padding: 5px; border: none;">
                <div style="border-radius: 4px; padding: 4px 8px; display: inline-block;">
                    <img src="{{ public_path('images/icons/warranty-shield.png') }}" width="12" height="12" style="vertical-align: middle; margin-left: 4px;">
                    <span style="font-size: 9px; color: #7c3aed; font-weight: bold;">
                        گارانتی:
                        @if($sampleWarrantyType === 'test')
                            تست
                        @elseif($sampleWarrantyType === 'limited' && $sampleWarrantyDuration)
                            {{ $sampleWarrantyDuration }} ماه
                        @elseif($sampleWarrantyType === 'lifetime')
                            مادام‌العمر
                        @endif
                    </span>
                </div>
            </td>
        </tr>
        @endif
    </table>
</td>
