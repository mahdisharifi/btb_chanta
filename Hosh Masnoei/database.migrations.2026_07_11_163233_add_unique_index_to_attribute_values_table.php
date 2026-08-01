<?php
// database/migrations/xxx_xxxxx_add_unique_index_to_attribute_values_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->unique(['attribute_id', 'value'], 'attribute_values_attribute_id_value_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropUnique('attribute_values_attribute_id_value_unique');
        });
    }
};
