<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 11)->index();
            $table->string('code', 6);
            $table->timestamp('expires_at')->index();
            $table->boolean('is_used')->default(false)->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // برای جلوگیری از spam، یک کد فعال برای هر موبایل
            $table->index(['mobile', 'is_used', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
