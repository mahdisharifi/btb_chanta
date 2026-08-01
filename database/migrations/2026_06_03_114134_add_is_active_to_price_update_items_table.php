<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('price_update_items', function (Blueprint $table) {
            $table->boolean('old_is_active')->nullable()->after('old_stock');
            $table->boolean('new_is_active')->nullable()->after('new_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_update_items', function (Blueprint $table) {
            //
        });
    }
};
