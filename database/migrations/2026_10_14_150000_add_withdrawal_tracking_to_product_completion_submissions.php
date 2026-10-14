<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_completion_submissions', function (Blueprint $table): void {
            $table->json('submission_context_json')->nullable();
            $table->foreignId('review_started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('review_started_by');
            $table->dropColumn(['submission_context_json', 'review_started_at', 'withdrawn_at']);
        });
    }
};
