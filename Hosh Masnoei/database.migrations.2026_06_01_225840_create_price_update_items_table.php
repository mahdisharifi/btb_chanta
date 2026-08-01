<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_update_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_update_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_supplier_id')->constrained('product_supplier')->cascadeOnDelete();

            $table->json('old_prices')->nullable(); // فرمت: {"retail": 1000, "wholesale": 900}
            $table->json('new_prices')->nullable();

            $table->integer('old_stock')->nullable();
            $table->integer('new_stock')->nullable();

            $table->enum('status', ['valid', 'invalid'])->default('valid');
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_update_items');
    }
};
