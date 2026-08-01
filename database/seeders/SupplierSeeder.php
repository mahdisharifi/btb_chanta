<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\User;
use App\Enums\UserRole;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $supplierUsers = User::role(UserRole::SUPPLIER->value)->get();

        $supplierData = [
            [
                'company_name' => 'شرکت تجارت نوین پارس',
                'company_name_en' => 'Pars Nova Trading Co.',
                'brand_name' => 'پارس نوین',
                'description' => 'تأمین‌کننده معتبر لوازم الکترونیکی و دیجیتال با بیش از ۱۵ سال سابقه فعالیت در بازار ایران. ارائه‌دهنده محصولات اصلی سامسونگ، اپل و شیائومی.',
                'phone' => '021-88001234',
                'mobile' => '09121234567',
                'email' => 'info@parsnovin.com',
                'website' => 'https://parsnovin.com',
                'address' => 'تهران، خیابان ولیعصر، بالاتر از میدان ونک، پلاک ۲۴۵',
                'city' => 'تهران',
                'province' => 'تهران',
                'postal_code' => '1435678901',
                'tax_id' => '10320067890',
                'national_id' => '10320067890123',
                'is_verified' => true,
                'is_active' => true,
                'verified_at' => now()->subDays(30),
            ],
            [
                'company_name' => 'بازرگانی لوازم خانگی آریا',
                'company_name_en' => 'Aria Home Appliances Trading',
                'brand_name' => 'آریا هوم',
                'description' => 'واردکننده و توزیع‌کننده انواع لوازم خانگی با نمایندگی رسمی برندهای معتبر جهانی. تضمین اصالت کالا و خدمات پس از فروش.',
                'phone' => '021-66005678',
                'mobile' => '09132345678',
                'email' => 'info@ariahome.ir',
                'website' => 'https://ariahome.ir',
                'address' => 'اصفهان، خیابان چهارباغ بالا، مجتمع تجاری سپاهان، طبقه ۳',
                'city' => 'اصفهان',
                'province' => 'اصفهان',
                'postal_code' => '8134567890',
                'tax_id' => '10840012345',
                'national_id' => '10840012345678',
                'is_verified' => true,
                'is_active' => true,
                'verified_at' => now()->subDays(15),
            ],
            [
                'company_name' => 'گروه بازرگانی سیمرغ',
                'company_name_en' => 'Simorgh Trading Group',
                'brand_name' => 'سیمرغ',
                'description' => 'مجموعه بازرگانی سیمرغ فعال در زمینه واردات و توزیع مواد غذایی، بهداشتی و آرایشی. همکاری با بیش از ۵۰۰ فروشگاه در سراسر کشور.',
                'phone' => '051-37001234',
                'mobile' => '09153456789',
                'email' => 'info@simorgh-group.ir',
                'website' => 'https://simorgh-group.ir',
                'address' => 'مشهد، بلوار وکیل‌آباد، نبش خیابان هفتم، ساختمان سیمرغ',
                'city' => 'مشهد',
                'province' => 'خراسان رضوی',
                'postal_code' => '9185678901',
                'tax_id' => '10380045678',
                'national_id' => '10380045678901',
                'is_verified' => false,
                'is_active' => true,
                'verified_at' => null,
            ],
        ];

        foreach ($supplierUsers as $index => $user) {
            if (isset($supplierData[$index])) {
                Supplier::create(array_merge(
                    $supplierData[$index],
                    ['user_id' => $user->id]
                ));
            }
        }
    }
}
