<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->decimal('paid_amount', 15, 0); // مبلغی که واقعا پرداخت شده (محاسبه شده)
            $table->integer('purchased_days'); // تعداد روزی که محاسبه و فروخته شده
            $table->timestamp('purchased_at');
            $table->timestamp('ends_at')->nullable(); // دقیقا برابر با تاریخ انقضای اشتراک اصلی
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_features');
    }
};
