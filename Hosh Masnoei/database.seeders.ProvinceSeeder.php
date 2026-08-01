<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $countryId = DB::table('countries')->where('iso2', 'IR')->value('id');

        if (! $countryId) {
            $this->command->error('کشور ایران یافت نشد. ابتدا CountrySeeder را اجرا کنید.');
            return;
        }

        // [نام استان, مرکز استان]
        $provinces = [
            ['آذربایجان شرقی', 'تبریز'],
            ['آذربایجان غربی', 'ارومیه'],
            ['اردبیل', 'اردبیل'],
            ['اصفهان', 'اصفهان'],
            ['البرز', 'کرج'],
            ['ایلام', 'ایلام'],
            ['بوشهر', 'بوشهر'],
            ['تهران', 'تهران'],
            ['چهارمحال و بختیاری', 'شهرکرد'],
            ['خراسان جنوبی', 'بیرجند'],
            ['خراسان رضوی', 'مشهد'],
            ['خراسان شمالی', 'بجنورد'],
            ['خوزستان', 'اهواز'],
            ['زنجان', 'زنجان'],
            ['سمنان', 'سمنان'],
            ['سیستان و بلوچستان', 'زاهدان'],
            ['فارس', 'شیراز'],
            ['قزوین', 'قزوین'],
            ['قم', 'قم'],
            ['کردستان', 'سنندج'],
            ['کرمان', 'کرمان'],
            ['کرمانشاه', 'کرمانشاه'],
            ['کهگیلویه و بویراحمد', 'یاسوج'],
            ['گلستان', 'گرگان'],
            ['گیلان', 'رشت'],
            ['لرستان', 'خرم‌آباد'],
            ['مازندران', 'ساری'],
            ['مرکزی', 'اراک'],
            ['هرمزگان', 'بندرعباس'],
            ['همدان', 'همدان'],
            ['یزد', 'یزد'],
        ];

        $sort = 0;
        foreach ($provinces as [$name, $capital]) {
            DB::table('provinces')->updateOrInsert(
                ['country_id' => $countryId, 'name' => $name],
                [
                    'native_name' => $name,
                    'capital'    => $capital,
                    'is_active'  => true,
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
