<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->timestamp('expires_at')->useCurrent()->useCurrentOnUpdate(false)->change();
        });
    }

    public function down(): void
    {
    }
};
