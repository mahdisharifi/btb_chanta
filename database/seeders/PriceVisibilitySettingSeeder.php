<?php

namespace Database\Seeders;

use App\Models\PriceVisibilitySetting;
use Illuminate\Database\Seeder;

class PriceVisibilitySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * این Seeder تنظیمات پیش‌فرض را بر اساس منطق فعلی سیستم ایجاد می‌کند.
     * منطق فعلی از ProductController::resolveVisibleDataByRole استخراج شده است.
     */
    public function run(): void
    {
        $settings = [
            // ═══════════════════════════════════════════════════════
            // مهمان (کاربر لاگین نکرده)
            // ═══════════════════════════════════════════════════════
            [
                'role' => 'guest',
                'can_see_retail_price' => true,
                'can_see_wholesale_price' => false,
                'can_see_supplier_info' => false,
                'can_filter_by_price' => false,
                'can_set_retail_price' => false,
                'restricted_price_types' => null, // همه انواع قیمت
                'description' => 'کاربر مهمان - فقط قیمت خرده‌فروشی را می‌بیند',
                'is_active' => true,
            ],

            // ═══════════════════════════════════════════════════════
            // کاربر عادی (End User)
            // ═══════════════════════════════════════════════════════
            [
                'role' => 'end_user',
                'can_see_retail_price' => true,
                'can_see_wholesale_price' => false,
                'can_see_supplier_info' => false,
                'can_filter_by_price' => false,
                'can_set_retail_price' => false,
                'restricted_price_types' => null,
                'description' => 'کاربر عادی - فقط قیمت خرده‌فروشی را می‌بیند',
                'is_active' => true,
            ],

            // ═══════════════════════════════════════════════════════
            // کاسب عادی (Retailer Basic)
            // ═══════════════════════════════════════════════════════
            [
                'role' => 'retailer_basic',
                'can_see_retail_price' => true,
                'can_see_wholesale_price' => false,
                'can_see_supplier_info' => false,
                'can_filter_by_price' => false,
                'can_set_retail_price' => true, // می‌تواند قیمت خودش را ثبت کند
                'restricted_price_types' => null,
                'description' => 'کاسب عادی - می‌تواند قیمت خرده‌فروشی ببیند و ثبت کند، اما قیمت عمده نمی‌بیند',
                'is_active' => true,
            ],

            // ═══════════════════════════════════════════════════════
            // کاسب ویژه (Retailer Premium)
            // ═══════════════════════════════════════════════════════
            [
                'role' => 'retailer_premium',
                'can_see_retail_price' => true,
                'can_see_wholesale_price' => true,
                'can_see_supplier_info' => true,
                'can_filter_by_price' => true,
                'can_set_retail_price' => true,
                'restricted_price_types' => null, // دسترسی به همه انواع قیمت
                'description' => 'کاسب ویژه - دسترسی کامل به قیمت‌های عمده، اطلاعات تامین‌کننده، و فیلتر قیمت',
                'is_active' => true,
            ],

            // ═══════════════════════════════════════════════════════
            // تامین‌کننده عادی (Regular Supplier)
            // ═══════════════════════════════════════════════════════
            [
                'role' => 'regular_supplier',
                'can_see_retail_price' => true,
                'can_see_wholesale_price' => true, // می‌تواند قیمت‌های عمده را ببیند
                'can_see_supplier_info' => true,
                'can_filter_by_price' => true,
                'can_set_retail_price' => false,
                'restricted_price_types' => null,
                'description' => 'تامین‌کننده عادی - دسترسی به قیمت‌های عمده و اطلاعات تامین‌کنندگان',
                'is_active' => true,
            ],

            // ═══════════════════════════════════════════════════════
            // تامین‌کننده (Supplier)
            // ═══════════════════════════════════════════════════════
            [
                'role' => 'supplier',
                'can_see_retail_price' => true,
                'can_see_wholesale_price' => true,
                'can_see_supplier_info' => true,
                'can_filter_by_price' => true,
                'can_set_retail_price' => false,
                'restricted_price_types' => null,
                'description' => 'تامین‌کننده - دسترسی کامل به قیمت‌ها و اطلاعات',
                'is_active' => true,
            ],

            // ═══════════════════════════════════════════════════════
            // مدیر ارشد (Super Admin)
            // ═══════════════════════════════════════════════════════
            [
                'role' => 'super_admin',
                'can_see_retail_price' => true,
                'can_see_wholesale_price' => true,
                'can_see_supplier_info' => true,
                'can_filter_by_price' => true,
                'can_set_retail_price' => true,
                'restricted_price_types' => null, // دسترسی به همه چیز
                'description' => 'مدیر ارشد - دسترسی کامل به تمام قیمت‌ها و تنظیمات',
                'is_active' => true,
            ],
        ];

        foreach ($settings as $setting) {
            PriceVisibilitySetting::updateOrCreate(
                ['role' => $setting['role']],
                $setting
            );
        }

        $this->command->info('✅ تنظیمات نمایش قیمت برای ' . count($settings) . ' نقش با موفقیت ایجاد شد.');
        
        // نمایش خلاصه تنظیمات
        $this->command->newLine();
        $this->command->info('📊 خلاصه تنظیمات:');
        $this->command->table(
            ['نقش', 'قیمت خرده', 'قیمت عمده', 'اطلاعات تامین‌کننده', 'فیلتر قیمت', 'ثبت قیمت'],
            collect($settings)->map(fn($s) => [
                $s['role'],
                $s['can_see_retail_price'] ? '✅' : '❌',
                $s['can_see_wholesale_price'] ? '✅' : '❌',
                $s['can_see_supplier_info'] ? '✅' : '❌',
                $s['can_filter_by_price'] ? '✅' : '❌',
                $s['can_set_retail_price'] ? '✅' : '❌',
            ])->toArray()
        );
    }
}
