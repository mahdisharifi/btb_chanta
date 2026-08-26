<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CheckPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * ایجاد دوره‌های چک پیش‌فرض (برای همه تأمین‌کنندگان)
     */
    public function run(): void
    {
        $checkPeriods = [
            [
                'supplier_id' => null, // برای همه
                'days' => 30,
                'label' => 'چک 30 روزه',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'supplier_id' => null,
                'days' => 60,
                'label' => 'چک 60 روزه',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'supplier_id' => null,
                'days' => 90,
                'label' => 'چک 90 روزه',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'supplier_id' => null,
                'days' => 120,
                'label' => 'چک 120 روزه',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'supplier_id' => null,
                'days' => 180,
                'label' => 'چک 180 روزه',
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        \DB::table('check_periods')->insert(array_map(function ($item) {
            return array_merge($item, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $checkPeriods));
    }
}
