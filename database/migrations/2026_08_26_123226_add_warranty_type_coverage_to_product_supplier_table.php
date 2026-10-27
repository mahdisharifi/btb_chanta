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
        Schema::table('product_supplier', function (Blueprint $table) {
            // نوع گارانتی: test (تست), limited (مدت‌دار), lifetime (مادام‌العمر)
            $table->enum('warranty_type_coverage', ['test', 'limited', 'lifetime'])
                ->default('limited')
                ->after('warranty_coverage_type')
                ->comment('نوع گارانتی: تست، مدت‌دار، مادام‌العمر');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropColumn('warranty_type_coverage');
        });
    }
};
