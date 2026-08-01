<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('countries')->updateOrInsert(
            ['iso2' => 'IR'],
            [
                'name'            => 'Iran',
                'native_name'    => 'ایران',
                'iso3'            => 'IRN',
                'phone_code'     => '+98',
                'currency_code'  => 'IRR',
                'currency_symbol' => 'ریال',
                'timezone'       => 'Asia/Tehran',
                'continent'      => 'asia',
                'is_active'      => true,
                'sort_order'     => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );
    }
}
