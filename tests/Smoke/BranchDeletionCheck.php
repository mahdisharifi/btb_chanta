<?php

// Run: php tests/Smoke/BranchDeletionCheck.php (isolated SQLite; no application data).
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierBranchDeletionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

$passed = false;
register_shutdown_function(static function () use (&$passed): void {
    if (!$passed) {
        exit(1);
    }
});
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'database.default' => 'branch_deletion_check',
    'database.connections.branch_deletion_check' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
    'cache.default' => 'array',
    'session.driver' => 'array',
]);
Queue::fake();
Auth::setUser((new User)->forceFill(['id' => 1]));

function checkBranchDeletion(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

Schema::create('suppliers', function ($table): void {
    $table->id(); $table->unsignedBigInteger('user_id');
    $table->foreignId('parent_supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
    $table->string('branch_name', 120)->nullable(); $table->string('company_name')->nullable();
    $table->string('province')->nullable(); $table->boolean('is_active')->default(true);
    $table->timestamps(); $table->softDeletes();
    $table->unique(['parent_supplier_id', 'branch_name'], 'supplier_parent_branch_name_unique');
});
Schema::create('products', function ($table): void {
    $table->id(); $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->cascadeOnDelete();
    $table->string('title'); $table->timestamps(); $table->softDeletes();
});
Schema::create('product_supplier', function ($table): void {
    $table->id(); $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->decimal('foreign_price_amount', 20, 4)->nullable();
    $table->boolean('is_active')->default(true); $table->timestamps(); $table->softDeletes();
});
Schema::create('check_periods', function ($table): void {
    $table->id(); $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
});
Schema::create('product_prices', function ($table): void {
    $table->id(); $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignId('product_supplier_id')->nullable()->constrained('product_supplier')->cascadeOnDelete();
    $table->foreignId('check_period_id')->nullable()->constrained('check_periods')->nullOnDelete();
    $table->unsignedBigInteger('price'); $table->boolean('is_active')->default(true);
});
Schema::create('price_histories', function ($table): void {
    $table->id(); $table->foreignId('product_supplier_id')->constrained('product_supplier')->cascadeOnDelete();
});
Schema::create('supplier_branch_product_transfers', function ($table): void {
    $table->id(); $table->foreignId('source_supplier_id')->constrained('suppliers')->cascadeOnDelete();
    $table->foreignId('target_supplier_id')->constrained('suppliers')->cascadeOnDelete(); $table->string('status');
});
Schema::create('catalog_templates', function ($table): void {
    $table->id(); $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
});
(require database_path('migrations/2026_10_29_090000_create_catalog_snapshot_revisions_table.php'))->up();
(require database_path('migrations/2026_11_11_130000_limit_branch_name_uniqueness_to_active_suppliers.php'))->up();

$root = DB::table('suppliers')->insertGetId(['user_id' => 1, 'company_name' => 'Root']);
$branch = DB::table('suppliers')->insertGetId(['user_id' => 1, 'parent_supplier_id' => $root, 'branch_name' => 'Reusable']);
DB::table('suppliers')->where('id', $branch)->update(['is_active' => false]);
$sibling = DB::table('suppliers')->insertGetId(['user_id' => 1, 'parent_supplier_id' => $root, 'branch_name' => 'Reusable']);
try {
    DB::table('suppliers')->insert(['user_id' => 1, 'parent_supplier_id' => $root, 'branch_name' => 'Reusable']);
    throw new RuntimeException('Duplicate active branch accepted');
} catch (QueryException) {
    // Active duplicate must still be rejected by the database.
}
DB::table('suppliers')->where('id', $branch)->update(['is_active' => true, 'deleted_at' => now()]);
$replica = Supplier::findOrFail($sibling)->replicate();
$replica->branch_name = 'Replicated'; $replica->save();
checkBranchDeletion($replica->exists, 'Generated name column prevented replication');

$product = DB::table('products')->insertGetId(['supplier_id' => $branch, 'title' => 'Preserved product']);
$deletedProduct = DB::table('products')->insertGetId(['supplier_id' => $branch, 'title' => 'Preserved archived product', 'deleted_at' => now()]);
$offer = DB::table('product_supplier')->insertGetId(['supplier_id' => $branch, 'product_id' => $product, 'deleted_at' => now(), 'is_active' => false]);
$otherOffer = DB::table('product_supplier')->insertGetId(['supplier_id' => $sibling, 'product_id' => $product]);
DB::table('product_prices')->insert([
    ['product_id' => $product, 'product_supplier_id' => $offer, 'price' => 1200, 'is_active' => false],
    ['product_id' => $product, 'product_supplier_id' => $otherOffer, 'price' => 1500, 'is_active' => true],
    ['product_id' => $product, 'product_supplier_id' => null, 'price' => 1000, 'is_active' => false],
]);
DB::table('price_histories')->insert(['product_supplier_id' => $offer]);
DB::table('catalog_templates')->insert(['supplier_id' => $branch]);
DB::table('supplier_branch_product_transfers')->insert(['source_supplier_id' => $root, 'target_supplier_id' => $branch, 'status' => 'pending']);
$primary = Supplier::findOrFail($root);
$service = app(SupplierBranchDeletionService::class);
$summary = $service->summary($primary, $branch);
checkBranchDeletion($summary['prices'] === 2 && $summary['products'] === 2, 'Preview counted other supplier prices or skipped archived products');
try {
    $service->delete($primary, $branch);
    throw new RuntimeException('Unresolved inactive prices did not block deletion');
} catch (ValidationException) {
    checkBranchDeletion(DB::table('suppliers')->where('id', $branch)->exists(), 'Rejected deletion was not atomic');
}
$period = DB::table('check_periods')->insertGetId(['supplier_id' => $branch]);
DB::table('product_prices')->where('product_supplier_id', $otherOffer)->update(['check_period_id' => $period]);
try {
    $service->delete($primary, $branch, true);
    throw new RuntimeException('External period dependency was ignored');
} catch (ValidationException) {
    checkBranchDeletion(DB::table('product_prices')->where('product_supplier_id', $offer)->exists(), 'Dependency rejection removed prices');
}
DB::table('product_prices')->where('product_supplier_id', $otherOffer)->update(['check_period_id' => null]);
$service->delete($primary, $branch, true);
checkBranchDeletion(!DB::table('suppliers')->where('id', $branch)->exists(), 'Branch was only soft deleted');
checkBranchDeletion(DB::table('products')->whereIn('id', [$product, $deletedProduct])->whereNull('supplier_id')->count() === 2, 'Products were deleted instead of detached');
checkBranchDeletion(DB::table('product_prices')->count() === 1 && DB::table('product_prices')->value('price') === 1500, 'Another supplier price changed');
checkBranchDeletion(DB::table('price_histories')->count() === 0 && DB::table('catalog_templates')->count() === 0, 'Branch dependencies were not removed');
checkBranchDeletion(DB::table('supplier_branch_product_transfers')->count() === 0 && DB::table('check_periods')->count() === 0, 'Transfer/period cleanup failed');
$foreignBranch = DB::table('suppliers')->insertGetId(['user_id' => 1, 'parent_supplier_id' => $root, 'branch_name' => 'Foreign amount only']);
$foreignOffer = DB::table('product_supplier')->insertGetId(['supplier_id' => $foreignBranch, 'product_id' => $product, 'foreign_price_amount' => 25]);
try {
    $service->delete($primary, $foreignBranch);
    throw new RuntimeException('Foreign amount without matrix prices did not block deletion');
} catch (ValidationException) {
}
DB::table('product_supplier')->where('id', $foreignOffer)->update(['foreign_price_amount' => null]);
$service->delete($primary, $foreignBranch);
checkBranchDeletion(!DB::table('suppliers')->where('id', $foreignBranch)->exists(), 'Resolved, unpriced branch could not be deleted');
Auth::setUser((new User)->forceFill(['id' => 2]));
try {
    $service->summary($primary, $sibling);
    throw new RuntimeException('Another owner could review branch deletion');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    checkBranchDeletion($exception->getStatusCode() === 403, 'Ownership denial was not explicit');
}
Auth::setUser((new User)->forceFill(['id' => 1]));
try {
    $service->delete($primary, $root, true);
    throw new RuntimeException('Primary supplier deletion was permitted');
} catch (Illuminate\Database\Eloquent\ModelNotFoundException) {
}
$passed = true;
echo "PASS: active-name uniqueness, replication, deletion preview, unresolved-price/dependency blocks, hard deletion, product and sibling-price preservation, dependent cleanup, primary protection.\n";
