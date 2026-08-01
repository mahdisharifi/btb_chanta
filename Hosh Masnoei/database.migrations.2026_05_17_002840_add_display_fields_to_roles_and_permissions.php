<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ارتقاء جدول roles
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'display_name')) {
                $table->string('display_name', 200)->nullable()->after('guard_name');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('display_name');
            }
        });

        // ارتقاء جدول permissions
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'group_name')) {
                $table->string('group_name', 100)->nullable()->after('guard_name');
            }
            if (!Schema::hasColumn('permissions', 'display_name')) {
                $table->string('display_name', 200)->nullable()->after('group_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['group_name', 'display_name']);
        });
    }
};
