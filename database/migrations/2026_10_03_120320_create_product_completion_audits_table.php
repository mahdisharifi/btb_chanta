<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained('product_completion_tasks')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 30)->default('user');
            $table->string('action', 100);
            $table->string('entity_type', 150)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('field_name')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->uuid('request_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_id', 'created_at'], 'pca_task_created_index');
            $table->index(['product_id', 'created_at'], 'pca_product_created_index');
            $table->index(['user_id', 'created_at'], 'pca_user_created_index');
            $table->index(['entity_type', 'entity_id'], 'pca_entity_index');
            $table->index(['action', 'created_at'], 'pca_action_created_index');
            $table->index('request_id', 'pca_request_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_completion_audits');
    }
};
