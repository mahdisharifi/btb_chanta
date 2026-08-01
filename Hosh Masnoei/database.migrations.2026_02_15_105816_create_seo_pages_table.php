<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_type');
            $table->string('page_identifier');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->nullable();
            $table->json('schema_markup')->nullable();
            $table->timestamps();

            $table->unique(['page_type', 'page_identifier']);
            $table->index(['page_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
    }
};
