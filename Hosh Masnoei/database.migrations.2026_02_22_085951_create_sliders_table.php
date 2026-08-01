<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();

            // ── محتوای اسلایدر ──
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();

            // ── تصاویر ──
            $table->string('image_path');                        // تصویر اصلی (دسکتاپ)
            $table->string('mobile_image_path')->nullable();     // تصویر موبایل (اختیاری)

            // ── لینک ──
            $table->string('link_url')->nullable();
            $table->string('link_text')->default('مشاهده بیشتر');
            $table->enum('link_target', ['_self', '_blank'])->default('_self');

            // ── استایل ──
            $table->string('bg_color', 25)->default('#1e40af');
            $table->string('text_color', 25)->default('#ffffff');
            $table->string('overlay_color', 30)->default('rgba(0,0,0,0.3)');
            $table->enum('text_position', ['right', 'left', 'center'])->default('right');

            // ── تنظیمات نمایش ──
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();          // شروع نمایش
            $table->timestamp('ends_at')->nullable();            // پایان نمایش

            // ── ردیابی ──
            $table->unsignedBigInteger('click_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);

            $table->timestamps();

            // ── ایندکس‌ها ──
            $table->index(['is_active', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sliders');
    }
};
