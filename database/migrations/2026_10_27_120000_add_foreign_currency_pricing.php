<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicatePriceCells = DB::table('product_prices')
            ->select('product_supplier_id', 'price_type_id', 'tier_index')
            ->selectRaw('COALESCE(check_period_id, 0) as normalized_check_period_id')
            ->whereNotNull('product_supplier_id')
            ->whereNotNull('price_type_id')
            ->groupBy('product_supplier_id', 'price_type_id', 'tier_index')
            ->groupByRaw('COALESCE(check_period_id, 0)')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicatePriceCells) {
            throw new \RuntimeException(
                'Cannot add foreign-currency pricing: duplicate product_prices matrix cells exist. '
                . 'Review and resolve duplicate cells before rerunning this migration; no price rows were changed.'
            );
        }

        Schema::table('product_supplier', function (Blueprint $table) {
            $table->char('price_currency', 3)->default('TOM')->after('is_active');
            $table->decimal('foreign_price_amount', 20, 4)->nullable()->after('price_currency');
            $table->boolean('foreign_price_auto_update')->default(false)->after('foreign_price_amount');
            $table->unsignedTinyInteger('foreign_price_refresh_hours')->nullable()->after('foreign_price_auto_update');
            $table->decimal('foreign_price_rate', 20, 4)->nullable()->after('foreign_price_refresh_hours');
            $table->string('foreign_price_source', 64)->nullable()->after('foreign_price_rate');
            $table->timestamp('foreign_price_updated_at')->nullable()->after('foreign_price_source');
            $table->timestamp('foreign_price_next_update_at')->nullable()->after('foreign_price_updated_at');
            $table->timestamp('foreign_price_last_attempt_at')->nullable()->after('foreign_price_next_update_at');
            $table->string('foreign_price_last_error', 500)->nullable()->after('foreign_price_last_attempt_at');
            $table->unsignedBigInteger('foreign_price_revision')->default(0)->after('foreign_price_last_error');

            $table->index(
                ['foreign_price_auto_update', 'foreign_price_next_update_at'],
                'product_supplier_foreign_price_due_idx'
            );
        });

        // Change the FK before adding the generated column: SQLite rebuilds tables for FK changes.
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropForeign(['check_period_id']);
            $table->foreign('check_period_id')
                ->references('id')
                ->on('check_periods')
                ->restrictOnDelete();
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->string('calculation_mode', 16)->nullable()->after('percentage_override');
            $table->unsignedBigInteger('normalized_check_period_id')
                ->virtualAs('COALESCE(check_period_id, 0)');
            $table->unique(
                ['product_supplier_id', 'price_type_id', 'tier_index', 'normalized_check_period_id'],
                'unique_price_matrix_normalized'
            );
            $table->dropUnique('unique_price_matrix');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('price_revision')->default(0);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->char('currency', 3)->primary();
            $table->decimal('rate_toman', 20, 4);
            $table->string('source', 64);
            $table->string('source_market_date', 32)->nullable();
            $table->string('source_market_time', 16)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_revision');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropUnique('unique_price_matrix_normalized');
            $table->dropColumn('normalized_check_period_id');
            $table->dropColumn('calculation_mode');
            $table->unique(
                ['product_supplier_id', 'price_type_id', 'tier_index', 'check_period_id'],
                'unique_price_matrix'
            );
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropForeign(['check_period_id']);
            $table->foreign('check_period_id')
                ->references('id')
                ->on('check_periods')
                ->nullOnDelete();
        });

        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropIndex('product_supplier_foreign_price_due_idx');
            $table->dropColumn([
                'price_currency',
                'foreign_price_amount',
                'foreign_price_auto_update',
                'foreign_price_refresh_hours',
                'foreign_price_rate',
                'foreign_price_source',
                'foreign_price_updated_at',
                'foreign_price_next_update_at',
                'foreign_price_last_attempt_at',
                'foreign_price_last_error',
                'foreign_price_revision',
            ]);
        });
    }
};
