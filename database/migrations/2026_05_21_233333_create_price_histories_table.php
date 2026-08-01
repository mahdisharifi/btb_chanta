<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            // تغییر نام جدول از product_suppliers به product_supplier
            $table->foreignId('product_supplier_id')->constrained('product_supplier')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('کاربری که قیمت را تغییر داده است');
            $table->string('price_type')->comment('نوع قیمت (مثلا عمده، خرده)');
            $table->decimal('old_price', 15, 0)->comment('قیمت قبلی');
            $table->decimal('new_price', 15, 0)->comment('قیمت جدید');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
