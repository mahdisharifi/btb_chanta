{{-- کاور سفارشی کاتالوگ - Table Builder --}}
{{-- سازگار با mPDF با table-based layout --}}

@php
    $coverData = $settings['custom_cover_table'] ?? [];
    $globalSettings = $coverData['settings'] ?? [];
    $rows = $coverData['rows'] ?? [];
    

    
    // تنظیمات کلی
    $pageBreak = $globalSettings['page_break'] ?? true;
    $bgColor = $globalSettings['background_color'] ?? '#667eea';
    $padding = $globalSettings['padding'] ?? 20;
    
    // supplier data
    $supplierName = $supplier->company_name ?? $supplierName ?? 'تأمین‌کننده';
    $supplierLogo = $forPdf ? $supplierLogoPath : $supplierLogoUrl;
@endphp

<table style="width: 100%; background-color: {{ $bgColor }}; border-collapse: collapse; font-family: 'IRANSans', Tahoma, Arial, sans-serif; padding: {{ $padding }}px;">
    
    @foreach($rows as $rowIndex => $row)
        @php
            $rowHeight = $row['height_unit'] === 'auto' ? 'auto' : $row['height'] . $row['height_unit'];
            $rowBg = $row['background_color'] ?? '';
            $verticalAlign = $row['vertical_align'] ?? 'middle';
            $columns = $row['columns'] ?? 1;
            $cells = $row['cells'] ?? [];
            
            // فونت پیش‌فرض
            $defaultFontSize = $globalSettings['default_font_size'] ?? 16;
            $defaultFontColor = $globalSettings['default_font_color'] ?? '#ffffff';
            $defaultFontWeight = $globalSettings['default_font_weight'] ?? 'normal';
        @endphp
        
        <tr style="{{ $rowHeight !== 'auto' ? 'height: ' . $rowHeight . ';' : '' }} {{ $rowBg ? 'background-color: ' . $rowBg . ';' : '' }} vertical-align: {{ $verticalAlign }};">
            @foreach($cells as $cellIndex => $cell)
                @php
                    $contentType = $cell['content_type'] ?? 'text';
                    $contentValue = $cell['content_value'] ?? '';
                    $useDefaultFont = $cell['use_default_font'] ?? true;
                    
                    // استفاده از فونت پیش‌فرض یا جداگانه
                    $fontSize = $useDefaultFont ? $defaultFontSize : ($cell['font_size'] ?? $defaultFontSize);
                    $fontColor = $useDefaultFont ? $defaultFontColor : ($cell['font_color'] ?? $defaultFontColor);
                    $fontWeight = $useDefaultFont ? $defaultFontWeight : ($cell['font_weight'] ?? $defaultFontWeight);
                    
                    $textAlign = $cell['text_align'] ?? 'center';
                    $cellBg = $cell['background_color'] ?? '';
                    $cellPadding = $cell['padding'] ?? 10;
                    $borderRadius = $cell['border_radius'] ?? 0;
                    $colspan = $cell['colspan'] ?? 1;
                    $width = $cell['width'] ?? '';
                    
                    // ابعاد لوگو - تبدیل به عدد صحیح
                    $logoWidth = isset($cell['logo_width']) ? (int)$cell['logo_width'] : (int)$fontSize;
                    $logoHeight = isset($cell['logo_height']) ? (int)$cell['logo_height'] : (int)$fontSize;
                    
                    // Debug - حذف بعد از تست
                    // dd(['logo_width' => $logoWidth, 'logo_height' => $logoHeight, 'cell' => $cell]);
                @endphp
                
                <td style="padding: {{ $cellPadding }}px; text-align: {{ $textAlign }}; {{ $cellBg ? 'background-color: ' . $cellBg . ';' : '' }} {{ $width ? 'width: ' . $width . ';' : '' }} border-radius: {{ $borderRadius }}px; {{ $colspan > 1 ? 'colspan: ' . $colspan . ';' : '' }}">
                    
                    @if($contentType === 'logo')
                        {{-- لوگو --}}
                        @php
                            // تنظیم margin بر اساس text-align
                            $logoMarginStyle = 'margin: 0 auto;';
                            if ($textAlign === 'left') {
                                $logoMarginStyle = 'margin-right: auto; margin-left: 0;';
                            } elseif ($textAlign === 'right') {
                                $logoMarginStyle = 'margin-left: auto; margin-right: 0;';
                            }
                        @endphp
                        @if($supplierLogo && ($forPdf ? (!empty($supplierLogoPath) && file_exists($supplierLogoPath)) : !empty($supplierLogoUrl)))
                            @if($forPdf)
                                {{-- خروجی PDF: استفاده از width/height مستقیم (سازگار با mPDF) --}}
                                <div style="text-align: {{ $textAlign }};">
                                    <img src="{{ $supplierLogo }}" alt="{{ $supplierName }}" width="{{ $logoWidth }}" height="{{ $logoHeight }}">
                                </div>
                            @else
                                {{-- خروجی Preview: استفاده از CSS برای نمایش بهتر در مرورگر --}}
                                <div style="width: {{ $logoWidth }}px; height: {{ $logoHeight }}px; {{ $logoMarginStyle }} overflow: hidden; border-radius: {{ $borderRadius }}px;">
                                    <img src="{{ $supplierLogo }}" alt="{{ $supplierName }}" style="width: 100%; height: 100%; object-fit: contain; display: block;">
                                </div>
                            @endif
                        @else
                            <div style="width: {{ $logoWidth }}px; height: {{ $logoHeight }}px; {{ $cellBg ? '' : 'background: white;' }} {{ $logoMarginStyle }} text-align: center; line-height: {{ $logoHeight }}px; overflow: hidden;">
                                <span style="font-size: {{ min($logoWidth, $logoHeight) * 0.5 }}px; font-weight: bold; color: {{ $cellBg ? $fontColor : $bgColor }};">
                                    {{ mb_substr($supplierName, 0, 1) }}
                                </span>
                            </div>
                        @endif
                    
                    @elseif($contentType === 'company_name')
                        {{-- نام شرکت --}}
                        <h1 style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }}; margin: 0; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            {{ $supplier->company_name ?? $supplierName }}
                        </h1>
                    
                    @elseif($contentType === 'brand_name')
                        {{-- نام برند --}}
                        @if(!empty($supplier->brand_name))
                            <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">
                                {{ $supplier->brand_name }}
                            </div>
                        @endif
                    
                    @elseif($contentType === 'catalog_title')
                        {{-- عنوان کاتالوگ --}}
                        <div style="display: inline-block; {{ $cellBg ? '' : 'background: white;' }} padding: {{ $cellPadding }}px {{ $cellPadding * 2 }}px; border-radius: {{ $borderRadius }}px;">
                            <span style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">
                                {{ $contentValue ?: ($catalogTitle ?? 'کاتالوگ محصولات') }}
                            </span>
                        </div>
                    
                    @elseif($contentType === 'contact')
                        {{-- اطلاعات تماس --}}
                        <table style="width: 100%; {{ $cellBg ? 'background-color: ' . $cellBg . ';' : 'background: white;' }} border-radius: {{ $borderRadius }}px; border-collapse: collapse;">
                            @if(!empty($contactPhones))
                                @foreach($contactPhones as $phone)
                                    @if(!empty($phone['number']))
                                        <tr>
                                            <td style="padding: 8px 12px; font-size: {{ $fontSize }}px; color: {{ $fontColor }};">
                                                @if(!empty($phone['label']))
                                                    <strong>{{ $phone['label'] }}:</strong>
                                                @endif
                                                {{ $phone['number'] }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endif
                            @if(!empty($supplier->address))
                                <tr>
                                    <td style="padding: 8px 12px; font-size: {{ $fontSize * 0.9 }}px; color: {{ $fontColor }};">
                                        <strong>آدرس:</strong>
                                        @if(!empty($supplier->city)) {{ $supplier->city }} - @endif
                                        {{ $supplier->address }}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    
                    @elseif($contentType === 'date')
                        {{-- تاریخ --}}
                        <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">
                            {{ jdate(now())->format('Y/m/d') }}
                        </div>
                    
                    @elseif($contentType === 'text')
                        {{-- متن دلخواه --}}
                        <div style="font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; color: {{ $fontColor }};">
                            {{ $contentValue }}
                        </div>
                    
                    @elseif($contentType === 'spacer')
                        {{-- فضای خالی --}}
                        <div style="height: {{ $fontSize }}px;"></div>
                    @endif
                    
                </td>
            @endforeach
        </tr>
    @endforeach
    
</table>
