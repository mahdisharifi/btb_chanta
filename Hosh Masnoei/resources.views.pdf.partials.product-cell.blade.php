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
    <div class="title">{{ $product['title'] }}</div>

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
