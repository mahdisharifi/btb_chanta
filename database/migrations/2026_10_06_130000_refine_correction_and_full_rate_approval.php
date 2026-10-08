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
        DB::statement(
            'ALTER TABLE products CHANGE allows_outsourcing_revision allows_outsourcing_correction TINYINT(1) NOT NULL DEFAULT 1'
        );

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

        DB::statement(
            'ALTER TABLE products CHANGE allows_outsourcing_correction allows_outsourcing_revision TINYINT(1) NOT NULL DEFAULT 1'
        );
    }
};
