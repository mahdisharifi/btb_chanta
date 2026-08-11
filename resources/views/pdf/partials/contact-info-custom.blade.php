{{-- بخش اطلاعات تماس سفارشی --}}
<table style="width: 100%; background: {{ $contactBgColor }}; border-radius: 15px; border-collapse: collapse;">
    
    {{-- عنوان بخش تماس --}}
    @if($contactTitleShow)
    <tr>
        <td colspan="2" style="padding: 15px 20px; border-bottom: 2px solid #f3f4f6;">
            <div style="font-size: {{ $contactTitleFontSize }}px; font-weight: bold; color: {{ $contactTitleColor }};">
                {{ $contactTitleText }}
            </div>
        </td>
    </tr>
    @endif
    
    {{-- نمایش شماره‌های تماس از کاتالوگ --}}
    @if(!empty($contactPhones) && is_array($contactPhones))
        @foreach($contactPhones as $index => $phone)
            @if(!empty($phone['number']))
            <tr>
                <td style="width: 50%; padding: 12px 20px; font-size: {{ $contactValueFontSize }}px; border-bottom: 1px solid #f3f4f6;">
                    @if(!empty($phone['label']))
                        <strong style="color: {{ $contactLabelColor }}; font-size: {{ $contactLabelFontSize }}px;">{{ $phone['label'] }}:</strong>
                    @else
                        <strong style="color: {{ $contactLabelColor }}; font-size: {{ $contactLabelFontSize }}px;">تماس {{ $index + 1 }}:</strong>
                    @endif
                    <span style="color: {{ $contactValueColor }};">{{ $phone['number'] }}</span>
                </td>
                <td style="width: 50%;"></td>
            </tr>
            @endif
        @endforeach
    @endif
    
    {{-- آدرس --}}
    @if(!empty($supplier->address))
    <tr>
        <td colspan="2" style="padding: 12px 20px; font-size: {{ $contactValueFontSize }}px;">
            <strong style="color: {{ $contactLabelColor }}; font-size: {{ $contactLabelFontSize }}px;">آدرس:</strong> 
            <span style="color: {{ $contactValueColor }};">
                @if(!empty($supplier->city)) {{ $supplier->city }} - @endif
                {{ $supplier->address }}
            </span>
        </td>
    </tr>
    @endif
</table>
