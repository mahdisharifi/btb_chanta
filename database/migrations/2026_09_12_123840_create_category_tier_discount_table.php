<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_tier_discount', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tier_index'); // 0=زیر کارتن, 2=عمده, 3=بار (1=مرجع استفاده نمی‌شود)
            $table->decimal('percentage_change', 5, 2); // مثلاً -5.00 یا +10.00
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            //.unique constraint
            $table->unique(['supplier_id', 'category_id', 'tier_index'], 'category_tier_unique');
            
            // ایندکس برای بهینه‌سازی
            $table->index(['supplier_id', 'category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_tier_discount');
    }
};
