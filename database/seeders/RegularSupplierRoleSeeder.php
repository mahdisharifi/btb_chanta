<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Enums\UserRole;

class RegularSupplierRoleSeeder extends Seeder
{
    /**
     * اضافه کردن نقش تأمین‌کننده عادی به سیستم
     * این نقش برای تأمین‌کنندگانی است که فقط می‌توانند محصولات را مشاهده کنند
     * و دسترسی مشابه END_USER دارند
     */
    public function run(): void
    {
        // پاکسازی کش مجوزها
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ایجاد نقش regular_supplier
        $regularSupplier = Role::firstOrCreate(
            ['name' => UserRole::REGULAR_SUPPLIER->value],
            [
                'display_name' => UserRole::REGULAR_SUPPLIER->label(),
                'guard_name' => 'web',
                'description' => 'تأمین‌کننده عادی که فقط دسترسی مشاهده محصولات را دارد'
            ]
        );

        // تخصیص مجوزها (مشابه END_USER)
        $permissions = [
            'view_product',
            'view_any_product'
        ];
        // بررسی وجود مجوزها و تخصیص
        $existingPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();

        if (count($existingPermissions) === count($permissions)) {
            $regularSupplier->syncPermissions($existingPermissions);

            $this->command->info('✓ نقش regular_supplier با موفقیت ایجاد شد');
            $this->command->info('✓ مجوزهای view_product و view_any_product تخصیص داده شد');
        } else {
            $this->command->warn('⚠ برخی مجوزها یافت نشدند. ابتدا RolesAndPermissionsSeeder را اجرا کنید');
        }
    }
}
