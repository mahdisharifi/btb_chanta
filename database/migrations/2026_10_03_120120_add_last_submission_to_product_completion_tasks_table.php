<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->foreignId('last_submission_id')
                ->nullable()
                ->constrained('product_completion_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_submission_id');
        });
    }
};
