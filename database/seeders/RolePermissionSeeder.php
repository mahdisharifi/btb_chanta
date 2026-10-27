<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->permissions();

        foreach ($permissions as $name => $metadata) {
            $permission = Permission::findOrCreate($name, 'web');
            $permission->forceFill($metadata)->save();
        }

        $this->syncRoleMetadataAndPermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissions(): array
    {
        return [
            // Legacy and current permissions are both preserved while callers converge.
            'manage_all' => $this->metadata('admin', 'مدیریت کامل سیستم'),
            'access_admin_panel' => $this->metadata('admin', 'دسترسی به پنل مدیریت'),
            'manage_users' => $this->metadata('users', 'مدیریت کاربران'),
            'view_any_user' => $this->metadata('users', 'مشاهده لیست کاربران'),
            'view_user' => $this->metadata('users', 'مشاهده کاربر'),
            'create_user' => $this->metadata('users', 'ایجاد کاربر'),
            'update_user' => $this->metadata('users', 'ویرایش کاربر'),
            'delete_user' => $this->metadata('users', 'حذف کاربر'),
            'manage_categories' => $this->metadata('categories', 'مدیریت دسته‌بندی‌ها'),
            'view_any_category' => $this->metadata('categories', 'مشاهده دسته‌بندی‌ها'),
            'view_category' => $this->metadata('categories', 'مشاهده دسته‌بندی'),
            'create_category' => $this->metadata('categories', 'ایجاد دسته‌بندی'),
            'update_category' => $this->metadata('categories', 'ویرایش دسته‌بندی'),
            'delete_category' => $this->metadata('categories', 'حذف دسته‌بندی'),
            'manage_products' => $this->metadata('products', 'مدیریت محصولات'),
            'view_products' => $this->metadata('products', 'مشاهده محصولات'),
            'view_any_product' => $this->metadata('products', 'مشاهده لیست محصولات'),
            'view_product' => $this->metadata('products', 'مشاهده محصول'),
            'create_product' => $this->metadata('products', 'ایجاد محصول'),
            'edit_product' => $this->metadata('products', 'ویرایش محصول'),
            'update_product' => $this->metadata('products', 'ویرایش محصول'),
            'delete_product' => $this->metadata('products', 'حذف محصول'),
            'manage_own_products' => $this->metadata('products', 'مدیریت محصولات خود'),
            'view_any_supplier' => $this->metadata('suppliers', 'مشاهده تأمین‌کنندگان'),
            'view_supplier' => $this->metadata('suppliers', 'مشاهده تأمین‌کننده'),
            'create_supplier' => $this->metadata('suppliers', 'ایجاد تأمین‌کننده'),
            'update_supplier' => $this->metadata('suppliers', 'ویرایش تأمین‌کننده'),
            'delete_supplier' => $this->metadata('suppliers', 'حذف تأمین‌کننده'),
            'view_supplier_info' => $this->metadata('suppliers', 'مشاهده اطلاعات تأمین‌کننده'),
            'manage_prices' => $this->metadata('prices', 'مدیریت قیمت‌ها'),
            'set_supplier_price' => $this->metadata('prices', 'تنظیم قیمت تأمین‌کننده'),
            'view_supplier_price' => $this->metadata('prices', 'مشاهده قیمت تأمین‌کننده'),
            'view_supplier_prices' => $this->metadata('prices', 'مشاهده قیمت‌های تأمین‌کننده'),
            'set_retail_price' => $this->metadata('prices', 'تنظیم قیمت خرده‌فروشی'),
            'view_retail_price' => $this->metadata('prices', 'مشاهده قیمت خرده‌فروشی'),
            'manage_retail_prices' => $this->metadata('prices', 'مدیریت قیمت‌های خرده‌فروشی'),
            'manage_gallery' => $this->metadata('media', 'مدیریت گالری'),
            'upload_media' => $this->metadata('media', 'بارگذاری رسانه'),
            'manage_seo' => $this->metadata('seo', 'مدیریت سئو'),

            'outsourcing.create_task' => $this->metadata('outsourcing', 'ایجاد تسک تکمیل محصول'),
            'outsourcing.publish_task' => $this->metadata('outsourcing', 'انتشار تسک برای واگذاری'),
            'outsourcing.assign_task' => $this->metadata('outsourcing', 'تخصیص تسک'),
            'outsourcing.view_tasks' => $this->metadata('outsourcing', 'مشاهده تسک‌های مجاز'),
            'outsourcing.view_all_tasks' => $this->metadata('outsourcing', 'مشاهده همه تسک‌ها'),
            'outsourcing.view_supplier_tasks' => $this->metadata('outsourcing', 'مشاهده تسک‌های تأمین‌کننده'),
            'outsourcing.accept_task' => $this->metadata('outsourcing', 'پذیرش تسک'),
            'outsourcing.start_task' => $this->metadata('outsourcing', 'شروع تسک'),
            'outsourcing.update_content' => $this->metadata('outsourcing', 'ویرایش محتوای مجاز'),
            'outsourcing.update_attributes' => $this->metadata('outsourcing', 'ویرایش مشخصات مجاز'),
            'outsourcing.manage_media' => $this->metadata('outsourcing', 'مدیریت رسانه موقت'),
            'outsourcing.manage_seo' => $this->metadata('outsourcing', 'ویرایش سئوی مجاز'),
            'outsourcing.view_product_draft' => $this->metadata('outsourcing', 'مشاهده پیش‌نویس محتوا'),
            'outsourcing.submit_task' => $this->metadata('outsourcing', 'ارسال تسک برای بررسی'),
            'outsourcing.view_revision_history' => $this->metadata('outsourcing', 'مشاهده تاریخچه اصلاحات'),
            'outsourcing.request_revision' => $this->metadata('outsourcing', 'درخواست اصلاح'),
            'outsourcing.review_submission' => $this->metadata('outsourcing', 'بررسی نسخه ارسالی'),
            'outsourcing.approve_full_correction_rate' => $this->metadata('outsourcing_finance', 'تأیید نرخ کامل Task اصلاحی'),
            'outsourcing.approve_revision_commission' => $this->metadata('outsourcing_finance', 'تأیید پورسانت تغییر درخواست'),
            'outsourcing.approve_submission' => $this->metadata('outsourcing', 'تأیید نسخه ارسالی'),
            'outsourcing.reject_submission' => $this->metadata('outsourcing', 'رد نسخه ارسالی'),
            'outsourcing.cancel_task' => $this->metadata('outsourcing', 'لغو تسک'),
            'outsourcing.manage_scope' => $this->metadata('outsourcing', 'مدیریت محدوده مجاز تسک'),
            'outsourcing.extend_deadline' => $this->metadata('outsourcing', 'تمدید مهلت تسک'),
            'outsourcing.extend_revision_limit' => $this->metadata('outsourcing', 'افزایش سقف اصلاحات'),
            'outsourcing.resolve_conflict' => $this->metadata('outsourcing', 'رفع تداخل محتوایی'),
            'outsourcing.expire_task' => $this->metadata('outsourcing', 'منقضی‌کردن تسک'),
            'outsourcing.create_completion_request' => $this->metadata('outsourcing', 'درخواست تکمیل محصول'),
            'outsourcing.override_correction_restriction' => $this->metadata('outsourcing', 'ایجاد Task اصلاحی برای محصول محدودشده'),
            'outsourcing.manage_commission_rules' => $this->metadata('outsourcing_finance', 'مدیریت قوانین پورسانت'),
            'outsourcing.view_own_commissions' => $this->metadata('outsourcing_finance', 'مشاهده پورسانت شخصی'),
            'outsourcing.view_reviewed_commissions' => $this->metadata('outsourcing_finance', 'مشاهده پورسانت تسک‌های بازبینی‌شده'),
            'outsourcing.view_all_commissions' => $this->metadata('outsourcing_finance', 'مشاهده همه پورسانت‌ها'),
            'outsourcing.approve_commission' => $this->metadata('outsourcing_finance', 'تأیید پورسانت'),
            'outsourcing.pay_commission' => $this->metadata('outsourcing_finance', 'پرداخت پورسانت'),
            'outsourcing.adjust_commission' => $this->metadata('outsourcing_finance', 'اصلاح مبلغ پورسانت'),
            'outsourcing.view_audit_logs' => $this->metadata('outsourcing_audit', 'مشاهده سوابق برون‌سپاری'),
        ];
    }

    private function syncRoleMetadataAndPermissions(): void
    {
        $roles = [
            UserRole::SUPER_ADMIN->value => [
                'display_name' => UserRole::SUPER_ADMIN->label(),
                'description' => 'دسترسی کامل به تمام بخش‌های سیستم',
                'permissions' => Permission::query()->where('guard_name', 'web')->pluck('name')->all(),
            ],
            UserRole::ADMIN->value => [
                'display_name' => UserRole::ADMIN->label(),
                'description' => 'مدیریت عملیات و محتوای سامانه بدون دسترسی پرداخت پورسانت',
                'permissions' => [
                    'access_admin_panel', 'manage_products', 'view_products', 'view_any_product',
                    'view_product', 'create_product', 'edit_product', 'update_product', 'manage_gallery',
                    'upload_media', 'manage_seo', 'outsourcing.create_task', 'outsourcing.publish_task',
                    'outsourcing.assign_task', 'outsourcing.view_tasks', 'outsourcing.view_all_tasks',
                    'outsourcing.view_product_draft', 'outsourcing.view_revision_history',
                    'outsourcing.request_revision', 'outsourcing.review_submission',
                    'outsourcing.approve_submission', 'outsourcing.reject_submission',
                    'outsourcing.cancel_task', 'outsourcing.manage_scope',
                    'outsourcing.extend_deadline', 'outsourcing.extend_revision_limit',
                    'outsourcing.resolve_conflict', 'outsourcing.expire_task',
                    'outsourcing.override_correction_restriction',
                    'outsourcing.view_all_commissions', 'outsourcing.view_audit_logs',
                ],
            ],
            UserRole::SUPPLIER->value => [
                'display_name' => UserRole::SUPPLIER->label(),
                'description' => 'مدیریت محصولات و اطلاعات تجاری متعلق به تأمین‌کننده',
                'permissions' => [
                    'manage_products', 'create_product', 'edit_product', 'update_product',
                    'manage_own_products', 'view_products', 'view_product', 'view_any_product',
                    'manage_prices', 'set_supplier_price', 'manage_gallery', 'upload_media',
                    'outsourcing.create_completion_request', 'outsourcing.view_supplier_tasks',
                    'outsourcing.view_product_draft', 'outsourcing.view_revision_history',
                    'outsourcing.request_revision', 'outsourcing.review_submission',
                    'outsourcing.approve_submission', 'outsourcing.reject_submission',
                ],
            ],
            UserRole::OUTSOURCER->value => [
                'display_name' => UserRole::OUTSOURCER->label(),
                'description' => 'تکمیل محتوای مجاز تسک‌های تخصیص‌یافته بدون دسترسی تجاری',
                'permissions' => [
                    'outsourcing.view_tasks', 'outsourcing.accept_task', 'outsourcing.start_task',
                    'outsourcing.update_content', 'outsourcing.update_attributes',
                    'outsourcing.manage_media', 'outsourcing.manage_seo',
                    'outsourcing.view_product_draft', 'outsourcing.submit_task',
                    'outsourcing.view_revision_history', 'outsourcing.view_own_commissions',
                ],
            ],
            UserRole::REVIEWER->value => [
                'display_name' => UserRole::REVIEWER->label(),
                'description' => 'بررسی نسخه‌های محتوایی تخصیص‌یافته',
                'permissions' => [
                    'outsourcing.view_tasks', 'outsourcing.view_product_draft',
                    'outsourcing.view_revision_history', 'outsourcing.request_revision',
                    'outsourcing.review_submission', 'outsourcing.approve_submission',
                    'outsourcing.reject_submission', 'outsourcing.approve_full_correction_rate',
                    'outsourcing.approve_revision_commission', 'outsourcing.view_reviewed_commissions',
                    'outsourcing.adjust_commission',
                ],
            ],
            UserRole::FINANCE_MANAGER->value => [
                'display_name' => UserRole::FINANCE_MANAGER->label(),
                'description' => 'مدیریت قوانین و پرداخت پورسانت بدون دسترسی محتوایی کامل',
                'permissions' => [
                    'outsourcing.manage_commission_rules', 'outsourcing.view_all_commissions',
                    'outsourcing.approve_commission', 'outsourcing.pay_commission',
                    'outsourcing.adjust_commission',
                ],
            ],
            UserRole::RETAILER_PREMIUM->value => [
                'display_name' => UserRole::RETAILER_PREMIUM->label(),
                'description' => 'مشاهده محصولات و اطلاعات مجاز تأمین‌کنندگان',
                'permissions' => [
                    'view_products', 'view_product', 'view_any_product', 'view_supplier',
                    'view_any_supplier', 'view_supplier_price', 'view_supplier_prices',
                    'view_supplier_info', 'set_retail_price', 'view_retail_price',
                ],
            ],
            UserRole::RETAILER_BASIC->value => [
                'display_name' => UserRole::RETAILER_BASIC->label(),
                'description' => 'مشاهده محصولات و قیمت خرده‌فروشی',
                'permissions' => [
                    'view_products', 'view_product', 'view_any_product', 'view_supplier',
                    'view_any_supplier', 'set_retail_price', 'view_retail_price',
                ],
            ],
            UserRole::END_USER->value => [
                'display_name' => UserRole::END_USER->label(),
                'description' => 'مشاهده محصولات و قیمت خرده‌فروشی',
                'permissions' => ['view_products', 'view_product', 'view_any_product', 'view_retail_price'],
            ],
        ];

        foreach ($roles as $name => $definition) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->forceFill([
                'display_name' => $definition['display_name'],
                'description' => $definition['description'],
            ])->save();
            $role->givePermissionTo($definition['permissions']);
        }
    }

    private function metadata(string $group, string $displayName): array
    {
        return [
            'group_name' => $group,
            'display_name' => $displayName,
        ];
    }
}
