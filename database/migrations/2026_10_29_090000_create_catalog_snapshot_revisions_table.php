<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_snapshot_revisions', function (Blueprint $table): void {
            $table->string('scope_type', 24);
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->unsignedBigInteger('revision')->default(0);
            $table->primary(['scope_type', 'scope_id']);
        });

        DB::table('catalog_snapshot_revisions')->insert([
            ['scope_type' => 'global', 'scope_id' => 0, 'revision' => 0],
            ['scope_type' => 'all_suppliers', 'scope_id' => 0, 'revision' => 0],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_snapshot_revisions');
    }
};
