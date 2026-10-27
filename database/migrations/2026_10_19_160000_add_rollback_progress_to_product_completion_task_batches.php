<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_completion_task_batches', 'rollback_processed_count')) {
            Schema::table('product_completion_task_batches', function (Blueprint $table): void {
                $table->unsignedInteger('rollback_processed_count')->default(0);
            });
        }

        if (! Schema::hasColumn('product_completion_task_batches', 'rollback_cursor_task_id')) {
            Schema::table('product_completion_task_batches', function (Blueprint $table): void {
                $table->unsignedBigInteger('rollback_cursor_task_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_completion_task_batches', function (Blueprint $table): void {
            $table->dropColumn(['rollback_processed_count', 'rollback_cursor_task_id']);
        });
    }
};
