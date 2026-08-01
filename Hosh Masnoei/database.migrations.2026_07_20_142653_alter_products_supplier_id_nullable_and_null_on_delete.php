<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->cascadeOnDelete();
        });
    }
};
