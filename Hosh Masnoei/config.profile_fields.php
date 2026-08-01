<?php

return [

    /*
    |--------------------------------------------------------------------------
    | فیلدهای قفل‌شونده پس از تایید نهایی
    |--------------------------------------------------------------------------
    |
    | کلید بیرونی = نقش/نوع کاربر (supplier, retailer, ...)
    | مقدار = آرایه‌ای از نام فیلدهایی که پس از approved شدن
    | دیگر نباید توسط خود کاربر تغییر کنند (داده‌های هویتی/قانونی).
    |
    */

    'locked_after_approval' => [

        'supplier' => [
            'company_name',
            'company_name_en',
            'brand_name',
            'national_id',
            'tax_id',
            'business_license',
            'economic_code',
        ],

        'regular_supplier' => [
            'company_name',
            'company_name_en',
            'brand_name',
            'national_id',
            'tax_id',
            'business_license',
            'economic_code',
        ],

        'retailer' => [
            'national_id',
        ],

    ],

];
