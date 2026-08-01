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
// database/migrations/xxxx_add_metadata_to_product_galleries_table.php
Schema::table('product_galleries', function (Blueprint $table) {
    $table->unsignedBigInteger('file_size')->nullable()->after('file_type');
    $table->string('mime_type', 100)->nullable()->after('file_size');
    $table->unsignedInteger('width')->nullable()->after('mime_type');
    $table->unsignedInteger('height')->nullable()->after('width');
    $table->unsignedInteger('duration')->nullable()->after('height'); // seconds (for video)
    $table->softDeletes();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_galleries', function (Blueprint $table) {
            //
        });
    }
};
