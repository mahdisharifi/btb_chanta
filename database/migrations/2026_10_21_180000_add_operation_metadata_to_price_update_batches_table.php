<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_update_batches', function (Blueprint $table) {
            $table->string('operation_type', 32)
                ->default('excel_import')
                ->after('supplier_id');
            $table->decimal('percentage', 7, 2)
                ->nullable()
                ->after('operation_type');

            $table->index(
                ['supplier_id', 'status', 'operation_type'],
                'price_batches_supplier_status_operation_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('price_update_batches', function (Blueprint $table) {
            $table->dropIndex('price_batches_supplier_status_operation_index');
            $table->dropColumn(['operation_type', 'percentage']);
        });
    }
};
