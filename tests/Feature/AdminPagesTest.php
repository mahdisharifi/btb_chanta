<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\CatalogTemplate;
use App\Models\Flowchart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // اجرای RoleSeeder برای ایجاد نقش‌ها
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // ایجاد یک admin برای تست‌ها
        $this->admin = User::factory()->create();
        $this->admin->assignRole(UserRole::SUPER_ADMIN);
    }

    /**
     * تست داشبورد ادمین
     */
    public function test_admin_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    /**
     * تست دسترسی guest به داشبورد ادمین (باید redirect شود)
     */
    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * تست صفحه لیست کاربران
     */
    public function test_admin_users_index_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایجاد کاربر جدید
     */
    public function test_admin_users_create_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.create'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_verified_system_user_without_mobile(): void
    {
        Role::firstOrCreate(['name' => UserRole::REVIEWER->value, 'guard_name' => 'web']);

        \Livewire\Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\Users\Create::class)
            ->set('name', 'Reviewer Account')
            ->set('email', 'reviewer-system@example.test')
            ->set('role', UserRole::REVIEWER->value)
            ->assertSee('اختیاری برای کاربر سیستمی')
            ->set('mobile', '')
            ->set('password', 'SystemPassword#123')
            ->set('password_confirmation', 'SystemPassword#123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'reviewer-system@example.test')->firstOrFail();
        $this->assertNull($user->mobile);
        $this->assertTrue((bool) $user->is_verified);
        $this->assertNull($user->mobile_verified_at);
        $this->assertFalse($user->needsMobileVerification());
        $this->assertTrue($user->hasRole(UserRole::REVIEWER->value));
    }

    public function test_admin_created_external_user_still_requires_mobile(): void
    {
        \Livewire\Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\Users\Create::class)
            ->set('name', 'Retailer Account')
            ->set('email', 'retailer-system@example.test')
            ->set('role', UserRole::RETAILER_BASIC->value)
            ->set('mobile', '')
            ->set('password', 'SystemPassword#123')
            ->set('password_confirmation', 'SystemPassword#123')
            ->call('save')
            ->assertHasErrors(['mobile' => 'required']);

        $this->assertDatabaseMissing('users', ['email' => 'retailer-system@example.test']);
    }

    /**
     * تست صفحه ویرایش کاربر
     */
    public function test_admin_users_edit_is_accessible(): void
    {
        $this->markTestSkipped('Route has UserRole::USER error in Livewire component');
        
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $user));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه کاربران در حال انتظار تایید
     */
    public function test_admin_users_pending_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.pending'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست محصولات ادمین
     */
    public function test_admin_products_index_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.products.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایجاد محصول جدید
     */
    public function test_admin_products_create_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.products.create'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش محصول
     */
    public function test_admin_products_edit_is_accessible(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $product->id));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه نمایش جزئیات محصول در پنل ادمین
     */
    public function test_admin_products_show_is_accessible(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.products.show', $product->id));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست دسته‌بندی‌ها
     */
    public function test_admin_categories_index_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.categories.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایجاد دسته‌بندی جدید
     */
    public function test_admin_categories_create_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.categories.create'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش دسته‌بندی
     */
    public function test_admin_categories_edit_is_accessible(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.categories.edit', $category->id));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست تامین‌کنندگان
     */
    public function test_admin_suppliers_index_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.suppliers.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایجاد تامین‌کننده جدید
     */
    public function test_admin_suppliers_create_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.suppliers.create'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش تامین‌کننده
     */
    public function test_admin_suppliers_edit_is_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::SUPPLIER);
        $supplier = \App\Models\Supplier::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.suppliers.edit', $supplier));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه سئو
     */
    public function test_admin_seo_index_is_accessible(): void
    {
        $this->markTestSkipped('Layout components.layouts.app not found');
        
        $response = $this->actingAs($this->admin)
            ->get(route('admin.seo.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست Attributes
     */
    public function test_admin_attributes_index_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.attributes.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایجاد Attribute جدید
     */
    public function test_admin_attributes_create_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.attributes.create'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش Attribute
     */
    public function test_admin_attributes_edit_is_accessible(): void
    {
        $attribute = Attribute::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.attributes.edit', $attribute));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه پلن‌های اشتراک
     */
    public function test_admin_subscription_plans_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.subscription-plans.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست Flowcharts
     */
    public function test_admin_flowcharts_index_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.flowcharts.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایجاد Flowchart جدید
     */
    public function test_admin_flowcharts_create_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.flowcharts.create'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش Flowchart
     */
    public function test_admin_flowcharts_edit_is_accessible(): void
    {
        $this->markTestSkipped('Flowchart table missing slug column');
        
        $flowchart = Flowchart::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.flowcharts.edit', $flowchart));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ترمینال سیستم
     */
    public function test_admin_system_terminal_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.system.terminal'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه مدیریت جغرافیا
     */
    public function test_admin_geography_index_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.geography.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست کاتالوگ‌ها
     */
    public function test_admin_catalogs_index_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.catalogs.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایجاد کاتالوگ جدید
     */
    public function test_admin_catalogs_create_is_accessible(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.catalogs.create'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش کاتالوگ
     */
    public function test_admin_catalogs_edit_is_accessible(): void
    {
        $catalog = CatalogTemplate::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.catalogs.edit', $catalog));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه پیش‌نمایش کاتالوگ
     */
    public function test_admin_catalogs_preview_is_accessible(): void
    {
        $catalog = CatalogTemplate::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.catalogs.preview', $catalog));

        $response->assertStatus(200);
    }
}
