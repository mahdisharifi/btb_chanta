<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Catalog;
use App\Models\Feature;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $supplier;
    protected User $regularSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        // ایجاد یک supplier برای تست‌ها
        $this->supplier = User::factory()->create([
            'approval_status' => 'approved'
        ]);
        $this->supplier->assignRole(UserRole::SUPPLIER);

        // ایجاد یک regular supplier برای تست‌ها
        $this->regularSupplier = User::factory()->create([
            'approval_status' => 'approved'
        ]);
        $this->regularSupplier->assignRole(UserRole::REGULAR_SUPPLIER);
    }

    /**
     * تست دسترسی guest به داشبورد supplier
     */
    public function test_guest_cannot_access_supplier_dashboard(): void
    {
        $response = $this->get(route('supplier.dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * تست داشبورد supplier
     */
    public function test_supplier_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.dashboard'));

        $response->assertStatus(200);
    }

    /**
     * تست داشبورد برای regular supplier
     */
    public function test_regular_supplier_dashboard_is_accessible(): void
    {
        $response = $this->actingAs($this->regularSupplier)
            ->get(route('supplier.dashboard'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست محصولات supplier
     */
    public function test_supplier_products_index_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.products.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایجاد محصول جدید
     */
    public function test_supplier_products_create_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.products.create'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش محصول
     */
    public function test_supplier_products_edit_is_accessible(): void
    {
        $product = Product::factory()->create([
            'supplier_id' => $this->supplier->id
        ]);

        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.products.edit', $product->id));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ویرایش قیمت محصول
     */
    public function test_supplier_products_edit_price_is_accessible(): void
    {
        $product = Product::factory()->create([
            'supplier_id' => $this->supplier->id
        ]);

        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.products.edit-price', $product->id));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه انتخاب محصولات
     */
    public function test_supplier_product_selection_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.product-selection'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه قیمت‌گذاری
     */
    public function test_supplier_pricing_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.pricing'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه محصولات من
     */
    public function test_supplier_my_products_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.my-products'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه گزارش‌ها
     */
    public function test_supplier_reports_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.reports'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه قیمت‌گذاری سریع
     */
    public function test_supplier_fast_pricing_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.fast-pricing'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه ایمپورت قیمت‌گذاری سریع
     */
    public function test_supplier_fast_pricing_import_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.fast-pricing.import'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه بررسی قیمت‌گذاری سریع
     */
    public function test_supplier_fast_pricing_review_is_accessible(): void
    {
        $batchId = 'test-batch-id';

        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.fast-pricing.review', $batchId));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه لیست کاتالوگ‌ها
     */
    public function test_supplier_catalog_index_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.catalog.index'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه نمایش کاتالوگ‌ها
     */
    public function test_supplier_catalog_show_is_accessible(): void
    {
        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.catalog.show'));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه پیش‌نمایش کاتالوگ
     */
    public function test_supplier_catalog_preview_is_accessible(): void
    {
        $catalog = Catalog::factory()->create([
            'supplier_id' => $this->supplier->id
        ]);

        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.catalog.preview', $catalog));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه خرید feature
     */
    public function test_supplier_feature_buy_is_accessible(): void
    {
        $feature = Feature::factory()->create();

        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.feature.buy', $feature));

        $response->assertStatus(200);
    }

    /**
     * تست صفحه خرید feature با featureId
     */
    public function test_supplier_features_buy_is_accessible(): void
    {
        $feature = Feature::factory()->create();

        $response = $this->actingAs($this->supplier)
            ->get(route('supplier.features.buy', $feature->id));

        $response->assertStatus(200);
    }
}
