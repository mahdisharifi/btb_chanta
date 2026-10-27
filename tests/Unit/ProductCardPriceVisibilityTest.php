<?php

namespace Tests\Unit;

use App\Models\{PriceType, PriceVisibilitySetting, Product, ProductPrice, ProductSupplier, RetailPrice, Supplier, User};
use App\Services\PriceVisibilityService;
use Carbon\Carbon;
use Tests\TestCase;

class ProductCardPriceVisibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(array $overrides = []): PriceVisibilityService
    {
        $settings = new PriceVisibilitySetting(array_merge([
            'is_active' => true, 'can_see_retail_price' => true, 'can_see_wholesale_price' => true,
            'restricted_price_types' => ['cash'],
        ], $overrides));
        return new class($settings) extends PriceVisibilityService {
            public function __construct(private PriceVisibilitySetting $settings) {}
            public function getSettings(?User $user = null): ?PriceVisibilitySetting { return $this->settings; }
        };
    }

    private function product(array $priceOverrides = [], bool $supplierActive = true, ?string $type = 'cash'): Product
    {
        $price = (new ProductPrice)->forceFill(array_merge([
            'id' => 1, 'product_supplier_id' => 3, 'price' => 123456, 'is_active' => true,
            'valid_from' => '2026-10-25', 'valid_until' => '2026-10-25', 'tier_index' => 1,
        ], $priceOverrides));
        $price->setRelation('priceType', $type === null ? null : (new PriceType)->forceFill(['code' => $type]));
        $supplier = (new Supplier)->forceFill(['id' => 7, 'is_active' => $supplierActive]);
        $pivot = (new ProductSupplier)->forceFill(['id' => 3, 'supplier_id' => 7, 'is_active' => true]);
        $pivot->setRelation('supplier', $supplier)->setRelation('prices', collect([$price]));
        $product = (new Product)->forceFill(['id' => 4, 'title' => 'Test product', 'slug' => 'test-product']);
        $product->setRelation('productSuppliers', collect([$pivot]));
        $product->setRelation('retailPrices', collect([(new RetailPrice)->forceFill(['retail_price' => 987654, 'is_published' => true])]));
        return $product;
    }

    public function test_end_date_includes_the_entire_day_and_rejects_future_or_expired_prices(): void
    {
        Carbon::setTestNow('2026-10-25 23:59:00'); $service = $this->service();
        $this->assertCount(1, $service->getCardPrices($this->product())['wholesale']);
        $this->assertCount(0, $service->getCardPrices($this->product(['valid_until' => '2026-10-24']))['wholesale']);
        $this->assertCount(0, $service->getCardPrices($this->product(['valid_from' => '2026-10-26']))['wholesale']);
    }

    public function test_role_permissions_and_price_types_are_enforced(): void
    {
        Carbon::setTestNow('2026-10-25');
        $hidden = $this->service(['can_see_retail_price' => false, 'can_see_wholesale_price' => false])->getCardPrices($this->product());
        $this->assertNull($hidden['retail']); $this->assertCount(0, $hidden['wholesale']);
        foreach (['check', null] as $type) $this->assertCount(0, $this->service()->getCardPrices($this->product([], true, $type))['wholesale']);
    }

    public function test_inactive_suppliers_prices_and_missing_relations_are_excluded(): void
    {
        Carbon::setTestNow('2026-10-25'); $service = $this->service();
        $this->assertCount(0, $service->getCardPrices($this->product([], false))['wholesale']);
        $this->assertCount(0, $service->getCardPrices($this->product(['is_active' => false]))['wholesale']);
        $product = $this->product(); $product->productSuppliers->first()->unsetRelation('supplier');
        $this->assertCount(0, $service->getCardPrices($product)['wholesale']);
        $this->assertCount(0, $service->getCardPrices(new Product)['wholesale']);
    }

    public function test_supplier_filter_and_counts_use_only_visible_prices(): void
    {
        Carbon::setTestNow('2026-10-25'); $service = $this->service(); $product = $this->product();
        $product->productSuppliers->first()->prices->push(clone $product->productSuppliers->first()->prices->first());
        $visible = $service->getCardPrices($product);
        $this->assertCount(2, $visible['wholesale']); $this->assertSame(1, $visible['suppliers_count']);
        $this->assertCount(0, $service->getCardPrices($product, null, (new Supplier)->forceFill(['id' => 8]))['wholesale']);
    }

    public function test_rendered_card_omits_forbidden_amounts_and_labels_visible_currency(): void
    {
        Carbon::setTestNow('2026-10-25');
        $this->app->instance(PriceVisibilityService::class, $this->service(['can_see_retail_price' => false, 'can_see_wholesale_price' => false]));
        $html = \Illuminate\Support\Facades\Blade::render('<x-product.card :product="$product" />', ['product' => $this->product()]);
        $this->assertStringNotContainsString('987,654', $html); $this->assertStringNotContainsString('123,456', $html);
        $this->app->instance(PriceVisibilityService::class, $this->service());
        $html = \Illuminate\Support\Facades\Blade::render('<x-product.card :product="$product" />', ['product' => $this->product()]);
        $this->assertStringContainsString('تومان', $html); $this->assertStringContainsString('123,456', $html);
    }
}
