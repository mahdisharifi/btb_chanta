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
        Schema::create('supplier_tier_percentages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->integer('tier_index')->comment('0=کارتن، 1=زیر کارتن، 2=تیراژ 3، ...');
            $table->string('tier_label', 100)->nullable()->comment('نام اختصاصی تیراژ (مثلاً "کارتن"، "زیر کارتن")');
            $table->decimal('percentage', 8, 2)->default(0)->comment('درصد افزایش/کاهش نسبت به تیراژ مرجع (+ = گرانتر، - = ارزانتر)');
            $table->boolean('is_reference')->default(false)->comment('آیا این تیراژ مرجع است؟ (percentage = 0)');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // یک supplier فقط یک تنظیم برای هر tier_index
            $table->unique(['supplier_id', 'tier_index']);
            
            // فقط یک تیراژ می‌تواند مرجع باشد
            $table->index(['supplier_id', 'is_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_tier_percentages');
    }
};
