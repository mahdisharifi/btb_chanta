<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_rule_items', function (Blueprint $table): void {
            $table->dropForeign(['commission_rule_id']);
            $table->foreign('commission_rule_id')
                ->references('id')
                ->on('commission_rules')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_rule_items', function (Blueprint $table): void {
            $table->dropForeign(['commission_rule_id']);
            $table->foreign('commission_rule_id')
                ->references('id')
                ->on('commission_rules')
                ->cascadeOnDelete();
        });
    }
};
