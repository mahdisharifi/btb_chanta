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
            $table->unsignedBigInteger('applied_product_version')->nullable();
            $table->timestamp('content_applied_at')->nullable();
            $table->timestamp('media_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'applied_product_version',
                'content_applied_at',
                'media_published_at',
            ]);
        });
    }
};
