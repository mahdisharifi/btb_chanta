<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Supplier\Catalog\Index;
use App\Livewire\Supplier\Catalog\Preview;
use App\Livewire\Supplier\Settings\ManageCheckPeriods;
use App\Models\CatalogTemplate;
use App\Models\Category;
use App\Models\CheckPeriod;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierExchangeRateSetting;
use App\Models\User;
use App\Services\CatalogPriceFormatter;
use App\Services\CatalogExchangeRateBasisResolver;
use App\Services\CatalogSnapshotRevisionService;
use App\Services\CatalogPriceTypeSelection;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierCatalogCurrencyDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Category $category;
    private PriceType $cashType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(UserRole::SUPPLIER->value);
        $this->supplier = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
            'pricing_currency' => 'USD',
        ]);
        $this->category = Category::factory()->create();
        $this->cashType = PriceType::query()->create([
            'code' => 'cash',
            'name' => 'نقدی',
            'description' => 'قیمت نقدی',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_supplier_can_save_and_reopen_foreign_currency_catalog_setting(): void
    {
        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->set('name', 'کاتالوگ ارزی')
            ->set('priceDisplayCurrency', 'supplier')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $template = CatalogTemplate::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('name', 'کاتالوگ ارزی')
            ->sole();

        $this->assertSame('supplier', $template->settings['price_display_currency']);

        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->call('editTemplate', $template->id)
            ->assertSet('priceDisplayCurrency', 'supplier');
    }

    public function test_preview_formats_saved_toman_matrix_with_its_applied_rate_without_mutating_prices(): void
    {
        [$product, $productSupplier, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );
        $originalPrice = ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->value('price');

        Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('5.12 دلار');

        $this->assertSame($originalPrice, ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->value('price'));
    }

    public function test_open_preview_reloads_when_a_supplier_price_revision_changes(): void
    {
        [, $productSupplier, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('5.12 دلار');
        $initialCacheKey = $component->instance()->catalogDataCacheKey;

        ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->sole()
            ->update(['price' => 2700000]);

        $component->call('$refresh')
            ->assertSee('10 دلار')
            ->assertDontSee('5.12 دلار');

        $this->assertNotSame($initialCacheKey, $component->instance()->catalogDataCacheKey);
    }

    public function test_catalog_contact_info_displays_the_manual_supplier_rate_in_preview_and_both_pdf_templates(): void
    {
        $this->supplier->update(['pricing_currency' => 'USD']);
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $this->supplier->id,
            'currency' => 'USD',
            'rate_toman' => '280000.0000',
            'manual_rate_toman' => '280000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

        [, $productSupplier, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'show_cover_page' => true,
                'cover_theme' => 'default',
            ],
            tomanPrice: 1400000,
            appliedCurrency: 'USD',
            appliedRate: '280000.0000',
        );

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('نرخ دلار')
            ->assertSee('۲۸۰,۰۰۰ تومان')
            ->assertDontSee('هر دلار =');

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($component, $view);
            $this->assertStringContainsString('نرخ دلار', $html);
            $this->assertStringContainsString('۲۸۰,۰۰۰ تومان', $html);
            $this->assertStringNotContainsString('هر دلار =', $html);
        }
        $this->assertPdfDownloadRoute($component, 'downloadPdf');
        $this->assertPdfDownloadRoute($component, 'downloadPdf2');

        ProductSupplier::query()->whereKey($productSupplier->id)
            ->update(['foreign_price_rate' => '270000.0000']);
        $component->call('clearCatalogCache')
            ->assertSee('نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده یکسان نیست یا معتبر نیست');
        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($component, $view);
            $this->assertStringContainsString('نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده یکسان نیست یا معتبر نیست', $html);
            $this->assertStringContainsString('یکسان نیست', $html);
        }

        SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('currency', 'USD')
            ->update(['rate_toman' => '281000.0000', 'manual_rate_toman' => '281000.0000']);
        $component->call('clearCatalogCache')
            ->assertSee('۲۸۱,۰۰۰ تومان')
            ->assertDontSee('هر دلار =');

        SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('currency', 'USD')
            ->update(['manual_rate_toman' => null, 'rate_toman' => '270000.0000']);

        $basis = app(CatalogExchangeRateBasisResolver::class)->resolve($this->supplier->fresh());
        $this->assertNull($basis['rate_display']);
        $this->assertSame('نرخ دستی تنظیم نشده', $basis['status']);
    }

    public function test_admin_catalog_rate_comes_from_the_selected_single_supplier(): void
    {
        $selectedSupplier = Supplier::factory()->create([
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
            'pricing_currency' => 'CNY',
        ]);
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $selectedSupplier->id,
            'currency' => 'CNY',
            'rate_toman' => '40000.0000',
            'manual_rate_toman' => '40000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

        $product = Product::factory()->create([
            'supplier_id' => $selectedSupplier->id,
            'category_id' => $this->category->id,
            'quantity_tiers' => [['name' => 'کارتن', 'min_quantity' => 1, 'max_quantity' => null]],
        ]);
        $productSupplier = ProductSupplier::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $selectedSupplier->id,
            'is_active' => true,
            'price_currency' => 'CNY',
            'foreign_price_amount' => '12.5000',
            'foreign_price_rate' => '39000.0000',
            'foreign_price_applied_currency' => 'CNY',
            'foreign_price_applied_setting_revision' => 1,
            'foreign_price_applied_tier_revision' => 1,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 0,
            'price' => 500000,
            'is_active' => true,
        ]);

        $template = CatalogTemplate::query()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Admin catalog',
            'selected_categories' => [$this->category->id],
            'selected_price_types' => [$this->cashType->id],
            'settings' => [
                'categories' => 'all',
                'prices' => ['0' => [$this->cashType->id]],
                'show_cover_page' => false,
                'price_display_currency' => 'supplier',
                'cover_theme' => 'default',
            ],
            'config' => ['supplier_ids' => [$selectedSupplier->id]],
        ]);
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        $component = Livewire::actingAs($admin)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('نرخ یوان')
            ->assertSee('۴۰,۰۰۰ تومان')
            ->assertSee('نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده یکسان نیست یا معتبر نیست')
            ->assertDontSee('هر دلار =');
        $this->assertSame(
            1,
            substr_count($component->html(), 'نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده یکسان نیست یا معتبر نیست'),
        );

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($component, $view);
            $this->assertStringContainsString('نرخ یوان', $html);
            $this->assertStringContainsString('نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده', $html);
            $this->assertStringContainsString('یکسان نیست', $html);
        }
        $this->assertPdfDownloadRoute($component, 'downloadPdf');
        $this->assertPdfDownloadRoute($component, 'downloadPdf2');
    }

    public function test_admin_multi_supplier_catalog_does_not_claim_a_single_currency_rate(): void
    {
        $otherSupplier = Supplier::factory()->create([
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
            'pricing_currency' => 'CNY',
        ]);
        $template = CatalogTemplate::query()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Admin multi-supplier catalog',
            'selected_categories' => [$this->category->id],
            'selected_price_types' => [$this->cashType->id],
            'settings' => [
                'categories' => 'all',
                'prices' => 'all',
                'show_cover_page' => false,
                'price_display_currency' => 'supplier',
                'cover_theme' => 'default',
            ],
            'config' => ['supplier_ids' => [$this->supplier->id, $otherSupplier->id]],
        ]);
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        Livewire::actingAs($admin)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('ارز پایهٔ کاتالوگ قابل تشخیص نیست')
            ->assertDontSee('نرخ دلار')
            ->assertDontSee('نرخ یوان')
            ->assertDontSee('نرخ درهم');
    }

    public function test_automatic_catalog_rate_uses_last_successful_saved_rate_and_timestamp(): void
    {
        $this->supplier->update(['pricing_currency' => 'USD']);
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $this->supplier->id,
            'currency' => 'USD',
            'rate_toman' => '282500.0000',
            'manual_rate_toman' => '270000.0000',
            'auto_update' => true,
            'fetched_at' => now()->utc(),
            'source' => 'nerkhsanj',
        ]);

        [, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'show_cover_page' => true,
                'cover_theme' => 'default',
            ],
            tomanPrice: 1412500,
            appliedCurrency: 'USD',
            appliedRate: '282500.0000',
        );

        $basis = app(CatalogExchangeRateBasisResolver::class)->resolve($this->supplier->fresh());
        $this->assertSame('۲۸۲,۵۰۰', $basis['rate_display']);
        $this->assertNotNull($basis['last_fetched_at']);

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('۲۸۲,۵۰۰ تومان')
            ->assertDontSee('هر دلار =')
            ->assertSee('آخرین دریافت موفق:');

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($component, $view);
            $this->assertStringContainsString('۲۸۲,۵۰۰ تومان', $html);
            $this->assertStringNotContainsString('هر دلار =', $html);
            $this->assertStringContainsString('آخرین دریافت موفق:', $html);
        }
    }

    public function test_catalog_exchange_rate_label_tracks_supplier_currency_and_omits_toman(): void
    {
        $resolver = app(CatalogExchangeRateBasisResolver::class);

        foreach ([
            'USD' => 'دلار',
            'CNY' => 'یوان',
            'AED' => 'درهم',
        ] as $currency => $expectedLabel) {
            $this->supplier->forceFill(['pricing_currency' => $currency])->save();
            SupplierExchangeRateSetting::query()->updateOrCreate(
                ['supplier_id' => $this->supplier->id, 'currency' => $currency],
                [
                    'rate_toman' => '270000.0000',
                    'manual_rate_toman' => '270000.0000',
                    'auto_update' => false,
                    'source' => 'manual',
                ],
            );

            $this->assertSame($expectedLabel, $resolver->resolve($this->supplier->fresh())['currency_label']);
        }

        $this->supplier->forceFill(['pricing_currency' => 'TOM'])->save();
        $this->assertNull($resolver->resolve($this->supplier->fresh()));
    }

    public function test_catalog_revision_batch_coalesces_repeated_supplier_invalidations(): void
    {
        $revisions = app(CatalogSnapshotRevisionService::class);
        $supplierId = (int) $this->supplier->id;
        $revisions->invalidateSupplier($supplierId);

        $supplierRevisionBefore = (int) DB::table('catalog_snapshot_revisions')
            ->where('scope_type', 'supplier')
            ->where('scope_id', $supplierId)
            ->value('revision');
        $allSuppliersRevisionBefore = (int) DB::table('catalog_snapshot_revisions')
            ->where('scope_type', 'all_suppliers_shard')
            ->where('scope_id', $supplierId % 64)
            ->value('revision');
        $allSuppliersSignatureBefore = $revisions->signatureFor([]);

        $revisions->batch(function () use ($revisions, $supplierId): void {
            for ($index = 0; $index < 8; $index++) {
                $revisions->invalidateSupplier($supplierId);
            }
        });

        $this->assertSame($supplierRevisionBefore + 1, (int) DB::table('catalog_snapshot_revisions')
            ->where('scope_type', 'supplier')
            ->where('scope_id', $supplierId)
            ->value('revision'));
        $this->assertSame($allSuppliersRevisionBefore + 1, (int) DB::table('catalog_snapshot_revisions')
            ->where('scope_type', 'all_suppliers_shard')
            ->where('scope_id', $supplierId % 64)
            ->value('revision'));
        $this->assertNotSame($allSuppliersSignatureBefore, $revisions->signatureFor([]));
    }

    public function test_concurrent_snapshot_build_returns_retry_state_without_building_a_duplicate_snapshot(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        $cacheKey = $component->instance()->catalogDataCacheKey;
        $lockKey = 'catalog_snapshot_build_' . $cacheKey;

        $releasedBuildLock = Cache::lock($lockKey, 10);
        $this->assertTrue($releasedBuildLock->get(), 'A successful cold build must release its lock.');
        $releasedBuildLock->release();

        Cache::forget($cacheKey);
        $activeBuildLock = Cache::lock($lockKey, 120);
        $this->assertTrue($activeBuildLock->get());
        config(['catalog.snapshot_lock_wait_seconds' => 1]);

        try {
            $component->call('retryCatalogSnapshot')
                ->assertSet('catalogSnapshotBusy', true)
                ->assertSee('درخواست دیگری در حال ساخت همین اطلاعات است.');

            $this->assertFalse(Cache::has($cacheKey), 'A competing request must not publish a duplicate snapshot.');

            $versionKey = 'catalog_version_' . $template->id;
            $versionBeforeRefresh = Cache::get($versionKey);
            $component->call('clearCatalogCache')
                ->assertSet('catalogSnapshotBusy', true);
            $this->assertSame(
                $versionBeforeRefresh,
                Cache::get($versionKey),
                'A manual refresh must not change the cache version while a snapshot build is busy.',
            );
        } finally {
            $activeBuildLock->release();
        }
    }

    public function test_hard_deleting_a_product_price_invalidates_the_catalog_snapshot(): void
    {
        [$product, $productSupplier, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        $firstPreview = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        $firstCacheKey = $firstPreview->instance()->catalogDataCacheKey;
        $this->assertTrue(Cache::has($firstCacheKey));

        ProductPrice::query()
            ->where('product_supplier_id', $productSupplier->id)
            ->sole()
            ->delete();

        $secondPreview = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);

        $this->assertNotSame($firstCacheKey, $secondPreview->instance()->catalogDataCacheKey);
        $secondPreview->assertDontSee($product->title);
    }

    public function test_check_period_and_supplier_pivot_changes_invalidate_the_cached_snapshot(): void
    {
        $checkType = PriceType::query()->create([
            'code' => 'check',
            'name' => 'چکی',
            'description' => 'قیمت چکی',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $period = CheckPeriod::query()->create([
            'supplier_id' => null,
            'name' => 'چک ۳۰ روزه',
            'scope' => 'global',
            'days' => 30,
            'label' => 'چک ۳۰ روزه',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        DB::table('supplier_check_period')->insert([
            'supplier_id' => $this->supplier->id,
            'check_period_id' => $period->id,
            'percentage_increase' => 10,
            'is_active' => true,
            'label' => 'چک ۳۰ روزه',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$product, $productSupplier, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => ['0' => [$this->cashType->id, $checkType->id]],
                'check_periods' => ['0' => [$period->id]],
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $checkType->id,
            'tier_index' => 0,
            'check_period_id' => $period->id,
            'price' => 1512000,
            'is_active' => true,
        ]);

        $firstPreview = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        $firstCacheKey = $firstPreview->instance()->catalogDataCacheKey;
        $this->assertTrue(Cache::has($firstCacheKey));

        $checkPriceKey = "0_{$checkType->id}_{$period->id}";
        $firstSnapshot = Cache::get($firstCacheKey);
        $firstProduct = collect($firstSnapshot['grouped_products'])->flatten(1)->firstWhere('id', $product->id);
        $this->assertArrayHasKey($checkPriceKey, $firstProduct['prices']);

        $period->update(['label' => 'چک ۶۰ روزه']);

        $renamedPreview = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        $renamedCacheKey = $renamedPreview->instance()->catalogDataCacheKey;
        $this->assertNotSame($firstCacheKey, $renamedCacheKey);
        $renamedSnapshot = Cache::get($renamedCacheKey);
        $renamedProduct = collect($renamedSnapshot['grouped_products'])->flatten(1)->firstWhere('id', $product->id);
        $this->assertSame('چک ۶۰ روزه', $renamedProduct['prices'][$checkPriceKey]['check_period_label']);

        Livewire::actingAs($this->user)
            ->test(ManageCheckPeriods::class)
            ->call('updateGlobalPercentage', $period->id, 12);

        $percentagePreview = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        $percentageCacheKey = $percentagePreview->instance()->catalogDataCacheKey;
        $this->assertNotSame($renamedCacheKey, $percentageCacheKey);

        Livewire::actingAs($this->user)
            ->test(ManageCheckPeriods::class)
            ->call('confirmDeactivatePeriod', $period->id);

        $disabledPreview = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        $disabledCacheKey = $disabledPreview->instance()->catalogDataCacheKey;
        $this->assertNotSame($percentageCacheKey, $disabledCacheKey);
        $disabledSnapshot = Cache::get($disabledCacheKey);
        $disabledProduct = collect($disabledSnapshot['grouped_products'])->flatten(1)->firstWhere('id', $product->id);
        $this->assertArrayNotHasKey($checkPriceKey, $disabledProduct['prices']);
    }

    public function test_preview_all_price_types_resolves_numeric_database_price_type_ids(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('5.12 دلار');
    }

    public function test_preview_does_not_label_a_price_when_its_applied_currency_snapshot_mismatches(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'CNY',
            appliedRate: '25000.0000',
        );

        Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده یکسان نیست یا معتبر نیست')
            ->assertSee('---')
            ->assertDontSee('5.12 دلار');
    }

    public function test_legacy_catalog_without_currency_setting_stays_in_toman(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: [],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('1,382,400')
            ->assertDontSee('5.12 دلار');
    }

    public function test_both_pdf_exports_download_valid_files_from_the_server_side_preview_snapshot(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        config(['queue.default' => 'database']);
        Queue::fake();

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);

        foreach ([
            ['action' => 'downloadPdf', 'format' => 'list'],
            ['action' => 'downloadPdf2', 'format' => 'catalog'],
        ] as $exportCase) {
            $this->assertPdfDownloadRoute($component, $exportCase['action']);

            $html = $this->renderPdfTemplateHtml(
                $component,
                $exportCase['format'] === 'list'
                    ? 'pdf.supplier-catalog'
                    : 'pdf.supplier-catalog2',
            );
            $this->assertStringContainsString('5.12 دلار', $html);
        }

        Queue::assertNothingPushed();
    }

    public function test_both_pdf_exports_rebuild_an_expired_preview_snapshot_before_download(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        Queue::fake();
        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);

        foreach (['downloadPdf', 'downloadPdf2'] as $action) {
            $cacheKey = $component->get('catalogDataCacheKey');
            $this->assertIsString($cacheKey);
            Cache::forget($cacheKey);

            $this->assertPdfDownloadRoute($component, $action);

            $this->assertTrue(Cache::has($component->get('catalogDataCacheKey')));
        }

        Queue::assertNothingPushed();
    }

    public function test_preview_marks_an_expired_snapshot_instead_of_showing_an_empty_catalog(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: ['price_display_currency' => 'supplier'],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        Cache::forget($component->get('catalogDataCacheKey'));

        $renderedView = $component->instance()->render();

        $this->assertTrue($renderedView->getData()['previewDataExpired']);
        $this->assertSame([], $renderedView->getData()['groupedProducts']);
    }

    public function test_expired_snapshot_refresh_recovers_a_new_supplier_currency(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        $this->supplier->forceFill(['pricing_currency' => 'TOM'])->save();
        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertDontSee('نرخ دلار')
            ->assertDontSee('نرخ یوان')
            ->assertDontSee('نرخ درهم');
        $this->assertSame('TOM', $component->get('displayCurrencyCode'));

        $productSupplier = ProductSupplier::query()->sole();
        $this->supplier->forceFill(['pricing_currency' => 'CNY'])->save();
        $productSupplier->forceFill([
            'foreign_price_rate' => '25000.0000',
            'foreign_price_applied_currency' => 'CNY',
        ])->save();
        Cache::forget($component->get('catalogDataCacheKey'));

        $this->assertPdfDownloadRoute($component, 'downloadPdf2');

        $this->assertSame('CNY', $component->get('displayCurrencyCode'));
        $html = $this->renderPdfTemplateHtml($component, 'pdf.supplier-catalog2');
        $this->assertStringContainsString('55.3 یوان', $html);
    }

    public function test_manual_catalog_refresh_resolves_the_supplier_currency_again(): void
    {
        [, $productSupplier, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSet('displayCurrencyCode', 'USD');

        $this->supplier->forceFill(['pricing_currency' => 'CNY'])->save();
        $productSupplier->forceFill([
            'foreign_price_rate' => '25000.0000',
            'foreign_price_applied_currency' => 'CNY',
        ])->save();

        $component->call('clearCatalogCache')
            ->assertHasNoErrors()
            ->assertSet('displayCurrencyCode', 'CNY')
            ->assertSee('55.3 یوان');
    }

    public function test_both_pdf_variants_render_check_prices_using_database_price_type_ids(): void
    {
        PriceType::query()->create([
            'code' => 'handling',
            'name' => 'حمل',
            'description' => 'نوع قیمت آزمایشی',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $checkType = PriceType::query()->create([
            'code' => 'check',
            'name' => 'چکی',
            'description' => 'قیمت چکی',
            'sort_order' => 3,
            'is_active' => true,
        ]);
        $this->assertGreaterThan(2, $checkType->id);

        $period = CheckPeriod::query()->create([
            'supplier_id' => null,
            'name' => 'چک ۳۰ روزه',
            'scope' => 'global',
            'days' => 30,
            'label' => 'چک ۳۰ روزه',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        DB::table('supplier_check_period')->insert([
            'supplier_id' => $this->supplier->id,
            'check_period_id' => $period->id,
            'percentage_increase' => 10,
            'is_active' => true,
            'label' => 'چک ۳۰ روزه',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [, $productSupplier, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => ['0' => [$this->cashType->id, $checkType->id]],
                'check_periods' => ['0' => [$period->id]],
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );
        ProductPrice::query()->create([
            'product_id' => $productSupplier->product_id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $checkType->id,
            'tier_index' => 0,
            'check_period_id' => $period->id,
            'price' => 1512000,
            'is_active' => true,
        ]);

        config(['queue.default' => 'database']);
        Queue::fake();
        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);

        foreach ([
            ['action' => 'downloadPdf', 'format' => 'list'],
            ['action' => 'downloadPdf2', 'format' => 'catalog'],
        ] as $exportCase) {
            $this->assertPdfDownloadRoute($component, $exportCase['action']);

            $html = $this->renderPdfTemplateHtml(
                $component,
                $exportCase['format'] === 'list'
                    ? 'pdf.supplier-catalog'
                    : 'pdf.supplier-catalog2',
            );
            $this->assertStringContainsString('چک ۳۰ روزه', $html);
            $this->assertStringContainsString('5.6 دلار', $html);
            $this->assertStringNotContainsString('کارتن - چکی', $html);
        }
    }

    public function test_pdf_download_does_not_depend_on_an_async_queue(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: ['price_display_currency' => 'supplier'],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        config(['queue.default' => 'sync']);
        Queue::fake();

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        $this->assertPdfDownloadRoute($component, 'downloadPdf');

        Queue::assertNothingPushed();
    }

    public function test_pdf_progress_route_is_authenticated_and_reads_progress_state(): void
    {
        $key = 'catalog_progress_' . \Illuminate\Support\Str::uuid();
        Cache::store('file')->put($key, [
            'percent' => 50,
            'message' => 'در حال رندر کردن صفحات PDF...',
            'status' => 'processing',
            'user_id' => $this->user->id,
            'timestamp' => time(),
        ], now()->addMinutes(5));

        $this->actingAs($this->user)
            ->getJson(route('catalog.progress', $key))
            ->assertOk()
            ->assertJsonPath('percent', 50)
            ->assertJsonPath('status', 'processing')
            ->assertJsonMissingPath('user_id');

        $this->actingAs($this->user)
            ->getJson(route('catalog.progress', 'catalog_progress_missing'))
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->getJson(route('catalog.progress', $key))
            ->assertNotFound();

        auth()->logout();
        $this->getJson(route('catalog.progress', $key))->assertUnauthorized();
    }

    public function test_direct_pdf_download_is_owner_bound_and_one_time(): void
    {
        [, , $template] = $this->pricedProductAndTemplate(
            settings: ['price_display_currency' => 'supplier'],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id]);
        $component->call('downloadPdf')->assertHasNoErrors();
        $token = $component->get('pdfDownloadToken');
        $this->assertIsString($token);

        $claimLock = \Illuminate\Support\Facades\Cache::store('file')
            ->lock('catalog_pdf_download_' . $token . ':claim', 30);
        $this->assertTrue($claimLock->get());
        try {
            $this->actingAs($this->user)
                ->get(route('catalog.pdf.download', ['token' => $token]))
                ->assertStatus(429)
                ->assertHeader('Retry-After', '1');
        } finally {
            $claimLock->release();
        }

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->get(route('catalog.pdf.download', ['token' => $token]))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->get(route('catalog.pdf.download', ['token' => $token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->user)
            ->get(route('catalog.pdf.download', ['token' => $token]))
            ->assertNotFound();
    }

    private function assertPdfDownloadRoute($component, string $action): string
    {
        $component->call($action)->assertHasNoErrors();
        $token = $component->get('pdfDownloadToken');
        $this->assertIsString($token);
        $component->assertRedirect(route('catalog.pdf.download', ['token' => $token]));

        $relativePath = 'catalog-pdf-downloads/' . $token . '.pdf';
        $this->assertTrue(Storage::disk('local')->exists($relativePath));
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($relativePath));

        $response = $this->get(route('catalog.pdf.download', ['token' => $token]));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'attachment',
            strtolower((string) $response->headers->get('Content-Disposition')),
        );
        $this->assertStringEndsWith('.pdf', (string) $response->headers->get('Content-Disposition'));

        return $token;
    }

    private function pricedProductAndTemplate(array $settings, int $tomanPrice, string $appliedCurrency, string $appliedRate): array
    {
        $product = Product::factory()->create([
            'supplier_id' => $this->supplier->id,
            'category_id' => $this->category->id,
            'quantity_tiers' => [
                ['name' => 'کارتن', 'min_quantity' => 1, 'max_quantity' => null],
            ],
        ]);

        $productSupplier = ProductSupplier::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'is_active' => true,
            'price_currency' => 'USD',
            'foreign_price_amount' => '5.1200',
            'foreign_price_rate' => $appliedRate,
            'foreign_price_applied_currency' => $appliedCurrency,
            'foreign_price_applied_setting_revision' => 1,
            'foreign_price_applied_tier_revision' => 1,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $productSupplier->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 0,
            'price' => $tomanPrice,
            'is_active' => true,
        ]);

        $template = CatalogTemplate::query()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Catalog',
            'selected_categories' => [$this->category->id],
            'selected_price_types' => [$this->cashType->id],
            'settings' => array_merge([
                'categories' => 'all',
                'prices' => ['0' => [$this->cashType->id]],
                'show_cover_page' => false,
            ], $settings),
            'config' => [],
        ]);

        return [$product, $productSupplier, $template];
    }

    private function renderPdfTemplateHtml($component, string $view): string
    {
        $preview = $component->instance();
        $catalogSnapshot = Cache::get($preview->catalogDataCacheKey);
        $priceTypesById = PriceType::query()
            ->active()
            ->ordered()
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(static fn (PriceType $priceType): array => [
                (int) $priceType->id => [
                    'code' => (string) $priceType->code,
                    'name' => (string) $priceType->name,
                ],
            ])
            ->all();

        return view($view, [
            'supplierName' => $preview->supplierName,
            'groupedProducts' => $catalogSnapshot['grouped_products'],
            'selectedPriceTypes' => app(CatalogPriceTypeSelection::class)
                ->normalizeForPdf($preview->selectedPriceTypes, $priceTypesById),
            'priceLabels' => $preview->priceLabels,
            'settings' => $preview->settings,
            'template' => $preview->template,
            'separateCategories' => $preview->separateCategories,
            'showCoverPage' => $preview->showCoverPage,
            'supplierLogoUrl' => $preview->supplierLogoUrl,
            'supplierLogoPath' => $preview->supplierLogoPath,
            'forPdf' => true,
            'supplier' => $preview->supplier,
            'contactPhones' => $preview->settings['contact_phones'] ?? [],
            'globalAttribute' => $preview->settings['global_attribute'] ?? null,
            'coverTheme' => $preview->settings['cover_theme'] ?? 'default',
            'catalogFont' => $preview->settings['catalog_font']
                ?? $preview->settings['custom_cover_table']['settings']['catalog_font']
                ?? 'vazir',
            'includeWarranty' => $preview->includeWarranty,
            'displayCurrencyCode' => $preview->displayCurrencyCode,
            'exchangeRateBasis' => $preview->exchangeRateBasis,
            'currencySnapshotWarning' => $preview->currencySnapshotWarning,
            'priceFormatter' => app(CatalogPriceFormatter::class),
            'priceTypesById' => $priceTypesById,
        ])->render();
    }
}
