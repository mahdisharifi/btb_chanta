<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_tasks', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('active_product_id')->nullable()->unique()->constrained('products')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('task_type', 50);
            $table->string('status', 50)->default('draft');
            $table->string('review_mode', 30)->default('reviewer');
            $table->unsignedSmallInteger('scope_schema_version')->default(1);
            $table->json('scope_json');
            $table->json('initial_snapshot_json');
            $table->json('current_draft_json')->nullable();
            $table->unsignedInteger('draft_version')->default(0);
            $table->unsignedInteger('lock_version')->default(0);

            $table->unsignedBigInteger('product_version_at_assignment');
            $table->timestamp('product_updated_at_assignment')->nullable();

            // The foreign key is added after commission_rules is created.
            $table->unsignedBigInteger('commission_rule_id')->nullable()->index();
            $table->json('commission_rule_snapshot_json');
            $table->decimal('quoted_base_amount', 18, 2)->default(0);
            $table->char('currency', 3)->default('IRR');

            $table->unsignedSmallInteger('revision_limit')->default(3);
            $table->unsignedSmallInteger('revision_count')->default(0);
            $table->unsignedTinyInteger('priority')->default(0);

            $table->timestamp('due_at')->nullable();
            $table->timestamp('assignment_expires_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->json('conflict_json')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at'], 'pct_status_due_index');
            $table->index(['assigned_to', 'status'], 'pct_assignee_status_index');
            $table->index(['reviewer_id', 'status'], 'pct_reviewer_status_index');
            $table->index(['supplier_id', 'status'], 'pct_supplier_status_index');
            $table->index(['product_id', 'created_at'], 'pct_product_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_completion_tasks');
    }
};
