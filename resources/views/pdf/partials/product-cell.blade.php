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
        @foreach($selectedPriceTypes as $priceType)
        @php 
            $priceData = $product['prices'][$priceType] ?? null;
            $priceValue = is_array($priceData) ? ($priceData['price'] ?? null) : $priceData;
            $warrantyMonths = is_array($priceData) ? ($priceData['warranty_months'] ?? null) : null;
        @endphp
        <tr>
            <td class="price-label">

              @if($priceValue)
                {{ $priceLabels[$priceType] ?? $priceType }}
                 @if($priceType === 'warranty' && $warrantyMonths)
                    <span style="font-size: 9px; color: #6b7280;">({{ $warrantyMonths }} ماه)</span>
                @endif
                @else
                <span class="price-empty">---</span>
                @endif
               
            </td>
            <td class="price-value">
                @if($priceValue)
                {{ number_format($priceValue) }}
                @else
                <span class="price-empty">---</span>
                @endif
            </td>
        </tr>
        @endforeach
    </table>
</td>
