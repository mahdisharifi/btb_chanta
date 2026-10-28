<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'allows_outsourcing_revision')
            && !Schema::hasColumn('products', 'allows_outsourcing_correction')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->renameColumn('allows_outsourcing_revision', 'allows_outsourcing_correction');
            });
        }

        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->decimal('commission_rate_percentage', 5, 2)->default(100);
            $table->foreignId('full_rate_approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('full_rate_approved_at')->nullable();
            $table->dropColumn('allows_revision');
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->boolean('allows_revision')->default(false);
            $table->dropConstrainedForeignId('full_rate_approved_by');
            $table->dropColumn([
                'commission_rate_percentage',
                'full_rate_approved_at',
            ]);
        });

        if (Schema::hasColumn('products', 'allows_outsourcing_correction')
            && !Schema::hasColumn('products', 'allows_outsourcing_revision')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->renameColumn('allows_outsourcing_correction', 'allows_outsourcing_revision');
            });
        }
    }
};
