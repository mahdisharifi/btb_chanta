<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Enums\UserRole;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Create Permissions
        |--------------------------------------------------------------------------
        */
        $permissions = [
            // Admin
            'manage_all',

            // Products
            'manage_products',
            'create_product',
            'edit_product',
            'delete_product',
            'view_products',

            // Prices
            'manage_prices',
            'set_supplier_price',
            'view_supplier_price',
            'set_retail_price',
            'view_retail_price',

            // Supplier Info
            'view_supplier_info',

            // Categories
            'manage_categories',

            // Users
            'manage_users',

            // Media / Gallery
            'manage_gallery',
            'upload_media',

            // SEO
            'manage_seo',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Roles & Assign Permissions
        |--------------------------------------------------------------------------
        */

        // SUPER ADMIN — full access
        $superAdmin = Role::firstOrCreate([
            'name' => UserRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
        ]);
        $superAdmin->givePermissionTo(Permission::all());

        // SUPPLIER
        $supplier = Role::firstOrCreate([
            'name' => UserRole::SUPPLIER->value,
            'guard_name' => 'web',
        ]);
        $supplier->givePermissionTo([
            'manage_products',
            'create_product',
            'edit_product',
            'manage_prices',
            'set_supplier_price',
            'manage_gallery',
            'upload_media',
            'view_products',
        ]);

        // RETAILER PREMIUM
        $retailerPremium = Role::firstOrCreate([
            'name' => UserRole::RETAILER_PREMIUM->value,
            'guard_name' => 'web',
        ]);
        $retailerPremium->givePermissionTo([
            'view_products',
            'view_supplier_price',
            'view_supplier_info',
            'set_retail_price',
            'view_retail_price',
        ]);

        // RETAILER BASIC
        $retailerBasic = Role::firstOrCreate([
            'name' => UserRole::RETAILER_BASIC->value,
            'guard_name' => 'web',
        ]);
        $retailerBasic->givePermissionTo([
            'view_products',
            'set_retail_price',
            'view_retail_price',
        ]);

        // END USER
        $endUser = Role::firstOrCreate([
            'name' => UserRole::END_USER->value,
            'guard_name' => 'web',
        ]);
        $endUser->givePermissionTo([
            'view_products',
            'view_retail_price',
        ]);
    }
}
