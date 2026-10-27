<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailerPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $premiumRetailer;
    protected User $basicRetailer;

    protected function setUp(): void
    {
        parent::setUp();

        // اجرای RoleSeeder برای ایجاد نقش‌ها
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // ایجاد یک premium retailer برای تست‌ها
        $this->premiumRetailer = User::factory()->create([
            'subscription_expires_at' => now()->addMonth()
        ]);
        $this->premiumRetailer->assignRole(UserRole::RETAILER_PREMIUM);

        // ایجاد یک basic retailer برای تست‌ها
        $this->basicRetailer = User::factory()->create([
            'subscription_expires_at' => now()->addMonth()
        ]);
        $this->basicRetailer->assignRole(UserRole::RETAILER_BASIC);
    }

    /**
     * تست دسترسی guest به داشبورد retailer
     */
    public function test_guest_cannot_access_retailer_dashboard(): void
    {
        $response = $this->get(route('retailer.dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * تست داشبورد premium retailer
     */
    public function test_premium_retailer_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->premiumRetailer)
            ->get(route('retailer.dashboard'));

        $response->assertStatus(200);
    }

    /**
     * تست داشبورد basic retailer
     */
    public function test_basic_retailer_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->basicRetailer)
            ->get(route('retailer.dashboard'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه اشتراک‌ها برای premium retailer
     */
    public function test_premium_retailer_subscriptions_index_is_accessible(): void
    {
        $response = $this->actingAs($this->premiumRetailer)
            ->get(route('retailer.subscriptions.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه اشتراک‌ها برای basic retailer
     */
    public function test_basic_retailer_subscriptions_index_is_accessible(): void
    {
        $response = $this->actingAs($this->basicRetailer)
            ->get(route('retailer.subscriptions.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه خرید feature برای premium retailer
     */
    public function test_premium_retailer_feature_buy_is_accessible(): void
    {
        $feature = Feature::factory()->create();

        $response = $this->actingAs($this->premiumRetailer)
            ->get(route('retailer.feature.buy', $feature));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه خرید feature با featureId برای premium retailer
     */
    public function test_premium_retailer_features_buy_is_accessible(): void
    {
        $feature = Feature::factory()->create();

        $response = $this->actingAs($this->premiumRetailer)
            ->get(route('retailer.features.buy', $feature->id));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه خرید feature برای basic retailer
     */
    public function test_basic_retailer_feature_buy_is_accessible(): void
    {
        $feature = Feature::factory()->create();

        $response = $this->actingAs($this->basicRetailer)
            ->get(route('retailer.feature.buy', $feature));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه خرید feature با featureId برای basic retailer
     */
    public function test_basic_retailer_features_buy_is_accessible(): void
    {
        $feature = Feature::factory()->create();

        $response = $this->actingAs($this->basicRetailer)
            ->get(route('retailer.features.buy', $feature->id));

        $response->assertStatus(200);
    }

    /**
     * تست redirect برای retailer با اشتراک منقضی شده
     */
    public function test_expired_subscription_redirects_to_subscription_page(): void
    {
        $expiredRetailer = User::factory()->create([
            'approval_status' => 'approved',
            'subscription_expires_at' => now()->subDay()
        ]);
        $expiredRetailer->assignRole(UserRole::RETAILER_PREMIUM);

        $response = $this->actingAs($expiredRetailer)
            ->get(route('retailer.dashboard'));

        // بررسی redirect یا نمایش پیام خطا
        // این بستگی به پیاده‌سازی middleware check.subscription دارد
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 403
        );
    }

    /**
     * تست نمایش اطلاعات تامین‌کننده برای premium retailer
     */
    public function test_premium_retailer_can_view_supplier_info(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole(UserRole::SUPPLIER);

        $response = $this->actingAs($this->premiumRetailer)
            ->get(route('suppliers.show', $supplier->id));

        $response->assertStatus(200);
    }

    /**
     * تست عدم دسترسی basic retailer به اطلاعات تامین‌کننده
     */
    public function test_basic_retailer_cannot_view_supplier_info(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole(UserRole::SUPPLIER);

        $response = $this->actingAs($this->basicRetailer)
            ->get(route('suppliers.show', $supplier->id));

        // بررسی عدم دسترسی (403 یا redirect)
        $this->assertTrue(
            $response->status() === 403 || $response->isRedirect()
        );
    }
}
