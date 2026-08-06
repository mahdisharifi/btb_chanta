<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>کاتالوگ محصولات {{ $supplierName }}</title>
    <style>
        body {
            font-family: 'vazir', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            color: #333;
            direction: rtl;
        }

        /* استایل‌های بخش کاور */
        .cover-wrapper { text-align: center; margin-top: 80px; }
        .logo-area { margin-bottom: 30px; }
        .logo-area img { max-width: 180px; max-height: 180px; }
        .company-name { font-size: 32px; font-weight: bold; color: #111827; margin-bottom: 10px; }
        .brand-name { font-size: 20px; color: #4b5563; margin-bottom: 30px; }
        .divider { border-top: 2px solid #e5e7eb; width: 60%; margin: 30px auto; }
        .catalog-title-box { margin: 40px auto; padding: 25px; background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 12px; font-size: 26px; font-weight: bold; color: #1e40af; width: 70%; }
        .info-grid { width: 80%; margin: 40px auto 0; border: 1px solid #e5e7eb; border-radius: 12px; padding: 25px; background: #f9fafb; text-align: right; }
        .info-item { margin-bottom: 12px; }
        .info-label { font-weight: bold; color: #374151; display: inline-block; width: 100px; }
        .info-value { color: #111827; }

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
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #e5e7eb; padding: 10px 8px; text-align: center; vertical-align: middle; }
        th { background-color: #f9fafb; font-weight: bold; color: #374151; }
        .product-name { font-weight: bold; color: #111827; }
    </style>
</head>
<body>

{{-- فراخوانی کاور در صورت فعال بودن تنظیمات --}}
@if($showCoverPage)

@include('pdf.supplier-catalog-cover', [
    'supplierName'     => $supplierName,
    'supplierLogoUrl'  => $supplierLogoUrl ?? null,
    'supplierLogoPath' => $supplierLogoPath ?? null,
    'forPdf'           => $forPdf ?? true,
    'catalogTitle'     => $template->title ?? null,
    'supplier'         => $supplier ?? null,
])
{{-- جداکننده استاندارد صفحه در mPDF --}}
<div style="page-break-after: always; break-after: page;"></div>

@endif

{{-- حلقه نمایش دسته‌بندی‌ها و محصولات --}}
@foreach($groupedProducts as $category => $products)
<!--<div class="category-title">{{ $category }}</div>-->

<table>
    <thead>
    <tr>
        <th style="width: 40px;">ردیف</th>
        <th style="width: 70px;">تصویر</th>
        <th>نام محصول</th>
        @foreach($selectedPriceTypes as $type)
        <th>{{ $priceLabels[$type] ?? $type }}</th>
        @endforeach
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
            {{ $product['title'] }}
            @if(!empty($product['attribute_value']))
                <span style="font-size: 11px; color: #6b7280; display: block;">
                    ({{ $product['attribute_value'] }})
                </span>
            @endif
        </td>
        @foreach($selectedPriceTypes as $type)
        <td>
            @if(isset($product['prices'][$type]) && $product['prices'][$type] > 0)
            {{ number_format($product['prices'][$type]) }} تومان
            @else
            <span style="color: #9ca3af;">-</span>
            @endif
        </td>
        @endforeach
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
