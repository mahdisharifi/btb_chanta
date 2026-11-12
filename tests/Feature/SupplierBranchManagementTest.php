<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Helpers\TierPriceUpdater;
use App\Http\Controllers\Supplier\BranchSwitchController;
use App\Http\Controllers\Web\ProductPriceSnapshotController;
use App\Jobs\BroadcastSupplierProductPrices;
use App\Jobs\InvalidateSupplierBranchPublicCaches;
use App\Livewire\Supplier\Branches\ManageBranches;
use App\Livewire\Supplier\Branches\ProductTransfers;
use App\Livewire\Supplier\Products\Edit;
use App\Livewire\Supplier\Products\EditPrice;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\CheckPeriod;
use App\Models\Product;
use App\Models\ProductGallery;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\PriceVisibilitySetting;
use App\Models\PriceType;
use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use App\Models\SupplierTierDiscount;
use App\Models\User;
use App\Services\SupplierBranchTransferService;
use App\Services\SupplierBranchProductCopyService;
use App\Services\SupplierBranchPriceSyncService;
use App\Services\SupplierBranchPrimaryService;
use App\Services\SupplierBranchPublicCacheInvalidator;
use App\Services\CatalogSnapshotRevisionService;
use App\Services\PriceVisibilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SupplierBranchManagementTest extends TestCase
{
    private User $user;
    private Supplier $primary;
    private Category $category;
    private Product $primaryProduct;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'branch_management',
            'database.connections.branch_management' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        DB::purge('branch_management');
        $this->createSchemaBeforeBranchMigration();

        // Production runs the catalog snapshot revision migrations before branch
        // observers invalidate snapshots. Keep this isolated schema aligned.
        $catalogRevisionMigration = require database_path('migrations/2026_10_29_090000_create_catalog_snapshot_revisions_table.php');
        $catalogRevisionMigration->up();

        $this->user = User::factory()->create();
        $this->primary = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'company_name' => 'فروشگاه اصلی',
            'brand_name' => 'برند اصلی',
            'slug' => 'primary-store',
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $this->category = Category::create([
            'name' => 'دسته اصلی',
            'slug' => 'main-category',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $legacyProductId = DB::table('products')->insertGetId([
            'supplier_id' => $this->primary->id,
            'category_id' => $this->category->id,
            'title' => 'محصول قدیمی',
            'slug' => 'legacy-product',
            'sku' => 'LEGACY-001',
            'legacy_id' => 'legacy-77',
            'specifications' => json_encode(['material' => 'آهن']),
            'is_active' => true,
            'is_featured' => false,
            'status' => ProductStatus::ACTIVE->value,
            'view_count' => 19,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchMigration = require database_path('migrations/2026_10_28_120000_add_supplier_branch_support.php');
        $branchMigration->up();
        $precisionMigration = require database_path('migrations/2026_10_30_120000_expand_supplier_branch_price_percentage_precision.php');
        $precisionMigration->up();
        (require database_path('migrations/2026_11_11_130000_limit_branch_name_uniqueness_to_active_suppliers.php'))->up();
        (require database_path('migrations/2026_11_11_160000_add_branch_pricing_mode_to_suppliers.php'))->up();
        $this->primaryProduct = Product::findOrFail($legacyProductId);

        $this->createSupportingTables();
        request()->setLaravelSession(app('session')->driver());
        Storage::fake('public');
        Queue::fake();
    }

    public function test_migration_backfills_existing_products_as_their_own_family_root(): void
    {
        $this->assertSame($this->primaryProduct->id, $this->primaryProduct->family_product_id);
        $this->assertSame('legacy-77', $this->primaryProduct->legacy_id);
    }

    public function test_percentage_precision_migration_refuses_a_lossy_rollback(): void
    {
        $offer = $this->offer($this->primaryProduct, $this->primary);
        DB::table('product_supplier')
            ->where('id', $offer->id)
            ->update(['branch_price_percentage' => '2.192106123456']);
        $migration = require database_path('migrations/2026_10_30_120000_expand_supplier_branch_price_percentage_precision.php');

        try {
            $migration->down();
            $this->fail('The precision migration rounded a twelve-place percentage down to six places.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('more than six decimal places', $exception->getMessage());
        }

        $this->assertSame('2.192106123456', (string) $offer->fresh()->branch_price_percentage);
    }

    public function test_migration_rollback_refuses_to_drop_surviving_branch_product_or_price_links(): void
    {
        $copySupplier = Supplier::factory()->create([
            'user_id' => $this->user->id,
            'slug' => 'branch-copy-rollback-guard-store',
            'is_active' => true,
        ]);
        $branchProductId = DB::table('products')->insertGetId([
            'supplier_id' => $copySupplier->id,
            'category_id' => $this->category->id,
            'title' => 'نسخه شعبه',
            'slug' => 'branch-copy-rollback-guard',
            'sku' => 'BRANCH-ROLLBACK-1',
            'family_product_id' => $this->primaryProduct->id,
            'is_active' => true,
            'status' => ProductStatus::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_10_28_120000_add_supplier_branch_support.php');

        try {
            $migration->down();
            $this->fail('Rollback discarded a surviving branch product link.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Branch product links exist', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('products', 'family_product_id'));
        DB::table('products')->where('id', $branchProductId)->delete();
        DB::table('product_prices')->insert([
            'product_id' => $this->primaryProduct->id,
            'branch_source_price_id' => 987654,
            'price' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $migration->down();
            $this->fail('Rollback discarded a surviving branch price link.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Branch price snapshots exist', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('product_prices', 'branch_source_price_id'));
    }

    public function test_transfer_seeding_creates_one_request_per_family_and_prefers_the_owned_product(): void
    {
        $otherOwner = User::factory()->create();
        $otherSupplier = Supplier::factory()->create([
            'user_id' => $otherOwner->id,
            'slug' => 'other-family-owner',
            'is_active' => true,
        ]);
        $alternate = $this->primaryProduct->replicate([
            'id', 'legacy_id', 'slug', 'sku', 'supplier_id', 'family_product_id', 'created_at', 'updated_at', 'deleted_at',
        ]);
        $alternate->supplier_id = $otherSupplier->id;
        $alternate->family_product_id = $this->primaryProduct->id;
        $alternate->legacy_id = null;
        $alternate->slug = 'alternate-family-copy';
        $alternate->sku = 'ALT-FAMILY-001';
        $alternate->save();
        $this->offer($alternate, $this->primary);

        $branch = $this->makeBranch('شعبه بدون تکرار');
        $this->seedTransfers($branch);

        $transfers = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->get();

        $this->assertCount(1, $transfers);
        $this->assertSame($this->primaryProduct->id, (int) $transfers->sole()->source_product_id);
    }

    public function test_livewire_branch_creation_adds_the_same_product_to_its_sales_pivot(): void
    {
        $this->addSourceProductDetails();
        $this->authorizeSupplierUserForTest();
        $this->actingAs($this->user);
        $location = DB::table('cities')->first();

        Livewire::test(ManageBranches::class)
            ->set('branchName', 'شعبه غرب')
            ->set('branchProvinceIds.new', $location->province_id)
            ->set('branchCityIds.new', $location->id)
            ->set('newBranchAddress', 'New branch address')
            ->set('newBranchPostalCode', '1234567890')
            ->call('createBranch')
            ->assertHasNoErrors()
            ->assertRedirect(route('supplier.branches.transfers'));

        $branch = Supplier::query()->where('parent_supplier_id', $this->primary->id)->firstOrFail();
        $this->assertSame($this->primary->user_id, $branch->user_id);
        $this->assertSame($branch->id, (int) session('supplier.active_branch_id'));
        $this->assertSame((int) $location->id, (int) $branch->city_id);
        $this->assertSame('New branch address', $branch->address);
        $this->assertSame('1234567890', $branch->postal_code);

        $tehran = $this->locationIds('تهران');
        $shiraz = $this->locationIds('شیراز');
        Livewire::actingAs($this->user)->test(ManageBranches::class)
            ->set('branchProvinceIds.' . $this->primary->id, $tehran['province_id'])
            ->set('branchCityIds.' . $this->primary->id, $tehran['city_id'])
            ->set('branchAddresses.' . $this->primary->id, 'خیابان نمونه')
            ->set('branchPostalCodes.' . $this->primary->id, '1234567890')
            ->call('saveLocation', $this->primary->id)
            ->set('branchProvinceIds.' . $branch->id, $shiraz['province_id'])
            ->set('branchCityIds.' . $branch->id, $shiraz['city_id'])
            ->call('saveLocation', $branch->id)
            ->assertHasNoErrors()
            ->assertDispatched('notify')
            ->assertSee('شهر: تهران')
            ->assertSee('شهر: شیراز');
        $this->assertSame('تهران', $this->primary->fresh()->city);
        $this->assertSame('شیراز', $branch->fresh()->city);
        $this->assertSame($tehran['province_id'], (int) $this->primary->fresh()->province_id);
        $this->assertSame($tehran['city_id'], (int) $this->primary->fresh()->city_id);
        $this->assertSame('خیابان نمونه', $this->primary->fresh()->address);
        $this->assertSame('1234567890', $this->primary->fresh()->postal_code);

        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->where('source_product_id', $this->primaryProduct->id)
            ->firstOrFail();
        $this->assertSame(SupplierBranchProductTransfer::PENDING, $transfer->status);

        $this->withSession(['supplier.active_branch_id' => $branch->id]);
        request()->setLaravelSession(app('session')->driver());
        Livewire::test(ProductTransfers::class)
            ->set('tab', 'pending')
            ->assertSee($this->primaryProduct->title)
            ->assertDontSee('products/source.webp')
            ->call('accept', $transfer->id)
            ->assertDispatched('notify');


        $destination = Product::findOrFail($transfer->fresh()->destination_product_id);
        $this->assertSame($this->primaryProduct->id, $destination->id);
        $this->assertSame($this->primary->id, $destination->supplier_id);
        $this->assertSame('independent', $branch->branch_pricing_mode);
        $this->assertSame(0, Product::where('supplier_id', $branch->id)->count());
        $offer = $this->offer($destination, $branch);
        $this->assertFalse((bool) $offer->is_active);
        $this->assertSame(0, $offer->prices()->count());
        $this->assertSame(SupplierBranchProductTransfer::ACCEPTED, $transfer->fresh()->status);
        $sameDestination = app(SupplierBranchTransferService::class)->accept($this->primary, $branch, $transfer->id);
        $this->assertSame($destination->id, $sameDestination->id);
        $this->assertSame(1, $branch->products()->count());
        Livewire::test(Edit::class, ['product' => $destination])->assertForbidden();
        Livewire::test(EditPrice::class, ['product' => $destination])
            ->assertSet('isBranchCopy', false)
            ->assertSee('مدیریت ماتریس قیمت');
    }

    public function test_branch_name_validation_error_is_associated_with_its_input(): void
    {
        $this->authorizeSupplierUserForTest();

        Livewire::actingAs($this->user)
            ->test(ManageBranches::class)
            ->set('branchName', '')
            ->call('createBranch')
            ->assertHasErrors('branchName')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('branch-name-error', false);
    }

    public function test_branch_price_save_requires_active_verified_branch_and_primary_accounts(): void
    {
        $this->authorizeSupplierUserForTest();
        $this->addSourceProductDetails();
        $branch = $this->makeBranch('شعبه با کنترل دسترسی قیمت');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = $this->acceptLegacyCopy($this->primary, $branch, $transfer->id);
        $branchOffer = $this->offer($branchProduct, $branch);

        $this->actingAs($this->user);
        $this->withSession(['supplier.active_branch_id' => $branch->id]);
        request()->setLaravelSession(app('session')->driver());

        $this->primary->update(['is_active' => false]);
        Livewire::test(EditPrice::class, ['product' => $branchProduct])
            ->set('branchPricePercentage', '25')
            ->call('save')
            ->assertStatus(403);

        $this->assertSame(0.0, (float) $branchOffer->fresh()->branch_price_percentage);
        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $branchProduct->id)->value('price'));

        $this->primary->update(['is_active' => true]);
        $branch->update(['is_verified' => false]);
        Livewire::test(EditPrice::class, ['product' => $branchProduct])
            ->set('branchPricePercentage', '25')
            ->call('save')
            ->assertStatus(403);

        $this->assertSame(0.0, (float) $branchOffer->fresh()->branch_price_percentage);
        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $branchProduct->id)->value('price'));
    }

    public function test_supplier_location_update_invalidates_city_cache_for_old_and_new_provinces(): void
    {
        $this->primary->forceFill(['province' => 'تهران', 'city' => 'تهران'])->saveQuietly();
        Cache::put('web:suppliers:cities:تهران', ['تهران']);
        Cache::put('web:suppliers:cities:یزد', ['یزد']);

        $this->primary->update(['province' => 'یزد', 'city' => 'یزد']);

        $this->assertNull(Cache::get('web:suppliers:cities:تهران'));
        $this->assertNull(Cache::get('web:suppliers:cities:یزد'));
    }

    public function test_supplier_profile_update_commits_and_queues_cache_retry_when_after_commit_clear_fails(): void
    {
        $this->primary->forceFill(['province' => 'تهران', 'city' => 'تهران'])->saveQuietly();
        config(['queue.default' => 'sync']);
        $invalidator = \Mockery::mock(SupplierBranchPublicCacheInvalidator::class);
        $invalidator->shouldReceive('invalidate')->once()->with((int) $this->primary->id)
            ->andThrow(new RuntimeException('cache backend unavailable'));
        app()->instance(SupplierBranchPublicCacheInvalidator::class, $invalidator);

        $this->primary->update(['province' => 'یزد', 'city' => 'یزد']);

        $this->assertSame('یزد', $this->primary->fresh()->province);
        Queue::assertPushed(InvalidateSupplierBranchPublicCaches::class, fn ($job): bool =>
            $job->supplierIds === [(int) $this->primary->id]
            && $job->provinceNames === ['تهران', 'یزد']
            && $job->clearGeography
            && ! $job->refreshRevisions
            && $job->connection === 'database'
        );
    }

    public function test_branch_location_rejects_a_city_outside_the_selected_province(): void
    {
        $this->authorizeSupplierUserForTest();
        $tehran = $this->locationIds('تهران');
        $shiraz = $this->locationIds('شیراز');

        Livewire::actingAs($this->user)
            ->test(ManageBranches::class)
            ->set('branchProvinceIds.' . $this->primary->id, $tehran['province_id'])
            ->set('branchCityIds.' . $this->primary->id, $shiraz['city_id'])
            ->call('saveLocation', $this->primary->id)
            ->assertHasErrors(['branchCityIds.' . $this->primary->id => 'exists']);

        $this->assertNull($this->primary->fresh()->province_id);
        $this->assertNull($this->primary->fresh()->city_id);
    }

    public function test_branch_location_rejects_inactive_cities_and_an_inactive_iran_record(): void
    {
        $this->authorizeSupplierUserForTest();
        $tehran = $this->locationIds('تهران');
        DB::table('cities')->where('id', $tehran['city_id'])->update(['is_active' => false]);

        Livewire::actingAs($this->user)
            ->test(ManageBranches::class)
            ->set('branchProvinceIds.' . $this->primary->id, $tehran['province_id'])
            ->set('branchCityIds.' . $this->primary->id, $tehran['city_id'])
            ->call('saveLocation', $this->primary->id)
            ->assertHasErrors(['branchCityIds.' . $this->primary->id => 'exists']);

        $this->assertNull($this->primary->fresh()->city_id);

        DB::table('cities')->where('id', $tehran['city_id'])->update(['is_active' => true]);
        DB::table('countries')->where('iso2', 'IR')->update(['is_active' => false]);

        Livewire::actingAs($this->user)
            ->test(ManageBranches::class)
            ->set('branchProvinceIds.' . $this->primary->id, $tehran['province_id'])
            ->set('branchCityIds.' . $this->primary->id, $tehran['city_id'])
            ->call('saveLocation', $this->primary->id)
            ->assertHasErrors(['branchProvinceIds.' . $this->primary->id]);

        $this->assertNull($this->primary->fresh()->province_id);
        $this->assertNull($this->primary->fresh()->city_id);
    }

    public function test_branch_price_save_rejects_branch_deactivation_between_initial_check_and_transaction(): void
    {
        $this->authorizeSupplierUserForTest();
        $this->addSourceProductDetails();
        $branch = $this->makeBranch('شعبه برای race مجوز');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = $this->acceptLegacyCopy($this->primary, $branch, $transfer->id);
        $branchOffer = $this->offer($branchProduct, $branch);

        $this->actingAs($this->user);
        $this->withSession(['supplier.active_branch_id' => $branch->id]);
        request()->setLaravelSession(app('session')->driver());
        $component = Livewire::test(EditPrice::class, ['product' => $branchProduct])
            ->set('branchPricePercentage', '25');

        $branchSelectionReads = 0;
        DB::listen(function (QueryExecuted $query) use (&$branchSelectionReads, $branch): void {
            if (!str_contains($query->sql, 'parent_supplier_id')
                || !str_contains($query->sql, 'is_active')
                || $branchSelectionReads >= 2) {
                return;
            }

            $branchSelectionReads++;
            if ($branchSelectionReads === 2) {
                DB::table('suppliers')->where('id', $branch->id)->update(['is_active' => false]);
            }
        });

        $component->call('save')->assertStatus(403);

        $this->assertFalse((bool) $branch->fresh()->is_active);
        $this->assertSame(0.0, (float) $branchOffer->fresh()->branch_price_percentage);
        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $branchProduct->id)->value('price'));
    }

    public function test_primary_matrix_and_revision_roll_back_when_branch_sync_fails(): void
    {
        $this->authorizeSupplierUserForTest();
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $tierPercentagesMigration = require database_path('migrations/2026_09_05_160929_create_supplier_tier_percentages_table.php');
        $tierPercentagesMigration->up();
        $branch = $this->makeBranch('شعبه برای تراکنش ماتریس');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = $this->acceptLegacyCopy($this->primary, $branch, $transfer->id);
        $beforeRevision = (int) $this->primaryProduct->fresh()->price_revision;
        $cashPriceTypeId = (int) PriceType::query()->where('code', 'cash')->value('id');

        $sync = \Mockery::mock(SupplierBranchPriceSyncService::class);
        $sync->shouldReceive('syncPrimaryMatrix')
            ->once()
            ->andThrow(new RuntimeException('forced branch matrix failure'));
        $this->app->instance(SupplierBranchPriceSyncService::class, $sync);
        $this->actingAs($this->user);

        try {
            Livewire::test(EditPrice::class, ['product' => $this->primaryProduct])
                ->set("priceMatrix.0.{$cashPriceTypeId}.price", '1300')
                ->call('save');
            $this->fail('Expected the branch matrix sync failure to abort the primary price save.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced branch matrix failure', $exception->getMessage());
        }

        $this->assertSame(1200, (int) DB::table('product_prices')
            ->where('product_supplier_id', $primaryOffer->id)
            ->value('price'));
        $this->assertSame(1200, (int) DB::table('product_prices')
            ->where('product_id', $branchProduct->id)
            ->value('price'));
        $this->assertSame($beforeRevision, (int) $this->primaryProduct->fresh()->price_revision);
    }

    public function test_primary_price_fanout_is_atomic_per_product_and_retry_is_idempotent(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $firstBranch = $this->makeBranch('شعبه اول برای همگام‌سازی');
        $secondBranch = $this->makeBranch('شعبه دوم برای همگام‌سازی');
        $this->seedTransfers($firstBranch);
        $this->seedTransfers($secondBranch);

        $firstTransfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $firstBranch->id)
            ->firstOrFail();
        $secondTransfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $secondBranch->id)
            ->firstOrFail();
        $firstCopy = $this->acceptLegacyCopy($this->primary, $firstBranch, $firstTransfer->id);
        $secondCopy = $this->acceptLegacyCopy($this->primary, $secondBranch, $secondTransfer->id);
        $firstOffer = $this->offer($firstCopy, $firstBranch);
        $secondOffer = $this->offer($secondCopy, $secondBranch);
        $firstOffer->update(['branch_price_percentage' => -10]);
        $secondOffer->update(['branch_price_percentage' => 10]);

        $sync = app(SupplierBranchPriceSyncService::class);
        $sync->syncBranchProduct($firstCopy, $firstBranch, -10);
        $sync->syncBranchProduct($secondCopy, $secondBranch, 10);
        $this->assertSame(1080, (int) DB::table('product_prices')->where('product_id', $firstCopy->id)->value('price'));
        $this->assertSame(1320, (int) DB::table('product_prices')->where('product_id', $secondCopy->id)->value('price'));

        DB::table('product_prices')->where('product_supplier_id', $primaryOffer->id)->update([
            'price' => 1500,
            'calculation_mode' => 'manual',
            'percentage_override' => 4.25,
        ]);
        $triggerName = 'fail_second_branch_price_sync';
        DB::unprepared("CREATE TRIGGER {$triggerName} BEFORE UPDATE ON product_prices
            WHEN NEW.product_id = {$secondCopy->id} AND NEW.branch_source_price_id IS NOT NULL
            BEGIN SELECT RAISE(ABORT, 'forced branch sync failure'); END");

        try {
            $sync->syncBranchesForPrimaryPrices(ProductPrice::query()->where('product_supplier_id', $primaryOffer->id)->get());
            $this->fail('Expected a forced branch price write failure.');
        } catch (\Illuminate\Database\QueryException) {
            // The whole product group must roll back when one branch write fails.
        }

        $this->assertSame(1080, (int) DB::table('product_prices')->where('product_id', $firstCopy->id)->value('price'));
        $this->assertSame(1320, (int) DB::table('product_prices')->where('product_id', $secondCopy->id)->value('price'));
        $this->assertNull(DB::table('product_prices')->where('product_id', $firstCopy->id)->value('calculation_mode'));
        DB::unprepared("DROP TRIGGER {$triggerName}");

        $sourcePrices = ProductPrice::query()->where('product_supplier_id', $primaryOffer->id)->get();
        $sync->syncBranchesForPrimaryPrices($sourcePrices);
        $sync->syncBranchesForPrimaryPrices($sourcePrices);

        $this->assertSame(1350, (int) DB::table('product_prices')->where('product_id', $firstCopy->id)->value('price'));
        $this->assertSame(1650, (int) DB::table('product_prices')->where('product_id', $secondCopy->id)->value('price'));
        $this->assertSame('manual', DB::table('product_prices')->where('product_id', $firstCopy->id)->value('calculation_mode'));
        $this->assertSame(4.25, (float) DB::table('product_prices')->where('product_id', $secondCopy->id)->value('percentage_override'));
        $this->assertSame(1, DB::table('product_prices')->where('product_id', $firstCopy->id)->count());
        $this->assertSame(1, DB::table('product_prices')->where('product_id', $secondCopy->id)->count());
    }

    public function test_primary_price_fanout_reloads_source_prices_and_branch_percentages(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $branch = $this->makeBranch('شعبه با نرخ همگام');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = $this->acceptLegacyCopy($this->primary, $branch, $transfer->id);
        $branchOffer = $this->offer($branchProduct, $branch);

        $staleSourcePrices = ProductPrice::query()->where('product_supplier_id', $primaryOffer->id)->get();
        DB::table('product_prices')->where('product_supplier_id', $primaryOffer->id)->update(['price' => 2000]);
        $branchOffer->update(['branch_price_percentage' => 10]);

        app(SupplierBranchPriceSyncService::class)->syncBranchesForPrimaryPrices($staleSourcePrices);

        $this->assertSame(2200, (int) DB::table('product_prices')
            ->where('product_supplier_id', $branchOffer->id)
            ->value('price'));
    }

    public function test_cache_invalidation_failure_queues_retry_and_keeps_live_price_broadcast(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $branch = $this->makeBranch('شعبه با کش موقت');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = $this->acceptLegacyCopy($this->primary, $branch, $transfer->id);
        $branchOffer = $this->offer($branchProduct, $branch);
        $branchOffer->update(['branch_price_percentage' => -10]);

        $invalidator = \Mockery::mock(\App\Services\SupplierBranchPublicCacheInvalidator::class);
        $invalidator->shouldReceive('invalidateMany')
            ->once()
            ->andThrow(new RuntimeException('cache temporarily unavailable'));
        $this->app->instance(\App\Services\SupplierBranchPublicCacheInvalidator::class, $invalidator);

        app(SupplierBranchPriceSyncService::class)->syncBranchesForPrimaryPrices(
            ProductPrice::query()->where('product_supplier_id', $primaryOffer->id)->get(),
        );

        $this->assertSame(1080, (int) DB::table('product_prices')
            ->where('product_supplier_id', $branchOffer->id)
            ->value('price'));
        Queue::assertPushed(InvalidateSupplierBranchPublicCaches::class, fn ($job): bool =>
            in_array((int) $branch->id, $job->supplierIds, true)
            && ! $job->refreshRevisions
        );
        Queue::assertPushed(BroadcastSupplierProductPrices::class, fn ($job): bool =>
            (int) $job->supplierId === (int) $branch->id
        );
    }

    public function test_revision_retry_does_not_bump_snapshot_before_cache_invalidation_succeeds(): void
    {
        $invalidator = \Mockery::mock(SupplierBranchPublicCacheInvalidator::class);
        $invalidator->shouldReceive('invalidateMany')
            ->once()
            ->with([(int) $this->primary->id])
            ->andThrow(new RuntimeException('cache temporarily unavailable'));
        $revisions = app(CatalogSnapshotRevisionService::class);
        $revisionSnapshot = fn (): array => DB::table('catalog_snapshot_revisions')
            ->orderBy('scope_type')
            ->orderBy('scope_id')
            ->get(['scope_type', 'scope_id', 'revision'])
            ->mapWithKeys(static fn ($row): array => [
                $row->scope_type . ':' . $row->scope_id => (int) $row->revision,
            ])
            ->all();
        $before = $revisionSnapshot();

        try {
            (new InvalidateSupplierBranchPublicCaches([(int) $this->primary->id], [], false, true))
                ->handle($revisions, $invalidator);
            $this->fail('The cache invalidation error should be retried by the queue.');
        } catch (RuntimeException $exception) {
            $this->assertSame('cache temporarily unavailable', $exception->getMessage());
        }

        $this->assertSame($before, $revisionSnapshot());
    }

    public function test_replacing_primary_matrix_rebinds_kept_branch_rows_and_removes_deleted_cells(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $keptSourcePriceId = (int) ProductPrice::query()
            ->where('product_supplier_id', $primaryOffer->id)
            ->value('id');
        $removedSourcePriceId = DB::table('product_prices')->insertGetId(array_merge(
            $this->priceRow($this->primaryProduct->id, $primaryOffer->id, 1800),
            ['tier_index' => 1],
        ));

        $branch = $this->makeBranch('شعبه ماتریس');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $copy = $this->acceptLegacyCopy($this->primary, $branch, $transfer->id);
        $branchOffer = $this->offer($copy, $branch);
        $branchOffer->update(['branch_price_percentage' => 10]);
        app(SupplierBranchPriceSyncService::class)->syncBranchProduct($copy, $branch, 10);
        Queue::assertPushed(\App\Jobs\BroadcastSupplierProductPrices::class, fn ($job): bool =>
            $job->supplierId === (int) $this->primary->id
            && $job->productSupplierIds === [(int) $primaryOffer->id]
        );

        DB::table('product_prices')->whereIn('id', [$keptSourcePriceId, $removedSourcePriceId])->delete();
        $replacementSource = array_merge(
            $this->priceRow($this->primaryProduct->id, $primaryOffer->id, 2000),
            ['percentage_override' => 7.5, 'calculation_mode' => 'percentage'],
        );
        $newKeptSourcePriceId = DB::table('product_prices')->insertGetId($replacementSource);

        app(SupplierBranchPriceSyncService::class)->syncPrimaryMatrix(
            $this->primaryProduct,
            $this->primary,
            ProductPrice::query()->where('product_supplier_id', $primaryOffer->id)->get(),
        );

        $branchPrices = ProductPrice::query()->where('product_supplier_id', $branchOffer->id)->get();
        $this->assertCount(1, $branchPrices);
        $this->assertSame($newKeptSourcePriceId, (int) $branchPrices->sole()->branch_source_price_id);
        $this->assertSame(2200, (int) $branchPrices->sole()->price);
        $this->assertSame(7.5, (float) $branchPrices->sole()->percentage_override);
        $this->assertSame('percentage', $branchPrices->sole()->calculation_mode);
        $this->assertSame(0, ProductPrice::query()
            ->where('product_supplier_id', $branchOffer->id)
            ->where('branch_source_price_id', $removedSourcePriceId)
            ->count());
    }

    public function test_branch_snapshot_uses_cash_or_check_base_and_respects_supplier_info_visibility(): void
    {
        $this->addSourceProductDetails();
        $offer = $this->offer($this->primaryProduct, $this->primary);
        $warrantyType = PriceType::query()->create(['code' => 'warranty', 'name' => 'گارانتی']);
        DB::table('product_prices')->insert(array_merge(
            $this->priceRow($this->primaryProduct->id, $offer->id, 200),
            ['price_type_id' => $warrantyType->id],
        ));

        $settings = new PriceVisibilitySetting([
            'is_active' => true,
            'can_see_wholesale_price' => true,
            'can_see_supplier_info' => true,
            'restricted_price_types' => null,
        ]);
        $visibility = \Mockery::mock(PriceVisibilityService::class);
        $visibility->shouldReceive('canSeeWholesalePrice')->andReturn(true);
        $visibility->shouldReceive('getSettings')->andReturn($settings);

        $response = (new ProductPriceSnapshotController())->__invoke(
            $this->primaryProduct,
            Request::create('/api/products/' . $this->primaryProduct->id . '/price-snapshot', 'GET'),
            $visibility,
        )->getData(true);

        $this->assertSame(1200, $response['branches'][0]['price']);

        $settings->setAttribute('can_see_supplier_info', false);
        $hiddenSupplierResponse = (new ProductPriceSnapshotController())->__invoke(
            $this->primaryProduct,
            Request::create('/api/products/' . $this->primaryProduct->id . '/price-snapshot', 'GET'),
            $visibility,
        )->getData(true);

        $this->assertSame([], $hiddenSupplierResponse['branches']);
    }

    public function test_branch_snapshot_includes_an_active_branch_offer_attached_to_the_canonical_product(): void
    {
        $this->addSourceProductDetails();
        $this->offer($this->primaryProduct, $this->primary);
        $branch = $this->makeBranch('شعبه شرق');
        $branchOffer = $this->offer($this->primaryProduct, $branch);
        DB::table('product_prices')->insert($this->priceRow($this->primaryProduct->id, $branchOffer->id, 900));
        $checkType = PriceType::query()->create(['code' => 'check', 'name' => 'چکی']);
        $checkPeriod = CheckPeriod::query()->create([
            'supplier_id' => null,
            'name' => 'چک ۴۵ روزه',
            'scope' => 'global',
            'days' => 45,
            'label' => 'چک ۴۵ روزه',
            'is_active' => true,
        ]);
        DB::table('supplier_check_period')->insert([
            'supplier_id' => $this->primary->id,
            'check_period_id' => $checkPeriod->id,
            'percentage_increase' => 3,
            'is_active' => true,
            'label' => 'چک اختصاصی تهران',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('supplier_check_period')->insert([
            'supplier_id' => $branch->id,
            'check_period_id' => $checkPeriod->id,
            'percentage_increase' => 4,
            'is_active' => true,
            'label' => 'چک اختصاصی شرق',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_prices')->insert(array_merge(
            $this->priceRow($this->primaryProduct->id, $branchOffer->id, 800),
            ['price_type_id' => $checkType->id, 'check_period_id' => $checkPeriod->id],
        ));

        $settings = new PriceVisibilitySetting([
            'is_active' => true,
            'can_see_wholesale_price' => true,
            'can_see_supplier_info' => true,
            'restricted_price_types' => null,
        ]);
        $visibility = \Mockery::mock(PriceVisibilityService::class);
        $visibility->shouldReceive('canSeeWholesalePrice')->andReturn(true);
        $visibility->shouldReceive('getSettings')->andReturn($settings);

        $response = (new ProductPriceSnapshotController())->__invoke(
            $this->primaryProduct,
            Request::create('/api/products/' . $this->primaryProduct->id . '/price-snapshot', 'GET'),
            $visibility,
        )->getData(true);

        $branchRow = collect($response['branches'])->firstWhere('supplier_id', $branch->id);
        $this->assertNotNull($branchRow, 'A branch offer shown on the initial card must remain present after live refresh.');
        $this->assertSame(800, $branchRow['price']);
        $this->assertSame('check', $branchRow['price_type']);
        $this->assertSame('چک اختصاصی شرق', $branchRow['check_period_label']);
    }

    public function test_branch_snapshot_uses_displayed_supplier_context_for_a_product_owned_by_another_supplier(): void
    {
        $this->addSourceProductDetails();
        $otherOwner = Supplier::factory()->create([
            'user_id' => User::factory()->create()->id,
            'is_active' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);
        $externalProduct = Product::factory()->create([
            'supplier_id' => $otherOwner->id,
            'category_id' => $this->category->id,
            'is_active' => true,
            'status' => ProductStatus::ACTIVE->value,
        ]);
        $branch = $this->makeBranch('شعبه شرق');
        $branchOffer = $this->offer($externalProduct, $branch);
        DB::table('product_prices')->insert($this->priceRow($externalProduct->id, $branchOffer->id, 900));

        $settings = new PriceVisibilitySetting([
            'is_active' => true,
            'can_see_wholesale_price' => true,
            'can_see_supplier_info' => true,
            'restricted_price_types' => null,
        ]);
        $visibility = \Mockery::mock(PriceVisibilityService::class);
        $visibility->shouldReceive('canSeeWholesalePrice')->andReturn(true);
        $visibility->shouldReceive('getSettings')->andReturn($settings);

        $response = (new ProductPriceSnapshotController())->__invoke(
            $externalProduct,
            Request::create('/api/products/' . $externalProduct->id . '/price-snapshot', 'GET', [
                'supplier_id' => $this->primary->id,
            ]),
            $visibility,
        )->getData(true);

        $branchRow = collect($response['branches'])->firstWhere('supplier_id', $branch->id);
        $this->assertNotNull($branchRow);
        $this->assertSame(900, $branchRow['price']);

        $unrelatedContextResponse = (new ProductPriceSnapshotController())->__invoke(
            $externalProduct,
            Request::create('/api/products/' . $externalProduct->id . '/price-snapshot', 'GET', [
                'supplier_id' => $otherOwner->id,
            ]),
            $visibility,
        )->getData(true);
        $this->assertSame([], $unrelatedContextResponse['branches']);
    }

    public function test_branch_snapshot_omits_an_unverified_active_branch_offer(): void
    {
        $this->addSourceProductDetails();
        $this->offer($this->primaryProduct, $this->primary);
        $branch = $this->makeBranch('شعبه تأییدنشده');
        $branch->forceFill(['is_verified' => false])->save();
        $branchOffer = $this->offer($this->primaryProduct, $branch);
        DB::table('product_prices')->insert($this->priceRow($this->primaryProduct->id, $branchOffer->id, 900));

        $settings = new PriceVisibilitySetting([
            'is_active' => true,
            'can_see_wholesale_price' => true,
            'can_see_supplier_info' => true,
            'restricted_price_types' => null,
        ]);
        $visibility = \Mockery::mock(PriceVisibilityService::class);
        $visibility->shouldReceive('canSeeWholesalePrice')->andReturn(true);
        $visibility->shouldReceive('getSettings')->andReturn($settings);

        $response = (new ProductPriceSnapshotController())->__invoke(
            $this->primaryProduct,
            Request::create('/api/products/' . $this->primaryProduct->id . '/price-snapshot', 'GET', [
                'supplier_id' => $this->primary->id,
            ]),
            $visibility,
        )->getData(true);

        $this->assertNull(collect($response['branches'])->firstWhere('supplier_id', $branch->id));
    }

    public function test_branch_snapshot_is_empty_when_the_primary_supplier_is_unverified(): void
    {
        $this->addSourceProductDetails();
        $branch = $this->makeBranch('شعبه فعال');
        $branchOffer = $this->offer($this->primaryProduct, $branch);
        DB::table('product_prices')->insert($this->priceRow($this->primaryProduct->id, $branchOffer->id, 900));
        $this->primary->forceFill(['is_verified' => false])->save();

        $settings = new PriceVisibilitySetting([
            'is_active' => true,
            'can_see_wholesale_price' => true,
            'can_see_supplier_info' => true,
            'restricted_price_types' => null,
        ]);
        $visibility = \Mockery::mock(PriceVisibilityService::class);
        $visibility->shouldReceive('canSeeWholesalePrice')->andReturn(true);
        $visibility->shouldReceive('getSettings')->andReturn($settings);

        $response = (new ProductPriceSnapshotController())->__invoke(
            $this->primaryProduct,
            Request::create('/api/products/' . $this->primaryProduct->id . '/price-snapshot', 'GET', [
                'supplier_id' => $branch->id,
            ]),
            $visibility,
        )->getData(true);

        $this->assertSame([], $response['branches']);
    }

    public function test_branch_prices_and_deletions_are_isolated_and_public_family_has_one_canonical_product(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $secondSourcePriceId = DB::table('product_prices')->insertGetId(array_merge(
            $this->priceRow($this->primaryProduct->id, $primaryOffer->id, 1800),
            ['tier_index' => 1]
        ));
        $branch = $this->makeBranch('شعبه شرق');
        $anotherBranch = $this->makeBranch('شعبه جنوب');
        $service = app(SupplierBranchTransferService::class);

        $this->seedTransfers($branch);
        $this->seedTransfers($anotherBranch);
        $firstTransfer = SupplierBranchProductTransfer::where('target_supplier_id', $branch->id)->firstOrFail();
        $secondTransfer = SupplierBranchProductTransfer::where('target_supplier_id', $anotherBranch->id)->firstOrFail();
        $firstCopy = $this->acceptLegacyCopy($this->primary, $branch, $firstTransfer->id);
        $secondCopy = $this->acceptLegacyCopy($this->primary, $anotherBranch, $secondTransfer->id);

        $firstOffer = $this->offer($firstCopy, $branch);
        $secondOffer = $this->offer($secondCopy, $anotherBranch);
        $firstOffer->update(['branch_price_percentage' => 10]);
        $secondOffer->update(['branch_price_percentage' => -10]);
        $priceSync = app(\App\Services\SupplierBranchPriceSyncService::class);
        $priceSync->syncBranchProduct($firstCopy, $branch, 10);
        $priceSync->syncBranchProduct($secondCopy, $anotherBranch, -10);

        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $this->primaryProduct->id)->value('price'));
        $this->assertSame(1320, (int) DB::table('product_prices')->where('product_id', $firstCopy->id)->value('price'));
        $this->assertSame(1080, (int) DB::table('product_prices')->where('product_id', $secondCopy->id)->value('price'));

        $firstSourcePrice = ProductPrice::query()
            ->where('product_id', $this->primaryProduct->id)
            ->where('tier_index', 0)
            ->firstOrFail();
        DB::table('product_prices')->where('id', $firstSourcePrice->id)->update(['price' => 2000]);
        $priceSync->syncBranchesForPrimaryPrice($firstSourcePrice->fresh());
        $this->assertSame(2200, (int) DB::table('product_prices')->where('product_id', $firstCopy->id)->value('price'));
        $this->assertSame(1800, (int) DB::table('product_prices')->where('product_id', $secondCopy->id)->value('price'));
        $this->assertSame(1980, (int) DB::table('product_prices')->where('product_id', $firstCopy->id)->where('branch_source_price_id', $secondSourcePriceId)->value('price'));
        $this->assertSame(1620, (int) DB::table('product_prices')->where('product_id', $secondCopy->id)->where('branch_source_price_id', $secondSourcePriceId)->value('price'));

        DB::table('product_prices')
            ->where('product_id', $firstCopy->id)
            ->where('branch_source_price_id', $firstSourcePrice->id)
            ->update(['is_active' => false]);
        DB::table('product_prices')->where('id', $firstSourcePrice->id)->update(['price' => 2100]);
        $priceSync->syncBranchesForPrimaryPrice($firstSourcePrice->fresh());

        $this->assertSame(2310, (int) DB::table('product_prices')->where('product_id', $firstCopy->id)->where('branch_source_price_id', $firstSourcePrice->id)->value('price'));
        $this->assertSame(1890, (int) DB::table('product_prices')->where('product_id', $secondCopy->id)->where('branch_source_price_id', $firstSourcePrice->id)->value('price'));
        $this->assertDatabaseHas('product_prices', ['product_id' => $firstCopy->id, 'branch_source_price_id' => $firstSourcePrice->id, 'is_active' => 0]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $secondCopy->id, 'branch_source_price_id' => $firstSourcePrice->id, 'is_active' => 1]);

        ProductPrice::query()->findOrFail($secondSourcePriceId)->delete();
        $this->assertDatabaseMissing('product_prices', ['product_id' => $firstCopy->id, 'branch_source_price_id' => $secondSourcePriceId]);
        $this->assertDatabaseMissing('product_prices', ['product_id' => $secondCopy->id, 'branch_source_price_id' => $secondSourcePriceId]);
        $this->assertCount(1, Product::query()->canonical()->get());

        $firstCopy->delete();
        $this->assertTrue($this->primaryProduct->fresh()->is_active);
        $this->assertFalse($secondCopy->fresh()->trashed());
        $this->assertSame(1, Product::query()->canonical()->count(), 'The deleted copy must not become a second public representative.');
    }

    public function test_primary_tier_discount_repricing_syncs_accepted_branch_prices(): void
    {
        $this->addSourceProductDetails();
        $this->primaryProduct->update(['quantity_tiers' => [
            ['name' => 'زیر کارتن', 'min_quantity' => 1, 'max_quantity' => 9],
            ['name' => 'کارتن', 'min_quantity' => 10, 'max_quantity' => 49],
            ['name' => 'عمده', 'min_quantity' => 50, 'max_quantity' => null],
        ]]);
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $primaryOffer->update(['tier_enabled' => [0 => false]]);
        $referencePriceId = DB::table('product_prices')->insertGetId(array_merge(
            $this->priceRow($this->primaryProduct->id, $primaryOffer->id, 1000),
            ['tier_index' => 1]
        ));
        $tierPriceId = DB::table('product_prices')->insertGetId(array_merge(
            $this->priceRow($this->primaryProduct->id, $primaryOffer->id, 1500),
            ['tier_index' => 2]
        ));

        $branch = $this->makeBranch('شعبه تیراژ');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = $this->acceptLegacyCopy($this->primary, $branch, (int) $transfer->id);
        $branchOffer = $this->offer($branchProduct, $branch);
        $branchOffer->update(['branch_price_percentage' => 10]);
        app(SupplierBranchPriceSyncService::class)->syncBranchProduct($branchProduct, $branch, 10);

        SupplierTierDiscount::query()->create([
            'supplier_id' => $this->primary->id,
            'tier_index' => 2,
            'percentage_change' => 20,
            'is_active' => true,
        ]);

        $result = TierPriceUpdater::updateSupplierPrices((int) $this->primary->id);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1200, (int) DB::table('product_prices')->where('id', $tierPriceId)->value('price'));
        $this->assertSame(1320, (int) DB::table('product_prices')
            ->where('product_id', $branchProduct->id)
            ->where('branch_source_price_id', $tierPriceId)
            ->value('price'));
        $this->assertSame(1000, (int) DB::table('product_prices')->where('id', $referencePriceId)->value('price'));
    }

    public function test_rejection_is_recorded_without_copying_and_cannot_later_be_accepted(): void
    {
        $branch = $this->makeBranch('شعبه رد');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::where('target_supplier_id', $branch->id)->firstOrFail();
        $service = app(SupplierBranchTransferService::class);

        $this->assertTrue($service->reject($this->primary, $branch, $transfer->id));
        $this->assertSame(SupplierBranchProductTransfer::REJECTED, $transfer->fresh()->status);
        $this->assertSame(0, Product::where('supplier_id', $branch->id)->count());

        $this->expectException(RuntimeException::class);
        $service->accept($this->primary, $branch, $transfer->id);
    }

    public function test_check_period_access_is_scoped_to_the_current_supplier(): void
    {
        $otherOwner = User::factory()->create();
        $otherSupplier = Supplier::factory()->create([
            'user_id' => $otherOwner->id,
            'slug' => 'other-period-owner',
            'is_active' => true,
        ]);
        $foreignPeriod = CheckPeriod::create([
            'name' => 'دوره فروشگاه دیگر',
            'supplier_id' => $otherSupplier->id,
            'scope' => 'supplier',
            'days' => 45,
            'is_active' => true,
        ]);
        $ownPeriod = CheckPeriod::create([
            'name' => 'دوره همین فروشگاه',
            'supplier_id' => $this->primary->id,
            'scope' => 'supplier',
            'days' => 60,
            'is_active' => true,
        ]);
        $globalPeriod = CheckPeriod::create([
            'name' => 'دوره سراسری',
            'supplier_id' => null,
            'scope' => 'global',
            'days' => 90,
            'is_active' => true,
        ]);

        $this->assertNull(CheckPeriod::availableToSupplier($this->primary->id)->find($foreignPeriod->id));
        $this->assertSame($ownPeriod->id, CheckPeriod::availableToSupplier($this->primary->id)->findOrFail($ownPeriod->id)->id);
        $this->assertNull(CheckPeriod::availableToSupplier($this->primary->id)->find($globalPeriod->id));

        DB::table('supplier_check_period')->insert([
            'supplier_id' => $this->primary->id,
            'check_period_id' => $globalPeriod->id,
            'percentage_increase' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame($globalPeriod->id, CheckPeriod::availableToSupplier($this->primary->id)->findOrFail($globalPeriod->id)->id);
    }

    public function test_transfer_cannot_be_used_by_another_store_owner_and_stale_branch_session_falls_back_to_primary(): void
    {
        $branch = $this->makeBranch('شعبه امن');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::where('target_supplier_id', $branch->id)->firstOrFail();
        $otherOwner = User::factory()->create();
        $otherPrimary = Supplier::factory()->create([
            'user_id' => $otherOwner->id,
            'slug' => 'other-store',
            'is_active' => true,
        ]);

        try {
            app(SupplierBranchTransferService::class)->accept($otherPrimary, $branch, $transfer->id);
            $this->fail('A different store owner accepted the transfer.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        session()->put('supplier.active_branch_id', $branch->id);
        $this->assertSame($branch->id, $this->user->currentSupplier()->id);
        session()->put('supplier.active_branch_id', $otherPrimary->id);
        $this->assertSame($this->primary->id, $this->user->currentSupplier()->id);
        $this->assertNull(session('supplier.active_branch_id'));
    }

    public function test_branch_switch_rejects_external_return_urls_that_contain_the_application_origin(): void
    {
        $branch = $this->makeBranch('شعبه امن');
        $request = Request::create('/supplier/branches/switch', 'POST', [
            'branch_id' => $branch->id,
            'return_to' => 'https://evil.example/' . url('/') . '/supplier/branches/product-transfers',
        ]);
        $request->setUserResolver(fn () => $this->user);
        $request->setLaravelSession(app('session')->driver());

        $response = app(BranchSwitchController::class)($request);

        $this->assertSame(route('supplier.dashboard'), $response->getTargetUrl());
        $this->assertSame($branch->id, (int) $request->session()->get('supplier.active_branch_id'));
    }

    public function test_owner_can_permanently_delete_an_unpriced_branch_without_deleting_sibling_branches(): void
    {
        $branchToDelete = $this->makeBranch('شعبه قابل حذف');
        $sibling = $this->makeBranch('شعبه باقی');
        $this->seedTransfers($branchToDelete);
        $this->actingAs($this->user);
        session()->put('supplier.active_branch_id', $branchToDelete->id);

        Livewire::test(ManageBranches::class)
            ->call('deleteBranch', $branchToDelete->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('suppliers', ['id' => $branchToDelete->id]);
        $this->assertDatabaseMissing('supplier_branch_product_transfers', ['target_supplier_id' => $branchToDelete->id]);
        $this->assertSame($sibling->id, (int) $this->primary->branches()->whereKey($sibling->id)->value('id'));
        $this->assertNull(session('supplier.active_branch_id'));
    }

    public function test_primary_promotion_action_is_hidden_and_rejects_stale_requests(): void
    {
        $branch = $this->makeBranch('Fixed hierarchy');
        $this->actingAs($this->user);
        session(['supplier.active_branch_id' => $branch->id]);

        Livewire::test(ManageBranches::class)
            ->assertDontSeeHtml('wire:click="makePrimary(')
            ->call('makePrimary', $branch->id)
            ->assertForbidden();

        $this->assertNull($this->primary->fresh()->parent_supplier_id);
        $this->assertSame($this->primary->id, (int) $branch->fresh()->parent_supplier_id);
        $this->assertSame($branch->id, session('supplier.active_branch_id'));
    }

    public function test_primary_promotion_service_rejects_even_an_accepted_branch_without_mutation(): void
    {
        $branch = $this->makeBranch('Accepted fixed branch');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)->firstOrFail();
        $this->actingAs($this->user);
        app(SupplierBranchTransferService::class)->accept($this->primary, $branch, $transfer->id);
        $before = [];
        foreach (['suppliers', 'products', 'product_supplier', 'product_prices', 'supplier_branch_product_transfers'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        try {
            app(SupplierBranchPrimaryService::class)->promote($this->primary, $branch->id);
            $this->fail('Retired promotion must always be forbidden.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        foreach ($before as $table => $snapshot) {
            $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }

    public function test_branch_creation_requires_a_valid_location_before_saving(): void
    {
        $this->authorizeSupplierUserForTest();
        Livewire::actingAs($this->user)->test(ManageBranches::class)
            ->set('branchName', 'Incomplete branch')
            ->call('createBranch')
            ->assertHasErrors(['branchProvinceIds.new', 'branchCityIds.new']);

        $this->assertSame(0, $this->primary->branches()->count());
    }

    public function test_unpriced_product_acceptance_creates_a_branch_sales_list_entry_and_is_idempotent(): void
    {
        $this->actingAs($this->user);
        $branch = $this->makeBranch('Unpriced branch');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::where('target_supplier_id', $branch->id)->firstOrFail();
        $service = app(SupplierBranchTransferService::class);
        $product = $service->accept($this->primary, $branch, $transfer->id);

        $this->assertSame($this->primaryProduct->id, $product->id);
        $this->assertSame(0, Product::where('supplier_id', $branch->id)->count());
        $this->assertSame(1, $branch->products()->where('products.id', $product->id)->count());
        $this->assertSame(0, DB::table('product_prices')->where('product_id', $product->id)->count());
        $this->assertFalse((bool) $product->productSuppliers()->where('supplier_id', $branch->id)->firstOrFail()->is_active);
        $this->assertSame($product->id, $service->accept($this->primary, $branch, $transfer->id)->id);
        $this->assertSame(1, $branch->products()->count());
    }

    public function test_primary_can_set_branch_percentages_without_changing_a_sibling_or_publishing_offers(): void
    {
        $this->addSourceProductDetails();
        $this->authorizeSupplierUserForTest();
        $branch = $this->makeBranch('Pricing branch');
        $sibling = $this->makeBranch('Sibling pricing branch');
        $this->seedTransfers($branch);
        $this->seedTransfers($sibling);
        $service = app(SupplierBranchTransferService::class);
        $product = $this->acceptLegacyCopy($this->primary, $branch, SupplierBranchProductTransfer::where('target_supplier_id', $branch->id)->value('id'));
        $siblingProduct = $this->acceptLegacyCopy($this->primary, $sibling, SupplierBranchProductTransfer::where('target_supplier_id', $sibling->id)->value('id'));

        $component = Livewire::actingAs($this->user)->test(ManageBranches::class)
            ->set('pricingBranchId', (string) $branch->id)
            ->set('pricingPercentage', '10')
            ->call('applyBranchPercentage')->assertHasNoErrors();
        $this->assertSame(1320, (int) DB::table('product_prices')->where('product_id', $product->id)->value('price'));

        $component->set('pricingCategoryId', (string) $product->category_id)
            ->set('pricingPercentage', '20')->call('applyBranchPercentage')->assertHasNoErrors();
        $this->assertSame(1440, (int) DB::table('product_prices')->where('product_id', $product->id)->value('price'));

        $component->set('pricingProductId', (string) $product->id)
            ->set('pricingPercentage', '-10')->call('applyBranchPercentage')->assertHasNoErrors();
        $this->assertSame(1080, (int) DB::table('product_prices')->where('product_id', $product->id)->value('price'));
        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $siblingProduct->id)->value('price'));
        $this->assertFalse((bool) $product->productSuppliers()->where('supplier_id', $branch->id)->firstOrFail()->is_active);
    }

    public function test_shared_product_has_full_independent_price_editing_and_rejects_a_stale_mode(): void
    {
        $this->addSourceProductDetails();
        $this->authorizeSupplierUserForTest();
        $this->actingAs($this->user);
        $branch = $this->makeBranch('Independent sales branch');
        $branch->update(['branch_pricing_mode' => 'independent']);
        session()->put('supplier.active_branch_id', $branch->id);
        $offer = app(\App\Services\SupplierSalesListService::class)->add($this->primaryProduct, $branch);

        Livewire::test(EditPrice::class, ['product' => $this->primaryProduct])
            ->assertSet('isBranchCopy', false)
            ->set('priceMatrix.0.1.price', 2500)
            ->set('is_active', true)
            ->call('save')->assertHasNoErrors();

        $this->assertSame(2500, (int) $offer->prices()->value('price'));
        $this->assertTrue((bool) $offer->fresh()->is_active);
        $this->assertSame(ProductSupplier::STATUS_ACTIVE, $offer->fresh()->status);
        $this->assertSame(1200, (int) $this->offer($this->primaryProduct, $this->primary)->prices()->value('price'));
        $this->assertSame(0, Product::where('supplier_id', $branch->id)->count());

        $editor = Livewire::test(EditPrice::class, ['product' => $this->primaryProduct]);
        app(\App\Services\SupplierBranchPricingService::class)->configure($this->primary, $branch->id, 'percentage', '10');
        $editor->set('priceMatrix.0.1.price', 9999)->call('save')->assertStatus(422);
        $this->assertSame(1320, (int) $offer->prices()->value('price'));
    }

    public function test_hub_price_and_selection_links_set_the_target_branch_context(): void
    {
        $this->authorizeSupplierUserForTest();
        $branch = $this->makeBranch('Hub target branch');
        $offer = $this->offer($this->primaryProduct, $branch);
        Livewire::actingAs($this->user)->test(ProductTransfers::class)
            ->set('branchId', $branch->id)
            ->call('openPricing', $offer->id)
            ->assertRedirect(route('supplier.products.edit-price', ['product' => $this->primaryProduct->id]));
        $this->assertSame($branch->id, (int) session('supplier.active_branch_id'));

        session()->forget('supplier.active_branch_id');
        Livewire::test(ProductTransfers::class)->set('branchId', $branch->id)
            ->call('openSelection')->assertRedirect(route('supplier.product-selection'));
        $this->assertSame($branch->id, (int) session('supplier.active_branch_id'));
    }

    private function addSourceProductDetails(): void
    {
        $this->primaryProduct->update([
            'specifications' => ['material' => 'steel'],
            'quantity_tiers' => [['name' => 'کارتن', 'quantity' => 10]],
            'legacy_id' => 'legacy-77',
        ]);

        $attribute = Attribute::create(['name' => 'جنس', 'type' => 'text', 'is_filterable' => false]);
        DB::table('attribute_category')->insert([
            'attribute_id' => $attribute->id,
            'category_id' => $this->category->id,
        ]);
        $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'آهن']);
        $this->primaryProduct->attributeValues()->attach($value->id, ['value' => 'آهن']);

        Storage::disk('public')->put('products/source.webp', 'source-image');
        Storage::disk('public')->put('products/source-poster.webp', 'source-poster');
        ProductGallery::create([
            'product_id' => $this->primaryProduct->id,
            'file_path' => 'products/source.webp',
            'poster_path' => 'products/source-poster.webp',
            'file_type' => 'image',
            'file_name' => 'source.webp',
            'base_name' => 'source',
            'versions' => ['small' => ['path' => 'products/source.webp']],
            'is_primary' => true,
            'is_seo_image' => true,
            'sort_order' => 0,
        ]);

        $sourceOffer = $this->offer($this->primaryProduct, $this->primary);
        DB::table('product_prices')->insert($this->priceRow($this->primaryProduct->id, $sourceOffer->id, 1200));
    }

    private function authorizeSupplierUserForTest(): void
    {
        $permissionMigration = require database_path('migrations/2026_02_15_104854_create_permission_tables.php');
        $permissionMigration->up();
        \Spatie\Permission\Models\Role::query()->create(['name' => 'supplier', 'guard_name' => 'web']);
        $this->user->assignRole('supplier');
    }

    /** @return array{province_id:int,city_id:int} */
    private function locationIds(string $cityName): array
    {
        $city = DB::table('cities')->where('native_name', $cityName)->first();
        $this->assertNotNull($city, "Test city {$cityName} must be seeded.");

        return ['province_id' => (int) $city->province_id, 'city_id' => (int) $city->id];
    }

    private function makeBranch(string $name): Supplier
    {
        $branch = $this->primary->replicate();
        $branch->parent_supplier_id = $this->primary->id;
        $branch->branch_name = $name;
        $branch->branch_pricing_mode = 'percentage';
        $branch->slug = 'primary-' . strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . uniqid();
        $branch->save();

        return $branch;
    }

    private function acceptLegacyCopy(Supplier $primary, Supplier $branch, int $transferId): Product
    {
        $transfer = SupplierBranchProductTransfer::query()
            ->where('source_supplier_id', $primary->id)
            ->where('target_supplier_id', $branch->id)->findOrFail($transferId);

        return app(SupplierBranchProductCopyService::class)->copyForBranch(
            Product::findOrFail($transfer->source_product_id),
            $branch,
            function (Product $product) use ($transfer): void {
                $transfer->forceFill([
                    'destination_product_id' => $product->id,
                    'status' => SupplierBranchProductTransfer::ACCEPTED,
                    'decided_at' => now(),
                ])->save();
            },
            false,
        );
    }

    private function seedTransfers(Supplier $branch): void
    {
        app(SupplierBranchTransferService::class)->seedPendingTransfers($this->primary, $branch);
    }

    private function offer(Product $product, Supplier $supplier): ProductSupplier
    {
        return ProductSupplier::query()->firstOrCreate(
            ['product_id' => $product->id, 'supplier_id' => $supplier->id],
            ['is_active' => true, 'is_primary' => $supplier->id === $product->supplier_id, 'status' => ProductSupplier::STATUS_ACTIVE]
        );
    }

    private function priceRow(int $productId, int $offerId, int $price): array
    {
        return [
            'product_id' => $productId,
            'product_supplier_id' => $offerId,
            'price_type_id' => 1,
            'tier_index' => 0,
            'check_period_id' => null,
            'percentage_override' => null,
            'price' => $price,
            'warranty_type' => null,
            'warranty_duration' => null,
            'warranty_price' => null,
            'description' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function createSupportingTables(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('iso2', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('country_id');
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $iranId = DB::table('countries')->insertGetId([
            'name' => 'Iran', 'native_name' => 'ایران', 'iso2' => 'IR', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tehranProvinceId = DB::table('provinces')->insertGetId([
            'country_id' => $iranId, 'name' => 'Tehran', 'native_name' => 'تهران', 'is_active' => true,
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $farsProvinceId = DB::table('provinces')->insertGetId([
            'country_id' => $iranId, 'name' => 'Fars', 'native_name' => 'فارس', 'is_active' => true,
            'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$tehranProvinceId, 'Tehran', 'تهران'], [$farsProvinceId, 'Shiraz', 'شیراز']] as [$provinceId, $name, $nativeName]) {
            DB::table('cities')->insert([
                'province_id' => $provinceId, 'country_id' => $iranId, 'name' => $name, 'native_name' => $nativeName,
                'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('supplier_exchange_rate_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->char('currency', 3);
        });

        Schema::create('price_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
        });
        PriceType::query()->create(['code' => 'cash', 'name' => 'نقدی']);

        Schema::create('supplier_tier_discount', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedTinyInteger('tier_index');
            $table->decimal('percentage_change', 8, 2)->default(0);
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'tier_index']);
        });

        Schema::create('category_tier_discount', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedTinyInteger('tier_index');
            $table->decimal('percentage_change', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier_price_rounding_ranges', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->decimal('min_price', 15, 2)->default(0);
            $table->decimal('max_price', 15, 2)->nullable();
            $table->string('rounding_method')->default('nearest');
            $table->unsignedInteger('rounding_step')->default(100);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('check_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('scope')->default('global');
            $table->unsignedInteger('days');
            $table->string('label')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier_check_period', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('check_period_id');
            $table->decimal('percentage_increase', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'check_period_id']);
        });

        Schema::create('category_check_period', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('check_period_id');
            $table->decimal('percentage_increase', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_galleries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('base_name')->nullable();
            $table->string('file_type')->default('image');
            $table->string('poster_path')->nullable();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_seo_image')->default(false);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('versions')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('unit', 50)->nullable();
            $table->string('type')->default('text');
            $table->boolean('is_filterable')->default(false);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('attribute_id');
            $table->string('value');
            $table->timestamps();
        });

        Schema::create('attribute_product', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('attribute_value_id');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'attribute_value_id']);
        });

        Schema::create('attribute_category', function (Blueprint $table): void {
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->unique(['attribute_id', 'category_id']);
        });
    }

    private function createSchemaBeforeBranchMigration(): void
    {
        $this->createBaseTables();
    }

    private function createBaseTables(): void
    {
        $this->createUsersSuppliersCategoriesAndProducts();
        $this->createProductSupplierAndPriceTables();
    }

    private function createUsersSuppliersCategoriesAndProducts(): void
    {
        $this->createUsersTable();
        $this->createSuppliersTable();
        $this->createCategoriesTable();
        $this->createCategorySlugRedirectsTable();
        $this->createProductsTable();
    }

    private function createCategorySlugRedirectsTable(): void
    {
        Schema::create('category_slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('old_slug')->unique();
            $table->timestamps();
        });
    }

    private function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('mobile')->nullable();
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->timestamps();
        });
    }

    private function createSuppliersTable(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('company_name');
            $table->string('company_name_en')->nullable();
            $table->string('brand_name')->nullable();
            $table->text('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('type')->default('supplier');
            $table->string('name')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('verification_status')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('price_rounding_type')->nullable();
            $table->char('pricing_currency', 3)->default('TOM');
            $table->unsignedInteger('pricing_currency_revision')->default(1);
            $table->unsignedBigInteger('tier_pricing_revision')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createCategoriesTable(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createProductsTable(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('legacy_id')->nullable()->unique();
            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->json('quantity_tiers')->nullable();
            $table->string('packaging_unit_type')->nullable();
            $table->unsignedInteger('quantity_per_packaging')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('view_count')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('seo_canonical_url')->nullable();
            $table->boolean('allows_outsourcing_correction')->default(false);
            $table->unsignedInteger('content_version')->default(1);
            $table->unsignedBigInteger('price_revision')->default(0);
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createProductSupplierAndPriceTables(): void
    {
        Schema::create('product_supplier', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('sku')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->char('price_currency', 3)->default('TOM');
            $table->decimal('foreign_price_amount', 20, 4)->nullable();
            $table->boolean('foreign_price_auto_update')->default(false);
            $table->unsignedTinyInteger('foreign_price_refresh_hours')->nullable();
            $table->decimal('foreign_price_rate', 20, 4)->nullable();
            $table->string('foreign_price_source', 64)->nullable();
            $table->char('foreign_price_applied_currency', 3)->nullable();
            $table->unsignedBigInteger('foreign_price_applied_setting_revision')->default(0);
            $table->unsignedBigInteger('foreign_price_applied_tier_revision')->nullable();
            $table->timestamp('foreign_price_updated_at')->nullable();
            $table->timestamp('foreign_price_next_update_at')->nullable();
            $table->timestamp('foreign_price_last_attempt_at')->nullable();
            $table->string('foreign_price_last_error', 500)->nullable();
            $table->unsignedBigInteger('foreign_price_revision')->default(0);
            $table->string('status')->default('pending');
            $table->integer('sort_order')->default(0);
            $table->string('warranty_type')->nullable();
            $table->decimal('warranty_value', 10, 2)->nullable();
            $table->boolean('has_warranty')->default(false);
            $table->string('warranty_provider')->nullable();
            $table->string('warranty_coverage_type')->nullable();
            $table->string('warranty_type_coverage')->nullable();
            $table->unsignedInteger('warranty_duration_days')->nullable();
            $table->boolean('auto_calculate_tiers')->default(false);
            $table->json('tier_percentages')->nullable();
            $table->json('tier_enabled')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'supplier_id']);
        });

        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_supplier_id')->nullable();
            $table->unsignedBigInteger('price_type_id')->nullable();
            $table->unsignedTinyInteger('tier_index')->default(0);
            $table->unsignedBigInteger('check_period_id')->nullable();
            $table->float('percentage_override')->nullable();
            $table->string('calculation_mode', 16)->nullable();
            $table->unsignedBigInteger('normalized_check_period_id')
                ->virtualAs('COALESCE(check_period_id, 0)');
            $table->unsignedBigInteger('price');
            $table->string('warranty_type', 50)->nullable();
            $table->unsignedInteger('warranty_duration')->nullable();
            $table->unsignedBigInteger('warranty_price')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->unique(
                ['product_supplier_id', 'price_type_id', 'tier_index', 'normalized_check_period_id'],
                'unique_price_matrix_normalized'
            );
        });
    }
}
