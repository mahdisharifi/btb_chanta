<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id(); // bigint unsigned auto_increment
            $table->string('name', 100);
            $table->string('native_name', 100)->nullable();
            $table->char('iso2', 2)->unique()->nullable()->comment('ISO 3166-1 alpha-2');
            $table->char('iso3', 3)->unique()->nullable()->comment('ISO 3166-1 alpha-3');
            $table->string('phone_code', 10)->nullable();
            $table->string('currency_code', 3)->nullable()->comment('ISO 4217 currency code');
            $table->string('currency_symbol', 10)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->enum('continent', ['asia', 'europe', 'africa', 'north_america', 'south_america', 'australia', 'antarctica'])->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
