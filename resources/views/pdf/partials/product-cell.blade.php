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
        @if(!empty($product['attribute_value']))
            <span style="font-size: 10px; color: #6b7280; display: block; margin-top: 2px;">
                ({{ $product['attribute_value'] }})
            </span>
        @endif
    </div>

    {{-- لیست قیمت‌ها --}}
    <table class="price-table">
        @foreach($selectedPriceTypes as $priceType)
        @php $priceValue = $product['prices'][$priceType] ?? null; @endphp
        <tr>
            <td class="price-label">{{ $priceLabels[$priceType] ?? $priceType }}</td>
            <td class="price-value">
                @if($priceValue)
                {{ number_format($priceValue) }}
                @else
                <span class="price-empty">-</span>
                @endif
            </td>
        </tr>
        @endforeach
    </table>
</td>
