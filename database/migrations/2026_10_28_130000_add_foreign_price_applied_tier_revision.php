<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_supplier')
            || Schema::hasColumn('product_supplier', 'foreign_price_applied_tier_revision')) {
            return;
        }

        Schema::table('product_supplier', function (Blueprint $table): void {
            // Null marks legacy matrices that have not been recalculated against
            // the supplier's current global tier percentages yet.
            $table->unsignedBigInteger('foreign_price_applied_tier_revision')
                ->nullable()
                ->after('foreign_price_applied_setting_revision');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('product_supplier')
            && Schema::hasColumn('product_supplier', 'foreign_price_applied_tier_revision')) {
            Schema::table('product_supplier', function (Blueprint $table): void {
                $table->dropColumn('foreign_price_applied_tier_revision');
            });
        }
    }
};
