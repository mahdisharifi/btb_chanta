<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // پاک کردن کش دسترسی‌ها
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // تعریف Permissionها با نام نمایشی فارسی
        $permissions = [
            // مدیریت کاربران
            ['name' => 'view_any_user', 'group_name' => 'users', 'display_name' => 'مشاهده لیست کاربران'],
            ['name' => 'view_user', 'group_name' => 'users', 'display_name' => 'مشاهده جزئیات کاربر'],
            ['name' => 'create_user', 'group_name' => 'users', 'display_name' => 'ایجاد کاربر'],
            ['name' => 'update_user', 'group_name' => 'users', 'display_name' => 'ویرایش کاربر'],
            ['name' => 'delete_user', 'group_name' => 'users', 'display_name' => 'حذف کاربر'],

            // مدیریت دسته‌بندی‌ها
            ['name' => 'view_any_category', 'group_name' => 'categories', 'display_name' => 'مشاهده لیست دسته‌بندی‌ها'],
            ['name' => 'view_category', 'group_name' => 'categories', 'display_name' => 'مشاهده جزئیات دسته‌بندی'],
            ['name' => 'create_category', 'group_name' => 'categories', 'display_name' => 'ایجاد دسته‌بندی'],
            ['name' => 'update_category', 'group_name' => 'categories', 'display_name' => 'ویرایش دسته‌بندی'],
            ['name' => 'delete_category', 'group_name' => 'categories', 'display_name' => 'حذف دسته‌بندی'],

            // مدیریت محصولات
            ['name' => 'view_any_product', 'group_name' => 'products', 'display_name' => 'مشاهده لیست محصولات'],
            ['name' => 'view_product', 'group_name' => 'products', 'display_name' => 'مشاهده جزئیات محصول'],
            ['name' => 'create_product', 'group_name' => 'products', 'display_name' => 'ایجاد محصول'],
            ['name' => 'update_product', 'group_name' => 'products', 'display_name' => 'ویرایش محصول'],
            ['name' => 'delete_product', 'group_name' => 'products', 'display_name' => 'حذف محصول'],
            ['name' => 'manage_own_products', 'group_name' => 'products', 'display_name' => 'مدیریت محصولات خود'],

            // مدیریت تأمین‌کنندگان
            ['name' => 'view_any_supplier', 'group_name' => 'suppliers', 'display_name' => 'مشاهده لیست تأمین‌کنندگان'],
            ['name' => 'view_supplier', 'group_name' => 'suppliers', 'display_name' => 'مشاهده جزئیات تأمین‌کننده'],
            ['name' => 'create_supplier', 'group_name' => 'suppliers', 'display_name' => 'ایجاد تأمین‌کننده'],
            ['name' => 'update_supplier', 'group_name' => 'suppliers', 'display_name' => 'ویرایش تأمین‌کننده'],
            ['name' => 'delete_supplier', 'group_name' => 'suppliers', 'display_name' => 'حذف تأمین‌کننده'],

            // سئو
            ['name' => 'manage_seo', 'group_name' => 'seo', 'display_name' => 'مدیریت صفحات سئو'],

            // قیمت‌ها
            ['name' => 'set_supplier_price', 'group_name' => 'prices', 'display_name' => 'تنظیم قیمت تأمین‌کننده'],
            ['name' => 'manage_retail_prices', 'group_name' => 'prices', 'display_name' => 'مدیریت قیمت‌های خرده‌فروشی'],
            ['name' => 'view_supplier_prices', 'group_name' => 'prices', 'display_name' => 'مشاهده قیمت تأمین‌کننده'],

            // دسترسی‌های ویژه ادمین
            ['name' => 'access_admin_panel', 'group_name' => 'admin', 'display_name' => 'دسترسی به پنل مدیریت'],
        ];

        // ایجاد Permissionها
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['group_name' => $perm['group_name'], 'display_name' => $perm['display_name']]
            );
        }

        // 1. ایجاد نقش Super Admin
        $superAdmin = Role::firstOrCreate([
            'name' => UserRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
        ], [
            'display_name' => 'مدیر ارشد',
            'description' => 'دسترسی کامل به تمام بخش‌های سیستم',
        ]);
        $superAdmin->syncPermissions(Permission::all());

        // 2. ایجاد نقش Supplier (تأمین‌کننده)
        $supplier = Role::firstOrCreate([
            'name' => UserRole::SUPPLIER->value,
            'guard_name' => 'web',
        ], [
            'display_name' => 'تأمین‌کننده',
            'description' => 'مدیریت محصولات و قیمت‌های تأمین',
        ]);
        $supplier->syncPermissions([
            'manage_own_products',
            'view_product',
            'view_any_product',
            'set_supplier_price',
        ]);

        // 3. ایجاد نقش Retailer Premium (کاسب ویژه)
        $retailerPremium = Role::firstOrCreate([
            'name' => UserRole::RETAILER_PREMIUM->value,
            'guard_name' => 'web',
        ], [
            'display_name' => 'کاسب ویژه',
            'description' => 'مشاهده محصولات و اطلاعات قیمت تأمین‌کنندگان',
        ]);
        $retailerPremium->syncPermissions([
            'view_product',
            'view_any_product',
            'view_supplier',
            'view_any_supplier',
            'view_supplier_prices',
        ]);

        // 4. ایجاد نقش Retailer Basic (کاسب عادی)
        $retailerBasic = Role::firstOrCreate([
            'name' => UserRole::RETAILER_BASIC->value,
            'guard_name' => 'web',
        ], [
            'display_name' => 'کاسب عادی',
            'description' => 'مشاهده محصولات و تأمین‌کنندگان',
        ]);
        $retailerBasic->syncPermissions([
            'view_product',
            'view_any_product',
            'view_supplier',
            'view_any_supplier',
        ]);

        // 5. ایجاد نقش End User (کاربر عادی)
        $endUser = Role::firstOrCreate([
            'name' => UserRole::END_USER->value,
            'guard_name' => 'web',
        ], [
            'display_name' => 'کاربر عادی',
            'description' => 'مشاهده محصولات',
        ]);
        $endUser->syncPermissions([
            'view_product',
            'view_any_product',
        ]);
    }
}
