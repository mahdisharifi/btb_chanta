<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;
        if (!preg_match('/^10\.4\.\d+.*MariaDB/i', $version)) {
            return;
        }

        $table = DB::connection()->getQueryGrammar()->wrapTable('suppliers');
        $column = DB::selectOne("SHOW FULL COLUMNS FROM {$table} WHERE Field = 'active_branch_name'");
        if (!$column) {
            throw new RuntimeException('The active branch name column must exist before its storage repair.');
        }
        if (!str_contains(strtoupper($column->Extra), 'VIRTUAL')) {
            return;
        }

        // A plain rebuild still crashes on the affected indexed VIRTUAL column.
        // Recreate it as STORED in one COPY operation, keeping the same values,
        // uniqueness expression, index name, and all referencing foreign keys.
        DB::statement("ALTER TABLE {$table} DROP INDEX suppliers_active_branch_name_unique, DROP COLUMN active_branch_name, ADD COLUMN active_branch_name VARCHAR(120) GENERATED ALWAYS AS (CASE WHEN is_active = 1 AND deleted_at IS NULL THEN branch_name ELSE NULL END) STORED AFTER supplier_tier_discount_revision, ADD UNIQUE INDEX suppliers_active_branch_name_unique (parent_supplier_id, active_branch_name), ALGORITHM=COPY");
    }

    public function down(): void
    {
        // Keep the safe generated storage on rollback; its business expression
        // and unique constraint have exactly the same semantics as before.
    }
};
