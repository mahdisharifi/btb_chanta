<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // مثلا: supplier_catalog
            $table->string('name'); // نام امکان: کاتالوگ پیشرفته
            $table->text('description')->nullable();
            $table->decimal('base_price', 15, 0); // قیمت پایه (مثلا 30000 تومان)
            $table->integer('base_duration_days'); // مدت زمان پایه (مثلا 30 روز)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
