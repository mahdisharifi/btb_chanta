<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_networks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('instagram, telegram, whatsapp, website');
            $table->string('display_name', 100)->comment('نام نمایشی فارسی');
            $table->string('icon')->nullable()->comment('نام آیکون heroicon');
            $table->string('base_url')->nullable()->comment('URL پایه برای ساخت لینک کامل');
            $table->text('validation_pattern')->comment('الگوی regex برای اعتبارسنجی');
            $table->string('placeholder_example')->nullable()->comment('مثال برای placeholder');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_networks');
    }
};
