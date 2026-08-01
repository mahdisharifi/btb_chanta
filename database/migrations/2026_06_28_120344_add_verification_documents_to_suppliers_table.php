<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('national_card_file')->nullable()->after('national_id');
            $table->string('business_license_file')->nullable()->after('tax_id');
            $table->json('verification_documents')->nullable()->after('business_license_file');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['national_card_file', 'business_license_file', 'verification_documents']);
        });
    }
};
