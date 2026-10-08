<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The order matters — each seeder depends on the previous ones.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,  // 1. Roles & Permissions first
            ProductCompletionSeeder::class, // Inactive base commission rules
            CategorySeeder::class,        // 2. Categories (needed by products)
            UserSeeder::class,            // 3. Users with role assignments
            SupplierSeeder::class,        // 4. Supplier profiles (needs supplier users)
            ProductSeeder::class,         // 5. Products + Prices + Retail Prices
            RegularSupplierRoleSeeder::class,  // ← اضافه کن
            CountrySeeder::class,
            ProvinceSeeder::class,
            CitySeeder::class,
            PriceTypeSeeder::class,       // انواع قیمت‌ها (نقدی، چکی، گارانتی)
            CheckPeriodSeeder::class,     // دوره‌های پیشفرض چک
            SocialNetworkSeeder::class,   // شبکه‌های اجتماعی

        ]);

        $this->command->info('');
        $this->command->info('══════════════════════════════════════════════');
        $this->command->info('  ✅  Database seeded successfully!');
        $this->command->info('══════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('  📧 Test Accounts (password: "password"):');
        $this->command->info('  ─────────────────────────────────────────');
        $this->command->info('  Admin:            admin@b2b.test');
        $this->command->info('  Supplier 1:       supplier1@b2b.test');
        $this->command->info('  Supplier 2:       supplier2@b2b.test');
        $this->command->info('  Supplier 3:       supplier3@b2b.test');
        $this->command->info('  Retailer Premium: premium1@b2b.test');
        $this->command->info('  Retailer Premium: premium2@b2b.test');
        $this->command->info('  Retailer Basic:   basic1@b2b.test');
        $this->command->info('  Retailer Basic:   basic2@b2b.test');
        $this->command->info('  End User:         user1@b2b.test');
        $this->command->info('  End User:         user2@b2b.test');
        $this->command->info('');
    }
}
