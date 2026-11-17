<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('base_url', 255)->default('https://api.avalai.ir');
            $table->text('api_key')->nullable();
            $table->unsignedBigInteger('credit_to_toman')->default(268050);
            $table->unsignedTinyInteger('max_upload_mb')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_ai_settings');
    }
};
