<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialNetworkSeeder extends Seeder
{
    public function run(): void
    {
        $networks = [
            // شبکه‌های اجتماعی بین‌المللی
            [
                'name' => 'instagram',
                'display_name' => 'اینستاگرام',
                'icon' => 'heroicon-o-camera',
                'base_url' => 'https://instagram.com/',
                'validation_pattern' => '^@?[a-zA-Z0-9._]{1,30}$',
                'placeholder_example' => '@username یا username',
                'sort_order' => 1,
            ],
            [
                'name' => 'telegram',
                'display_name' => 'تلگرام',
                'icon' => 'heroicon-o-paper-airplane',
                'base_url' => 'https://t.me/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{5,32}$',
                'placeholder_example' => '@channel یا channel',
                'sort_order' => 2,
            ],
            [
                'name' => 'whatsapp',
                'display_name' => 'واتس‌اپ',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'base_url' => 'https://wa.me/',
                'validation_pattern' => '^[0-9]{10,15}$',
                'placeholder_example' => '989123456789',
                'sort_order' => 3,
            ],
            
            // برنامه‌های ایرانی
            [
                'name' => 'bale',
                'display_name' => 'بله',
                'icon' => 'heroicon-o-chat-bubble-oval-left-ellipsis',
                'base_url' => 'https://ble.ir/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{3,30}$',
                'placeholder_example' => '@channel یا username',
                'sort_order' => 10,
            ],
            [
                'name' => 'rubika',
                'display_name' => 'روبیکا',
                'icon' => 'heroicon-o-chat-bubble-left',
                'base_url' => 'https://rubika.ir/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{3,30}$',
                'placeholder_example' => '@channel یا username',
                'sort_order' => 11,
            ],
            [
                'name' => 'shad',
                'display_name' => 'شاد',
                'icon' => 'heroicon-o-sun',
                'base_url' => 'https://shad.ir/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{3,30}$',
                'placeholder_example' => '@channel یا username',
                'sort_order' => 12,
            ],
            [
                'name' => 'eitaa',
                'display_name' => 'ایتا',
                'icon' => 'heroicon-o-device-phone-mobile',
                'base_url' => 'https://eitaa.com/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{3,30}$',
                'placeholder_example' => '@channel یا username',
                'sort_order' => 13,
            ],
            [
                'name' => 'gap',
                'display_name' => 'گپ',
                'icon' => 'heroicon-o-chat-bubble-bottom-center',
                'base_url' => 'https://gap.im/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{3,30}$',
                'placeholder_example' => '@channel یا username',
                'sort_order' => 14,
            ],
            [
                'name' => 'soroush',
                'display_name' => 'سروش',
                'icon' => 'heroicon-o-chat-bubble-oval-left',
                'base_url' => 'https://soroush.ir/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{3,30}$',
                'placeholder_example' => '@channel یا username',
                'sort_order' => 15,
            ],
            [
                'name' => 'igram',
                'display_name' => 'آی‌گرام',
                'icon' => 'heroicon-o-camera',
                'base_url' => 'https://igram.ir/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{3,30}$',
                'placeholder_example' => '@username یا username',
                'sort_order' => 16,
            ],
            
            // وب‌سایت و شبکه‌های دیگر
            [
                'name' => 'website',
                'display_name' => 'وب‌سایت',
                'icon' => 'heroicon-o-globe-alt',
                'base_url' => null,
                'validation_pattern' => '^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)$',
                'placeholder_example' => 'https://example.com',
                'sort_order' => 100,
            ],
            [
                'name' => 'linkedin',
                'display_name' => 'لینکدین',
                'icon' => 'heroicon-o-briefcase',
                'base_url' => 'https://linkedin.com/in/',
                'validation_pattern' => '^[a-zA-Z0-9-]{3,100}$',
                'placeholder_example' => 'username یا company-name',
                'sort_order' => 4,
            ],
            [
                'name' => 'twitter',
                'display_name' => 'توییتر (X)',
                'icon' => 'heroicon-o-at-symbol',
                'base_url' => 'https://twitter.com/',
                'validation_pattern' => '^@?[a-zA-Z0-9_]{1,15}$',
                'placeholder_example' => '@username یا username',
                'sort_order' => 5,
            ],
        ];

        foreach ($networks as $network) {
            $exists = DB::table('social_networks')
                ->where('name', $network['name'])
                ->exists();

            if ($exists) {
                DB::table('social_networks')
                    ->where('name', $network['name'])
                    ->update(array_merge($network, [
                        'updated_at' => now(),
                    ]));
            } else {
                DB::table('social_networks')->insert(array_merge($network, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}
