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
            $table->integer('old_min_order_quantity')->nullable()->after('new_stock');
            $table->integer('new_min_order_quantity')->nullable()->after('old_min_order_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_update_items', function (Blueprint $table) {
            $table->dropColumn(['old_min_order_quantity', 'new_min_order_quantity']);
        });
    }
};
