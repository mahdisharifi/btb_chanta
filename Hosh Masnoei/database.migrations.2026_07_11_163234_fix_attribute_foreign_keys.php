<?php
// database/migrations/xxx_xx_xxxxxx_fix_attribute_foreign_keys.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_product', function (Blueprint $table) {
            $table->dropForeign(['attribute_value_id']);
            $table->foreign('attribute_value_id')
                ->references('id')->on('attribute_values')
                ->restrictOnDelete();
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropForeign(['attribute_id']);
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attribute_product', function (Blueprint $table) {
            $table->dropForeign(['attribute_value_id']);
            $table->foreign('attribute_value_id')
                ->references('id')->on('attribute_values')
                ->cascadeOnDelete();
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropForeign(['attribute_id']);
            $table->foreign('attribute_id')
                ->references('id')->on('attributes')
                ->cascadeOnDelete();
        });
    }
};
