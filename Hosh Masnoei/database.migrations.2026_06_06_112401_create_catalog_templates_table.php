<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('name');
            $table->json('selected_categories')->nullable();
            $table->json('selected_price_types')->nullable();
            // تمام تنظیمات شامل نمایش کاور، تفکیک دسته‌بندی، سورت و ... در این فیلد قرار می‌گیرد
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_templates');
    }
};
