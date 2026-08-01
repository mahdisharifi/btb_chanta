<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 0); // مبلغ پرداختی
            $table->string('gateway')->nullable(); // نام درگاه (مثلا zarinpal)
            $table->string('authority')->nullable()->unique(); // توکن ارسالی به درگاه
            $table->string('ref_id')->nullable()->unique(); // شماره پیگیری درگاه پس از پرداخت موفق
            $table->enum('status', ['pending', 'success', 'failed', 'canceled'])->default('pending'); // وضعیت پرداخت
            $table->text('description')->nullable(); // توضیحات تراکنش
            $table->string('ip_address', 45)->nullable(); // آی‌پی پرداخت کننده
            $table->json('gateway_response')->nullable(); // لاگ پاسخ درگاه برای دیباگ
            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌ها برای جستجوی سریعتر در پنل ادمین
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
