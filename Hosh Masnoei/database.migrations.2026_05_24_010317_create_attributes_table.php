<?php




use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ۱. جدول اصلی ویژگی‌ها
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('text'); // text, select, boolean
            $table->boolean('is_filterable')->default(false);
            $table->timestamps();
        });

        // ۲. جدول مقادیر پیش‌فرض برای ویژگی‌های از نوع انتخاب‌کردنی (Select)
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->timestamps();
        });

        // ۳. جدول میانی (Pivot) برای اتصال ویژگی‌ها به دسته‌بندی‌ها
        Schema::create('attribute_category', function (Blueprint $table) {
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            // جلوگیری از ثبت تکراری یک ویژگی برای یک دسته‌بندی
            $table->unique(['attribute_id', 'category_id']);
        });

        // ۴. جدول میانی (Pivot) برای اتصال محصول به ویژگی و ذخیره مقدار
        Schema::create('attribute_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable(); // مقدار اختصاصی محصول برای این ویژگی
            $table->timestamps();

            // جلوگیری از ثبت ویژگی تکراری برای یک محصول
            $table->unique(['product_id', 'attribute_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_product');
        Schema::dropIfExists('attribute_category');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
