<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_submission_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('product_completion_submissions')
                ->cascadeOnDelete();
            $table->foreignId('media_id')
                ->nullable()
                ->constrained('product_completion_media')
                ->restrictOnDelete();
            $table->foreignId('product_gallery_id')
                ->nullable()
                ->constrained('product_galleries')
                ->nullOnDelete();
            $table->string('action', 20);
            $table->unsignedSmallInteger('sort_order')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_seo_image')->default(false);
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('review_status', 30)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'sort_order'], 'pcsm_submission_sort_unique');
            $table->unique(['submission_id', 'media_id'], 'pcsm_submission_media_unique');
            $table->unique(['submission_id', 'product_gallery_id'], 'pcsm_submission_gallery_unique');
            $table->index(['submission_id', 'review_status'], 'pcsm_submission_review_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_completion_submission_media');
    }
};
