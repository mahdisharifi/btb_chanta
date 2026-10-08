<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->foreign('commission_rule_id')
                ->references('id')
                ->on('commission_rules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_completion_tasks', function (Blueprint $table): void {
            $table->dropForeign(['commission_rule_id']);
        });
    }
};
