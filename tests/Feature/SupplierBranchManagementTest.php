<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Helpers\TierPriceUpdater;
use App\Http\Controllers\Supplier\BranchSwitchController;
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
use App\Models\PriceType;
use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use App\Models\SupplierTierDiscount;
use App\Models\User;
use App\Services\SupplierBranchTransferService;
use App\Services\SupplierBranchPriceSyncService;
use App\Services\SupplierBranchPrimaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->primaryProduct = Product::findOrFail($legacyProductId);

        $this->createSupportingTables();
        request()->setLaravelSession(app('session')->driver());
        Storage::fake('public');
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

    public function test_livewire_branch_creation_seeds_a_compact_review_queue_and_acceptance_creates_an_independent_copy(): void
    {
        $this->addSourceProductDetails();
        $this->actingAs($this->user);

        Livewire::test(ManageBranches::class)
            ->set('branchName', 'شعبه غرب')
            ->call('createBranch')
            ->assertHasNoErrors()
            ->assertRedirect(route('supplier.branches.transfers'));

        $branch = Supplier::query()->where('parent_supplier_id', $this->primary->id)->firstOrFail();
        $this->assertSame($this->primary->user_id, $branch->user_id);
        $this->assertSame($branch->id, (int) session('supplier.active_branch_id'));

        Livewire::test(ManageBranches::class)
            ->set('branchCities.' . $this->primary->id, 'تهران')
            ->call('saveCity', $this->primary->id)
            ->set('branchCities.' . $branch->id, 'شیراز')
            ->call('saveCity', $branch->id)
            ->assertDispatched('notify');
        $this->assertSame('تهران', $this->primary->fresh()->city);
        $this->assertSame('شیراز', $branch->fresh()->city);

        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->where('source_product_id', $this->primaryProduct->id)
            ->firstOrFail();
        $this->assertSame(SupplierBranchProductTransfer::PENDING, $transfer->status);

        $this->withSession(['supplier.active_branch_id' => $branch->id]);
        request()->setLaravelSession(app('session')->driver());
        Livewire::test(ProductTransfers::class)
            ->assertSee($this->primaryProduct->title)
            ->assertDontSee('products/source.webp')
            ->call('accept', $transfer->id)
            ->assertDispatched('notify');

        $destination = Product::query()->where('supplier_id', $branch->id)->firstOrFail();
        $this->assertSame($this->primaryProduct->id, $destination->family_product_id);
        $this->assertSame($this->primaryProduct->category_id, $destination->category_id);
        $this->assertSame($this->primaryProduct->title, $destination->title);
        $this->assertNull($destination->specifications, 'Branch-specific specifications must be entered after acceptance.');
        $this->assertSame(0, $destination->attributeValues()->count());
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $destination->id,
            'supplier_id' => $branch->id,
            'is_active' => 1,
        ]);
        $this->assertSame(1, DB::table('product_prices')->where('product_id', $destination->id)->count());
        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $destination->id)->value('price'));

        $sourceGallery = $this->primaryProduct->galleries()->firstOrFail();
        $destinationGallery = $destination->galleries()->firstOrFail();
        $this->assertNotSame($sourceGallery->file_path, $destinationGallery->file_path);
        $this->assertTrue(Storage::disk('public')->exists($sourceGallery->file_path));
        $this->assertTrue(Storage::disk('public')->exists($destinationGallery->file_path));
        $this->assertNull($destination->legacy_id);

        $transfer->refresh();
        $this->assertSame(SupplierBranchProductTransfer::ACCEPTED, $transfer->status);
        $this->assertSame($destination->id, $transfer->destination_product_id);

        $sameDestination = app(SupplierBranchTransferService::class)
            ->accept($this->primary, $branch, $transfer->id);
        $this->assertSame($destination->id, $sameDestination->id, 'Repeated acceptance must be idempotent.');

        $attribute = Attribute::where('name', 'جنس')->firstOrFail();
        $branchValue = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'پلاستیک']);
        $this->actingAs($this->user);
        Livewire::test(Edit::class, ['product' => $destination])
            ->assertSet('isBranchCopy', true)
            ->assertSee('این محصول نسخهٔ مستقل همین شعبه است')
            ->set('title', 'نسخهٔ شعبه غرب')
            ->set('attributeValues', [$attribute->id => $branchValue->id])
            ->call('save')
            ->assertRedirect(route('supplier.products.index'));

        $this->assertSame('نسخهٔ شعبه غرب', $destination->fresh()->title);
        $this->assertSame('محصول قدیمی', $this->primaryProduct->fresh()->title);
        $this->assertSame([$branchValue->id], $destination->fresh()->attributeValues()->pluck('attribute_values.id')->all());
        $this->assertSame(1, $this->primaryProduct->fresh()->attributeValues()->count());

        Livewire::test(EditPrice::class, ['product' => $destination])
            ->assertSet('isBranchCopy', true)
            ->assertSee('1,200')
            ->set('branchPricePercentage', '10')
            ->assertSee('1,320')
            ->call('save')
            ->assertDispatched('notify');

        $this->assertSame(1320, (int) DB::table('product_prices')->where('product_id', $destination->id)->value('price'));
        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $this->primaryProduct->id)->value('price'));

        Livewire::test(EditPrice::class, ['product' => $destination])
            ->set('branchPricePercentage', '10.123456789012')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('10.123456789012', (string) $this->offer($destination, $branch)->fresh()->branch_price_percentage);
        $this->assertSame(1321, (int) DB::table('product_prices')->where('product_id', $destination->id)->value('price'));
        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $this->primaryProduct->id)->value('price'));

        Livewire::test(EditPrice::class, ['product' => $destination])
            ->set('branchPricePercentage', '10.1234567890123')
            ->call('save')
            ->assertHasErrors(['branchPricePercentage' => 'decimal']);

        $this->assertSame('10.123456789012', (string) $this->offer($destination, $branch)->fresh()->branch_price_percentage);
        $this->assertSame(1321, (int) DB::table('product_prices')->where('product_id', $destination->id)->value('price'));
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
        $firstCopy = $service->accept($this->primary, $branch, $firstTransfer->id);
        $secondCopy = $service->accept($this->primary, $anotherBranch, $secondTransfer->id);

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
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
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
        $branchProduct = app(SupplierBranchTransferService::class)
            ->accept($this->primary, $branch, (int) $transfer->id);
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

    public function test_owner_can_soft_delete_a_branch_without_deleting_sibling_branches(): void
    {
        $branchToDelete = $this->makeBranch('شعبه قابل حذف');
        $sibling = $this->makeBranch('شعبه باقی');
        $this->seedTransfers($branchToDelete);
        $this->actingAs($this->user);
        session()->put('supplier.active_branch_id', $branchToDelete->id);

        Livewire::test(ManageBranches::class)
            ->call('deleteBranch', $branchToDelete->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('suppliers', ['id' => $branchToDelete->id]);
        $this->assertDatabaseHas('supplier_branch_product_transfers', [
            'target_supplier_id' => $branchToDelete->id,
            'status' => SupplierBranchProductTransfer::CANCELLED,
        ]);
        $this->assertSame($sibling->id, (int) $this->primary->branches()->whereKey($sibling->id)->value('id'));
        $this->assertNull(session('supplier.active_branch_id'));
    }

    public function test_owner_can_promote_an_accepted_branch_and_preserve_shared_product_prices(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $branch = $this->makeBranch('شعبه شیراز');
        $branch->update(['city' => 'شیراز']);
        $sibling = $this->makeBranch('شعبه اصفهان');

        $transferService = app(SupplierBranchTransferService::class);
        $this->seedTransfers($branch);
        $this->seedTransfers($sibling);
        $branchTransfer = SupplierBranchProductTransfer::query()->where('target_supplier_id', $branch->id)->firstOrFail();
        $siblingTransfer = SupplierBranchProductTransfer::query()->where('target_supplier_id', $sibling->id)->firstOrFail();
        $branchProduct = $transferService->accept($this->primary, $branch, $branchTransfer->id);
        $siblingProduct = $transferService->accept($this->primary, $sibling, $siblingTransfer->id);

        $branchOffer = $this->offer($branchProduct, $branch);
        $siblingOffer = $this->offer($siblingProduct, $sibling);
        $branchOffer->update(['branch_price_percentage' => -10]);
        $siblingOffer->update(['branch_price_percentage' => 10]);
        $priceSync = app(SupplierBranchPriceSyncService::class);
        $priceSync->syncBranchProduct($branchProduct, $branch, -10);
        $priceSync->syncBranchProduct($siblingProduct, $sibling, 10);

        $this->assertSame(1080, (int) DB::table('product_prices')->where('product_id', $branchProduct->id)->value('price'));
        $this->assertSame(1320, (int) DB::table('product_prices')->where('product_id', $siblingProduct->id)->value('price'));
        DB::table('product_prices')->where('product_id', $siblingProduct->id)->update(['is_active' => false]);
        $originalSupplierSlug = $this->primary->slug;
        $originalProductSlug = $this->primaryProduct->slug;

        $this->actingAs($this->user);
        Livewire::test(ManageBranches::class)
            ->call('makePrimary', $branch->id)
            ->assertHasNoErrors()
            ->assertDispatched('notify')
            ->assertRedirect(route('supplier.branches.index'));

        $this->assertNull($branch->fresh()->parent_supplier_id);
        $this->assertSame($branch->id, (int) $this->primary->fresh()->parent_supplier_id);
        $this->assertSame($branch->id, (int) $sibling->fresh()->parent_supplier_id);
        $this->assertSame($originalSupplierSlug, $branch->fresh()->slug);
        $this->assertSame($branch->slug, $this->primary->fresh()->slug);
        $this->assertSame($branchProduct->id, (int) $branchProduct->fresh()->family_product_id);
        $this->assertSame($originalProductSlug, $branchProduct->fresh()->slug);
        $this->assertSame($branch->id, Product::query()->canonical()->where('family_product_id', $branchProduct->id)->value('id'));

        $rootPercentage = (float) $this->offer($branchProduct, $branch->fresh())->fresh()->branch_price_percentage;
        $formerPrimaryPercentage = (float) $this->offer($this->primaryProduct, $this->primary->fresh())->fresh()->branch_price_percentage;
        $siblingPercentage = (float) $this->offer($siblingProduct, $sibling->fresh())->fresh()->branch_price_percentage;
        $this->assertSame(0.0, $rootPercentage);
        $this->assertSame(11.111111111111, $formerPrimaryPercentage);
        $this->assertSame(22.222222222222, $siblingPercentage);
        $this->assertSame(1080, (int) DB::table('product_prices')->where('product_id', $branchProduct->id)->whereNull('branch_source_price_id')->value('price'));
        $this->assertSame(1200, (int) DB::table('product_prices')->where('product_id', $this->primaryProduct->id)->value('price'));
        $this->assertSame(1320, (int) DB::table('product_prices')->where('product_id', $siblingProduct->id)->value('price'));
        $this->assertSame(
            0,
            (int) DB::table('product_prices')->where('product_id', $siblingProduct->id)->value('is_active'),
            'Rebasing must retain the branch-level active state while preserving its price.'
        );
        $this->assertSame($branch->id, $this->user->fresh()->supplier()->value('id'));
        $this->assertNull(session('supplier.active_branch_id'));
    }

    public function test_promotion_preserves_price_snapshots_when_rebased_percentage_rounding_would_shift_them(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        DB::table('product_prices')
            ->where('product_supplier_id', $primaryOffer->id)
            ->update(['price' => 10000]);

        $branch = $this->makeBranch('شعبه با درصد اعشاری');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = app(SupplierBranchTransferService::class)->accept($this->primary, $branch, $transfer->id);
        $branchOffer = $this->offer($branchProduct, $branch);
        $branchOffer->update(['branch_price_percentage' => 2.192106]);
        app(SupplierBranchPriceSyncService::class)->syncBranchProduct($branchProduct, $branch, 2.192106);

        $this->assertSame(10000, (int) DB::table('product_prices')
            ->where('product_id', $this->primaryProduct->id)
            ->value('price'));
        $this->assertSame(10219, (int) DB::table('product_prices')
            ->where('product_id', $branchProduct->id)
            ->value('price'));

        app(SupplierBranchPrimaryService::class)->promote($this->primary, $branch->id);

        $this->assertSame(10219, (int) DB::table('product_prices')
            ->where('product_id', $branchProduct->id)
            ->whereNull('branch_source_price_id')
            ->value('price'));
        $this->assertSame(10000, (int) DB::table('product_prices')
            ->where('product_id', $this->primaryProduct->id)
            ->whereNotNull('branch_source_price_id')
            ->value('price'), 'Promotion must preserve the existing amount when percentage rounding crosses a price unit.');
        $this->assertSame(-2.14508349598, (float) $this->offer($this->primaryProduct, $this->primary->fresh())->fresh()->branch_price_percentage);

        $this->actingAs($this->user);
        $branchesForSwitch = Livewire::test(ManageBranches::class)->get('branches');
        $formerPrimaryBranch = $branchesForSwitch->firstWhere('id', $this->primary->id);
        $this->assertTrue((bool) $formerPrimaryBranch->has_shared_active_product);

        Livewire::test(ManageBranches::class)
            ->call('makePrimary', $this->primary->id)
            ->assertHasNoErrors()
            ->assertRedirect(route('supplier.branches.index'));

        $this->assertNull($this->primary->fresh()->parent_supplier_id);
        $this->assertSame($this->primary->id, (int) $branch->fresh()->parent_supplier_id);
        $this->assertSame(2.192106, (float) $branchOffer->fresh()->branch_price_percentage);
        $this->assertSame(10000, (int) DB::table('product_prices')
            ->where('product_id', $this->primaryProduct->id)
            ->whereNull('branch_source_price_id')
            ->value('price'));
        $this->assertSame(10219, (int) DB::table('product_prices')
            ->where('product_id', $branchProduct->id)
            ->whereNotNull('branch_source_price_id')
            ->value('price'));
    }

    public function test_promotion_stops_when_duplicate_matrix_rows_cannot_be_matched_safely(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        DB::table('product_prices')
            ->where('product_supplier_id', $primaryOffer->id)
            ->update(['price' => 10000, 'price_type_id' => null]);
        DB::table('product_prices')->insert(array_merge(
            $this->priceRow($this->primaryProduct->id, $primaryOffer->id, 12000),
            ['price_type_id' => null]
        ));

        $branch = $this->makeBranch('شعبه با ردیف‌های تکراری');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = app(SupplierBranchTransferService::class)->accept($this->primary, $branch, $transfer->id);
        DB::table('product_prices')
            ->where('product_id', $branchProduct->id)
            ->update(['branch_source_price_id' => null]);

        try {
            app(SupplierBranchPrimaryService::class)->promote($this->primary, $branch->id);
            $this->fail('Promotion guessed a mapping for duplicate matrix rows.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primaryBranch', $exception->errors());
        }

        $this->assertSame($this->primary->id, (int) $branch->fresh()->parent_supplier_id);
        $this->assertNull($this->primary->fresh()->parent_supplier_id);
        $this->assertEqualsCanonicalizing(
            [10000, 12000],
            DB::table('product_prices')->where('product_id', $this->primaryProduct->id)->pluck('price')->map(fn ($price) => (int) $price)->all()
        );
        $this->assertEqualsCanonicalizing(
            [10000, 12000],
            DB::table('product_prices')->where('product_id', $branchProduct->id)->pluck('price')->map(fn ($price) => (int) $price)->all()
        );
    }

    public function test_promotion_matches_standalone_price_rows_by_all_matrix_identity_fields(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        $firstPriceId = (int) DB::table('product_prices')
            ->where('product_supplier_id', $primaryOffer->id)
            ->value('id');
        DB::table('product_prices')->where('id', $firstPriceId)->update([
            'price' => 10000,
            'price_type_id' => null,
            'percentage_override' => 1.25,
            'valid_from' => '2026-10-01',
        ]);
        $secondPrice = $this->priceRow($this->primaryProduct->id, $primaryOffer->id, 12000);
        $secondPrice['price_type_id'] = null;
        $secondPrice['percentage_override'] = 2.50;
        $secondPrice['valid_from'] = '2026-11-01';
        DB::table('product_prices')->insert($secondPrice);

        $branch = $this->makeBranch('شعبه با ماتریس مستقل');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = app(SupplierBranchTransferService::class)->accept($this->primary, $branch, $transfer->id);
        DB::table('product_prices')
            ->where('product_id', $branchProduct->id)
            ->update(['branch_source_price_id' => null]);

        app(SupplierBranchPrimaryService::class)->promote($this->primary, $branch->id);

        $this->assertSame(10000, (int) DB::table('product_prices')
            ->where('product_id', $this->primaryProduct->id)
            ->whereDate('valid_from', '2026-10-01')
            ->value('price'));
        $this->assertSame(12000, (int) DB::table('product_prices')
            ->where('product_id', $this->primaryProduct->id)
            ->whereDate('valid_from', '2026-11-01')
            ->value('price'));
    }

    public function test_promotion_stops_when_a_direct_match_leaves_an_unlinked_branch_price_row(): void
    {
        $this->addSourceProductDetails();
        $primaryOffer = $this->offer($this->primaryProduct, $this->primary);
        DB::table('product_prices')
            ->where('product_supplier_id', $primaryOffer->id)
            ->update(['price_type_id' => null]);

        $branch = $this->makeBranch('شعبه با ردیف قیمت اضافی');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        app(SupplierBranchTransferService::class)->accept($this->primary, $branch, $transfer->id);

        DB::table('product_prices')->insert(array_merge(
            $this->priceRow($this->primaryProduct->id, $primaryOffer->id, 1300),
            ['price_type_id' => null]
        ));

        try {
            app(SupplierBranchPrimaryService::class)->promote($this->primary, $branch->id);
            $this->fail('Promotion ignored an unmatched primary matrix row.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primaryBranch', $exception->errors());
        }

        $this->assertSame($this->primary->id, (int) $branch->fresh()->parent_supplier_id);
        $this->assertNull($this->primary->fresh()->parent_supplier_id);
        $this->assertSame(2, DB::table('product_prices')
            ->where('product_supplier_id', $primaryOffer->id)
            ->count());
    }

    public function test_owner_cannot_promote_a_branch_before_accepting_a_product(): void
    {
        $branch = $this->makeBranch('شعبه بدون محصول');
        $this->actingAs($this->user);

        Livewire::test(ManageBranches::class)
            ->call('makePrimary', $branch->id)
            ->assertHasErrors(['primaryBranch']);

        $this->assertNull($this->primary->fresh()->parent_supplier_id);
        $this->assertSame($this->primary->id, $this->user->fresh()->supplier()->value('id'));
    }

    public function test_promotion_reroots_an_accepted_unpriced_family_without_using_a_stale_branch_percentage(): void
    {
        $branch = $this->makeBranch('شعبه بدون ماتریس');
        $this->seedTransfers($branch);
        $transfer = SupplierBranchProductTransfer::query()
            ->where('target_supplier_id', $branch->id)
            ->firstOrFail();
        $branchProduct = app(SupplierBranchTransferService::class)->accept($this->primary, $branch, $transfer->id);
        $branchOffer = $this->offer($branchProduct, $branch);
        $branchOffer->update(['branch_price_percentage' => -10]);

        $this->actingAs($this->user);
        Livewire::test(ManageBranches::class)
            ->call('makePrimary', $branch->id)
            ->assertHasNoErrors()
            ->assertDispatched('notify')
            ->assertRedirect(route('supplier.branches.index'));

        $this->assertSame($branch->id, (int) $branchProduct->fresh()->family_product_id);
        $this->assertSame($branch->id, (int) Product::query()->canonical()->where('family_product_id', $branch->id)->value('id'));
        $this->assertSame(0.0, (float) $branchOffer->fresh()->branch_price_percentage);
        $this->assertSame($branch->id, (int) $this->primary->fresh()->parent_supplier_id);
        $this->assertSame(0, DB::table('product_prices')->where('product_id', $this->primaryProduct->id)->count());
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

    private function makeBranch(string $name): Supplier
    {
        $branch = $this->primary->replicate();
        $branch->parent_supplier_id = $this->primary->id;
        $branch->branch_name = $name;
        $branch->slug = 'primary-' . strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . uniqid();
        $branch->save();

        return $branch;
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
            $table->string('city')->nullable();
            $table->string('type')->default('supplier');
            $table->string('name')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('verification_status')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('price_rounding_type')->nullable();
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
            $table->unsignedBigInteger('supplier_id');
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
                ['product_supplier_id', 'price_type_id', 'tier_index', 'check_period_id'],
                'unique_price_matrix'
            );
        });
    }
}
