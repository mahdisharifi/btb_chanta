<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();

            // تغییر: استفاده از dateTime یا دادن ویژگی nullable به timestamp
            $table->dateTime('starts_at')->nullable(); // زمان شروع اشتراک
            $table->dateTime('ends_at')->nullable(); // زمان پایان اشتراک

            $table->enum('status', ['pending', 'active', 'expired', 'canceled'])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            // ایندکس برای بررسی سریع وضعیت اشتراک کاربر
            $table->index(['user_id', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
