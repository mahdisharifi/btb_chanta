<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialNetworkSeeder extends Seeder
{
    public function run(): void
    {
        $networks = [
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
            [
                'name' => 'website',
                'display_name' => 'وب‌سایت',
                'icon' => 'heroicon-o-globe-alt',
                'base_url' => null,
                'validation_pattern' => '^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)$',
                'placeholder_example' => 'https://example.com',
                'sort_order' => 4,
            ],
        ];

        foreach ($networks as $network) {
            DB::table('social_networks')->insert(array_merge($network, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
