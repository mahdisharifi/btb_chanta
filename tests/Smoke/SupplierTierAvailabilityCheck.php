<?php

use App\Livewire\Supplier\Settings\ManageTierDiscounts;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierTierDiscount;
use App\Models\User;
use App\Services\CatalogSnapshotRevisionService;
use App\Services\SupplierTierAvailabilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require __DIR__.'/BranchSalesListCheck.php';

$tierCheckCompleted = false;
register_shutdown_function(static function () use (&$tierCheckCompleted): void {
    if (!$tierCheckCompleted) {
        exit(1);
    }
});

Schema::table('suppliers', function (Blueprint $table): void {
    $table->unsignedBigInteger('tier_pricing_revision')->default(1);
    $table->unsignedBigInteger('supplier_tier_discount_revision')->default(1);
    $table->boolean('is_verified')->default(true);
    $table->string('verification_status')->default('approved');
});
Schema::table('product_supplier', function (Blueprint $table): void {
    $table->json('tier_enabled')->nullable();
});
Schema::table('price_types', function (Blueprint $table): void {
    $table->string('code')->default('cash');
});
Schema::table('check_periods', function (Blueprint $table): void {
    $table->string('name')->nullable();
    $table->string('scope')->default('global');
    $table->unsignedBigInteger('supplier_id')->nullable();
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
});
Schema::create('supplier_check_period', function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('supplier_id');
    $table->unsignedBigInteger('check_period_id');
    $table->decimal('percentage_increase', 8, 2)->default(0);
    $table->boolean('is_active')->default(false);
    $table->string('label')->nullable();
});
Schema::create('supplier_tier_discount', function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('supplier_id');
    $table->unsignedInteger('tier_index');
    $table->decimal('percentage_change', 8, 2)->default(0);
    $table->string('label')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['supplier_id', 'tier_index']);
});
Schema::create('supplier_tier_percentages', function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('supplier_id');
    $table->unsignedBigInteger('product_id');
    $table->unsignedInteger('tier_index');
    $table->decimal('percentage', 8, 2);
    $table->timestamps();
});

class TierAvailabilityUser extends User
{
    public int $selectedSupplier;

    public function currentSupplier(): Supplier
    {
        return Supplier::findOrFail($this->selectedSupplier);
    }

    public function hasAnyRole(...$roles): bool
    {
        return true;
    }

    public function isAccountApproved(): bool
    {
        return true;
    }
}

$user = (new TierAvailabilityUser)->forceFill(['id' => 1]);
$user->selectedSupplier = (int) $independent->id;
Auth::setUser($user);
$availability = app(SupplierTierAvailabilityService::class);
$newSupplier = Supplier::create(['user_id' => 1, 'verification_status' => 'pending', 'is_verified' => false, 'is_active' => false]);
checkBranchCatalog($availability->forSupplier((int) $newSupplier->id) === SupplierTierDiscount::DEFAULT_ENABLED, 'New registration did not use carton-only defaults.');
$newSupplier->update(['verification_status' => 'approved', 'is_verified' => true, 'is_active' => true]);
checkBranchCatalog($availability->forSupplier((int) $newSupplier->id) === SupplierTierDiscount::DEFAULT_ENABLED, 'Approval enabled additional tiers.');
DB::table('check_periods')->insert(['name' => '30 days', 'days' => 30]);
checkBranchCatalog(\App\Models\CheckPeriod::getActiveForSuppliers([(int) $newSupplier->id])->isEmpty(), 'Newly approved supplier inherited enabled check periods.');
$flags = $availability->forSupplier($user->selectedSupplier);
checkBranchCatalog($flags === SupplierTierDiscount::DEFAULT_ENABLED, 'Unconfigured supplier must enable only the reference.');
checkBranchCatalog(!ProductPrice::query()->activeAndValid()->whereKey($manualPriceId)->exists(), 'SQL displayed a non-reference price without supplier opt-in.');

$form = app(ManageTierDiscounts::class);
$form->loadTierSettings();
checkBranchCatalog(count($form->tierSettings) === 4 && $form->tierSettings[1]['is_active'], 'Default tier form is incomplete.');
checkBranchCatalog(array_column($form->tierSettings, 'is_active') === array_values(SupplierTierDiscount::DEFAULT_ENABLED), 'Settings form did not show the carton-only defaults.');
$form->tierSettings[0]['is_active'] = true;
$form->save();
$unconfiguredOffer = new ProductSupplier(['supplier_id' => $user->selectedSupplier]);
checkBranchCatalog($unconfiguredOffer->effective_tier_enabled === SupplierTierDiscount::DEFAULT_ENABLED, 'Global activation automatically opted an unconfigured product in.');
$unconfiguredOffer->tier_enabled = [0 => true, 1 => true];
checkBranchCatalog($unconfiguredOffer->effective_tier_enabled[0], 'Product cannot opt in to a globally enabled tier.');
$unconfiguredOffer->tier_enabled = [0 => false, 1 => true];
checkBranchCatalog(!$unconfiguredOffer->effective_tier_enabled[0], 'Product opt-out was not respected.');
$offer = ProductSupplier::findOrFail($manualPriceId ? ProductPrice::findOrFail($manualPriceId)->product_supplier_id : 0);
DB::table('product_supplier')->where('id', $offer->id)->update(['tier_enabled' => json_encode([0 => true, 1 => true, 2 => false, 3 => true])]);
$offer->refresh();
$priceBefore = DB::table('product_prices')->orderBy('id')->get()->toJson();
$productFlagsBefore = DB::table('product_supplier')->where('id', $offer->id)->value('tier_enabled');
$revisionBefore = app(CatalogSnapshotRevisionService::class)->signatureFor([$user->selectedSupplier]);
$priceCacheVersionBefore = (int) \Illuminate\Support\Facades\Cache::get('products.prices.version', 0);
$form->tierSettings[0]['is_active'] = false;
$form->tierSettings[3]['is_active'] = true;
$form->tierSettings[0]['percentage_change'] = 8.5;
$form->tierSettings[1]['is_active'] = false;
$form->tierSettings[1]['percentage_change'] = 83;
$form->save();
checkBranchCatalog(!$availability->forSupplier($user->selectedSupplier)[0], 'Saving did not disable the supplier tier or clear the request cache.');
checkBranchCatalog($availability->forSupplier($user->selectedSupplier)[1], 'Reference tier was disabled by forged input.');
checkBranchCatalog((float) SupplierTierDiscount::where('supplier_id', $user->selectedSupplier)->where('tier_index', 1)->value('percentage_change') === 0.0, 'Reference percentage was changed.');
checkBranchCatalog($revisionBefore !== app(CatalogSnapshotRevisionService::class)->signatureFor([$user->selectedSupplier]), 'Tier visibility did not invalidate catalog revisions.');
checkBranchCatalog((int) \Illuminate\Support\Facades\Cache::get('products.prices.version', 0) > $priceCacheVersionBefore, 'Tier visibility did not invalidate the cached public price range.');
checkBranchCatalog($offer->effective_tier_enabled === [0 => false, 1 => true, 2 => false, 3 => true], 'Supplier ceiling did not take precedence over product flags.');
checkBranchCatalog(!$availability->forSupplier((int) $primary->id)[0], 'Branch settings leaked into its parent.');
checkBranchCatalog($priceBefore === DB::table('product_prices')->orderBy('id')->get()->toJson(), 'Visibility save changed stored prices.');
checkBranchCatalog($productFlagsBefore === DB::table('product_supplier')->where('id', $offer->id)->value('tier_enabled'), 'Visibility save changed product flags.');
checkBranchCatalog((float) SupplierTierDiscount::getAllForSupplier($user->selectedSupplier)->firstWhere('tier_index', 0)->percentage_change === 8.5, 'Disabled tier lost its saved percentage.');

// Saving the product percentage policy must not erase a hidden tier's history.
$hiddenOverrideId = DB::table('supplier_tier_percentages')->insertGetId([
    'supplier_id' => $user->selectedSupplier, 'product_id' => $source->id,
    'tier_index' => 0, 'percentage' => 7.5,
]);
$visibleOverrideId = DB::table('supplier_tier_percentages')->insertGetId([
    'supplier_id' => $user->selectedSupplier, 'product_id' => $source->id,
    'tier_index' => 1, 'percentage' => 0,
]);
$priceForm = app(\App\Livewire\Supplier\Products\EditPrice::class);
$priceForm->product = $source;
$priceForm->productSupplier = $offer;
$priceForm->supplierTierEnabled = $availability->forSupplier($user->selectedSupplier);
$priceForm->autoCalculateTiers = false;
$priceForm->saveTierPercentages();
checkBranchCatalog((float) DB::table('supplier_tier_percentages')->where('id', $hiddenOverrideId)->value('percentage') === 7.5, 'Saving product policy erased a hidden tier override.');
checkBranchCatalog(!DB::table('supplier_tier_percentages')->where('id', $visibleOverrideId)->exists(), 'Visible product percentage policy was not saved.');

// A second tab must not silently undo a newer visibility decision.
$stale = app(ManageTierDiscounts::class);
$stale->loadTierSettings();
$form->tierSettings[3]['is_active'] = false;
$form->save();
$stale->tierSettings[0]['is_active'] = true;
$stale->save();
checkBranchCatalog(!$availability->forSupplier($user->selectedSupplier)[0] && !$stale->tierSettings[0]['is_active'], 'Stale settings overwrote a newer decision.');

// Real SQL filtering precedes aggregates and supports legacy unscoped rows.
$hiddenId = DB::table('product_prices')->insertGetId([
    'product_id' => $source->id, 'product_supplier_id' => $offer->id,
    'price_type_id' => 1, 'tier_index' => 3, 'price' => 1, 'is_active' => true,
]);
$visibleId = DB::table('product_prices')->insertGetId([
    'product_id' => $source->id, 'product_supplier_id' => $offer->id,
    'price_type_id' => 1, 'tier_index' => 1, 'price' => 5000, 'is_active' => true,
]);
$ids = ProductPrice::query()->whereIn('id', [$hiddenId, $visibleId])->activeAndValid()->pluck('id')->all();
checkBranchCatalog($ids === [$visibleId], 'Public SQL leaked a disabled supplier tier.');
checkBranchCatalog(ProductPrice::find($hiddenId) !== null, 'Hidden price was deleted.');
checkBranchCatalog((int) $offer->lowest_price === 5000, 'Hidden tier affected the price aggregate.');
$legacyId = DB::table('product_prices')->insertGetId([
    'product_id' => $source->id, 'product_supplier_id' => null,
    'price_type_id' => 1, 'tier_index' => 3, 'price' => 2, 'is_active' => true,
]);
SupplierTierDiscount::create(['supplier_id' => $primary->id, 'tier_index' => 3, 'is_active' => false]);
checkBranchCatalog(!ProductPrice::query()->activeAndValid()->whereKey($legacyId)->exists(), 'Legacy owner price bypassed supplier visibility.');
DB::table('supplier_tier_discount')->where('supplier_id', $user->selectedSupplier)->where('tier_index', 1)->update(['is_active' => false]);
$availability->forget($user->selectedSupplier);
checkBranchCatalog($availability->forSupplier($user->selectedSupplier)[1], 'Invalid stored setting disabled the reference.');
checkBranchCatalog(ProductPrice::query()->activeAndValid()->whereKey($visibleId)->exists(), 'Invalid stored setting hid reference prices in SQL.');

$form->loadTierSettings();
$form->tierSettings[0]['is_active'] = true;
$form->tierSettings[3]['is_active'] = true;
$form->save();
checkBranchCatalog($offer->effective_tier_enabled === [0 => true, 1 => true, 2 => false, 3 => true], 'Reactivation did not restore product-level choices.');
checkBranchCatalog(ProductPrice::query()->activeAndValid()->whereKey($hiddenId)->exists(), 'Reactivation did not restore the stored price.');
checkBranchCatalog($productFlagsBefore === DB::table('product_supplier')->where('id', $offer->id)->value('tier_enabled'), 'Reactivation overwrote product choices.');

$form->tierSettings[3]['tier_index'] = 99;
try {
    $form->save();
    throw new RuntimeException('Forged tier index was accepted.');
} catch (ValidationException $exception) {
    checkBranchCatalog(isset($exception->errors()['tierSettings.3.tier_index']), 'Forged tier index was not validated.');
}

$form->loadTierSettings();
$form->resetToDefaults();
checkBranchCatalog(array_column($form->tierSettings, 'is_active') === array_values(SupplierTierDiscount::DEFAULT_ENABLED), 'Reset defaults enabled extra tiers.');
checkBranchCatalog($availability->forSupplier($user->selectedSupplier)[0], 'Reset changed persisted settings before save.');

$queryCount = 0;
$availability->forget((int) $primary->id);
$availability->forget((int) $independent->id);
DB::listen(static function () use (&$queryCount): void { $queryCount++; });
$availability->prime([$primary->id, $independent->id]);
for ($i = 0; $i < 20; $i++) {
    $availability->forSupplier((int) $primary->id);
    $offer->effective_tier_enabled;
}
checkBranchCatalog($queryCount === 1, 'Visibility performed queries per product or tier.');
$tierCheckCompleted = true;
echo "PASS: registration/approval carton-only and check-off defaults, global activation without product opt-in, product opt-in/out, supplier ceiling, fixed reference, settings persistence, stale tabs, preserved prices/overrides, SQL filtering, aggregates, cache invalidation, branch isolation, reactivation and batched reads.\n";
