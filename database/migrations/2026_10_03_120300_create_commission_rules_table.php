<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->string('task_type', 50);
            $table->string('calculation_type', 50)->default('per_task');
            $table->string('revision_policy', 50)->default('scope_change_only');
            $table->decimal('base_amount', 18, 2)->default(0);
            $table->decimal('per_revision_amount', 18, 2)->default(0);
            $table->decimal('per_approved_item_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->char('currency', 3)->default('IRR');
            $table->json('conditions_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'version'], 'commission_rules_name_version_unique');
            $table->index(['task_type', 'is_active'], 'commission_rules_type_active_index');
            $table->index(['effective_from', 'effective_until'], 'commission_rules_effective_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
