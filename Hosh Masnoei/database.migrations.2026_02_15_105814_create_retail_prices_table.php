<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('retail_price');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'user_id']);
            $table->index(['is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_prices');
    }
};
