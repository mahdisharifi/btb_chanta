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
        // فونت Vazir (پیش‌فرض)
        'vazir' => [
            'R'          => 'Vazir-Regular-FD.ttf',
            'B'          => 'Vazir-Bold-FD.ttf',
            'useOTL'     => 0xFF,
            'useKashida' => 75,
        ],
        // فونت Shabnam
        'shabnam' => [
            'R'          => 'Shabnam-FD.ttf',
            'B'          => 'Shabnam-Bold-FD.ttf',
            'useOTL'     => 0xFF,
            'useKashida' => 75,
        ],
        // فونت Sahel
        'sahel' => [
            'R'          => 'Sahel-FD.ttf',
            'B'          => 'Sahel-Bold-FD.ttf',
            'useOTL'     => 0xFF,
            'useKashida' => 75,
        ],
        // فونت IRANSans (اگر فایل‌های آن موجود است)
        'iransans' => [
            'R'          => 'IRANSans-Regular.ttf',
            'B'          => 'IRANSans-Bold.ttf',
            'useOTL'     => 0xFF,
            'useKashida' => 75,
        ],
    ],
    'auto_language_detection'  => false,
    'temp_dir'                 => storage_path('app/temp'), // پوشه تمپ برای پردازش PDF
    'pdfa'                     => false,
    'pdfaauto'                 => false,
    'use_active_forms'         => false,
];
