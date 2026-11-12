<?php

use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierBranchCatalogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'database.default' => 'branch_catalog_check',
    'database.connections.branch_catalog_check' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
    'cache.default' => 'array', 'session.driver' => 'array',
]);
Queue::fake();
Auth::setUser((new User)->forceFill(['id' => 1]));
Schema::create('price_types', function ($table): void {
    $table->id(); $table->string('name');
});
Schema::create('check_periods', function ($table): void {
    $table->id(); $table->string('label')->nullable(); $table->unsignedInteger('days')->default(0);
});
Schema::create('suppliers', function ($table): void {
    $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('parent_supplier_id')->nullable();
    $table->boolean('is_active')->default(true); $table->timestamps(); $table->softDeletes();
});
Schema::create('products', function ($table): void {
    $table->id(); $table->unsignedBigInteger('supplier_id')->nullable(); $table->unsignedBigInteger('family_product_id')->nullable();
    $table->string('title'); $table->string('status')->default('active'); $table->timestamps(); $table->softDeletes();
});
Schema::create('product_supplier', function ($table): void {
    $table->id(); $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->unsignedBigInteger('supplier_id'); $table->boolean('is_active')->default(false);
    $table->decimal('branch_price_percentage', 20, 12)->default(0); $table->timestamps(); $table->softDeletes();
});
Schema::create('product_prices', function ($table): void {
    $table->id(); $table->unsignedBigInteger('product_id');
    $table->foreignId('product_supplier_id')->nullable()->constrained('product_supplier')->cascadeOnDelete();
    $table->unsignedBigInteger('branch_source_price_id')->nullable(); $table->unsignedBigInteger('price_type_id')->default(1);
    $table->unsignedInteger('tier_index')->default(0); $table->unsignedBigInteger('check_period_id')->nullable();
    $table->unsignedBigInteger('price'); $table->boolean('is_active')->default(true);
    $table->timestamp('valid_from')->nullable(); $table->timestamp('valid_until')->nullable(); $table->timestamps();
});
Schema::create('supplier_branch_product_transfers', function ($table): void {
    $table->id(); $table->unsignedBigInteger('target_supplier_id'); $table->unsignedBigInteger('destination_product_id');
    $table->string('status'); $table->timestamp('decided_at')->nullable(); $table->timestamps();
});
(require database_path('migrations/2026_10_29_090000_create_catalog_snapshot_revisions_table.php'))->up();

function checkBranchCatalog(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$rootId = DB::table('suppliers')->insertGetId(['user_id' => 1]);
$branchId = DB::table('suppliers')->insertGetId(['user_id' => 1, 'parent_supplier_id' => $rootId]);
$foreignId = DB::table('suppliers')->insertGetId(['user_id' => 2, 'parent_supplier_id' => $rootId]);
$sourceId = DB::table('products')->insertGetId(['supplier_id' => $rootId, 'family_product_id' => 1, 'title' => 'Approved source']);
$copyId = DB::table('products')->insertGetId(['supplier_id' => $branchId, 'family_product_id' => $sourceId, 'title' => 'Approved copy']);
$mainOfferId = DB::table('product_supplier')->insertGetId(['supplier_id' => $rootId, 'product_id' => $sourceId]);
$offerId = DB::table('product_supplier')->insertGetId(['supplier_id' => $branchId, 'product_id' => $copyId, 'branch_price_percentage' => 10]);
$priceId = DB::table('product_prices')->insertGetId(['product_id' => $sourceId, 'product_supplier_id' => $mainOfferId, 'price' => 1000]);
$branchPriceId = DB::table('product_prices')->insertGetId(['product_id' => $copyId, 'product_supplier_id' => $offerId, 'branch_source_price_id' => $priceId, 'price' => 1100, 'is_active' => false]);
DB::table('supplier_branch_product_transfers')->insert(['target_supplier_id' => $branchId, 'destination_product_id' => $copyId, 'status' => 'accepted']);
$service = app(SupplierBranchCatalogService::class);
$primary = Supplier::findOrFail($rootId);
$offer = ProductSupplier::findOrFail($offerId);
try {
    (new ReflectionMethod(App\Livewire\Supplier\Products\Edit::class, 'editableOwnedProduct'))->invoke(
        new App\Livewire\Supplier\Products\Edit,
        App\Models\Product::findOrFail($copyId),
        Supplier::findOrFail($branchId),
    );
    throw new RuntimeException('Approved branch copy remained editable');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    checkBranchCatalog($exception->getStatusCode() === 403, 'Approved product protection did not reject editing');
}
$comparison = $service->comparison($offer, $primary);
checkBranchCatalog($comparison['state'] === 'synced', 'An intentional percentage difference was treated as drift');
checkBranchCatalog($comparison['rows'][0]['difference'] === 100 && $comparison['rows'][0]['percent'] === 10.0, 'Difference calculation failed');
DB::table('product_prices')->where('id', $priceId)->update(['price' => 2000]);
try {
    $service->synchronize($primary, $branchId, [$offerId => $comparison['fingerprint']]);
    throw new RuntimeException('Stale preview was accepted');
} catch (ValidationException) {
}
checkBranchCatalog((int) DB::table('product_prices')->where('id', $branchPriceId)->value('price') === 1100, 'Rejected preview changed a price');
$fresh = $service->comparison($offer->fresh(), $primary);
checkBranchCatalog($fresh['state'] === 'drift', 'Outdated branch price was not identified');
$service->synchronize($primary, $branchId, [$offerId => $fresh['fingerprint']]);
checkBranchCatalog((int) DB::table('product_prices')->where('id', $branchPriceId)->value('price') === 2200, 'Sync failed');
checkBranchCatalog(!DB::table('product_supplier')->where('id', $offerId)->value('is_active')
    && !DB::table('product_prices')->where('id', $branchPriceId)->value('is_active'), 'Sync published an inactive offer or price');
try {
    $service->branch($primary, $foreignId);
    throw new RuntimeException('Another owner branch was accepted');
} catch (Illuminate\Database\Eloquent\ModelNotFoundException) {
}
try {
    $service->remove($primary, $branchId, $mainOfferId);
    throw new RuntimeException('Another branch offer was removed');
} catch (Illuminate\Database\Eloquent\ModelNotFoundException) {
}
$service->remove($primary, $branchId, $offerId);
checkBranchCatalog(DB::table('products')->count() === 2, 'Removal deleted product content');
checkBranchCatalog(DB::table('product_prices')->count() === 1 && DB::table('product_supplier')->count() === 1, 'Removal affected main branch prices or retained branch prices');
checkBranchCatalog(DB::table('supplier_branch_product_transfers')->value('status') === 'pending', 'Removed offer cannot be added again');
echo "PASS: matrix comparison, intentional differences, stale preview rejection, sync, inactive-state preservation, ownership, offer-only removal and re-add eligibility.\n";
