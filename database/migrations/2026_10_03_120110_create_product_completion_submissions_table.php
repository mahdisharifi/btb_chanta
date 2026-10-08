<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_submissions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('task_id')->constrained('product_completion_tasks')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version_no');
            $table->unsignedInteger('draft_version');
            $table->json('payload_json');
            $table->json('diff_json')->nullable();
            $table->json('media_manifest_json')->nullable();
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('base_product_version');
            $table->char('idempotency_key', 64)->unique();
            $table->string('status', 50)->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->unique(['task_id', 'version_no'], 'pcs_task_version_unique');
            $table->index(['task_id', 'status'], 'pcs_task_status_index');
            $table->index(['submitted_by', 'submitted_at'], 'pcs_submitter_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_completion_submissions');
    }
};
