<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $shards = [];
        for ($shard = 0; $shard < 64; $shard++) {
            $shards[] = [
                'scope_type' => 'all_suppliers_shard',
                'scope_id' => $shard,
                'revision' => 0,
            ];
        }

        DB::table('catalog_snapshot_revisions')->insertOrIgnore($shards);
    }

    public function down(): void
    {
        DB::table('catalog_snapshot_revisions')
            ->where('scope_type', 'all_suppliers_shard')
            ->delete();
    }
};
