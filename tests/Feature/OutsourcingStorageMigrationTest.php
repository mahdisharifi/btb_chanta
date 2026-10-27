<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ProductCompletionStorageGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OutsourcingStorageMigrationTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== 'b2b_marketplace_test') {
            throw new \RuntimeException('Storage tests require the isolated test database.');
        }

        return $app;
    }

    public function test_conversion_and_guard_support_prefixes_preserve_data_and_allow_safe_reruns(): void
    {
        $original = DB::getDefaultConnection();
        $configuration = config('database.connections.mysql');
        unset($configuration['url'], $configuration['read'], $configuration['write']);
        config(['database.connections.outsourcing_engine_test' => [
            ...$configuration, 'prefix' => 'engine_test_'.uniqid().'_',
        ]]);
        DB::setDefaultConnection('outsourcing_engine_test');
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $tables = [
            'products', 'attribute_values', 'attribute_product', 'product_galleries',
            'commission_rules', 'commission_rule_items', 'product_completion_tasks',
            'product_completion_submissions', 'product_completion_revisions', 'product_completion_media',
            'product_completion_submission_media', 'product_completion_audits', 'product_completion_operations',
            'product_completion_commissions', 'product_completion_task_batches', 'outsourcing_settlements',
            'outsourcing_settlement_items', 'outsourcing_settlement_payments',
        ];
        $created = [];

        try {
            if ($connection->getDatabaseName() !== 'b2b_marketplace_test'
                || $connection->selectOne('SELECT DATABASE() AS current_database', [], false)->current_database !== 'b2b_marketplace_test') {
                throw new \RuntimeException('Resolved connection must use the isolated test database.');
            }
            foreach ($tables as $table) {
                $created[] = $table;
                $schema->create($table, function (Blueprint $blueprint): void {
                    $blueprint->engine = 'MyISAM';
                    $blueprint->id();
                    $blueprint->string('token', 64)->unique('token_unique');
                });
            }
            $connection->table('product_completion_tasks')->insert(['token' => 'preserved']);
            try {
                app(ProductCompletionStorageGuard::class)->assertTransactional();
                $this->fail('Unsafe engines must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('storage', $exception->errors());
            }
            $indexes = $schema->getIndexes('product_completion_tasks');
            $migration = require database_path('migrations/2026_10_24_120000_require_transactional_outsourcing_tables.php');
            $migration->up();
            app(ProductCompletionStorageGuard::class)->assertTransactional();
            $migration->up();
            $migration->down();
            $this->assertSame('preserved', $connection->table('product_completion_tasks')->value('token'));
            $this->assertSame($indexes, $schema->getIndexes('product_completion_tasks'));
            $engines = $connection->query()->fromRaw('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $connection->getDatabaseName())
                ->whereIn('TABLE_NAME', array_map(fn (string $table): string => $connection->getTablePrefix().$table, $tables))
                ->pluck('ENGINE');
            $this->assertCount(count($tables), $engines);
            $this->assertSame(['InnoDB'], $engines->unique()->values()->all());

            $connection->statement('ALTER TABLE '.$connection->getQueryGrammar()->wrapTable('products').' ENGINE=MyISAM');
            $schema->drop('outsourcing_settlement_payments');
            try {
                $migration->up();
                $this->fail('Missing tables must fail before converting any table.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('outsourcing_settlement_payments', $exception->getMessage());
            }
            $engine = $connection->query()->fromRaw('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $connection->getDatabaseName())
                ->where('TABLE_NAME', $connection->getTablePrefix().'products')->value('ENGINE');
            $this->assertSame('MyISAM', $engine);
        } finally {
            foreach (array_reverse($created) as $table) {
                $schema->dropIfExists($table);
            }
            DB::setDefaultConnection($original);
            DB::purge('outsourcing_engine_test');
        }
    }
}
