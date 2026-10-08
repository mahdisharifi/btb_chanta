<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_completion_media', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('task_id')->constrained('product_completion_tasks')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_product_gallery_id')
                ->nullable()
                ->constrained('product_galleries')
                ->nullOnDelete();
            $table->foreignId('published_product_gallery_id')
                ->nullable()
                ->constrained('product_galleries')
                ->nullOnDelete();

            $table->string('disk', 50)->default('local');
            $table->string('temporary_path')->nullable();
            $table->string('prepared_path')->nullable();
            $table->string('final_path')->nullable();
            $table->string('original_name');
            $table->string('safe_name');
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum', 64);
            $table->json('versions_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('status', 30)->default('temporary');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['task_id', 'checksum'], 'pcm_task_checksum_unique');
            $table->index(['task_id', 'status'], 'pcm_task_status_index');
            $table->index(['status', 'created_at'], 'pcm_cleanup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_completion_media');
    }
};
