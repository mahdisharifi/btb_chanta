<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_revisions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('task_id')->constrained('product_completion_tasks')->cascadeOnDelete();
            $table->unsignedBigInteger('open_task_id')->nullable()->unique();
            $table->foreignId('submission_id')->constrained('product_completion_submissions')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_for')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('revision_no');
            $table->string('classification', 50)->default('contractor_error');
            $table->string('status', 30)->default('open');
            $table->boolean('is_commissionable')->default(false);
            $table->text('reason');
            $table->json('requested_fields_json');
            $table->foreignId('resolved_by_submission_id')
                ->nullable()
                ->constrained('product_completion_submissions')
                ->nullOnDelete();
            $table->dateTime('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'revision_no'], 'pcr_task_revision_unique');
            $table->index(['task_id', 'status'], 'pcr_task_status_index');
            $table->index(['requested_for', 'status'], 'pcr_assignee_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_completion_revisions');
    }
};
