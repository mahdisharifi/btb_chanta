<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_supplier')) {
            return;
        }

        Schema::table('product_supplier', function (Blueprint $table): void {
            if (!Schema::hasColumn('product_supplier', 'foreign_price_applied_currency')) {
                $table->char('foreign_price_applied_currency', 3)->nullable()->after('foreign_price_source');
            }

            if (!Schema::hasColumn('product_supplier', 'foreign_price_applied_setting_revision')) {
                $table->unsignedBigInteger('foreign_price_applied_setting_revision')->default(0)->after('foreign_price_applied_currency');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_supplier')) {
            return;
        }

        $columns = [];
        foreach (['foreign_price_applied_currency', 'foreign_price_applied_setting_revision'] as $column) {
            if (Schema::hasColumn('product_supplier', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('product_supplier', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
