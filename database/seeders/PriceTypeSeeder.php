<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PriceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priceTypes = [
            [
                'code' => 'cash',
                'name' => 'نقدی',
                'description' => 'قیمت نقدی محصول',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'check',
                'name' => 'چکی',
                'description' => 'قیمت با پرداخت چکی (دوره‌های مختلف)',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'warranty',
                'name' => 'گارانتی',
                'description' => 'هزینه اضافی گارانتی محصول',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        \DB::table('price_types')->insert($priceTypes);
    }
}
