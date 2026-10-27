<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'supplier_slug_redirects';

        if (! Schema::hasTable($tableName)) {
            throw new RuntimeException('supplier_slug_redirects is missing. Restore its history or run its create migration first.');
        }

        foreach (['id', 'supplier_id', 'old_slug', 'created_at'] as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                throw new RuntimeException('supplier_slug_redirects is missing required column: '.$column.'. Back up the table and reconcile its schema manually.');
            }
        }

        $hasUniqueSlug = Schema::hasIndex($tableName, ['old_slug'], 'unique');
        if (! $hasUniqueSlug && DB::table($tableName)->select('old_slug')->groupBy('old_slug')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('supplier_slug_redirects contains duplicate old_slug values. Resolve ownership manually without deleting history, then rerun migrate.');
        }

        $slugLengthFunction = DB::connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
        if (DB::table($tableName)->whereRaw($slugLengthFunction.'(old_slug) > 191')->exists()) {
            throw new RuntimeException('supplier_slug_redirects contains an old_slug longer than 191 characters. Back up and reconcile those URLs manually before rerunning migrate.');
        }

        if (! Schema::hasColumn($tableName, 'new_slug')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('new_slug', 191)->nullable();
            });
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('old_slug', 191)->change();
        });

        if (! Schema::hasColumn($tableName, 'created_by')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! $hasUniqueSlug) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('old_slug');
            });
        }
    }

    public function down(): void
    {
    }
};
