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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_type', 20)
                ->default('customer')
                ->comment('admin, supplier, retailer, customer');

            $table->string('name');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();

            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();

            $table->string('mobile', 15)->nullable()->unique();
            $table->timestamp('mobile_verified_at')->nullable();

            $table->string('national_code', 10)->nullable()->unique();
            $table->string('avatar')->nullable();

            $table->string('password');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);

            $table->rememberToken();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_type');
            $table->index('is_active');
            $table->index('email_verified_at');
            $table->index('created_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
