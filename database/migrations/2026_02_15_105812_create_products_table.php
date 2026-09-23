<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')
                  ->constrained('suppliers')
                  ->cascadeOnDelete();
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('view_count')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('seo_canonical_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index(['status']);
            $table->index(['is_featured', 'is_active']);
            $table->index(['created_at']);
            // FULLTEXT index moved to separate migration for server compatibility
            // See: 2026_09_23_create_products_fulltext_index.php
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
