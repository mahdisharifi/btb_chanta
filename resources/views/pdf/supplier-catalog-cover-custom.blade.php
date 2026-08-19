{{-- کاور سفارشی کاتالوگ با پشتیبانی کامل mPDF --}}
{{-- طراحی کاملاً قابل تنظیم: محل، اندازه، فونت، رنگ --}}
{{-- بهینه‌سازی شده برای mPDF با table-based layout --}}

@php
    $customSettings = $settings['custom_cover'] ?? [];
    
    // === تنظیمات پس‌زمینه ===
    $bgColor = $customSettings['background']['color'] ?? '#667eea';
    $pageBreak = $customSettings['background']['page_break'] ?? true;
    
    // === تنظیمات لوگو ===
    $logoEnabled = $customSettings['logo']['enabled'] ?? true;
    $logoSize = $customSettings['logo']['size'] ?? 120;
    $logoPosition = $customSettings['logo']['position'] ?? 'top-center';
    $logoBorderRadius = $customSettings['logo']['border_radius'] ?? 15;
    
    // تبدیل position به text-align
    $logoAlign = 'center';
    if (str_contains($logoPosition, '-left')) {
        $logoAlign = 'left';
    } elseif (str_contains($logoPosition, '-right')) {
        $logoAlign = 'right';
    }
    
    // === تنظیمات نام شرکت ===
    $companyNameEnabled = $customSettings['company_name']['enabled'] ?? true;
    $companyFontSize = $customSettings['company_name']['font_size'] ?? 42;
    $companyColor = $customSettings['company_name']['color'] ?? '#ffffff';
    $companyTextAlign = $customSettings['company_name']['text_align'] ?? 'center';
    $companyPosition = $customSettings['company_name']['position'] ?? 'top';
    
    // === تنظیمات نام برند ===
    $brandNameEnabled = $customSettings['brand_name']['enabled'] ?? true;
    $brandFontSize = $customSettings['brand_name']['font_size'] ?? 20;
    $brandColor = $customSettings['brand_name']['color'] ?? 'rgba(255,255,255,0.9)';
    $brandPosition = $customSettings['brand_name']['position'] ?? 'top';
    
    // === تنظیمات شعار ===
    $sloganEnabled = $customSettings['slogan']['enabled'] ?? false;
    $sloganText = $customSettings['slogan']['text'] ?? '';
    $sloganFontSize = $customSettings['slogan']['font_size'] ?? 20;
    $sloganColor = $customSettings['slogan']['color'] ?? '#ffffff';
    $sloganPosition = $customSettings['slogan']['position'] ?? 'center';
    
    // === تنظیمات عنوان کاتالوگ ===
    $catalogTitleEnabled = $customSettings['catalog_title']['enabled'] ?? true;
    $catalogTitleFontSize = $customSettings['catalog_title']['font_size'] ?? 20;
    $catalogTitleColor = $customSettings['catalog_title']['color'] ?? '#667eea';
    $catalogTitleBgColor = $customSettings['catalog_title']['bg_color'] ?? '#ffffff';
    $catalogTitlePosition = $customSettings['catalog_title']['position'] ?? 'center';
    
    // === تنظیمات اطلاعات تماس ===
    $contactEnabled = $customSettings['contact_info']['enabled'] ?? true;
    $contactTitleShow = $customSettings['contact_info']['title']['show'] ?? true;
    $contactTitleText = $customSettings['contact_info']['title']['text'] ?? 'اطلاعات تماس';
    $contactTitleFontSize = $customSettings['contact_info']['title']['font_size'] ?? 16;
    $contactTitleColor = $customSettings['contact_info']['title']['color'] ?? '#667eea';
    
    $contactLabelFontSize = $customSettings['contact_info']['label_font_size'] ?? 13;
    $contactLabelColor = $customSettings['contact_info']['label_color'] ?? '#6b7280';
    $contactValueFontSize = $customSettings['contact_info']['value_font_size'] ?? 13;
    $contactValueColor = $customSettings['contact_info']['value_color'] ?? '#111827';
    $contactBgColor = $customSettings['contact_info']['bg_color'] ?? '#ffffff';
    $contactPosition = $customSettings['contact_info']['position'] ?? 'bottom';
    
    // استفاده از contact_phones کاتالوگ
    $contactPhones = $contactPhones ?? [];
    
    // === تنظیمات تاریخ ===
    $dateEnabled = $customSettings['date']['enabled'] ?? true;
    $dateFormat = $customSettings['date']['format'] ?? 'Y/m/d';
    $datePosition = $customSettings['date']['position'] ?? 'bottom-right';
    $dateFontSize = $customSettings['date']['font_size'] ?? 16;
    $dateColor = $customSettings['date']['color'] ?? 'rgba(255,255,255,0.9)';
@endphp

{{-- استفاده از table برای full-page background (mPDF compatible) --}}
{{-- page break در فایل اصلی (supplier-catalog2.blade.php) مدیریت می‌شود --}}
<table style="width: 100%; height: 297mm; background-color: {{ $bgColor }}; border-collapse: collapse; font-family: 'IRANSans', Tahoma, Arial, sans-serif;">
    
    {{-- TOP Section --}}
    @if($companyPosition === 'top' || $brandPosition === 'top' || $sloganPosition === 'top' || $catalogTitlePosition === 'top' || $contactPosition === 'top' || in_array($logoPosition, ['top-left', 'top-center', 'top-right']))
    <tr style="height: 33%;">
        <td style="vertical-align: top; padding: 40px 30px; text-align: {{ $companyTextAlign }};">
            
            {{-- لوگو --}}
            @if($logoEnabled && in_array($logoPosition, ['top-left', 'top-center', 'top-right']))
                <div style="text-align: {{ $logoAlign }}; margin-bottom: 20px;">
                    @if(!empty($forPdf) && !empty($supplierLogoPath) && file_exists($supplierLogoPath))
                        {{-- خروجی PDF - فقط ارتفاع لحاظ میشه، عرض خودکار --}}
                        <img src="{{ $supplierLogoPath }}" alt="{{ $supplierName ?? 'لوگو' }}" height="{{ $logoSize }}" style="width: auto;">
                    @elseif(empty($forPdf) && !empty($supplierLogoUrl))
                        {{-- خروجی Preview - فقط ارتفاع لحاظ میشه، عرض خودکار --}}
                        <img src="{{ $supplierLogoUrl }}" alt="{{ $supplierName ?? 'لوگو' }}" style="height: {{ $logoSize }}px; width: auto; max-width: 100%; border-radius: {{ $logoBorderRadius }}px; display: inline-block;">
                    @else
                        <div style="width: {{ $logoSize }}px; height: {{ $logoSize }}px; background: white; display: inline-block; text-align: center; line-height: {{ $logoSize }}px;{{ $logoAlign !== 'center' ? 'margin-' . ($logoAlign === 'left' ? 'right' : 'left') . ': auto;' : '' }}">
                            <span style="font-size: {{ $logoSize/2 }}px; font-weight: bold; color: {{ $bgColor }};">{{ mb_substr($supplierName ?? 'L', 0, 1) }}</span>
                        </div>
                    @endif
                </div>
            @endif
            
            {{-- نام شرکت --}}
            @if($companyNameEnabled && $companyPosition === 'top')
                <h1 style="font-size: {{ $companyFontSize }}px; font-weight: bold; color: {{ $companyColor }}; margin: 20px 0 10px 0; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                    {{ $supplier->company_name ?? $supplierName ?? 'تأمین‌کننده' }}
                </h1>
            @endif
            
            {{-- نام برند --}}
            @if($brandNameEnabled && $brandPosition === 'top' && !empty($supplier->brand_name))
                <div style="font-size: {{ $brandFontSize }}px; color: {{ $brandColor }}; margin-bottom: 10px;">
                    {{ $supplier->brand_name }}
                </div>
            @endif
            
            {{-- شعار --}}
            @if($sloganEnabled && $sloganPosition === 'top' && !empty($sloganText))
                <div style="font-size: {{ $sloganFontSize }}px; color: {{ $sloganColor }}; margin: 20px auto; padding: 15px 30px; max-width: 500px; border-top: 2px solid rgba(255,255,255,0.3); border-bottom: 2px solid rgba(255,255,255,0.3);">
                    {{ $sloganText }}
                </div>
            @endif
            
            {{-- عنوان کاتالوگ --}}
            @if($catalogTitleEnabled && $catalogTitlePosition === 'top')
                <div style="text-align: center; margin: 20px 0;">
                    <div style="display: inline-block; background: {{ $catalogTitleBgColor }}; padding: 15px 40px; border-radius: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <span style="font-size: {{ $catalogTitleFontSize }}px; font-weight: bold; color: {{ $catalogTitleColor }};">
                            {{ $catalogTitle ?? 'کاتالوگ جامع محصولات' }}
                        </span>
                    </div>
                </div>
            @endif
            
            {{-- اطلاعات تماس --}}
            @if($contactEnabled && $contactPosition === 'top')
                @include('pdf.partials.contact-info-custom')
            @endif
        </td>
    </tr>
    @endif
    
    {{-- CENTER Section --}}
    @if($companyPosition === 'center' || $brandPosition === 'center' || $sloganPosition === 'center' || $catalogTitlePosition === 'center' || $contactPosition === 'center' || $logoPosition === 'center')
    <tr style="height: 34%;">
        <td style="vertical-align: middle; padding: 20px 40px; text-align: center;">
            
            {{-- لوگو --}}
            @if($logoEnabled && $logoPosition === 'center')
                <div style="margin-bottom: 20px;">
                    @if(!empty($forPdf) && !empty($supplierLogoPath) && file_exists($supplierLogoPath))
                        {{-- خروجی PDF - فقط ارتفاع لحاظ میشه، عرض خودکار --}}
                        <img src="{{ $supplierLogoPath }}" alt="{{ $supplierName ?? 'لوگو' }}" height="{{ $logoSize }}" style="width: auto;">
                    @elseif(empty($forPdf) && !empty($supplierLogoUrl))
                        {{-- خروجی Preview - فقط ارتفاع لحاظ میشه، عرض خودکار --}}
                        <img src="{{ $supplierLogoUrl }}" alt="{{ $supplierName ?? 'لوگو' }}" style="height: {{ $logoSize }}px; width: auto; max-width: 100%; border-radius: {{ $logoBorderRadius }}px;">
                    @else
                        <div style="width: {{ $logoSize }}px; height: {{ $logoSize }}px; background: white; display: inline-block; text-align: center; line-height: {{ $logoSize }}px;">
                            <span style="font-size: {{ $logoSize/2 }}px; font-weight: bold; color: {{ $bgColor }};">{{ mb_substr($supplierName ?? 'L', 0, 1) }}</span>
                        </div>
                    @endif
                </div>
            @endif
            
            {{-- نام شرکت --}}
            @if($companyNameEnabled && $companyPosition === 'center')
                <h1 style="font-size: {{ $companyFontSize }}px; font-weight: bold; color: {{ $companyColor }}; margin: 20px 0; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                    {{ $supplier->company_name ?? $supplierName ?? 'تأمین‌کننده' }}
                </h1>
            @endif
            
            {{-- نام برند --}}
            @if($brandNameEnabled && $brandPosition === 'center' && !empty($supplier->brand_name))
                <div style="font-size: {{ $brandFontSize }}px; color: {{ $brandColor }}; margin-bottom: 10px;">
                    {{ $supplier->brand_name }}
                </div>
            @endif
            
            {{-- شعار --}}
            @if($sloganEnabled && $sloganPosition === 'center' && !empty($sloganText))
                <div style="font-size: {{ $sloganFontSize }}px; color: {{ $sloganColor }}; margin: 20px auto; padding: 15px 30px; max-width: 500px; border-top: 2px solid rgba(255,255,255,0.3); border-bottom: 2px solid rgba(255,255,255,0.3);">
                    {{ $sloganText }}
                </div>
            @endif
            
            {{-- عنوان کاتالوگ --}}
            @if($catalogTitleEnabled && $catalogTitlePosition === 'center')
                <div style="display: inline-block; background: {{ $catalogTitleBgColor }}; padding: 15px 40px; border-radius: 30px; margin: 20px 0; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                    <span style="font-size: {{ $catalogTitleFontSize }}px; font-weight: bold; color: {{ $catalogTitleColor }};">
                        {{ $catalogTitle ?? 'کاتالوگ جامع محصولات' }}
                    </span>
                </div>
            @endif
            
            {{-- اطلاعات تماس --}}
            @if($contactEnabled && $contactPosition === 'center')
                @include('pdf.partials.contact-info-custom')
            @endif
        </td>
    </tr>
    @endif
    
    {{-- BOTTOM Section --}}
    @if($companyPosition === 'bottom' || $brandPosition === 'bottom' || $sloganPosition === 'bottom' || $catalogTitlePosition === 'bottom' || $contactPosition === 'bottom' || in_array($logoPosition, ['bottom-left', 'bottom-center', 'bottom-right']))
    <tr style="height: 33%;">
        <td style="vertical-align: bottom; padding: 20px 30px 40px;">
            
            {{-- لوگو --}}
            @if($logoEnabled && in_array($logoPosition, ['bottom-left', 'bottom-center', 'bottom-right']))
                <div style="text-align: {{ $logoAlign }}; margin-bottom: 20px;">
                    @if(!empty($forPdf) && !empty($supplierLogoPath) && file_exists($supplierLogoPath))
                        {{-- خروجی PDF - فقط ارتفاع لحاظ میشه، عرض خودکار --}}
                        <img src="{{ $supplierLogoPath }}" alt="{{ $supplierName ?? 'لوگو' }}" height="{{ $logoSize }}" style="width: auto;">
                    @elseif(empty($forPdf) && !empty($supplierLogoUrl))
                        {{-- خروجی Preview - فقط ارتفاع لحاظ میشه، عرض خودکار --}}
                        <img src="{{ $supplierLogoUrl }}" alt="{{ $supplierName ?? 'لوگو' }}" style="height: {{ $logoSize }}px; width: auto; max-width: 100%; border-radius: {{ $logoBorderRadius }}px; display: inline-block;">
                    @else
                        <div style="width: {{ $logoSize }}px; height: {{ $logoSize }}px; background: white; display: inline-block; text-align: center; line-height: {{ $logoSize }}px;">
                            <span style="font-size: {{ $logoSize/2 }}px; font-weight: bold; color: {{ $bgColor }};">{{ mb_substr($supplierName ?? 'L', 0, 1) }}</span>
                        </div>
                    @endif
                </div>
            @endif
            
            {{-- نام شرکت --}}
            @if($companyNameEnabled && $companyPosition === 'bottom')
                <h1 style="font-size: {{ $companyFontSize }}px; font-weight: bold; color: {{ $companyColor }}; margin: 20px 0; text-shadow: 0 2px 4px rgba(0,0,0,0.2); text-align: {{ $companyTextAlign }};">
                    {{ $supplier->company_name ?? $supplierName ?? 'تأمین‌کننده' }}
                </h1>
            @endif
            
            {{-- نام برند --}}
            @if($brandNameEnabled && $brandPosition === 'bottom' && !empty($supplier->brand_name))
                <div style="font-size: {{ $brandFontSize }}px; color: {{ $brandColor }}; margin-bottom: 10px; text-align: {{ $companyTextAlign }};">
                    {{ $supplier->brand_name }}
                </div>
            @endif
            
            {{-- شعار --}}
            @if($sloganEnabled && $sloganPosition === 'bottom' && !empty($sloganText))
                <div style="font-size: {{ $sloganFontSize }}px; color: {{ $sloganColor }}; margin: 20px auto; padding: 15px 30px; max-width: 500px; border-top: 2px solid rgba(255,255,255,0.3); border-bottom: 2px solid rgba(255,255,255,0.3); text-align: center;">
                    {{ $sloganText }}
                </div>
            @endif
            
            {{-- عنوان کاتالوگ --}}
            @if($catalogTitleEnabled && $catalogTitlePosition === 'bottom')
                <div style="text-align: center; margin: 20px 0;">
                    <div style="display: inline-block; background: {{ $catalogTitleBgColor }}; padding: 15px 40px; border-radius: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <span style="font-size: {{ $catalogTitleFontSize }}px; font-weight: bold; color: {{ $catalogTitleColor }};">
                            {{ $catalogTitle ?? 'کاتالوگ جامع محصولات' }}
                        </span>
                    </div>
                </div>
            @endif
            
            {{-- اطلاعات تماس --}}
            @if($contactEnabled && $contactPosition === 'bottom')
                @include('pdf.partials.contact-info-custom')
            @endif
            
            {{-- تاریخ --}}
            @if($dateEnabled)
                <div style="text-align: {{ $datePosition === 'bottom-left' ? 'left' : 'right' }}; margin-top: 15px; font-size: {{ $dateFontSize }}px; font-weight: bold; color: {{ $dateColor }}; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                    {{ jdate(now())->format($dateFormat) }}
                </div>
            @endif
        </td>
    </tr>
    @endif
</table>
