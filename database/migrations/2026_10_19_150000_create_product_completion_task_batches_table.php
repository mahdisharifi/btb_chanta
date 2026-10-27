<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_task_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedInteger('protected_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'created_at'], 'pctb_creator_created_index');
        });

        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->foreignId('bulk_creation_batch_id')
                ->nullable()
                ->after('created_by')
                ->constrained('product_completion_task_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bulk_creation_batch_id');
        });

        Schema::dropIfExists('product_completion_task_batches');
    }
};
