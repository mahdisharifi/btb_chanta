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
        Schema::create('check_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete()->comment('NULL یعنی برای همه تأمین‌کنندگان');
            $table->unsignedInteger('days')->comment('تعداد روز: 30, 60, 90, ...');
            $table->string('label', 100)->nullable()->comment('برچسب: چک 30 روزه');
            $table->integer('sort_order')->default(0)->comment('ترتیب نمایش');
            $table->boolean('is_active')->default(true)->comment('فعال/غیرفعال');
            $table->timestamps();
            
            $table->index(['supplier_id', 'is_active', 'sort_order']);
            $table->unique(['supplier_id', 'days'], 'unique_supplier_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_periods');
    }
};
