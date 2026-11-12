<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Use a physical rebuild when adding fields next to a generated
            // column; do not leave instant-DDL storage mappings on MariaDB 10.4.
            $table = DB::connection()->getQueryGrammar()->wrapTable('suppliers');
            DB::statement("ALTER TABLE {$table} ADD COLUMN branch_pricing_mode VARCHAR(20) NOT NULL DEFAULT 'independent', ADD COLUMN branch_price_percentage DECIMAL(20, 12) NULL, ALGORITHM=COPY");
        } else {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->string('branch_pricing_mode', 20)->default('independent');
                $table->decimal('branch_price_percentage', 20, 12)->nullable();
            });
        }

        DB::table('suppliers')->whereNotNull('parent_supplier_id')
            ->update(['branch_pricing_mode' => 'percentage']);
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $table = DB::connection()->getQueryGrammar()->wrapTable('suppliers');
            DB::statement("ALTER TABLE {$table} DROP COLUMN branch_pricing_mode, DROP COLUMN branch_price_percentage, ALGORITHM=COPY");
        } else {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropColumn(['branch_pricing_mode', 'branch_price_percentage']);
            });
        }
    }
};
