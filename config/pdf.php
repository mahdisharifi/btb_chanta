<?php

return [
    'mode'                     => 'utf-8',
    'format'                   => 'A4',
    'default_font_size'        => '12',
    'default_font'             => 'vazir', // فونت پیش‌فرض
    'margin_left'              => 10,
    'margin_right'             => 10,
    'margin_top'               => 10,
    'margin_bottom'            => 10,
    'margin_header'            => 0,
    'margin_footer'            => 0,
    'orientation'              => 'P',
    'title'                    => 'کاتالوگ محصولات',
    'author'                   => '',
    'watermark'                => '',
    'show_watermark'           => false,
    'show_watermark_image'     => false,
    'watermark_font'           => 'vazir',
    'display_mode'             => 'fullpage',
    'watermark_text_alpha'     => 0.1,
    'watermark_image_path'     => '',
    'watermark_image_alpha'    => 0.2,
    'watermark_image_size'     => 'D',
    'watermark_image_position' => 'P',
    'custom_font_dir'          => public_path('fonts/'), // مسیر پوشه فونت‌های شما
    'custom_font_data'         => [
        'vazir' => [ // نام فونت که در استایل‌ها استفاده خواهید کرد
            'R'          => 'Vazir-Regular-FD.ttf', // فونت معمولی
            'B'          => 'Vazir-Bold-FD.ttf',    // فونت بولد
            'useOTL'     => 0xFF,                   // ضروری برای فارسی
            'useKashida' => 75,                     // کشیدگی متون فارسی
        ],
        // در صورت نیاز فونت‌های دیگر را اینجا اضافه کنید
    ],
    'auto_language_detection'  => false,
    'temp_dir'                 => storage_path('app/temp'), // پوشه تمپ برای پردازش PDF
    'pdfa'                     => false,
    'pdfaauto'                 => false,
    'use_active_forms'         => false,
];
