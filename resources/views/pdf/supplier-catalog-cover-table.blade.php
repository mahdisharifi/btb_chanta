{{-- کاور سفارشی کاتالوگ - Table Builder --}}
{{-- سازگار با mPDF با table-based layout --}}
{{-- هر ردیف یک جدول مستقل دارد تا ارتفاع‌ها مستقل باشند --}}

@php
    $coverData = $settings['custom_cover_table'] ?? [];
    $globalSettings = $coverData['settings'] ?? [];
    $rows = $coverData['rows'] ?? [];
    
    // تنظیمات کلی
    $pageBreak = $globalSettings['page_break'] ?? true;
    $bgColor = $globalSettings['background_color'] ?? '#667eea';
    $padding = $globalSettings['padding'] ?? 20;
    
    // فونت کاتالوگ
    $catalogFont = $globalSettings['catalog_font'] ?? $settings['catalog_font'] ?? 'vazir';
    
    // supplier data
    $supplierName = $supplier->company_name ?? $supplierName ?? 'تأمین‌کننده';
    $supplierLogo = $forPdf ? $supplierLogoPath : $supplierLogoUrl;
@endphp

@if($forPdf)
    {{-- ===================== خروجی PDF ===================== --}}
    {{-- استفاده از pagebreak به جای page-break-after برای جلوگیری از صفحه سفید --}}
    <div style="width: 100%; background-color: {{ $bgColor }};">
        
        @foreach($rows as $rowIndex => $row)
            @php
                $rowHeight = $row['height_unit'] === 'auto' ? null : (int)$row['height'];
                $rowHeightUnit = $row['height_unit'] ?? 'px';
                $rowBg = $row['background_color'] ?? '';
                $verticalAlign = $row['vertical_align'] ?? 'middle';
                $cells = $row['cells'] ?? [];
                
                $defaultFontSize = $globalSettings['default_font_size'] ?? 16;
                $defaultFontColor = $globalSettings['default_font_color'] ?? '#ffffff';
                $defaultFontWeight = $globalSettings['default_font_weight'] ?? 'normal';
                
                // تبدیل px به mm برای mPDF (1px ≈ 0.264583mm)
                $rowHeightMm = $rowHeight ? ($rowHeightUnit === 'px' ? $rowHeight * 0.264583 : $rowHeight) : null;
            @endphp
            
            <table style="width: 100%; border-collapse: collapse; font-family: '{{ $catalogFont }}', Tahoma, Arial, sans-serif; {{ $rowHeightMm ? 'height: ' . $rowHeightMm . 'mm;' : '' }}">
                <tr style="{{ $rowHeightMm ? 'height: ' . $rowHeightMm . 'mm;' : '' }} {{ $rowBg ? 'background-color: ' . $rowBg . ';' : '' }} vertical-align: {{ $verticalAlign }};">
                    @foreach($cells as $cellIndex => $cell)
                        @php
                            $contentType = $cell['content_type'] ?? 'text';
                            $contentValue = $cell['content_value'] ?? '';
                            $useDefaultFont = $cell['use_default_font'] ?? true;
                            
                            $fontSize = $useDefaultFont ? $defaultFontSize : ($cell['font_size'] ?? $defaultFontSize);
                            $fontColor = $useDefaultFont ? $defaultFontColor : ($cell['font_color'] ?? $defaultFontColor);
                            $fontWeight = $useDefaultFont ? $defaultFontWeight : ($cell['font_weight'] ?? $defaultFontWeight);
                            
                            $textAlign = $cell['text_align'] ?? 'center';
                            $cellBg = $cell['background_color'] ?? '';
                            $cellPadding = $cell['padding'] ?? 10;
                            $borderRadius = $cell['border_radius'] ?? 0;
                            $colspan = $cell['colspan'] ?? 1;
                            $width = $cell['width'] ?? '';
                            
                            $logoHeight = isset($cell['logo_height']) ? (int)$cell['logo_height'] : (int)$fontSize;
                        @endphp
                        
                        <td style="{{ $width ? 'width: ' . $width . ';' : '' }} padding: {{ $cellPadding }}px; text-align: {{ $textAlign }}; {{ $rowHeightMm ? 'height: ' . $rowHeightMm . 'mm;' : '' }} {{ $cellBg ? 'background-color: ' . $cellBg . ';' : '' }}" {!! $colspan > 1 ? 'colspan="' . $colspan . '"' : '' !!}>
                            
                            @if($contentType === 'logo')
                                @if($supplierLogo && !empty($supplierLogoPath) && file_exists($supplierLogoPath))
                                    <div style="text-align: {{ $textAlign }};">
                                        <img src="{{ $supplierLogo }}" alt="{{ $supplierName }}" height="{{ $logoHeight }}" style="width: auto; max-width: 100%;">
                                    </div>
                                @else
                                    <div style="width: {{ $logoHeight }}px; height: {{ $logoHeight }}px; {{ $cellBg ? '' : 'background: white;' }} margin: 0 auto; text-align: center; line-height: {{ $logoHeight }}px;">
                                        <span style="font-size: {{ $logoHeight * 0.5 }}px; font-weight: bold; color: {{ $cellBg ? $fontColor : $bgColor }};">{{ mb_substr($supplierName, 0, 1) }}</span>
                                    </div>
                                @endif
                            @elseif($contentType === 'company_name')
                                <h1 style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }}; margin: 0;">{{ $supplier->company_name ?? $supplierName }}</h1>
                            @elseif($contentType === 'brand_name')
                                @if(!empty($supplier->brand_name))
                                    <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ $supplier->brand_name }}</div>
                                @endif
                            @elseif($contentType === 'catalog_title')
                                {{-- استفاده از div با border-radius برای mPDF --}}
                                <div style="display: inline-block; {{ $cellBg ? 'background-color: ' . $cellBg . ';' : 'background: white;' }} padding: {{ $cellPadding }}px {{ $cellPadding * 2 }}px; {{ $borderRadius > 0 ? 'border-radius: ' . $borderRadius . 'px;' : '' }}">
                                    <span style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ $contentValue ?: ($catalogTitle ?? 'کاتالوگ محصولات') }}</span>
                                </div>
                            @elseif($contentType === 'contact')
                                {{-- استفاده از div با border-radius برای mPDF --}}
                                <div style="{{ $cellBg ? 'background-color: ' . $cellBg . ';' : 'background: white;' }} {{ $borderRadius > 0 ? 'border-radius: ' . $borderRadius . 'px;' : '' }} padding: {{ $cellPadding }}px;">
                                    @if(!empty($contactPhones))
                                        @foreach($contactPhones as $phone)
                                            @if(!empty($phone['number']))
                                                <div style="padding: 4px 0; font-size: {{ $fontSize }}px; color: {{ $fontColor }};">@if(!empty($phone['label']))<strong>{{ $phone['label'] }}:</strong>@endif {{ $phone['number'] }}</div>
                                            @endif
                                        @endforeach
                                    @endif
                                    @if(!empty($supplier->address))
                                        <div style="padding: 4px 0; font-size: {{ $fontSize * 0.9 }}px; color: {{ $fontColor }};"><strong>آدرس:</strong> @if(!empty($supplier->city)){{ $supplier->city }} - @endif{{ $supplier->address }}</div>
                                    @endif
                                </div>
                            @elseif($contentType === 'date')
                                <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ jdate(now())->format('Y/m/d') }}</div>
                            @elseif($contentType === 'text')
                                <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ $contentValue }}</div>
                            @elseif($contentType === 'spacer')
                                <div style="height: {{ $fontSize }}px;"></div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </table>
        @endforeach
    </div>
@else
    {{-- ===================== خروجی Preview (مرورگر) ===================== --}}
    <div class="w-full" style="background-color: {{ $bgColor }}; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        
        @foreach($rows as $rowIndex => $row)
            @php
                $rowHeight = $row['height_unit'] === 'auto' ? 'auto' : $row['height'] . $row['height_unit'];
                $rowBg = $row['background_color'] ?? '';
                $verticalAlign = $row['vertical_align'] ?? 'middle';
                $cells = $row['cells'] ?? [];
                
                $defaultFontSize = $globalSettings['default_font_size'] ?? 16;
                $defaultFontColor = $globalSettings['default_font_color'] ?? '#ffffff';
                $defaultFontWeight = $globalSettings['default_font_weight'] ?? 'normal';
            @endphp
            
            <div class="w-full flex" style="{{ $rowBg ? 'background-color: ' . $rowBg . ';' : '' }} {{ $rowHeight !== 'auto' ? 'min-height: ' . $rowHeight . ';' : '' }} align-items: {{ $verticalAlign }};">
                @foreach($cells as $cellIndex => $cell)
                    @php
                        $contentType = $cell['content_type'] ?? 'text';
                        $contentValue = $cell['content_value'] ?? '';
                        $useDefaultFont = $cell['use_default_font'] ?? true;
                        
                        $fontSize = $useDefaultFont ? $defaultFontSize : ($cell['font_size'] ?? $defaultFontSize);
                        $fontColor = $useDefaultFont ? $defaultFontColor : ($cell['font_color'] ?? $defaultFontColor);
                        $fontWeight = $useDefaultFont ? $defaultFontWeight : ($cell['font_weight'] ?? $defaultFontWeight);
                        
                        $textAlign = $cell['text_align'] ?? 'center';
                        $cellBg = $cell['background_color'] ?? '';
                        $cellPadding = $cell['padding'] ?? 10;
                        $borderRadius = $cell['border_radius'] ?? 0;
                        $colspan = $cell['colspan'] ?? 1;
                        $width = $cell['width'] ?? '';
                        
                        $logoHeight = isset($cell['logo_height']) ? (int)$cell['logo_height'] : (int)$fontSize;
                    @endphp
                    
                    <div class="flex-1 p-4" style="{{ $width ? 'width: ' . $width . ';' : '' }} text-align: {{ $textAlign }}; {{ $cellBg ? 'background-color: ' . $cellBg . ';' : '' }} flex: {{ $colspan }};">
                        
                        @if($contentType === 'logo')
                            <div class="flex justify-{{ $textAlign === 'center' ? 'center' : ($textAlign === 'left' ? 'start' : 'end') }}">
                                @if($supplierLogo && !empty($supplierLogoUrl))
                                    <img src="{{ $supplierLogo }}" alt="{{ $supplierName }}" style="height: {{ $logoHeight }}px; width: auto; max-width: 100%; border-radius: {{ $borderRadius }}px; object-fit: contain;">
                                @else
                                    <div class="flex items-center justify-center rounded-lg" style="width: {{ $logoHeight }}px; height: {{ $logoHeight }}px; {{ $cellBg ? '' : 'background: white;' }}">
                                        <span style="font-size: {{ $logoHeight * 0.5 }}px; font-weight: bold; color: {{ $cellBg ? $fontColor : $bgColor }};">{{ mb_substr($supplierName, 0, 1) }}</span>
                                    </div>
                                @endif
                            </div>
                        @elseif($contentType === 'company_name')
                            <h1 class="my-2" style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ $supplier->company_name ?? $supplierName }}</h1>
                        @elseif($contentType === 'brand_name')
                            @if(!empty($supplier->brand_name))
                                <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ $supplier->brand_name }}</div>
                            @endif
                        @elseif($contentType === 'catalog_title')
                            <div class="inline-block px-6 py-3 rounded-full" style="{{ $cellBg ? '' : 'background: white;' }}">
                                <span style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ $contentValue ?: ($catalogTitle ?? 'کاتالوگ محصولات') }}</span>
                            </div>
                        @elseif($contentType === 'contact')
                            <div class="rounded-lg p-4" style="{{ $cellBg ? 'background-color: ' . $cellBg . ';' : 'background: white;' }}">
                                @if(!empty($contactPhones))
                                    @foreach($contactPhones as $phone)
                                        @if(!empty($phone['number']))
                                            <div class="mb-2" style="font-size: {{ $fontSize }}px; color: {{ $fontColor }};">
                                                @if(!empty($phone['label']))<strong>{{ $phone['label'] }}:</strong>@endif {{ $phone['number'] }}
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                                @if(!empty($supplier->address))
                                    <div style="font-size: {{ $fontSize * 0.9 }}px; color: {{ $fontColor }};">
                                        <strong>آدرس:</strong> @if(!empty($supplier->city)){{ $supplier->city }} - @endif{{ $supplier->address }}
                                    </div>
                                @endif
                            </div>
                        @elseif($contentType === 'date')
                            <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ jdate(now())->format('Y/m/d') }}</div>
                        @elseif($contentType === 'text')
                            <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">{{ $contentValue }}</div>
                        @elseif($contentType === 'spacer')
                            <div style="height: {{ $fontSize }}px;"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
