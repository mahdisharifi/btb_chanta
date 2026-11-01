<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_exchange_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->decimal('rate_toman', 20, 4)->nullable();
            $table->decimal('manual_rate_toman', 20, 4)->nullable();
            $table->boolean('auto_update')->default(false);
            $table->unsignedTinyInteger('refresh_hours')->nullable();
            $table->timestamp('next_update_at')->nullable();
            $table->string('source', 64)->nullable();
            $table->string('source_market_date', 32)->nullable();
            $table->string('source_market_time', 16)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['supplier_id', 'currency'], 'supplier_currency_rate_unique');
            $table->index(
                ['auto_update', 'next_update_at', 'supplier_id'],
                'supplier_currency_rate_due_idx'
            );
        });

        if (Schema::hasTable('product_supplier')
            && !Schema::hasIndex('product_supplier', 'product_supplier_fx_price_scan_idx')) {
            Schema::table('product_supplier', function (Blueprint $table): void {
                $table->index(
                    ['supplier_id', 'price_currency', 'is_active', 'id'],
                    'product_supplier_fx_price_scan_idx'
                );
            });
        }

        $this->backfillExistingSupplierPolicies();
    }

    private function backfillExistingSupplierPolicies(): void
    {
        if (!Schema::hasTable('product_supplier') || !Schema::hasColumn('product_supplier', 'foreign_price_amount')) {
            return;
        }

        $groups = DB::table('product_supplier')
            ->whereIn('price_currency', ['USD', 'CNY', 'AED'])
            ->whereNotNull('foreign_price_amount')
            ->select('supplier_id', 'price_currency')
            ->groupBy('supplier_id', 'price_currency')
            ->get();

        foreach ($groups as $group) {
            $summary = DB::table('product_supplier')
                ->where('supplier_id', $group->supplier_id)
                ->where('price_currency', $group->price_currency)
                ->whereNotNull('foreign_price_amount')
                ->selectRaw('COUNT(*) as products_count')
                ->selectRaw('SUM(CASE WHEN foreign_price_auto_update = 1 THEN 1 ELSE 0 END) as auto_count')
                ->selectRaw('COUNT(DISTINCT foreign_price_refresh_hours) as interval_count')
                ->selectRaw('COUNT(foreign_price_refresh_hours) as interval_values_count')
                ->selectRaw('MIN(foreign_price_refresh_hours) as interval_hours')
                ->selectRaw('MIN(foreign_price_next_update_at) as next_update_at')
                ->selectRaw('COUNT(CASE WHEN foreign_price_rate > 0 THEN 1 END) as rate_values_count')
                ->selectRaw('COUNT(DISTINCT CASE WHEN foreign_price_rate > 0 THEN foreign_price_rate END) as legacy_rate_count')
                ->selectRaw('MIN(CASE WHEN foreign_price_rate > 0 THEN foreign_price_rate END) as legacy_rate_toman')
                ->first();

            $intervalHours = $summary?->interval_hours === null ? null : (int) $summary->interval_hours;
            $consistentAutomaticPolicy = (int) ($summary?->products_count ?? 0) > 0
                && (int) ($summary?->auto_count ?? 0) === (int) $summary->products_count
                && (int) ($summary?->interval_values_count ?? 0) === (int) $summary->products_count
                && (int) ($summary?->interval_count ?? 0) === 1
                && in_array($intervalHours, [2, 6, 12, 24], true);

            $snapshot = Schema::hasTable('exchange_rates')
                ? DB::table('exchange_rates')->where('currency', $group->price_currency)->first()
                : null;

            $legacyRateCount = (int) ($summary?->legacy_rate_count ?? 0);
            $hasConsistentLegacyRate = (int) ($summary?->products_count ?? 0) > 0
                && (int) ($summary?->rate_values_count ?? 0) === (int) $summary->products_count
                && $legacyRateCount === 1;
            $warnings = [];

            if ((int) ($summary?->auto_count ?? 0) > 0 && !$consistentAutomaticPolicy) {
                $warnings[] = 'تنظیمات خودکار محصولات قدیمی متفاوت بود؛ برای زمان‌بندی سراسری، تنظیم خودکار را دوباره انتخاب کنید.';
            }

            if (!$hasConsistentLegacyRate) {
                $warnings[] = $legacyRateCount > 1
                    ? 'نرخ‌های دستی محصولات قدیمی یکسان نبودند؛ نرخ واحدی اعمال نشد. نرخ دستی را بازبینی کنید.'
                    : 'نرخ قبلی محصولات برای بازیابی موجود نبود؛ پیش از اعمال گروهی، نرخ دستی را تعیین کنید.';
            }

            $now = CarbonImmutable::now('UTC');
            $legacyNextUpdate = $summary?->next_update_at
                ? CarbonImmutable::parse($summary->next_update_at, config('app.timezone', 'UTC'))->utc()
                : null;
            DB::table('supplier_exchange_rate_settings')->insert([
                'supplier_id' => $group->supplier_id,
                'currency' => $group->price_currency,
                'rate_toman' => $snapshot?->rate_toman,
                'manual_rate_toman' => $hasConsistentLegacyRate ? $summary->legacy_rate_toman : null,
                'auto_update' => $consistentAutomaticPolicy,
                'refresh_hours' => $consistentAutomaticPolicy ? $intervalHours : null,
                'next_update_at' => $consistentAutomaticPolicy ? ($legacyNextUpdate ?? $now) : null,
                'source' => $snapshot?->source,
                'source_market_date' => $snapshot?->source_market_date,
                'source_market_time' => $snapshot?->source_market_time,
                'source_updated_at' => $snapshot?->source_updated_at,
                'fetched_at' => $snapshot?->fetched_at,
                'last_attempt_at' => $snapshot?->last_attempt_at,
                'last_error' => $warnings !== []
                    ? implode(' ', $warnings)
                    : $snapshot?->last_error,
                'revision' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_supplier')
            && Schema::hasIndex('product_supplier', 'product_supplier_fx_price_scan_idx')) {
            Schema::table('product_supplier', function (Blueprint $table): void {
                $table->dropIndex('product_supplier_fx_price_scan_idx');
            });
        }

        Schema::dropIfExists('supplier_exchange_rate_settings');
    }
};
