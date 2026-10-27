<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_operations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('task_id')->constrained('product_completion_tasks')->cascadeOnDelete();
            $table->string('operation_type', 100);
            $table->char('idempotency_key', 64)->unique();
            $table->string('status', 30)->default('pending');
            $table->json('payload_json')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at'], 'pco_status_available_index');
            $table->index(['task_id', 'operation_type'], 'pco_task_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_completion_operations');
    }
};
