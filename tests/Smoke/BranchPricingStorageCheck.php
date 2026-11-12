<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Run against a disposable MariaDB instance, never the application's port:
// php tests/Smoke/BranchPricingStorageCheck.php --port=13307
$options = getopt('', ['port:']);
$port = (int) ($options['port'] ?? 0);
if ($port < 1024 || $port > 65535 || $port === 3306) {
    fwrite(STDERR, "Pass the port of a disposable MariaDB instance (not 3306).\n");
    exit(1);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$completed = false;
register_shutdown_function(static function () use (&$completed): void {
    if (!$completed) {
        exit(1);
    }
});

$connection = config('database.connections.mysql');
$connection['url'] = null;
$connection['host'] = '127.0.0.1';
$connection['port'] = $port;
$connection['database'] = '';
$connection['prefix'] = '';
$connection['options'][PDO::ATTR_EMULATE_PREPARES] = false;
config(['database.connections.branch_storage_server' => $connection]);
$server = DB::connection('branch_storage_server');
$database = 'branch_storage_check_'.bin2hex(random_bytes(6));
$created = false;
$exitCode = 0;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $version = (string) $server->selectOne('SELECT VERSION() AS version')->version;
    $assert(str_contains($version, 'MariaDB'), 'This regression requires a MariaDB server.');
    $assert((int) $server->selectOne('SELECT @@port AS port')->port === $port, 'Unexpected server port.');
    $server->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $created = true;
    $connection['database'] = $database;
    config(['database.connections.branch_storage_check' => $connection]);
    DB::setDefaultConnection('branch_storage_check');

    Schema::create('suppliers', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('parent_supplier_id')->nullable();
        $table->string('branch_name', 120)->nullable();
        $table->boolean('is_active')->default(true);
        $table->softDeletes();
        $table->unsignedBigInteger('supplier_tier_discount_revision')->default(1);
        $table->unique(['parent_supplier_id', 'branch_name'], 'supplier_parent_branch_name_unique');
        $table->index(['user_id', 'parent_supplier_id']);
        $table->foreign('parent_supplier_id')->references('id')->on('suppliers');
    });
    Schema::create('offer_storage_check', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('supplier_id')->constrained('suppliers');
        $table->unsignedInteger('price');
    });
    DB::table('suppliers')->insert([
        ['id' => 1, 'user_id' => 1, 'parent_supplier_id' => null, 'branch_name' => null, 'is_active' => true],
        ['id' => 2, 'user_id' => 2, 'parent_supplier_id' => null, 'branch_name' => null, 'is_active' => true],
        ['id' => 3, 'user_id' => 1, 'parent_supplier_id' => 1, 'branch_name' => 'Shared name', 'is_active' => true],
        ['id' => 4, 'user_id' => 2, 'parent_supplier_id' => 2, 'branch_name' => 'Shared name', 'is_active' => true],
    ]);
    DB::table('offer_storage_check')->insert(['supplier_id' => 3, 'price' => 876543]);

    $nameMigration = require database_path('migrations/2026_11_11_130000_limit_branch_name_uniqueness_to_active_suppliers.php');
    $pricingMigration = require database_path('migrations/2026_11_11_160000_add_branch_pricing_mode_to_suppliers.php');
    $repairMigration = require database_path('migrations/2026_11_12_130000_store_active_branch_name_on_mariadb.php');
    $nameMigration->up();
    $pricingMigration->up();
    $assert(str_contains(DB::selectOne("SHOW COLUMNS FROM suppliers WHERE Field='active_branch_name'")->Extra, 'STORED'), 'Fresh installs must use stored generated names.');
    $assert(DB::table('suppliers')->where('id', 1)->value('branch_pricing_mode') === 'independent', 'Primary default changed.');
    $assert(DB::table('suppliers')->where('id', 3)->value('branch_pricing_mode') === 'percentage', 'Existing branch pricing mode was lost.');

    // Legacy installed databases have VIRTUAL storage. Exercise the actual
    // repair migration rather than merely testing an equivalent SQL statement.
    if (preg_match('/^10\.4\.\d+.*MariaDB/i', $version)) {
        DB::statement('ALTER TABLE suppliers DROP INDEX suppliers_active_branch_name_unique, DROP COLUMN active_branch_name, ADD COLUMN active_branch_name VARCHAR(120) GENERATED ALWAYS AS (CASE WHEN is_active=1 AND deleted_at IS NULL THEN branch_name ELSE NULL END) VIRTUAL AFTER supplier_tier_discount_revision, ADD UNIQUE INDEX suppliers_active_branch_name_unique (parent_supplier_id, active_branch_name), ALGORITHM=COPY');
    }
    $snapshot = static fn (): string => json_encode(DB::table('suppliers')->orderBy('id')->get(), JSON_THROW_ON_ERROR);
    $before = $snapshot();
    $repairMigration->up();
    $repairMigration->up();
    $repairMigration->down();
    $assert($before === $snapshot(), 'Repair changed supplier fields or identifiers.');
    $assert(str_contains(DB::selectOne("SHOW COLUMNS FROM suppliers WHERE Field='active_branch_name'")->Extra, 'STORED'), 'Legacy names were not repaired, or rollback restored unsafe storage.');
    $assert((int) DB::table('offer_storage_check')->value('price') === 876543, 'A linked offer changed.');
    $assert((int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='suppliers'")->total === 2, 'Supplier foreign keys were lost.');

    // Reuse native prepared statements and alternate parents in the same
    // transaction: the FX job repeats these locked branch reads per product.
    DB::transaction(function () use ($assert): void {
        $statement = DB::connection()->getPdo()->prepare('select * from `suppliers` where `parent_supplier_id` = ? and `user_id` = ? and `is_active` = ? and `suppliers`.`deleted_at` is null order by `id` asc for update');
        for ($iteration = 0; $iteration < 20; ++$iteration) {
            foreach ([1, 2] as $parentId) {
                $statement->execute([$parentId, $parentId, 1]);
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                $assert(count($rows) === 1, 'Locked branch query returned an unexpected family.');
                $assert($rows[0]['branch_pricing_mode'] === 'percentage', 'Locked query read incorrect column values.');
            }
        }
    });

    DB::beginTransaction();
    try {
        $inactiveId = DB::table('suppliers')->insertGetId(['user_id' => 1, 'parent_supplier_id' => 1, 'branch_name' => 'Shared name', 'is_active' => false]);
        $deletedId = DB::table('suppliers')->insertGetId(['user_id' => 1, 'parent_supplier_id' => 1, 'branch_name' => 'Shared name', 'is_active' => true, 'deleted_at' => now()]);
        $assert(DB::table('suppliers')->where('id', $inactiveId)->value('active_branch_name') === null, 'Inactive names still reserve uniqueness.');
        $assert(DB::table('suppliers')->where('id', $deletedId)->value('active_branch_name') === null, 'Deleted names still reserve uniqueness.');
        $duplicateBlocked = false;
        try {
            DB::table('suppliers')->where('id', $inactiveId)->update(['is_active' => true]);
        } catch (QueryException $exception) {
            $duplicateBlocked = ($exception->errorInfo[1] ?? null) === 1062;
        }
        $assert($duplicateBlocked, 'Duplicate active branch name was accepted.');
        DB::table('suppliers')->where('id', 3)->update(['is_active' => false]);
        DB::table('suppliers')->where('id', $inactiveId)->update(['is_active' => true]);
        $assert(DB::table('suppliers')->where('id', $inactiveId)->value('active_branch_name') === 'Shared name', 'Deactivation did not release the generated name.');
    } finally {
        DB::rollBack();
    }
    $assert($before === $snapshot(), 'Name constraint checks changed their fixtures.');
    $pricingMigration->down();
    $pricingMigration->up();
    $assert($before === $snapshot(), 'Pricing migration round trip lost supplier data.');
    $upgradeResult = preg_match('/^10\.4\.\d+.*MariaDB/i', $version) ? 'legacy upgrade verified' : 'legacy 10.4 upgrade skipped on this version';
    echo "PASS: fresh generated storage ({$upgradeResult}), native locked reads, unchanged suppliers/offers/foreign keys, active-name uniqueness, pricing migration rollback ({$version}).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    DB::disconnect('branch_storage_check');
    if ($created) {
        $server->statement("DROP DATABASE `{$database}`");
    }
}
$completed = true;
exit($exitCode);
