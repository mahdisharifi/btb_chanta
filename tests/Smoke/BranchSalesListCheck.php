<?php

use App\Enums\ProductStatus;
use App\Livewire\Supplier\Products\EditPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierBranchProductTransfer;
use App\Services\SupplierBranchPriceSyncService;
use App\Services\SupplierBranchPricingService;
use App\Services\SupplierBranchTransferService;
use App\Services\SupplierSalesListService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/BranchCatalogCheck.php';

$salesListCheckCompleted = false;
register_shutdown_function(function () use (&$salesListCheckCompleted): void {
    if (!$salesListCheckCompleted) {
        exit(1);
    }
});

Schema::table('suppliers', function (Blueprint $table): void {
    $table->string('branch_pricing_mode')->default('percentage');
    $table->decimal('branch_price_percentage', 20, 12)->nullable();
});
Schema::table('products', function (Blueprint $table): void {
    $table->boolean('is_active')->default(true);
});
Schema::table('product_supplier', function (Blueprint $table): void {
    $table->string('status')->default('inactive');
    $table->integer('sort_order')->nullable();
    $table->timestamp('activated_at')->nullable();
    $table->timestamp('deactivated_at')->nullable();
    $table->unique(['product_id', 'supplier_id']);
});
Schema::table('product_prices', function (Blueprint $table): void {
    $table->unsignedInteger('normalized_check_period_id')->default(0);
    $table->decimal('percentage_override')->nullable();
    $table->string('calculation_mode')->nullable();
    $table->string('warranty_type')->nullable();
    $table->integer('warranty_duration')->nullable();
    $table->unsignedBigInteger('warranty_price')->nullable();
    $table->text('description')->nullable();
    $table->unique(['product_supplier_id', 'price_type_id', 'tier_index', 'normalized_check_period_id']);
});
Schema::table('supplier_branch_product_transfers', function (Blueprint $table): void {
    $table->unsignedBigInteger('source_supplier_id')->nullable();
    $table->unsignedBigInteger('source_product_id')->nullable();
});

$independentId = DB::table('suppliers')->insertGetId([
    'user_id' => 1, 'parent_supplier_id' => $rootId, 'branch_pricing_mode' => 'independent',
]);
$linkedId = DB::table('suppliers')->insertGetId([
    'user_id' => 1, 'parent_supplier_id' => $rootId, 'branch_pricing_mode' => 'percentage',
    'branch_price_percentage' => 10,
]);
$independent = Supplier::findOrFail($independentId);
$linked = Supplier::findOrFail($linkedId);
$source = Product::findOrFail($sourceId);
$beforeProducts = Product::count();
$transferId = DB::table('supplier_branch_product_transfers')->insertGetId([
    'source_supplier_id' => $rootId, 'target_supplier_id' => $independentId,
    'source_product_id' => $sourceId, 'destination_product_id' => 0, 'status' => 'pending',
]);
$transfers = app(SupplierBranchTransferService::class);
$accepted = $transfers->accept($primary, $independent, $transferId);
checkBranchCatalog($accepted->id === $source->id && Product::count() === $beforeProducts, 'Acceptance copied or changed the approved product');
$independentOffer = ProductSupplier::where('supplier_id', $independentId)->where('product_id', $sourceId)->firstOrFail();
checkBranchCatalog(!$independentOffer->is_active && $independentOffer->prices()->count() === 0, 'Acceptance published or priced an independent offer');
checkBranchCatalog($transfers->accept($primary, $independent, $transferId)->id === $sourceId, 'Repeated acceptance changed the product identity');
checkBranchCatalog(app(SupplierSalesListService::class)->add($source, $independent)->id === $independentOffer->id, 'Selection and transfer created different offers');

$linkedOffer = app(SupplierSalesListService::class)->add($source, $linked);
checkBranchCatalog((int) $linkedOffer->prices()->firstOrFail()->price === 2200, 'New shared-product offer did not inherit the branch percentage');
$sync = app(SupplierBranchPriceSyncService::class);
checkBranchCatalog(!$sync->usesPrimaryPricing($source, $independent), 'Independent pricing form was restricted');
checkBranchCatalog($sync->usesPrimaryPricing($source, $linked), 'Linked shared product was not recognized');
$editor = new EditPrice;
$editor->product = $source;
$editor->productSupplier = $independentOffer;
(new ReflectionMethod(EditPrice::class, 'resolveOwnedProductAndOffer'))->invoke($editor, $independent);
checkBranchCatalog($editor->productSupplier->id === $independentOffer->id, 'Canonical product was rejected by the branch price write scope');

$manualPriceId = DB::table('product_prices')->insertGetId([
    'product_id' => $sourceId, 'product_supplier_id' => $independentOffer->id, 'price' => 7777,
]);
DB::table('product_prices')->where('id', $priceId)->update(['price' => 3000]);
$sync->syncBranchesForPrimaryPrice(ProductPrice::findOrFail($priceId));
checkBranchCatalog((int) $linkedOffer->prices()->firstOrFail()->price === 3300, 'Automatic fan-out missed the shared-product branch pivot');
checkBranchCatalog((int) ProductPrice::findOrFail($manualPriceId)->price === 7777, 'Automatic fan-out overwrote independent pricing');

$settings = app(SupplierBranchPricingService::class);
$settings->configure($primary, $independentId, 'percentage', '20');
checkBranchCatalog((int) ProductPrice::findOrFail($manualPriceId)->price === 3600, 'Switching to percentage duplicated or failed to rebind the existing matrix');
checkBranchCatalog($independentOffer->prices()->count() === 1, 'Mode switch duplicated a matrix cell');
$settings->configure($primary, $independentId, 'independent', null);
checkBranchCatalog(ProductPrice::findOrFail($manualPriceId)->branch_source_price_id === null, 'Independent mode retained the source dependency');
DB::table('product_prices')->where('id', $priceId)->update(['price' => 4000]);
$sync->syncPrimaryMatrix($source, $primary, [ProductPrice::findOrFail($priceId)]);
checkBranchCatalog((int) ProductPrice::findOrFail($manualPriceId)->price === 3600, 'Full primary matrix sync changed independent prices');
checkBranchCatalog((int) $linkedOffer->prices()->firstOrFail()->price === 4400, 'Full primary matrix sync missed a shared-product offer');
$sync->removeBranchesForDeletedPrimaryPrice(ProductPrice::findOrFail($priceId));
checkBranchCatalog($linkedOffer->prices()->count() === 0 && ProductPrice::find($manualPriceId), 'Source deletion did not isolate independent and linked branches');

try {
    app(SupplierSalesListService::class)->add($source, Supplier::findOrFail($foreignId));
    throw new RuntimeException('Another owner could add a product to a branch');
} catch (HttpException $exception) {
    checkBranchCatalog($exception->getStatusCode() === 403, 'Wrong ownership response');
}
$draftId = DB::table('products')->insertGetId([
    'supplier_id' => $rootId, 'family_product_id' => 9999, 'title' => 'Private draft',
    'status' => ProductStatus::DRAFT->value, 'is_active' => false,
]);
try {
    app(SupplierSalesListService::class)->add(Product::findOrFail($draftId), $independent->fresh());
    throw new RuntimeException('An unrelated private draft was added to sales');
} catch (HttpException $exception) {
    checkBranchCatalog($exception->getStatusCode() === 404, 'Private draft was exposed');
}

$salesListCheckCompleted = true;
echo "PASS: same-product pivot acceptance, idempotent sales-list addition, price access scope, independent pricing, global percentage, both fan-out paths, source deletion isolation and ownership.\n";
