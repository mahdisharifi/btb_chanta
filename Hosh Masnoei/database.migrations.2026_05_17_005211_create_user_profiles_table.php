<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // اطلاعات تکمیلی
            $table->string('gender', 10)->nullable()->comment('male, female, other');
            $table->date('birth_date')->nullable();
            $table->text('bio')->nullable();

            // شبکه‌های اجتماعی و وب‌سایت
            $table->string('website', 500)->nullable();
            $table->string('telegram', 100)->nullable();
            $table->string('instagram', 100)->nullable();
            $table->string('linkedin', 100)->nullable();
            $table->string('twitter', 100)->nullable();

            // آدرس
            $table->string('address')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('postal_code', 10)->nullable();

            // تنظیمات
            $table->json('preferences')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
