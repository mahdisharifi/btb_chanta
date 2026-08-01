<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // نام پلن (مثلا: اشتراک طلایی یک ماهه)
            $table->text('description')->nullable(); // توضیحات پلن
            $table->integer('duration_days'); // مدت زمان به روز
            $table->decimal('price', 15, 0); // مبلغ به تومان یا ریال
            $table->decimal('old_price', 15, 0)->nullable(); // قیمت قبلی برای نمایش تخفیف
            $table->json('features')->nullable(); // ویژگی‌های پلن به صورت آرایه (برای نمایش در سایت)
            $table->boolean('is_active')->default(true); // وضعیت فعال بودن پلن
            $table->integer('sort_order')->default(0); // ترتیب نمایش
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
