{{--
    ⚠️ DEPRECATED - این فایل دیگر استفاده نمی‌شود
    
    این template برای سیستم قدیمی قیمت‌گذاری بود که از $product->prices استفاده می‌کرد.
    سیستم جدید از ProductSupplier و ProductSupplierPrice استفاده می‌کند.
    
    @deprecated از تاریخ 2026-08-25
--}}

<tr>
    @foreach($chunk as $product)
    @php
    $title = $product->title ?? $product->name;
    $titleLength = \Illuminate\Support\Str::length($title);
    $fontSize = $titleLength > 35 ? '10px' : ($titleLength > 25 ? '11px' : '12px');
    $prices = collect($product->prices)->unique('price_type')->values();
    $loopCount = max(4, $prices->count());

    // پیدا کردن تصویر بهینه‌تر
    $imagePath = null;
    if($product->primaryImage && $product->primaryImage->file_path) {
    $imagePath = public_path('storage/' . $product->primaryImage->file_path);
    } elseif($product->galleries && $product->galleries->isNotEmpty() && $product->galleries->first()->file_path) {
    $imagePath = public_path('storage/' . $product->galleries->first()->file_path);
    }
    @endphp

    <td style="width: 25%; vertical-align: top; padding: 10px; border: 1px solid #f1f5f9; text-align: center;">

        <!-- بخش تصویر -->
        <div style="height: 130px; margin-bottom: 10px;">
            @if($imagePath && file_exists($imagePath))
            <img src="{{ $imagePath }}" style="max-width: 100%; max-height: 120px;" alt="{{ $title }}">
            @else
            <div style="color: #cbd5e1; font-size: 10px; border: 1px dashed #e2e8f0; padding: 30px 10px; border-radius: 6px;">تصویری ثبت نشده</div>
            @endif
        </div>

        <!-- عنوان محصول -->
        <div style="font-weight: bold; font-size: {{ $fontSize }}; color: #0f172a; margin-bottom: 15px; height: 30px; overflow: hidden;" title="{{ $title }}">
            {{ $title }}
        </div>

        <!-- بخش قیمت‌ها -->
        <div style="font-size: 11px; color: #475569;">
            @for($j = 0; $j < $loopCount; $j++)
            @if(isset($prices[$j]))
            @php
            $price = $prices[$j];
            $priceLabel = $price->title ?? ($price->price_type instanceof \App\Enums\PriceType ? $price->price_type->label() : \App\Enums\PriceType::tryFrom($price->price_type)?->label() ?? 'قیمت');
            $priceAmount = number_format($price->amount ?? $price->price);
            @endphp
            <table style="width: 100%; margin-bottom: 4px; border-bottom: 1px solid #f8fafc;">
                <tr>
                    <td style="text-align: right; width: 45%; color: #64748b; font-size: 10px;">
                        {{ $priceLabel }}
                    </td>
                    <td style="text-align: left; width: 55%; font-weight: bold; color: #1e293b; font-size: 12px;">
                        {{ $priceAmount }} <span style="font-size: 8px; color: #94a3b8;">تومان</span>
                    </td>
                </tr>
            </table>
            @else
            <div style="height: 18px; margin-bottom: 4px;">&nbsp;</div>
            @endif
            @endfor

            @if($prices->isEmpty())
            <div style="text-align: center; color: #cbd5e1; font-size: 10px; margin-top: 10px;">بدون قیمت‌گذاری</div>
            @endif
        </div>
    </td>
    @endforeach

    <!-- پر کردن سلول‌های خالی اگر تعداد کمتر از ۴ بود -->
    @for($i = $chunk->count(); $i < 4; $i++)
    <td style="width: 25%; border: none;"></td>
    @endfor
</tr>
