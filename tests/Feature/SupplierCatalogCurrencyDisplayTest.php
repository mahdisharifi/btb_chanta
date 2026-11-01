<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Supplier\Catalog\Index;
use App\Livewire\Supplier\Catalog\Preview;
use App\Livewire\Supplier\Settings\ManageCheckPeriods;
use App\Models\CatalogTemplate;
use App\Models\Category;
use App\Models\City;
use App\Models\CheckPeriod;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierExchangeRateSetting;
use App\Models\User;
use App\Services\CatalogPriceFormatter;
use App\Services\CatalogBranchPriceRows;
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
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $this->supplier->id,
            'currency' => 'USD',
            'rate_toman' => '280000.0000',
            'manual_rate_toman' => '280000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

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

    public function test_catalog_exchange_rate_basis_resolves_many_suppliers_with_one_settings_query(): void
    {
        $branches = Supplier::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $suppliers = collect([$this->supplier, ...$branches]);

        foreach ($suppliers as $supplier) {
            $supplier->forceFill(['pricing_currency' => 'USD'])->save();
            SupplierExchangeRateSetting::query()->create([
                'supplier_id' => $supplier->id,
                'currency' => 'USD',
                'rate_toman' => '280000.0000',
                'manual_rate_toman' => '280000.0000',
                'auto_update' => false,
                'source' => 'manual',
            ]);
        }

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $resolved = app(\App\Services\CatalogExchangeRateBasisResolver::class)->resolveMany($suppliers);
        $rateSettingQueries = collect($connection->getQueryLog())
            ->filter(static fn (array $query): bool => str_contains($query['query'], 'supplier_exchange_rate_settings'));
        $connection->disableQueryLog();

        $this->assertCount(1, $rateSettingQueries);
        $this->assertCount(3, $resolved);
        $this->assertSame('280000.0000', $resolved->get((int) $this->supplier->id)['rate_value']);
    }

    public function test_supplier_can_save_an_all_branches_catalog_scope(): void
    {
        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->set('name', 'کاتالوگ همه شعب')
            ->set('catalogBranchScope', 'all')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $template = CatalogTemplate::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('name', 'کاتالوگ همه شعب')
            ->sole();

        $this->assertSame('all', $template->settings['catalog_branch_scope']);
        $this->assertNull($template->settings['catalog_branch_supplier_id']);
    }

    public function test_combined_catalog_retains_period_name_when_custom_check_label_is_generic(): void
    {
        $rows = (new CatalogBranchPriceRows())->build(
            [
                9 => [
                    'label' => 'گناوه',
                    'prices' => [
                        '0_2_3' => [
                            'price' => 2500,
                            'tier_index' => 0,
                            'price_type_id' => 2,
                            'check_period_id' => 3,
                            'check_period_days' => 30,
                            'check_period_name' => 'چک ۳۰ روزه',
                            'check_period_label' => 'چکی',
                        ],
                    ],
                ],
            ],
            [0 => [2]],
            [2 => ['code' => 'check', 'name' => 'چکی', 'is_active' => true]],
        );

        $this->assertSame('گناوه - زیرکارتن - چکی · ۳۰ روزه', $rows[0]['label']);
    }

    public function test_all_branches_catalog_keeps_one_product_card_and_uses_each_branch_price_in_preview_and_pdfs(): void
    {
        [$product, $rootOffer, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'toman',
                'catalog_branch_scope' => 'all',
                'prices' => ['0' => [$this->cashType->id]],
            ],
            tomanPrice: 1_400_000,
            appliedCurrency: 'USD',
            appliedRate: '280000.0000',
        );

        $now = now();
        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Iran', 'native_name' => 'ایران', 'iso2' => 'IR', 'is_active' => true,
            'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $tehranProvinceId = DB::table('provinces')->insertGetId([
            'country_id' => $countryId, 'name' => 'Tehran', 'native_name' => 'تهران',
            'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $bushehrProvinceId = DB::table('provinces')->insertGetId([
            'country_id' => $countryId, 'name' => 'Bushehr', 'native_name' => 'بوشهر',
            'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $tehranCityId = DB::table('cities')->insertGetId([
            'province_id' => $tehranProvinceId, 'country_id' => $countryId,
            'name' => 'Tehran', 'native_name' => 'تهران', 'is_active' => true,
            'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $ganavehCityId = DB::table('cities')->insertGetId([
            'province_id' => $bushehrProvinceId, 'country_id' => $countryId,
            'name' => 'Ganaveh', 'native_name' => 'گناوه', 'is_active' => true,
            'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->supplier->update([
            'province_id' => $tehranProvinceId,
            'city_id' => $tehranCityId,
            'province' => 'تهران',
            'city' => 'تهران',
        ]);

        $branch = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'parent_supplier_id' => $this->supplier->id,
            'branch_name' => 'شعبه گناوه',
            'slug' => 'catalog-ganaveh-' . uniqid(),
            'company_name' => 'فروشگاه گناوه',
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
            'pricing_currency' => 'USD',
            'province_id' => $bushehrProvinceId,
            'city_id' => $ganavehCityId,
            'province' => 'بوشهر',
            'city' => 'گناوه',
        ]);
        $branchProduct = Product::factory()->create([
            'supplier_id' => $branch->id,
            'family_product_id' => $product->id,
            'category_id' => $product->category_id,
            'title' => 'عنوان کپی شعبه',
            'is_active' => true,
            'status' => 'active',
        ]);
        $branchOffer = ProductSupplier::query()->create([
            'product_id' => $branchProduct->id,
            'supplier_id' => $branch->id,
            'is_active' => true,
            'price_currency' => 'USD',
            'foreign_price_amount' => '7.5000',
            'foreign_price_rate' => '280000.0000',
            'foreign_price_applied_currency' => 'USD',
            'branch_price_percentage' => 50,
            'foreign_price_applied_setting_revision' => 1,
            'foreign_price_applied_tier_revision' => 1,
        ]);
        ProductPrice::query()->create([
            'product_id' => $branchProduct->id,
            'product_supplier_id' => $branchOffer->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 0,
            'price' => 2_100_000,
            'is_active' => true,
        ]);

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
        foreach ([$this->supplier, $branch] as $checkSupplier) {
            DB::table('supplier_check_period')->insert([
                'supplier_id' => $checkSupplier->id,
                'check_period_id' => $period->id,
                'percentage_increase' => 10,
                'is_active' => true,
                'label' => $checkSupplier->id === $this->supplier->id
                    ? 'چک تهران ۳۰ روزه'
                    : 'چک گناوه ۳۰ روزه',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $primaryScopedPeriod = CheckPeriod::query()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'چک مادر ۶۰ روزه',
            'scope' => 'supplier',
            'days' => 60,
            'label' => 'چک مادر ۶۰ روزه',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        DB::table('supplier_check_period')->insert([
            'supplier_id' => $this->supplier->id,
            'check_period_id' => $primaryScopedPeriod->id,
            'percentage_increase' => 20,
            'is_active' => true,
            'label' => 'چک مادر ۶۰ روزه',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ([[$product, $rootOffer, 1_540_000]] as [$matrixProduct, $matrixOffer, $checkPrice]) {
            ProductPrice::query()->create([
                'product_id' => $matrixProduct->id,
                'product_supplier_id' => $matrixOffer->id,
                'price_type_id' => $checkType->id,
                'tier_index' => 0,
                'check_period_id' => $period->id,
                'price' => $checkPrice,
                'is_active' => true,
            ]);
        }
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'product_supplier_id' => $rootOffer->id,
            'price_type_id' => $checkType->id,
            'tier_index' => 0,
            'check_period_id' => $primaryScopedPeriod->id,
            'price' => 1_680_000,
            'is_active' => true,
        ]);
        $this->assertSame(2_310_000, ProductPrice::query()
            ->where('product_supplier_id', $branchOffer->id)
            ->where('price_type_id', $checkType->id)
            ->where('check_period_id', $period->id)
            ->value('price'), 'The branch check matrix should be derived from the primary check price and branch percentage.');

        $branchScopedPeriod = CheckPeriod::query()->create([
            'supplier_id' => $branch->id,
            'name' => 'چک شعبه ۴۵ روزه',
            'scope' => 'supplier',
            'days' => 45,
            'label' => 'چک شعبه ۴۵ روزه',
            'sort_order' => 3,
            'is_active' => true,
        ]);
        DB::table('supplier_check_period')->insert([
            'supplier_id' => $branch->id,
            'check_period_id' => $branchScopedPeriod->id,
            'percentage_increase' => 5,
            'is_active' => true,
            'label' => 'چک اختصاصی گناوه',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        ProductPrice::query()->create([
            'product_id' => $branchProduct->id,
            'product_supplier_id' => $branchOffer->id,
            'price_type_id' => $checkType->id,
            'tier_index' => 0,
            'check_period_id' => $branchScopedPeriod->id,
            'price' => 2_660_000,
            'is_active' => true,
        ]);

        $availablePeriods = Livewire::actingAs($this->user)
            ->test(Index::class)
            ->set('catalogBranchScope', 'all')
            ->instance()
            ->availableCheckPeriods;
        $this->assertTrue($availablePeriods->contains('id', $primaryScopedPeriod->id));
        $this->assertTrue($availablePeriods->contains('id', $branchScopedPeriod->id));

        $templateSettings = $template->settings;
        $templateSettings['prices'] = ['0' => [$this->cashType->id, $checkType->id]];
        $templateSettings['check_periods'] = ['0' => [$period->id, $primaryScopedPeriod->id, $branchScopedPeriod->id]];
        $template->forceFill(['settings' => $templateSettings])->save();

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('تهران - زیرکارتن')
            ->assertSee('گناوه - زیرکارتن')
            ->assertSee('تهران - زیرکارتن - نقدی')
            ->assertSee('گناوه - زیرکارتن - نقدی')
            ->assertSee('تهران - زیرکارتن - چکی · تهران ۳۰ روزه')
            ->assertSee('گناوه - زیرکارتن - چکی · گناوه ۳۰ روزه')
            ->assertSee('تهران - زیرکارتن - چکی · مادر ۶۰ روزه')
            ->assertSee('گناوه - زیرکارتن - چکی · مادر ۶۰ روزه')
            ->assertSee('گناوه - زیرکارتن - چکی · اختصاصی گناوه')
            ->assertSee('تهران - زیرکارتن - چکی · شعبه ۴۵ روزه')
            ->assertSee('1,400,000')
            ->assertSee('2,100,000')
            ->assertSee('1,540,000')
            ->assertSee('2,310,000')
            ->assertSee('1,680,000')
            ->assertSee('2,520,000')
            ->assertSee('2,660,000')
            ->assertDontSee('عنوان کپی شعبه');

        $preview = $component->instance();
        $snapshot = Cache::get($preview->catalogDataCacheKey);
        $items = $snapshot['grouped_products']['all'];
        $this->assertCount(1, $items, 'Branch copies should produce a single canonical product card.');
        $this->assertSame($product->title, $items[0]['title']);
        $this->assertSame(1_400_000, $items[0]['branch_prices'][$this->supplier->id]['prices']['0_' . $this->cashType->id]['price']);
        $this->assertSame(2_100_000, $items[0]['branch_prices'][$branch->id]['prices']['0_' . $this->cashType->id]['price']);

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($component, $view);
            $this->assertStringContainsString('تهران - زیرکارتن', $html);
            $this->assertStringContainsString('گناوه - زیرکارتن', $html);
            $this->assertStringContainsString('تهران - زیرکارتن - نقدی', $html);
            $this->assertStringContainsString('گناوه - زیرکارتن - نقدی', $html);
            $this->assertStringContainsString('تهران - زیرکارتن - چکی · تهران ۳۰ روزه', $html);
            $this->assertStringContainsString('گناوه - زیرکارتن - چکی · گناوه ۳۰ روزه', $html);
            $this->assertStringContainsString('تهران - زیرکارتن - چکی · مادر ۶۰ روزه', $html);
            $this->assertStringContainsString('گناوه - زیرکارتن - چکی · مادر ۶۰ روزه', $html);
            $this->assertStringContainsString('گناوه - زیرکارتن - چکی · اختصاصی گناوه', $html);
            $this->assertStringContainsString('تهران - زیرکارتن - چکی · شعبه ۴۵ روزه', $html);
            $this->assertStringContainsString('1,400,000', $html);
            $this->assertStringContainsString('2,100,000', $html);
            $this->assertStringContainsString('1,540,000', $html);
            $this->assertStringContainsString('2,310,000', $html);
            $this->assertStringContainsString('1,680,000', $html);
            $this->assertStringContainsString('2,520,000', $html);
            $this->assertStringContainsString('2,660,000', $html);
            $this->assertStringNotContainsString('عنوان کپی شعبه', $html);
            $this->assertSame(1, substr_count($html, $product->title));
        }

        $branchCity = City::query()->findOrFail($ganavehCityId);
        $branchCity->update(['native_name' => 'گناوهٔ تازه']);
        $refreshedForCity = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('گناوهٔ تازه - زیرکارتن');
        $this->assertNotSame($preview->catalogDataCacheKey, $refreshedForCity->instance()->catalogDataCacheKey);
        $branchCity->update(['native_name' => 'گناوه']);

        $this->assertPdfDownloadRoute($component, 'downloadPdf');
        $this->assertPdfDownloadRoute($component, 'downloadPdf2');

        foreach ([$this->supplier, $branch] as $currencySupplier) {
            SupplierExchangeRateSetting::query()->create([
                'supplier_id' => $currencySupplier->id,
                'currency' => 'USD',
                'rate_toman' => '280000.0000',
                'manual_rate_toman' => '280000.0000',
                'auto_update' => false,
                'source' => 'manual',
            ]);
        }

        $createScopedTemplate = function (string $name, string $currency, string $scope, ?int $branchId = null) use ($template): CatalogTemplate {
            $settings = $template->settings;
            $settings['price_display_currency'] = $currency;
            $settings['catalog_branch_scope'] = $scope;
            $settings['catalog_branch_supplier_id'] = $branchId;

            return CatalogTemplate::query()->create([
                'supplier_id' => $this->supplier->id,
                'name' => $name,
                'selected_categories' => $template->selected_categories,
                'selected_price_types' => $template->selected_price_types,
                'settings' => $settings,
                'config' => [],
            ]);
        };

        $foreignAllTemplate = $createScopedTemplate('همه شعب ارزی', 'supplier', 'all');
        $foreignAll = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $foreignAllTemplate->id])
            ->assertSet('currencySnapshotWarning', false)
            ->assertSee('تهران - زیرکارتن')
            ->assertSee('گناوه - زیرکارتن')
            ->assertSee('5 دلار')
            ->assertSee('7.5 دلار')
            ->assertSee('5.5 دلار')
            ->assertSee('8.25 دلار')
            ->assertSee('6 دلار')
            ->assertSee('9 دلار')
            ->assertSee('9.5 دلار');

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($foreignAll, $view);
            $this->assertStringContainsString('تهران - زیرکارتن', $html);
            $this->assertStringContainsString('گناوه - زیرکارتن', $html);
            $this->assertStringContainsString('5 دلار', $html);
            $this->assertStringContainsString('7.5 دلار', $html);
            $this->assertStringContainsString('5.5 دلار', $html);
            $this->assertStringContainsString('8.25 دلار', $html);
            $this->assertStringContainsString('6 دلار', $html);
            $this->assertStringContainsString('9 دلار', $html);
            $this->assertStringContainsString('9.5 دلار', $html);
        }

        $singleTomanTemplate = $createScopedTemplate('یک شعبه تومانی', 'toman', 'single', (int) $branch->id);
        $singleToman = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $singleTomanTemplate->id])
            ->assertSee('2,100,000')
            ->assertSee('2,520,000')
            ->assertSee('2,660,000')
            ->assertDontSee('1,400,000')
            ->assertDontSee('تهران - زیرکارتن')
            ->assertSee('عنوان کپی شعبه');

        $singleForeignTemplate = $createScopedTemplate('یک شعبه ارزی', 'supplier', 'single', (int) $branch->id);
        $singleForeign = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $singleForeignTemplate->id])
            ->assertSet('displayCurrencyCode', 'USD')
            ->assertSee('7.5 دلار')
            ->assertSee('9 دلار')
            ->assertSee('9.5 دلار');

        foreach ([$singleToman, $singleForeign] as $singleComponent) {
            $singlePreview = $singleComponent->instance();
            $singleSnapshot = Cache::get($singlePreview->catalogDataCacheKey);
            $singleItem = $singleSnapshot['grouped_products']['all'][0];
            $this->assertSame(
                2_100_000,
                $singleItem['prices']['0_' . $this->cashType->id]['price'],
                'A selected-branch catalog must use the child branch cash matrix, not the primary price.'
            );
            $this->assertSame(
                2_310_000,
                $singleItem['prices']['0_' . $checkType->id . '_' . $period->id]['price'],
                'A selected-branch catalog must use the child branch check matrix, not the primary price.'
            );
            $this->assertSame(
                2_520_000,
                $singleItem['prices']['0_' . $checkType->id . '_' . $primaryScopedPeriod->id]['price'],
                'A selected branch catalog must retain a check period inherited from the primary supplier.'
            );
            $this->assertSame(
                2_660_000,
                $singleItem['prices']['0_' . $checkType->id . '_' . $branchScopedPeriod->id]['price'],
                'A selected branch catalog must retain its branch-owned check period and label.'
            );
        }

        foreach ([[$singleToman, '2,100,000'], [$singleForeign, '7.5 دلار']] as [$singleComponent, $expectedPrice]) {
            foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
                $html = $this->renderPdfTemplateHtml($singleComponent, $view);
                $this->assertStringContainsString($expectedPrice, $html);
                $this->assertStringNotContainsString('1,400,000', $html);
                $this->assertStringContainsString('عنوان کپی شعبه', $html);
            }
        }

        $this->assertPdfDownloadRoute($singleToman, 'downloadPdf');
        $this->assertPdfDownloadRoute($singleToman, 'downloadPdf2');

        foreach ([
            ['code' => 'CNY', 'rate' => 40_000, 'root_cash' => '35 یوان', 'branch_cash' => '52.5 یوان', 'root_check' => '38.5 یوان', 'branch_check' => '57.75 یوان', 'root_scoped_check' => '42 یوان', 'branch_scoped_check' => '63 یوان', 'branch_custom_check' => '66.5 یوان', 'single' => '52.5 یوان'],
            ['code' => 'AED', 'rate' => 70_000, 'root_cash' => '20 درهم', 'branch_cash' => '30 درهم', 'root_check' => '22 درهم', 'branch_check' => '33 درهم', 'root_scoped_check' => '24 درهم', 'branch_scoped_check' => '36 درهم', 'branch_custom_check' => '38 درهم', 'single' => '30 درهم'],
        ] as $currencyCase) {
            $currency = $currencyCase['code'];
            $rate = (string) $currencyCase['rate'] . '.0000';
            $this->supplier->forceFill(['pricing_currency' => $currency])->save();
            $branch->forceFill(['pricing_currency' => $currency])->save();
            foreach ([$this->supplier, $branch] as $currencySupplier) {
                SupplierExchangeRateSetting::query()->create([
                    'supplier_id' => $currencySupplier->id,
                    'currency' => $currency,
                    'rate_toman' => $rate,
                    'manual_rate_toman' => $rate,
                    'auto_update' => false,
                    'source' => 'manual',
                ]);
            }
            $rootOffer->update([
                'price_currency' => $currency,
                'foreign_price_amount' => number_format(1_400_000 / $currencyCase['rate'], 4, '.', ''),
                'foreign_price_rate' => $rate,
                'foreign_price_applied_currency' => $currency,
            ]);
            $branchOffer->update([
                'price_currency' => $currency,
                'foreign_price_amount' => number_format(2_100_000 / $currencyCase['rate'], 4, '.', ''),
                'foreign_price_rate' => $rate,
                'foreign_price_applied_currency' => $currency,
            ]);

            $allTemplate = $createScopedTemplate("همه شعب {$currency}", 'supplier', 'all');
            $allComponent = Livewire::actingAs($this->user)
                ->test(Preview::class, ['catalog' => $allTemplate->id])
                ->assertSet('displayCurrencyCode', $currency)
                ->assertSet('currencySnapshotWarning', false)
                ->assertSee($currencyCase['root_cash'])
                ->assertSee($currencyCase['branch_cash'])
                ->assertSee($currencyCase['root_check'])
                ->assertSee($currencyCase['branch_check'])
                ->assertSee($currencyCase['root_scoped_check'])
                ->assertSee($currencyCase['branch_scoped_check'])
                ->assertSee($currencyCase['branch_custom_check']);

            $singleTemplate = $createScopedTemplate("یک شعبه {$currency}", 'supplier', 'single', (int) $branch->id);
            $singleComponent = Livewire::actingAs($this->user)
                ->test(Preview::class, ['catalog' => $singleTemplate->id])
                ->assertSet('displayCurrencyCode', $currency)
                ->assertSee($currencyCase['single'])
                ->assertSee($currencyCase['branch_scoped_check'])
                ->assertSee($currencyCase['branch_custom_check']);

            foreach ([[$allComponent, true], [$singleComponent, false]] as [$currencyComponent, $combined]) {
                foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
                    $html = $this->renderPdfTemplateHtml($currencyComponent, $view);
                    if ($combined) {
                        $this->assertStringContainsString('تهران - زیرکارتن', $html);
                        $this->assertStringContainsString('گناوه - زیرکارتن', $html);
                        $this->assertStringContainsString($currencyCase['root_cash'], $html);
                        $this->assertStringContainsString($currencyCase['branch_cash'], $html);
                        $this->assertStringContainsString($currencyCase['root_check'], $html);
                        $this->assertStringContainsString($currencyCase['branch_check'], $html);
                    } else {
                        $this->assertStringContainsString($currencyCase['single'], $html);
                        $this->assertStringNotContainsString('1,400,000', $html);
                        $this->assertStringContainsString('عنوان کپی شعبه', $html);
                    }
                }
            }
        }
    }

    public function test_all_toman_branch_catalog_exports_both_pdfs_without_exchange_rates(): void
    {
        $this->supplier->forceFill(['pricing_currency' => 'TOM'])->save();
        [$product, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'catalog_branch_scope' => 'all',
                'prices' => 'all',
            ],
            tomanPrice: 1_200_000,
            appliedCurrency: 'TOM',
            appliedRate: '1.0000',
        );

        $branch = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'parent_supplier_id' => $this->supplier->id,
            'branch_name' => 'شعبهٔ گناوه',
            'slug' => 'toman-catalog-branch-' . uniqid(),
            'company_name' => 'فروشگاه گناوه',
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
            'pricing_currency' => 'TOM',
            'city' => 'گناوه',
        ]);
        $branchProduct = Product::factory()->create([
            'supplier_id' => $branch->id,
            'family_product_id' => $product->id,
            'category_id' => $product->category_id,
            'title' => 'محصول کپی شعبهٔ گناوه',
            'is_active' => true,
            'status' => 'active',
        ]);
        $branchOffer = ProductSupplier::query()->create([
            'product_id' => $branchProduct->id,
            'supplier_id' => $branch->id,
            'is_active' => true,
            'price_currency' => 'TOM',
            'branch_price_percentage' => -5,
        ]);
        ProductPrice::query()->create([
            'product_id' => $branchProduct->id,
            'product_supplier_id' => $branchOffer->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 0,
            'price' => 1_140_000,
            'is_active' => true,
        ]);

        $this->assertSame(0, SupplierExchangeRateSetting::query()
            ->whereIn('supplier_id', [$this->supplier->id, $branch->id])
            ->count());

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSet('displayCurrencyCode', 'TOM')
            ->assertSet('currencySnapshotWarning', false)
            ->assertSee('1,200,000')
            ->assertSee('1,140,000');

        foreach ([
            ['action' => 'downloadPdf', 'view' => 'pdf.supplier-catalog'],
            ['action' => 'downloadPdf2', 'view' => 'pdf.supplier-catalog2'],
        ] as $pdfCase) {
            $this->assertPdfDownloadRoute($component, $pdfCase['action']);
            $html = $this->renderPdfTemplateHtml($component, $pdfCase['view']);
            $this->assertStringContainsString('1,200,000', $html);
            $this->assertStringContainsString('1,140,000', $html);
            $this->assertStringContainsString('گناوه', $html);
        }
    }

    public function test_catalog_branch_selector_and_combined_export_exclude_unverified_branches(): void
    {
        [$product, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'toman',
                'catalog_branch_scope' => 'all',
                'prices' => ['0' => [$this->cashType->id]],
            ],
            tomanPrice: 1_400_000,
            appliedCurrency: 'USD',
            appliedRate: '280000.0000',
        );
        $unverifiedBranch = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'parent_supplier_id' => $this->supplier->id,
            'branch_name' => 'شعبه تأییدنشده',
            'slug' => 'catalog-unverified-branch-' . uniqid(),
            'is_active' => true,
            'is_verified' => false,
            'verification_status' => 'pending',
            'pricing_currency' => 'USD',
        ]);
        $branchProduct = Product::factory()->create([
            'supplier_id' => $unverifiedBranch->id,
            'family_product_id' => $product->id,
            'category_id' => $product->category_id,
            'title' => 'محصول شعبه تأییدنشده',
            'is_active' => true,
            'status' => 'active',
        ]);
        $branchOffer = ProductSupplier::query()->create([
            'product_id' => $branchProduct->id,
            'supplier_id' => $unverifiedBranch->id,
            'is_active' => true,
        ]);
        ProductPrice::query()->create([
            'product_id' => $branchProduct->id,
            'product_supplier_id' => $branchOffer->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 0,
            'price' => 1_000_000,
            'is_active' => true,
        ]);

        $selection = Livewire::actingAs($this->user)
            ->test(Index::class)
            ->call('createNew')
            ->set('name', 'کاتالوگ شعبه تأییدشده')
            ->set('catalogBranchScope', 'single')
            ->set('catalogBranchSupplierId', $unverifiedBranch->id)
            ->call('saveTemplate')
            ->assertHasErrors('catalogBranchSupplierId')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('catalogBranchSupplierId-error', false);

        $this->assertNotNull($selection);
        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertDontSee('شعبه تأییدنشده - زیرکارتن')
            ->assertDontSee('محصول شعبه تأییدنشده');
        $preview = $component->instance();
        $snapshot = Cache::get($preview->catalogDataCacheKey);
        $items = $snapshot['grouped_products']['all'];
        $this->assertCount(1, $items);
        $this->assertSame([$this->supplier->id], array_map('intval', array_keys($items[0]['branch_prices'])));
        $this->assertArrayNotHasKey($unverifiedBranch->id, $items[0]['branch_prices']);
    }

    public function test_catalog_all_branch_scope_can_save_without_a_branch_id(): void
    {
        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->set('name', 'کاتالوگ همه شعب بدون شناسه')
            ->set('catalogBranchScope', 'all')
            ->set('catalogBranchSupplierId', null)
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $settings = CatalogTemplate::query()
            ->where('name', 'کاتالوگ همه شعب بدون شناسه')
            ->sole()
            ->settings;

        $this->assertSame('all', $settings['catalog_branch_scope']);
        $this->assertNull($settings['catalog_branch_supplier_id']);
    }

    public function test_catalog_branch_scope_validation_error_is_associated_with_the_radio_group(): void
    {
        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->call('createNew')
            ->set('name', 'کاتالوگ با محدوده نامعتبر')
            ->set('catalogBranchScope', 'invalid')
            ->call('saveTemplate')
            ->assertHasErrors('catalogBranchScope')
            ->assertSee('catalogBranchScope-error', false);
    }

    public function test_all_branches_catalog_warns_and_blocks_pdf_when_branch_currency_rates_diverge(): void
    {
        [$product, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'catalog_branch_scope' => 'all',
                'prices' => ['0' => [$this->cashType->id]],
            ],
            tomanPrice: 1_400_000,
            appliedCurrency: 'USD',
            appliedRate: '280000.0000',
        );
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $this->supplier->id,
            'currency' => 'USD',
            'rate_toman' => '280000.0000',
            'manual_rate_toman' => '280000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);
        $branch = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'parent_supplier_id' => $this->supplier->id,
            'branch_name' => 'شعبه دوم',
            'slug' => 'currency-branch-' . uniqid(),
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
            'pricing_currency' => 'USD',
        ]);
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $branch->id,
            'currency' => 'USD',
            'rate_toman' => '280000.0000',
            'manual_rate_toman' => '280000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSet('displayCurrencyCode', 'USD');

        $branch->forceFill(['pricing_currency' => 'CNY'])->save();
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $branch->id,
            'currency' => 'CNY',
            'rate_toman' => '40000.0000',
            'manual_rate_toman' => '40000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

        $warning = 'ارز پایه و نرخ معتبر همهٔ شعبه‌های انتخاب‌شده باید یکسان باشد';
        $actionableMessage = 'خروجی ارزی ممکن نیست';
        $component->call('clearCatalogCache')
            ->assertSet('displayCurrencyCode', null)
            ->assertSee($warning);

        $component->call('downloadPdf2')
            ->assertHasErrors('pdfExport')
            ->assertSee($actionableMessage);
    }

    public function test_catalog_formats_copied_branch_prices_using_the_source_exchange_snapshot(): void
    {
        [$product, $rootOffer, $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'catalog_branch_scope' => 'all',
                'prices' => ['0' => [$this->cashType->id]],
            ],
            tomanPrice: 1_400_000,
            appliedCurrency: 'USD',
            appliedRate: '280000.0000',
        );

        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $this->supplier->id,
            'currency' => 'USD',
            'rate_toman' => '280000.0000',
            'manual_rate_toman' => '280000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

        $branch = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'parent_supplier_id' => $this->supplier->id,
            'branch_name' => 'شعبه گناوه',
            'slug' => 'derived-currency-branch-' . uniqid(),
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
            'pricing_currency' => 'USD',
        ]);
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $branch->id,
            'currency' => 'USD',
            'rate_toman' => '280000.0000',
            'manual_rate_toman' => '280000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

        $branchProduct = Product::factory()->create([
            'supplier_id' => $branch->id,
            'family_product_id' => $product->id,
            'category_id' => $product->category_id,
            'is_active' => true,
            'status' => 'active',
        ]);
        $branchOffer = ProductSupplier::query()->create([
            'product_id' => $branchProduct->id,
            'supplier_id' => $branch->id,
            'is_active' => true,
            'price_currency' => 'USD',
            'branch_price_percentage' => 50,
        ]);
        $rootCashPrice = ProductPrice::query()
            ->where('product_supplier_id', $rootOffer->id)
            ->where('price_type_id', $this->cashType->id)
            ->firstOrFail();
        ProductPrice::query()->create([
            'product_id' => $branchProduct->id,
            'product_supplier_id' => $branchOffer->id,
            'branch_source_price_id' => $rootCashPrice->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 0,
            'price' => 2_100_000,
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSet('displayCurrencyCode', 'USD')
            ->assertSet('currencySnapshotWarning', false)
            ->assertSee('5 دلار')
            ->assertSee('7.5 دلار');

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $this->assertStringContainsString('7.5 دلار', $this->renderPdfTemplateHtml($component, $view));
        }

        $branch->forceFill(['pricing_currency' => 'CNY'])->save();
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $branch->id,
            'currency' => 'CNY',
            'rate_toman' => '40000.0000',
            'manual_rate_toman' => '40000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);
        $singleSettings = $template->settings;
        $singleSettings['catalog_branch_scope'] = 'single';
        $singleSettings['catalog_branch_supplier_id'] = (int) $branch->id;
        $singleTemplate = CatalogTemplate::query()->create([
            'supplier_id' => $this->supplier->id,
            'name' => 'کاتالوگ ارزی شعبه',
            'selected_categories' => $template->selected_categories,
            'selected_price_types' => $template->selected_price_types,
            'settings' => $singleSettings,
            'config' => [],
        ]);
        $singleBranchComponent = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $singleTemplate->id])
            ->assertSet('displayCurrencyCode', 'CNY')
            ->assertSet('currencySnapshotWarning', false)
            ->assertSee('52.5 یوان');

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $this->assertStringContainsString('52.5 یوان', $this->renderPdfTemplateHtml($singleBranchComponent, $view));
        }

        $singleBranchCacheKey = $singleBranchComponent->instance()->catalogDataCacheKey;
        $rootUsdSetting = SupplierExchangeRateSetting::query()
            ->where('supplier_id', $this->supplier->id)
            ->where('currency', 'USD')
            ->firstOrFail();
        $rootUsdSetting->forceFill([
            'manual_rate_toman' => '290000.0000',
        ])->save();

        $staleSourceComponent = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $singleTemplate->id])
            ->assertSet('currencySnapshotWarning', true)
            ->assertSee('---')
            ->assertDontSee('52.5 یوان');
        $this->assertNotSame($singleBranchCacheKey, $staleSourceComponent->instance()->catalogDataCacheKey);

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($staleSourceComponent, $view);
            $this->assertStringNotContainsString('52.5 یوان', $html);
            $this->assertStringContainsString('---', $html);
        }
    }

    public function test_toman_all_branch_catalog_checks_each_price_against_its_own_currency_basis(): void
    {
        [$product, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'toman',
                'catalog_branch_scope' => 'all',
                'prices' => ['0' => [$this->cashType->id]],
            ],
            tomanPrice: 1_400_000,
            appliedCurrency: 'USD',
            appliedRate: '280000.0000',
        );
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $this->supplier->id,
            'currency' => 'USD',
            'rate_toman' => '280000.0000',
            'manual_rate_toman' => '280000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

        $branch = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'parent_supplier_id' => $this->supplier->id,
            'branch_name' => 'شعبه دوم',
            'slug' => 'toman-currency-branch-' . uniqid(),
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
            'pricing_currency' => 'CNY',
        ]);
        SupplierExchangeRateSetting::query()->create([
            'supplier_id' => $branch->id,
            'currency' => 'CNY',
            'rate_toman' => '40000.0000',
            'manual_rate_toman' => '40000.0000',
            'auto_update' => false,
            'source' => 'manual',
        ]);

        $branchProduct = Product::factory()->create([
            'supplier_id' => $branch->id,
            'family_product_id' => $product->id,
            'category_id' => $product->category_id,
            'is_active' => true,
            'status' => 'active',
        ]);
        $branchOffer = ProductSupplier::query()->create([
            'product_id' => $branchProduct->id,
            'supplier_id' => $branch->id,
            'is_active' => true,
            'price_currency' => 'CNY',
            'foreign_price_amount' => '52.5000',
            'foreign_price_rate' => '40000.0000',
            'foreign_price_applied_currency' => 'CNY',
            'foreign_price_applied_setting_revision' => 1,
            'foreign_price_applied_tier_revision' => 1,
        ]);
        ProductPrice::query()->create([
            'product_id' => $branchProduct->id,
            'product_supplier_id' => $branchOffer->id,
            'price_type_id' => $this->cashType->id,
            'tier_index' => 0,
            'price' => 2_100_000,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSet('displayCurrencyCode', 'TOM')
            ->assertSet('currencySnapshotWarning', false)
            ->assertSee('1,400,000')
            ->assertSee('2,100,000');
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
            ->assertSee('ارز پایهٔ کاتالوگ قابل استفاده نیست')
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

        Livewire::actingAs($this->user)
            ->test(Index::class)
            ->set('name', 'کاتالوگ پایه تومان')
            ->set('priceDisplayCurrency', 'supplier')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $this->assertSame(
            'supplier',
            CatalogTemplate::query()->where('name', 'کاتالوگ پایه تومان')->sole()->settings['price_display_currency'],
        );
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

        $period->update([
            'name' => 'چک ۶۰ روزه',
            'label' => 'چک ۶۰ روزه',
        ]);
        DB::table('supplier_check_period')
            ->where('supplier_id', $this->supplier->id)
            ->where('check_period_id', $period->id)
            ->update(['label' => null, 'updated_at' => now()]);

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
        [$product, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'supplier',
                'prices' => 'all',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'CNY',
            appliedRate: '25000.0000',
        );

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSee('نرخ ذخیره‌شدهٔ بعضی کالاها با نرخ تنظیم‌شده یکسان نیست یا معتبر نیست')
            ->assertSee('---')
            ->assertDontSee('5.12 دلار');

        $snapshot = Cache::get($component->instance()->catalogDataCacheKey);
        $productSnapshot = collect($snapshot['grouped_products'])->flatten(1)->firstWhere('id', $product->id);
        $this->assertFalse($productSnapshot['prices']['0_' . $this->cashType->id]['catalog_display_valid']);

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($component, $view);
            $this->assertStringNotContainsString('5.12 دلار', $html);
            $this->assertStringContainsString('---', $html);
        }
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

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($component, $view);
            $this->assertStringNotContainsString('نرخ دلار', $html);
            $this->assertStringNotContainsString('نرخ یوان', $html);
            $this->assertStringNotContainsString('نرخ درهم', $html);
        }

        $productSupplier = ProductSupplier::query()->sole();
        $this->supplier->forceFill(['pricing_currency' => 'CNY'])->save();
        SupplierExchangeRateSetting::query()->updateOrCreate(
            [
                'supplier_id' => $this->supplier->id,
                'currency' => 'CNY',
            ],
            [
                'rate_toman' => '25000.0000',
                'manual_rate_toman' => '25000.0000',
                'auto_update' => false,
                'source' => 'manual',
            ],
        );
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

    public function test_toman_catalog_hides_foreign_exchange_basis_while_retaining_it_for_snapshot_checks(): void
    {
        $this->supplier->forceFill(['pricing_currency' => 'USD'])->save();

        [, , $template] = $this->pricedProductAndTemplate(
            settings: [
                'price_display_currency' => 'toman',
                'show_cover_page' => true,
                'cover_theme' => 'default',
            ],
            tomanPrice: 1382400,
            appliedCurrency: 'USD',
            appliedRate: '270000.0000',
        );

        $component = Livewire::actingAs($this->user)
            ->test(Preview::class, ['catalog' => $template->id])
            ->assertSet('displayCurrencyCode', 'TOM')
            ->assertDontSee('نرخ دلار')
            ->assertSee('1,382,400');

        $this->assertSame('USD', $component->get('exchangeRateBasis')['currency']);
        $this->assertFalse($component->get('currencySnapshotWarning'));

        foreach (['pdf.supplier-catalog', 'pdf.supplier-catalog2'] as $view) {
            $html = $this->renderPdfTemplateHtml($component, $view);
            $this->assertStringNotContainsString('نرخ دلار', $html);
            $this->assertStringContainsString('1,382,400', $html);
        }

        $this->assertPdfDownloadRoute($component, 'downloadPdf');
        $this->assertPdfDownloadRoute($component, 'downloadPdf2');
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
        SupplierExchangeRateSetting::query()->updateOrCreate(
            [
                'supplier_id' => $this->supplier->id,
                'currency' => 'CNY',
            ],
            [
                'rate_toman' => '25000.0000',
                'manual_rate_toman' => '25000.0000',
                'auto_update' => false,
                'source' => 'manual',
            ],
        );
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
        $supplierCurrency = strtoupper((string) $this->supplier->pricing_currency);
        if (($settings['catalog_branch_scope'] ?? 'single') !== 'all'
            && in_array($supplierCurrency, ['USD', 'CNY', 'AED'], true)) {
            SupplierExchangeRateSetting::query()->firstOrCreate(
                [
                    'supplier_id' => $this->supplier->id,
                    'currency' => $supplierCurrency,
                ],
                [
                    'rate_toman' => $appliedRate,
                    'manual_rate_toman' => $appliedRate,
                    'auto_update' => false,
                    'source' => 'manual',
                ],
            );
        }

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
            'price_currency' => $appliedCurrency,
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
            'exchangeRateBasis' => $preview->displayCurrencyCode === 'TOM'
                ? null
                : $preview->exchangeRateBasis,
            'currencySnapshotWarning' => $preview->currencySnapshotWarning,
            'priceFormatter' => app(CatalogPriceFormatter::class),
            'catalogBranchPriceRows' => app(\App\Services\CatalogBranchPriceRows::class),
            'branchAwareCatalog' => !$preview->isAdmin
                && ($preview->settings['catalog_branch_scope'] ?? 'single') === 'all',
            'priceTypesById' => $priceTypesById,
        ])->render();
    }
}
