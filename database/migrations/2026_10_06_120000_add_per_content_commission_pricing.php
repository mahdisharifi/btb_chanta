<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pricing_schema_version')->default(1);
            $table->decimal('revision_percentage', 5, 2)->default(30);
        });

        Schema::create('commission_rule_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commission_rule_id')
                ->constrained('commission_rules')
                ->cascadeOnDelete();
            $table->string('content_type', 30);
            $table->decimal('unit_amount', 18, 2)->default(0);
            $table->unsignedSmallInteger('max_payable_units')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['commission_rule_id', 'content_type'],
                'commission_rule_items_rule_type_unique'
            );
            $table->index(
                ['content_type', 'is_enabled'],
                'commission_rule_items_type_enabled_index'
            );
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('allows_outsourcing_revision')->default(true);
        });

        // Existing products remain opt-in; products created after this migration default to enabled.
        DB::table('products')->update(['allows_outsourcing_revision' => false]);

        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->boolean('allows_revision')->default(false);
        });

        Schema::table('product_completion_revisions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pricing_schema_version')->default(1);
            $table->json('changed_units_json')->nullable();
            $table->unsignedSmallInteger('changed_unit_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_revisions', function (Blueprint $table): void {
            $table->dropColumn([
                'pricing_schema_version',
                'changed_units_json',
                'changed_unit_count',
            ]);
        });

        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->dropColumn('allows_revision');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('allows_outsourcing_revision');
        });

        Schema::dropIfExists('commission_rule_items');

        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->dropColumn(['pricing_schema_version', 'revision_percentage']);
        });
    }
};
