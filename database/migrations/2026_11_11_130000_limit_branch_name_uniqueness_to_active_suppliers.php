<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('active_branch_name', 120)->nullable()
                ->virtualAs('CASE WHEN is_active = 1 AND deleted_at IS NULL THEN branch_name ELSE NULL END');
            $table->unique(['parent_supplier_id', 'active_branch_name'], 'suppliers_active_branch_name_unique');
        });
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropUnique('supplier_parent_branch_name_unique'));
    }

    public function down(): void
    {
        if (DB::table('suppliers')->whereNotNull('parent_supplier_id')->whereNotNull('branch_name')
            ->groupBy('parent_supplier_id', 'branch_name')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Resolve duplicate inactive/deleted branch names before restoring the old unique constraint.');
        }

        Schema::table('suppliers', fn (Blueprint $table) => $table->unique(['parent_supplier_id', 'branch_name'], 'supplier_parent_branch_name_unique'));
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique('suppliers_active_branch_name_unique');
            $table->dropColumn('active_branch_name');
        });
    }
};
