<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */
        $admin = User::create([
            'name' => 'مدیر سیستم',
            'email' => 'admin@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        /*
        |--------------------------------------------------------------------------
        | Suppliers
        |--------------------------------------------------------------------------
        */
        $supplier1 = User::create([
            'name' => 'علی تأمین‌کننده',
            'email' => 'supplier1@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $supplier1->assignRole(UserRole::SUPPLIER->value);

        $supplier2 = User::create([
            'name' => 'رضا تأمین‌کننده',
            'email' => 'supplier2@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $supplier2->assignRole(UserRole::SUPPLIER->value);

        $supplier3 = User::create([
            'name' => 'محمد تأمین‌کننده',
            'email' => 'supplier3@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $supplier3->assignRole(UserRole::SUPPLIER->value);

        /*
        |--------------------------------------------------------------------------
        | Retailer Premium
        |--------------------------------------------------------------------------
        */
        $retailerPremium1 = User::create([
            'name' => 'حسن کاسب ویژه',
            'email' => 'premium1@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $retailerPremium1->assignRole(UserRole::RETAILER_PREMIUM->value);

        $retailerPremium2 = User::create([
            'name' => 'مهدی کاسب ویژه',
            'email' => 'premium2@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $retailerPremium2->assignRole(UserRole::RETAILER_PREMIUM->value);

        /*
        |--------------------------------------------------------------------------
        | Retailer Basic
        |--------------------------------------------------------------------------
        */
        $retailerBasic1 = User::create([
            'name' => 'احمد کاسب عادی',
            'email' => 'basic1@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $retailerBasic1->assignRole(UserRole::RETAILER_BASIC->value);

        $retailerBasic2 = User::create([
            'name' => 'سعید کاسب عادی',
            'email' => 'basic2@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $retailerBasic2->assignRole(UserRole::RETAILER_BASIC->value);

        /*
        |--------------------------------------------------------------------------
        | End Users
        |--------------------------------------------------------------------------
        */
        $endUser1 = User::create([
            'name' => 'زهرا مشتری',
            'email' => 'user1@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $endUser1->assignRole(UserRole::END_USER->value);

        $endUser2 = User::create([
            'name' => 'مریم مشتری',
            'email' => 'user2@b2b.test',
            'password' => Hash::make('1'),
            'email_verified_at' => now(),
        ]);
        $endUser2->assignRole(UserRole::END_USER->value);
    }
}
