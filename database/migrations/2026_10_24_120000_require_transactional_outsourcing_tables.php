<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tables = [
            'products', 'attribute_values', 'attribute_product', 'product_galleries',
            'commission_rules', 'commission_rule_items', 'product_completion_tasks',
            'product_completion_submissions', 'product_completion_revisions', 'product_completion_media',
            'product_completion_submission_media', 'product_completion_audits', 'product_completion_operations',
            'product_completion_commissions', 'product_completion_task_batches', 'outsourcing_settlements',
            'outsourcing_settlement_items', 'outsourcing_settlement_payments',
        ];
        $names = array_map(fn (string $table): string => $connection->getTablePrefix().$table, $tables);
        $engines = $connection->query()->fromRaw('information_schema.TABLES')->useWritePdo()
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->whereIn('TABLE_NAME', $names)->pluck('ENGINE', 'TABLE_NAME');

        foreach ($names as $name) {
            if (! in_array(strtolower((string) $engines->get($name)), ['innodb', 'myisam'], true)) {
                throw new RuntimeException('Expected an existing InnoDB or MyISAM outsourcing table: '.$name);
            }
        }

        foreach ($tables as $table) {
            if (strcasecmp((string) $engines->get($connection->getTablePrefix().$table), 'InnoDB') !== 0) {
                $connection->statement('ALTER TABLE '.$connection->getQueryGrammar()->wrapTable($table).' ENGINE=InnoDB');
            }
        }

        $verified = $connection->query()->fromRaw('information_schema.TABLES')->useWritePdo()
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->whereIn('TABLE_NAME', $names)->pluck('ENGINE', 'TABLE_NAME');
        foreach ($names as $name) {
            if (strcasecmp((string) $verified->get($name), 'InnoDB') !== 0) {
                throw new RuntimeException('Outsourcing table conversion was not completed: '.$name);
            }
        }
    }

    public function down(): void
    {
    }
};
