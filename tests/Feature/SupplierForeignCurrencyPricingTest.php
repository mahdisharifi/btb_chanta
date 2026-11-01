<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Helpers\PriceRoundingHelper;
use App\Livewire\Supplier\Products\EditPrice;
use App\Livewire\Supplier\Settings\ManageCategoryTierDiscounts;
use App\Livewire\Supplier\Settings\ManageTierDiscounts;
use App\Jobs\ApplySupplierCurrencyPriceChunk;
use App\Jobs\ApplySupplierCurrencyPrices;
use App\Jobs\ApplySupplierTomanTierPriceChunk;
use App\Jobs\ApplySupplierTomanTierPrices;
use App\Jobs\BroadcastSupplierProductPrices;
use App\Models\Category;
use App\Models\CheckPeriod;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierExchangeRateSetting;
use App\Models\SupplierTierDiscount;
use App\Models\SupplierTierPercentage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierForeignCurrencyPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Category $category;
    private PriceType $cashType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(UserRole::SUPPLIER->value);
        $this->supplier = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->category = Category::factory()->create();
        $this->cashType = PriceType::query()->create([
            'code' => 'cash',
            'name' => 'نقدی',
            'description' => 'قیمت نقدی',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        PriceType::query()->create([
            'code' => 'check',
            'name' => 'چکی',
            'description' => 'قیمت چکی',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        PriceType::query()->create([
            'code' => 'warranty',
            'name' => 'گارانتی',
            'description' => 'قیمت گارانتی',
            'sort_order' => 3,
            'is_active' => true,
        ]);
    }

    public function test_cash_price_label_tracks_the_selected_currency(): void
    {
        $product = $this->product('TOM', null);
        foreach (['USD' => 'دلار', 'CNY' => 'یوان', 'AED' => 'درهم'] as $currency => $label) {
            $this->supplier->forceFill([
                'pricing_currency' => $currency,
                'pricing_currency_revision' => ((int) $this->supplier->pricing_currency_revision) + 1,
            ])->save();

            Livewire::actingAs($this->user)
                ->test(EditPrice::class, ['product' => $product])
                ->assertSee("قیمت نقدی ({$label})")
                ->assertDontSee('price-currency-desktop');
        }
    }

    public function test_supplier_can_save_cash_price_in_the_selected_currency_with_two_decimals(): void
    {
        $product = $this->product('USD', null);
        $productSupplier = ProductSupplier::query()->where('product_id', $product->id)->sole();
        $this->setManualRate(270000);
        $this->setTierDiscount(2, -5);

        $component = Livewire::actingAs($this->user)
            ->test(EditPrice::class, ['product' => $product])
            ->assertSee('قیمت نقدی (دلار)')
            ->set('foreignPriceAmount', '5.12')
            ->assertHasNoErrors()
            ->call('save')
            ->assertHasNoErrors();

        $productSupplier->refresh();
        $this->assertSame('USD', $productSupplier->price_currency);
        $this->assertEqualsWithDelta(5.12, (float) $productSupplier->foreign_price_amount, 0.00001);
        $this->assertGreaterThan(0, (int) Cache::get("web:supplier:{$this->supplier->id}:stats:version", 0));

        $basePrice = app(\App\Services\ForeignCurrencyPriceService::class)
            ->calculateToman(5.12, 270000, $productSupplier);
        $this->assertDatabaseHas('product_prices', [
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => $basePrice,
        ]);

        $expectedBulkPrice = (int) round(PriceRoundingHelper::round(
            $basePrice * 0.95,
            $this->supplier
        ));
        $this->assertDatabaseHas('product_prices', [
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 2,
            'price' => $expectedBulkPrice,
        ]);
        $this->assertDatabaseHas('product_prices', [
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 0,
            'price' => (int) round(PriceRoundingHelper::round($basePrice * 1.05, $this->supplier)),
        ]);

        // The input accepts a whole amount as well as a two-decimal amount.
        $integerAmountForm = Livewire::actingAs($this->user)
            ->test(EditPrice::class, ['product' => $product])
            ->set('foreignPriceAmount', '5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(5.0, (float) $productSupplier->fresh()->foreign_price_amount, 0.00001);

        $integerAmountForm->set('foreignPriceAmount', '5.00004')
            ->call('save')
            ->assertHasErrors('foreignPriceAmount');
    }

    public function test_public_supplier_price_range_keeps_converted_values_in_toman(): void
    {
        $product = $this->product('USD', 5, true);
        $offer = ProductSupplier::query()
            ->where('product_id', $product->id)
            ->where('supplier_id', $this->supplier->id)
            ->sole();

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $offer->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 1_350_000,
            'is_active' => true,
        ]);

        $offer->refresh();

        $this->assertSame(1_350_000, $offer->lowest_price_toman);
        $this->assertSame('1,350,000 تومان', $offer->formatted_price_range);
    }

    public function test_unsaved_display_defaults_are_used_for_foreign_currency_tiers(): void
    {
        config()->set('queue.default', 'sync');
        $product = $this->product('USD', 5.0, active: true);
        $this->setManualRate(270000);

        Livewire::actingAs($this->user)
            ->test(ManageTierDiscounts::class)
            ->call('applyCurrencyRatesToProducts')
            ->assertHasNoErrors();

        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        $this->assertSame(1350000, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 1)
            ->value('price'));
        $this->assertSame(
            (int) round(PriceRoundingHelper::round(1350000 * 1.05, $this->supplier)),
            (int) ProductPrice::query()
                ->where('product_supplier_id', $productSupplier->id)
                ->where('price_type_id', $this->cashType->id)
                ->where('tier_index', 0)
                ->value('price')
        );
        $this->assertSame(
            (int) round(PriceRoundingHelper::round(1350000 * 0.95, $this->supplier)),
            (int) ProductPrice::query()
                ->where('product_supplier_id', $productSupplier->id)
                ->where('price_type_id', $this->cashType->id)
                ->where('tier_index', 2)
                ->value('price')
        );
        $this->assertDatabaseCount('supplier_tier_discount', 0);
    }

    public function test_supplier_default_tier_changes_are_not_pinned_as_product_overrides(): void
    {
        $this->setManualRate(270000);
        $this->setTierDiscount(2, -5);
        $product = $this->product('USD', 5.0, active: true);

        Livewire::actingAs($this->user)
            ->test(EditPrice::class, ['product' => $product])
            ->set('autoCalculateTiers', true)
            ->call('save')
            ->assertHasNoErrors();

        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        $this->assertNull($productSupplier->tier_percentages);

        SupplierTierDiscount::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('tier_index', 2)
            ->update(['percentage_change' => -10]);

        Livewire::actingAs($this->user)
            ->test(EditPrice::class, ['product' => $product])
            ->assertSet('tierPriceMode.2', 'default')
            ->assertSet('tierPercentages.2', -10.0)
            ->call('save')
            ->assertHasNoErrors();

        $productSupplier->refresh();
        $this->assertNull($productSupplier->tier_percentages);
        $this->assertSame(1350000, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 1)
            ->value('price'));
        $this->assertSame((int) round(PriceRoundingHelper::round(1350000 * 0.9, $this->supplier)),
            (int) ProductPrice::query()
                ->where('product_supplier_id', $productSupplier->id)
                ->where('price_type_id', $this->cashType->id)
                ->where('tier_index', 2)
                ->value('price'));
    }

    public function test_currency_refresh_also_updates_inactive_products_for_later_activation(): void
    {
        config()->set('queue.default', 'sync');
        $product = $this->product('USD', 5.0, active: false);
        $this->setManualRate(270000);

        Livewire::actingAs($this->user)
            ->test(ManageTierDiscounts::class)
            ->call('applyCurrencyRatesToProducts')
            ->assertHasNoErrors();

        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        $this->assertDatabaseHas('product_prices', [
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 1350000,
        ]);
        $this->assertSame(1282500, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 2)
            ->value('price'));
    }

    public function test_toman_tier_updater_can_insert_a_missing_price_row(): void
    {
        $product = $this->product('TOM', null, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 100000,
            'is_active' => true,
        ]);

        $result = \App\Helpers\TierPriceUpdater::updateSupplierPrices((int) $this->supplier->id);

        $this->assertSame(2, $result['updated']);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 2,
            'price' => (int) round(PriceRoundingHelper::round(95000, $this->supplier)),
        ]);
    }

    public function test_toman_tier_recalculation_updates_derived_checks_and_preserves_manual_checks(): void
    {
        $this->supplier->forceFill(['pricing_currency' => 'TOM'])->save();
        $product = $this->product('TOM', null, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        $checkType = PriceType::query()->where('code', 'check')->firstOrFail();
        $period = CheckPeriod::query()->create([
            'name' => '30 روزه',
            'label' => 'چک ۳۰ روزه',
            'scope' => 'global',
            'supplier_id' => null,
            'days' => 30,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        DB::table('supplier_check_period')->insert([
            'supplier_id' => $this->supplier->id,
            'check_period_id' => $period->id,
            'percentage_increase' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            ['tier_index' => 1, 'price' => 100000, 'mode' => 'manual'],
            ['tier_index' => 2, 'price' => 90000, 'mode' => 'default'],
        ] as $row) {
            ProductPrice::query()->create([
                'product_id' => $product->id,
                'product_supplier_id' => $productSupplier->id,
                'price_type_id' => $this->cashType->id,
                'tier_index' => $row['tier_index'],
                'price' => $row['price'],
                'is_active' => true,
            ]);
        }

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $checkType->id,
            'tier_index' => 2,
            'check_period_id' => $period->id,
            'price' => 99000,
            'calculation_mode' => 'default',
            'is_active' => true,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $checkType->id,
            'tier_index' => 1,
            'check_period_id' => $period->id,
            'price' => 123456,
            'calculation_mode' => 'manual',
            'is_active' => true,
        ]);

        \App\Helpers\TierPriceUpdater::updateSupplierPrices((int) $this->supplier->id);

        $this->assertSame(95000, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 2)
            ->value('price'));
        $this->assertSame(104500, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $checkType->id)
            ->where('tier_index', 2)
            ->where('check_period_id', $period->id)
            ->value('price'));
        $this->assertSame(123456, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $checkType->id)
            ->where('tier_index', 1)
            ->where('check_period_id', $period->id)
            ->value('price'));
    }

    public function test_toman_tier_recalculation_respects_product_overrides_and_disabled_tiers(): void
    {
        $this->setTierDiscount(2, -5);
        $product = $this->product('TOM', null, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        $productSupplier->update([
            'tier_percentages' => [2 => -8],
            'tier_enabled' => [0 => false, 1 => true, 2 => true],
        ]);

        foreach ([
            ['tier_index' => 0, 'price' => 123456],
            ['tier_index' => 1, 'price' => 100000],
            ['tier_index' => 2, 'price' => 90000],
        ] as $row) {
            ProductPrice::query()->create([
                'product_id' => $product->id,
                'product_supplier_id' => $productSupplier->id,
                'price_type_id' => $this->cashType->id,
                'tier_index' => $row['tier_index'],
                'price' => $row['price'],
                'is_active' => true,
            ]);
        }

        \App\Helpers\TierPriceUpdater::updateSupplierPrices((int) $this->supplier->id);

        $this->assertSame(123456, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 0)
            ->value('price'));
        $this->assertSame((int) round(PriceRoundingHelper::round(92000, $this->supplier)), (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 2)
            ->value('price'));
    }

    public function test_toman_tier_updater_keeps_disabled_price_rows_disabled(): void
    {
        $product = $this->product('TOM', null, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 100000,
            'is_active' => true,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 2,
            'price' => 95000,
            'is_active' => false,
        ]);

        \App\Helpers\TierPriceUpdater::updateSupplierPrices((int) $this->supplier->id);

        $this->assertDatabaseHas('product_prices', [
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 2,
            'price' => (int) round(PriceRoundingHelper::round(95000, $this->supplier)),
            'is_active' => false,
        ]);
    }

    public function test_toman_tier_updater_includes_inactive_supplier_products(): void
    {
        Queue::fake();
        $product = $this->product('TOM', null, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 100000,
            'is_active' => true,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 2,
            'price' => 90000,
            'is_active' => false,
        ]);

        // Use the production deactivation hook, which disables every matrix row.
        $productSupplier->update(['is_active' => false]);

        $supplier = $this->supplier->fresh();
        (new ApplySupplierTomanTierPrices(
            (int) $supplier->id,
            (int) $supplier->pricing_currency_revision,
            (int) $supplier->tier_pricing_revision,
        ))->handle();

        Queue::assertPushed(ApplySupplierTomanTierPriceChunk::class, fn ($job): bool =>
            in_array((int) $productSupplier->id, $job->productSupplierIds, true)
        );

        $result = \App\Helpers\TierPriceUpdater::updateSupplierPrices((int) $this->supplier->id);

        $this->assertGreaterThan(0, $result['updated']);
        $this->assertSame(false, (bool) $productSupplier->fresh()->is_active);
        $this->assertDatabaseHas('product_prices', [
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 100000,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('product_prices', [
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 2,
            'price' => (int) round(PriceRoundingHelper::round(95000, $this->supplier)),
            'is_active' => false,
        ]);
    }

    public function test_toman_update_action_queues_the_catalog_recalculation(): void
    {
        Queue::fake();
        $this->supplier->forceFill([
            'pricing_currency' => 'TOM',
            'pricing_currency_revision' => max(1, (int) $this->supplier->pricing_currency_revision),
        ])->save();

        Livewire::actingAs($this->user)
            ->test(ManageTierDiscounts::class)
            ->call('updatePrices')
            ->assertHasNoErrors();

        $supplier = $this->supplier->fresh();
        Queue::assertPushed(ApplySupplierTomanTierPrices::class, fn ($job): bool =>
            $job->supplierId === (int) $this->supplier->id
            && $job->currencyRevision === (int) $supplier->pricing_currency_revision
            && $job->tierPricingRevision === (int) $supplier->tier_pricing_revision
        );
    }

    public function test_currency_recalculation_passes_tier_revision_without_consuming_retry_attempt(): void
    {
        Queue::fake();
        $this->supplier->forceFill(['pricing_currency' => 'USD'])->save();
        $this->setManualRate(270000);
        $this->product('USD', 5.0, active: true);
        $supplier = $this->supplier->fresh();
        $setting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $supplier->id)
            ->where('currency', 'USD')
            ->sole();

        (new ApplySupplierCurrencyPrices(
            (int) $supplier->id,
            'USD',
            (int) $setting->revision,
            false,
            (int) $supplier->tier_pricing_revision,
        ))->handle(app(\App\Services\ForeignCurrencyPriceService::class));

        Queue::assertPushed(ApplySupplierCurrencyPriceChunk::class, fn ($job): bool =>
            $job->supplierId === (int) $supplier->id
            && $job->retryAttempt === 0
            && $job->tierPricingRevision === (int) $supplier->tier_pricing_revision
        );
    }

    public function test_currency_rebase_uses_carton_as_reference_even_with_legacy_marker(): void
    {
        $this->setManualRate(270000);
        $product = $this->product('TOM', null, active: true);
        $this->supplier->forceFill([
            'pricing_currency' => 'USD',
            'pricing_currency_revision' => 2,
        ])->save();
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();

        foreach ([['tier_index' => 0, 'price' => 100000], ['tier_index' => 1, 'price' => 2700000]] as $row) {
            ProductPrice::query()->create([
                'product_id' => $product->id,
                'product_supplier_id' => $productSupplier->id,
                'price_type_id' => $this->cashType->id,
                'tier_index' => $row['tier_index'],
                'price' => $row['price'],
                'is_active' => true,
            ]);
            SupplierTierPercentage::query()->create([
                'supplier_id' => $this->supplier->id,
                'product_id' => $product->id,
                'tier_index' => $row['tier_index'],
                'tier_label' => $row['tier_index'] === 0 ? 'زیر کارتن' : 'کارتن',
                'percentage' => 0,
                'is_reference' => $row['tier_index'] === 0,
                'is_active' => true,
                'sort_order' => $row['tier_index'],
            ]);
        }

        $this->assertTrue(app(\App\Services\ForeignCurrencyPriceService::class)->rebaseProductCurrency(
            (int) $productSupplier->id,
            (int) $this->supplier->id,
            'USD',
            2,
        ));

        $this->assertSame('10.0000', (string) $productSupplier->fresh()->foreign_price_amount);

        $form = Livewire::actingAs($this->user)->test(EditPrice::class, ['product' => $product]);
        $form->assertSet('referenceTierIndex', 1);
    }

    public function test_toman_price_chunk_broadcasts_committed_matrix_updates(): void
    {
        Queue::fake();
        $product = $this->product('TOM', null, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 100000,
            'is_active' => true,
        ]);

        $supplier = $this->supplier->fresh();
        (new ApplySupplierTomanTierPriceChunk(
            (int) $this->supplier->id,
            [(int) $productSupplier->id],
            (int) $supplier->pricing_currency_revision,
            (int) $supplier->tier_pricing_revision,
        ))->handle();

        $this->assertDatabaseHas('product_prices', [
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 2,
            'price' => (int) round(PriceRoundingHelper::round(95000, $this->supplier)),
        ]);

        Queue::assertPushed(BroadcastSupplierProductPrices::class, fn ($job): bool =>
            $job->supplierId === (int) $this->supplier->id
            && in_array((int) $productSupplier->id, $job->productSupplierIds, true)
            && $job->revisionAlreadyIncremented === false
        );
    }

    public function test_stale_foreign_tier_chunk_queues_current_percentage_generation(): void
    {
        Queue::fake();
        $this->supplier->forceFill(['pricing_currency' => 'USD'])->save();
        $this->setManualRate(270000);
        $product = $this->product('USD', 5.0, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        $settingRevision = (int) SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('currency', 'USD')
            ->value('revision');
        $staleTierRevision = (int) $this->supplier->fresh()->tier_pricing_revision;
        $this->supplier->increment('tier_pricing_revision');

        (new ApplySupplierCurrencyPriceChunk(
            (int) $this->supplier->id,
            'USD',
            [(int) $productSupplier->id],
            $settingRevision,
            false,
            0,
            $staleTierRevision,
        ))->handle(app(\App\Services\ForeignCurrencyPriceService::class));

        Queue::assertPushed(ApplySupplierCurrencyPrices::class, fn ($job): bool =>
            $job->supplierId === (int) $this->supplier->id
            && $job->settingRevision === $settingRevision
            && $job->tierPricingRevision === (int) $this->supplier->fresh()->tier_pricing_revision
        );
    }

    public function test_toman_tier_updater_skips_a_supplier_with_foreign_global_currency(): void
    {
        $product = $this->product('USD', 5.12, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 1382400,
            'is_active' => true,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 2,
            'price' => 1313280,
            'is_active' => true,
        ]);

        $result = \App\Helpers\TierPriceUpdater::updateSupplierPrices((int) $this->supplier->id);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['errors']);
        $this->assertFalse($result['settings_changed']);
        $this->assertSame(1313280, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 2)
            ->value('price'));
    }

    public function test_toman_tier_updater_prefers_category_percentage_over_supplier_default(): void
    {
        $product = $this->product('TOM', null, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 100000,
            'is_active' => true,
        ]);
        DB::table('category_tier_discount')->insert([
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'tier_index' => 2,
            'percentage_change' => -20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Helpers\TierPriceUpdater::updateSupplierPrices((int) $this->supplier->id);

        $this->assertSame((int) round(PriceRoundingHelper::round(80000, $this->supplier)),
            (int) ProductPrice::query()
                ->where('product_supplier_id', $productSupplier->id)
                ->where('price_type_id', $this->cashType->id)
                ->where('tier_index', 2)
                ->value('price'));
    }

    public function test_stale_category_tier_form_does_not_restore_an_overwritten_percentage(): void
    {
        DB::table('category_tier_discount')->insert([
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'tier_index' => 2,
            'percentage_change' => -5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staleForm = Livewire::actingAs($this->user)
            ->test(ManageCategoryTierDiscounts::class)
            ->call('startEditCategory', $this->category->id);

        Livewire::actingAs($this->user)
            ->test(ManageCategoryTierDiscounts::class)
            ->call('startEditCategory', $this->category->id)
            ->set('tierPercentages.2', '-10')
            ->call('saveCategoryEdit')
            ->assertHasNoErrors();

        $staleForm->set('tierPercentages.2', '-20')
            ->call('saveCategoryEdit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('category_tier_discount', [
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'tier_index' => 2,
            'percentage_change' => -10,
            'is_active' => true,
        ]);
    }

    public function test_stale_supplier_tier_form_does_not_overwrite_newer_percentages(): void
    {
        $staleForm = Livewire::actingAs($this->user)->test(ManageTierDiscounts::class);

        Livewire::actingAs($this->user)
            ->test(ManageTierDiscounts::class)
            ->set('tierSettings.2.percentage_change', '-10')
            ->call('save')
            ->assertHasNoErrors();

        $staleForm->set('tierSettings.2.percentage_change', '-20')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('supplier_tier_discount', [
            'supplier_id' => $this->supplier->id,
            'tier_index' => 2,
            'percentage_change' => -10,
            'is_active' => true,
        ]);
    }

    public function test_category_change_refreshes_default_form_revision_in_the_same_component(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManageTierDiscounts::class)
            ->set('selectedCategoryId', $this->category->id)
            ->set('categoryTierPercentages.2', '-20')
            ->call('addCategory')
            ->assertHasNoErrors()
            ->set('tierSettings.2.percentage_change', '-10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('category_tier_discount', [
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'tier_index' => 2,
            'percentage_change' => -20,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('supplier_tier_discount', [
            'supplier_id' => $this->supplier->id,
            'tier_index' => 2,
            'percentage_change' => -10,
            'is_active' => true,
        ]);
    }

    public function test_failed_rate_provider_is_recorded_and_retried_after_a_backoff(): void
    {
        $this->supplier->forceFill(['pricing_currency' => 'USD'])->save();
        $setting = SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $this->supplier->id,
            'currency' => 'USD',
            'rate_toman' => 270000,
            'manual_rate_toman' => 270000,
            'auto_update' => true,
            'refresh_hours' => 6,
            'next_update_at' => now()->subMinute(),
            'source' => 'nerkhsanj',
            'revision' => 1,
        ]);

        $providerRefresh = \Mockery::mock(\App\Services\ExchangeRates\ExchangeRateRefreshService::class);
        $providerRefresh->shouldReceive('refresh')->once()->andThrow(new \RuntimeException('rate source unavailable'));
        app()->instance(\App\Services\ExchangeRates\ExchangeRateRefreshService::class, $providerRefresh);

        $attemptedAt = now();
        $dispatched = app(\App\Services\ForeignCurrencyPriceService::class)->processDue();

        $this->assertSame(0, $dispatched);
        $setting->refresh();
        $this->assertSame('rate source unavailable', $setting->last_error);
        $this->assertNotNull($setting->last_attempt_at);
        $this->assertTrue($setting->next_update_at->gt($attemptedAt));
        $this->assertSame(270000, (int) $setting->rate_toman);
    }

    public function test_manual_currency_setting_applies_to_all_matching_products_through_the_livewire_action(): void
    {
        config()->set('queue.default', 'sync');
        $firstProduct = $this->product('USD', 5.12, active: true);
        $secondProduct = $this->product('USD', 2.5, active: true);
        $this->setManualRate(270000);
        $this->setTierDiscount(2, -5);

        $component = Livewire::actingAs($this->user)
            ->test(ManageTierDiscounts::class)
            ->assertSet('currencySettingRevisions.USD', 1)
            ->assertSet('currencySettingRevisions.CNY', 1)
            ->set('currencySettings.USD.manual_rate_toman', '300000')
            ->call('saveCurrencySettings')
            ->assertHasNoErrors()
            ->call('applyCurrencyRatesToProducts')
            ->assertHasNoErrors();

        $setting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('currency', 'USD')
            ->sole();
        $this->assertSame(3, (int) $setting->revision);
        $this->assertNull($setting->last_apply_error);
        $this->assertDatabaseHas('supplier_exchange_rate_settings', [
            'supplier_id' => $this->supplier->id,
            'currency' => 'CNY',
            'manual_rate_toman' => null,
        ]);
        $this->assertGreaterThan(0, (int) Cache::get('products.prices.version', 0));
        $this->assertGreaterThan(0, (int) Cache::get("web:supplier:{$this->supplier->id}:stats:version", 0));

        foreach ([[$firstProduct, 5.12], [$secondProduct, 2.5]] as [$product, $amount]) {
            $productSupplier = ProductSupplier::query()
                ->where('product_id', $product->id)
                ->where('supplier_id', $this->supplier->id)
                ->sole();
            $basePrice = app(\App\Services\ForeignCurrencyPriceService::class)
                ->calculateToman($amount, 300000, $productSupplier);

            $this->assertDatabaseHas('product_prices', [
                'product_supplier_id' => $productSupplier->id,
                'price_type_id' => $this->cashType->id,
                'tier_index' => 1,
                'price' => $basePrice,
            ]);
            $this->assertDatabaseHas('product_prices', [
                'product_supplier_id' => $productSupplier->id,
                'price_type_id' => $this->cashType->id,
                'tier_index' => 2,
                'price' => (int) round(PriceRoundingHelper::round($basePrice * 0.95, $this->supplier)),
            ]);

            $productSupplier->refresh();
            $this->assertSame('USD', $productSupplier->foreign_price_applied_currency);
            $this->assertSame((int) $setting->revision, (int) $productSupplier->foreign_price_applied_setting_revision);
            $productRevision = (int) $product->fresh()->price_revision;
            $foreignPriceRevision = (int) $productSupplier->foreign_price_revision;
            $changedIds = [];
            $wasReapplied = app(\App\Services\ForeignCurrencyPriceService::class)->applyConfiguredRate(
                (int) $productSupplier->id,
                (int) $this->supplier->id,
                'USD',
                (int) $setting->revision,
                PriceType::query()->whereIn('code', ['cash', 'check', 'warranty'])->get()->keyBy('code'),
                $changedIds,
            );

            $this->assertFalse($wasReapplied);
            $this->assertSame([], $changedIds);
            $this->assertSame($foreignPriceRevision, (int) $productSupplier->fresh()->foreign_price_revision);
            $this->assertSame($productRevision, (int) $product->fresh()->price_revision);
        }

        $firstProductSupplierId = ProductSupplier::query()
            ->where('product_id', $firstProduct->id)
            ->where('supplier_id', $this->supplier->id)
            ->value('id');
        $priceBeforeStaleRetry = (int) ProductPrice::query()
            ->where('product_supplier_id', $firstProductSupplierId)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 1)
            ->value('price');
        $priceVersionBeforeStaleRetry = (int) Cache::get('products.prices.version', 0);

        (new \App\Jobs\ApplySupplierCurrencyPriceChunk(
            (int) $this->supplier->id,
            'USD',
            [(int) $firstProductSupplierId],
            2,
        ))->handle(app(\App\Services\ForeignCurrencyPriceService::class));

        $this->assertSame($priceBeforeStaleRetry, (int) ProductPrice::query()
            ->where('product_supplier_id', $firstProductSupplierId)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 1)
            ->value('price'));
        $this->assertGreaterThan($priceVersionBeforeStaleRetry, (int) Cache::get('products.prices.version', 0));
    }

    public function test_foreign_price_reapplies_when_only_global_tier_percentages_change(): void
    {
        $this->supplier->forceFill(['pricing_currency' => 'USD'])->save();
        $this->setManualRate(270000);
        $this->setTierDiscount(2, -5);

        $product = $this->product('USD', 5.12, active: true);
        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        $setting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('currency', 'USD')
            ->firstOrFail();
        $priceTypes = PriceType::query()
            ->whereIn('code', ['cash', 'check', 'warranty'])
            ->get()
            ->keyBy('code');
        $service = app(\App\Services\ForeignCurrencyPriceService::class);
        $changedIds = [];
        $tierRevision = (int) $this->supplier->fresh()->tier_pricing_revision;

        $this->assertTrue($service->applyConfiguredRate(
            (int) $productSupplier->id,
            (int) $this->supplier->id,
            'USD',
            (int) $setting->revision,
            $priceTypes,
            $changedIds,
            $tierRevision,
        ));
        $this->assertSame(1313280, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 2)
            ->value('price'));

        SupplierTierDiscount::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('tier_index', 2)
            ->update(['percentage_change' => -10]);
        $this->supplier->increment('tier_pricing_revision');
        $newTierRevision = (int) $this->supplier->fresh()->tier_pricing_revision;
        $changedIds = [];

        $this->assertTrue($service->applyConfiguredRate(
            (int) $productSupplier->id,
            (int) $this->supplier->id,
            'USD',
            (int) $setting->revision,
            $priceTypes,
            $changedIds,
            $newTierRevision,
        ));
        $this->assertSame(1244160, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 2)
            ->value('price'));
        $this->assertSame($newTierRevision, (int) $productSupplier->fresh()->foreign_price_applied_tier_revision);

        $changedIds = [];
        $this->assertFalse($service->applyConfiguredRate(
            (int) $productSupplier->id,
            (int) $this->supplier->id,
            'USD',
            (int) $setting->revision,
            $priceTypes,
            $changedIds,
            $newTierRevision,
        ));
    }

    public function test_supplier_currency_is_selected_once_in_tier_settings_and_prices_are_rebased_without_value_change(): void
    {
        config()->set('queue.default', 'sync');
        $this->supplier->forceFill(['pricing_currency' => 'USD', 'pricing_currency_revision' => 2])->save();
        $this->setManualRate(270000);
        $this->setManualRateFor('CNY', 40000);

        $product = $this->product('USD', 5.0, active: true);
        $productSupplier = ProductSupplier::query()->where('product_id', $product->id)->sole();
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 1,
            'price' => 1350000,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManageTierDiscounts::class)
            ->assertSee('ارز قیمت‌گذاری همهٔ محصولات')
            ->assertSet('pricingCurrency', 'USD')
            ->set('pricingCurrency', 'CNY')
            ->call('saveCurrencySettings')
            ->assertHasNoErrors();

        $this->assertSame('CNY', $this->supplier->fresh()->pricing_currency);
        $productSupplier->refresh();
        $this->assertSame('CNY', $productSupplier->price_currency);
        $this->assertEqualsWithDelta(33.75, (float) $productSupplier->foreign_price_amount, 0.0001);
        $this->assertSame(1350000, (int) ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->where('price_type_id', $this->cashType->id)
            ->where('tier_index', 1)
            ->value('price'));

        Livewire::actingAs($this->user)
            ->test(EditPrice::class, ['product' => $product])
            ->assertSee('قیمت نقدی (یوان)')
            ->assertDontSee('price-currency-desktop')
            ->assertSet('foreignPriceAmount', '33.75');
    }

    public function test_stale_product_form_cannot_save_after_supplier_changes_global_currency(): void
    {
        $this->supplier->forceFill(['pricing_currency' => 'USD', 'pricing_currency_revision' => 2])->save();
        $product = $this->product('USD', 5.0);
        $form = Livewire::actingAs($this->user)->test(EditPrice::class, ['product' => $product]);

        $this->supplier->forceFill(['pricing_currency' => 'CNY', 'pricing_currency_revision' => 3])->save();
        $form->set('foreignPriceAmount', '6')
            ->call('save')
            ->assertHasErrors('priceCurrency');

        $this->assertEqualsWithDelta(5.0, (float) ProductSupplier::query()
            ->where('product_id', $product->id)
            ->value('foreign_price_amount'), 0.0001);
    }

    public function test_stale_product_form_cannot_save_prices_after_global_tier_percentages_change(): void
    {
        $this->supplier->forceFill(['pricing_currency' => 'USD'])->save();
        $this->setManualRate(270000);
        $product = $this->product('USD', 5.0);
        $form = Livewire::actingAs($this->user)->test(EditPrice::class, ['product' => $product]);
        $originalRevision = (int) $this->supplier->fresh()->tier_pricing_revision;

        SupplierTierDiscount::query()->create([
            'supplier_id' => $this->supplier->id,
            'tier_index' => 2,
            'percentage_change' => -10,
            'is_active' => true,
        ]);
        $this->supplier->increment('tier_pricing_revision');

        $form->call('save')->assertHasErrors('priceMatrix');

        $productSupplier = ProductSupplier::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('product_id', $product->id)
            ->sole();
        $form->assertSet('tierPricingRevision', $originalRevision);
        $this->assertNull($productSupplier->foreign_price_applied_tier_revision);
        $this->assertSame(0, ProductPrice::query()->where('product_supplier_id', $productSupplier->id)->count());
    }

    public function test_stale_currency_settings_form_cannot_overwrite_a_newer_exchange_rate(): void
    {
        $this->supplier->forceFill(['pricing_currency' => 'USD'])->save();
        $this->setManualRate(270000);

        $staleForm = Livewire::actingAs($this->user)->test(ManageTierDiscounts::class);

        Livewire::actingAs($this->user)
            ->test(ManageTierDiscounts::class)
            ->set('currencySettings.USD.manual_rate_toman', '280000')
            ->call('saveCurrencySettings')
            ->assertHasNoErrors();

        $staleForm->set('currencySettings.USD.manual_rate_toman', '260000')
            ->set('pricingCurrency', 'CNY')
            ->call('saveCurrencySettings')
            ->assertHasErrors('currencySettings');

        $this->assertSame('USD', $this->supplier->fresh()->pricing_currency);

        $this->assertSame(280000, (int) SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('currency', 'USD')
            ->value('manual_rate_toman'));
    }

    private function product(string $currency, ?float $amount, bool $active = false): Product
    {
        $this->supplier->forceFill([
            'pricing_currency' => $currency,
            'pricing_currency_revision' => max(1, (int) $this->supplier->pricing_currency_revision),
        ])->save();

        $product = Product::factory()->create([
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'quantity_tiers' => [
                ['name' => 'زیر کارتن', 'min_quantity' => 1, 'max_quantity' => 9],
                ['name' => 'کارتن', 'min_quantity' => 10, 'max_quantity' => 49],
                ['name' => 'عمده', 'min_quantity' => 50, 'max_quantity' => null],
            ],
        ]);

        ProductSupplier::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'is_active' => $active,
            'price_currency' => $currency,
            'foreign_price_amount' => $amount,
        ]);

        return $product;
    }

    private function setManualRate(int $rate): void
    {
        $this->setManualRateFor('USD', $rate);
    }

    private function setManualRateFor(string $currency, int $rate): void
    {
        SupplierExchangeRateSetting::query()->updateOrCreate(
            ['supplier_id' => $this->supplier->id, 'currency' => $currency],
            [
                'rate_toman' => $rate,
                'manual_rate_toman' => $rate,
                'auto_update' => false,
                'refresh_hours' => null,
                'next_update_at' => null,
                'source' => 'manual',
                'revision' => 1,
            ]
        );
    }

    private function setTierDiscount(int $tierIndex, float $percentage): void
    {
        SupplierTierDiscount::query()->create([
            'supplier_id' => $this->supplier->id,
            'tier_index' => $tierIndex,
            'percentage_change' => $percentage,
            'is_active' => true,
        ]);
    }
}
