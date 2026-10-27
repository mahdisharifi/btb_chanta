<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SupplierSlugMigrationRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('suppliers', fn (Blueprint $table) => $table->id());
        DB::table('suppliers')->insert(['id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_fresh_install_and_repeated_reconciliation_preserve_history(): void
    {
        $this->createMigration()->up();
        DB::table('supplier_slug_redirects')->insert([
            'supplier_id' => 1, 'old_slug' => 'old', 'new_slug' => 'current',
        ]);
        $this->repairMigration()->up();
        $this->repairMigration()->up();
        $this->assertSame('current', DB::table('supplier_slug_redirects')->value('new_slug'));
        $this->assertTrue(Schema::hasIndex('supplier_slug_redirects', ['old_slug'], 'unique'));
    }

    public function test_existing_unrecorded_table_is_adopted_and_both_migrations_are_recorded(): void
    {
        $this->createLegacyTable();
        $this->runMigrations();
        $this->assertDatabaseCount('migrations', 2);
        $this->assertDatabaseHas('supplier_slug_redirects', ['old_slug' => 'old', 'supplier_id' => 1, 'new_slug' => null, 'created_by' => null]);
        $this->runMigrations();
        $this->assertDatabaseCount('migrations', 2);
        $this->assertDatabaseCount('supplier_slug_redirects', 1);
    }

    public function test_recorded_old_create_migration_is_upgraded_by_forward_migration(): void
    {
        $this->createLegacyTable();
        $repository = app('migration.repository');
        $repository->createRepository();
        $repository->log('2026_10_20_120000_create_supplier_slug_redirects_table', 1);
        $this->runMigrations();
        $this->assertTrue(Schema::hasColumn('supplier_slug_redirects', 'new_slug'));
        $this->assertTrue(Schema::hasColumn('supplier_slug_redirects', 'created_by'));
        $this->assertDatabaseCount('migrations', 2);
        $this->assertDatabaseCount('supplier_slug_redirects', 1);
    }

    public function test_missing_unique_index_is_added_without_removing_rows(): void
    {
        $this->createLegacyTable(false);
        $this->repairMigration()->up();
        $this->assertTrue(Schema::hasIndex('supplier_slug_redirects', ['old_slug'], 'unique'));
        $this->assertDatabaseCount('supplier_slug_redirects', 1);
    }

    public function test_duplicates_stop_repair_before_schema_changes(): void
    {
        $this->createLegacyTable(false);
        DB::table('supplier_slug_redirects')->insert(['supplier_id' => 1, 'old_slug' => 'old']);
        try {
            $this->repairMigration()->up();
            $this->fail('Duplicate history should require manual review.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate old_slug', $exception->getMessage());
            $this->assertFalse(Schema::hasColumn('supplier_slug_redirects', 'new_slug'));
            $this->assertDatabaseCount('supplier_slug_redirects', 2);
        }
    }

    public function test_incompatible_existing_table_is_not_replaced(): void
    {
        Schema::create('supplier_slug_redirects', fn (Blueprint $table) => $table->id());
        $this->createMigration()->up();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing required column');
        $this->repairMigration()->up();
    }

    public function test_rollback_retains_adopted_history_and_audit_columns(): void
    {
        $this->createLegacyTable();
        $this->repairMigration()->up();
        $this->repairMigration()->down();
        $this->createMigration()->down();
        $this->assertTrue(Schema::hasColumn('supplier_slug_redirects', 'new_slug'));
        $this->assertDatabaseCount('supplier_slug_redirects', 1);
    }

    protected function createLegacyTable(bool $unique = true): void
    {
        Schema::create('supplier_slug_redirects', function (Blueprint $table) use ($unique): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug');
            if ($unique) {
                $table->unique('old_slug');
            }
            $table->timestamp('created_at')->useCurrent();
        });
        DB::table('supplier_slug_redirects')->insert(['supplier_id' => 1, 'old_slug' => 'old']);
    }

    protected function createMigration(): object
    {
        return require database_path('migrations/2026_10_20_120000_create_supplier_slug_redirects_table.php');
    }

    protected function repairMigration(): object
    {
        return require database_path('migrations/2026_10_21_120000_reconcile_supplier_slug_redirects_table.php');
    }

    protected function runMigrations(): void
    {
        $repository = app('migration.repository');
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
        app('migrator')->run([
            database_path('migrations/2026_10_20_120000_create_supplier_slug_redirects_table.php'),
            database_path('migrations/2026_10_21_120000_reconcile_supplier_slug_redirects_table.php'),
        ], ['realpath' => true]);
    }
}
