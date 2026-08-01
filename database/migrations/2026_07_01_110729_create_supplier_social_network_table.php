<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_social_network', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('social_network_id')->constrained('social_networks')->cascadeOnDelete();
            $table->string('link')->comment('لینک یا آیدی شبکه اجتماعی تامین‌کننده');
            $table->timestamps();

            // جلوگیری از تکرار
            $table->unique(['supplier_id', 'social_network_id']);

            // Index برای جستجو
            $table->index('supplier_id');
            $table->index('social_network_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_social_network');
    }
};
