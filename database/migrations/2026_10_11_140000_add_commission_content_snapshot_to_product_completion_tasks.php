<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->json('commission_content_snapshot_json')->nullable()->after('initial_snapshot_json');
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->dropColumn('commission_content_snapshot_json');
        });
    }
};
