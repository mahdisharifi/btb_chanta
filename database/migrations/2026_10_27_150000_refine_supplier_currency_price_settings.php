<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;

return new class extends Migration
{
    private const PRODUCT_SCAN_INDEX = 'product_supplier_fx_price_scan_idx';
    private const LEGACY_DUE_INDEX = 'product_supplier_foreign_price_due_idx';

    public function up(): void
    {
        if (!Schema::hasTable('supplier_currency_rate_migration_150_backups')) {
            Schema::create('supplier_currency_rate_migration_150_backups', function (Blueprint $table): void {
                $table->unsignedBigInteger('setting_id')->primary();
                $table->decimal('original_manual_rate_toman', 20, 4)->nullable();
                $table->string('original_last_error', 500)->nullable();
                $table->timestamp('original_updated_at')->nullable();
                $table->decimal('applied_manual_rate_toman', 20, 4)->nullable();
                $table->string('applied_last_error', 500)->nullable();
                $table->unsignedBigInteger('revision');
            });
        }

        if (Schema::hasTable('supplier_exchange_rate_settings')
            && !Schema::hasColumn('supplier_exchange_rate_settings', 'last_apply_error')) {
            Schema::table('supplier_exchange_rate_settings', function (Blueprint $table): void {
                $table->string('last_apply_error', 500)->nullable();
            });
        }

        if (Schema::hasTable('product_supplier')) {
            if (!Schema::hasIndex('product_supplier', self::PRODUCT_SCAN_INDEX)) {
                Schema::table('product_supplier', function (Blueprint $table): void {
                    $table->index(
                        ['supplier_id', 'price_currency', 'is_active', 'id'],
                        self::PRODUCT_SCAN_INDEX
                    );
                });
            }

            if (Schema::hasIndex('product_supplier', self::LEGACY_DUE_INDEX)) {
                Schema::table('product_supplier', function (Blueprint $table): void {
                    $table->dropIndex(self::LEGACY_DUE_INDEX);
                });
            }
        }

        $this->restoreUntouchedLegacyManualRates();
    }

    private function restoreUntouchedLegacyManualRates(): void
    {
        if (!Schema::hasTable('supplier_exchange_rate_settings')
            || !Schema::hasTable('product_supplier')
            || !Schema::hasColumn('product_supplier', 'foreign_price_rate')) {
            return;
        }

        $settingIds = DB::table('supplier_exchange_rate_settings')
            ->where('revision', 1)
            ->where('auto_update', false)
            ->whereColumn('created_at', 'updated_at')
            ->pluck('id');

        foreach ($settingIds as $settingId) {
            DB::transaction(function () use ($settingId): void {
                $setting = DB::table('supplier_exchange_rate_settings')
                    ->where('id', $settingId)
                    ->lockForUpdate()
                    ->first(['id', 'supplier_id', 'currency', 'manual_rate_toman', 'last_error', 'revision', 'auto_update', 'created_at', 'updated_at']);

                if (!$setting
                    || (int) $setting->revision !== 1
                    || (bool) $setting->auto_update
                    || $setting->created_at !== $setting->updated_at) {
                    return;
                }

                $legacy = DB::table('product_supplier')
                    ->where('supplier_id', $setting->supplier_id)
                    ->where('price_currency', $setting->currency)
                    ->whereNotNull('foreign_price_amount')
                    ->where('foreign_price_amount', '>', 0)
                    ->selectRaw('COUNT(*) as product_count')
                    ->selectRaw('COUNT(CASE WHEN foreign_price_rate > 0 THEN 1 END) as rate_values_count')
                    ->selectRaw('COUNT(DISTINCT CASE WHEN foreign_price_rate > 0 THEN foreign_price_rate END) as rate_count')
                    ->selectRaw('MIN(CASE WHEN foreign_price_rate > 0 THEN foreign_price_rate END) as rate_toman')
                    ->first();

                if ((int) ($legacy?->product_count ?? 0) === 0) {
                    return;
                }

                $rateCount = (int) ($legacy?->rate_count ?? 0);
                $hasConsistentRate = $rateCount === 1
                    && (int) ($legacy?->rate_values_count ?? 0) === (int) $legacy->product_count;
                $manualRate = $hasConsistentRate ? $legacy->rate_toman : null;
                $message = !$hasConsistentRate
                    ? ($rateCount > 1
                        ? 'نرخ‌های دستی محصولات قدیمی یکسان نبودند؛ نرخ واحدی اعمال نشد. نرخ دستی را بازبینی کنید.'
                        : 'نرخ قبلی محصولات برای بازیابی موجود نبود؛ پیش از اعمال گروهی، نرخ دستی را تعیین کنید.')
                    : null;

                $lastError = $setting->last_error;
                if ($message !== null && !str_contains((string) $lastError, $message)) {
                    $combinedError = trim(implode(' ', array_filter([$lastError, $message])));
                    $lastError = $combinedError === ''
                        ? null
                        : (mb_strlen($combinedError) > 500 ? mb_substr($combinedError, -500) : $combinedError);
                }

                $appliedLastError = $lastError;
                $rateChanged = $this->normalizeRate($setting->manual_rate_toman) !== $this->normalizeRate($manualRate);
                $errorChanged = $setting->last_error !== $appliedLastError;

                if (!$rateChanged && !$errorChanged) {
                    return;
                }

                DB::table('supplier_currency_rate_migration_150_backups')->insertOrIgnore([
                    'setting_id' => $setting->id,
                    'original_manual_rate_toman' => $setting->manual_rate_toman,
                    'original_last_error' => $setting->last_error,
                    'original_updated_at' => $setting->updated_at,
                    'applied_manual_rate_toman' => $manualRate,
                    'applied_last_error' => $appliedLastError,
                    'revision' => $setting->revision,
                ]);

                DB::table('supplier_currency_rate_migration_150_backups')
                    ->where('setting_id', $setting->id)
                    ->update([
                        'applied_manual_rate_toman' => $manualRate,
                        'applied_last_error' => $appliedLastError,
                        'revision' => $setting->revision,
                    ]);

                $updated = DB::table('supplier_exchange_rate_settings')
                    ->where('id', $setting->id)
                    ->where('revision', $setting->revision)
                    ->where('updated_at', $setting->updated_at)
                    ->where('auto_update', false)
                    ->update([
                        'manual_rate_toman' => $manualRate,
                        'last_error' => $appliedLastError,
                        'updated_at' => CarbonImmutable::now('UTC'),
                    ]);

                if ($updated !== 1) {
                    throw new RuntimeException('Supplier currency setting changed while migration 150 was backfilling it.');
                }
            });
        }
    }

    private function normalizeRate(mixed $rate): ?string
    {
        return $rate === null ? null : number_format((float) $rate, 4, '.', '');
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_currency_rate_migration_150_backups')
            && Schema::hasTable('supplier_exchange_rate_settings')) {
            $backups = DB::table('supplier_currency_rate_migration_150_backups')->get();

            foreach ($backups as $backup) {
                DB::transaction(function () use ($backup): void {
                    $current = DB::table('supplier_exchange_rate_settings')
                        ->where('id', $backup->setting_id)
                        ->lockForUpdate()
                        ->first(['manual_rate_toman', 'last_error', 'revision']);

                    if (!$current
                        || (int) $current->revision !== (int) $backup->revision
                        || $this->normalizeRate($current->manual_rate_toman) !== $this->normalizeRate($backup->applied_manual_rate_toman)
                        || $current->last_error !== $backup->applied_last_error) {
                        return;
                    }

                    DB::table('supplier_exchange_rate_settings')
                        ->where('id', $backup->setting_id)
                        ->where('revision', $backup->revision)
                        ->where('manual_rate_toman', $backup->applied_manual_rate_toman)
                        ->where('last_error', $backup->applied_last_error)
                        ->update([
                            'manual_rate_toman' => $backup->original_manual_rate_toman,
                            'last_error' => $backup->original_last_error,
                            'updated_at' => $backup->original_updated_at,
                        ]);
                });
            }
        }

        Schema::dropIfExists('supplier_currency_rate_migration_150_backups');

        if (Schema::hasTable('product_supplier')) {
            if (Schema::hasIndex('product_supplier', self::PRODUCT_SCAN_INDEX)) {
                Schema::table('product_supplier', function (Blueprint $table): void {
                    $table->dropIndex(self::PRODUCT_SCAN_INDEX);
                });
            }

            if (!Schema::hasIndex('product_supplier', self::LEGACY_DUE_INDEX)
                && Schema::hasColumn('product_supplier', 'foreign_price_auto_update')
                && Schema::hasColumn('product_supplier', 'foreign_price_next_update_at')) {
                Schema::table('product_supplier', function (Blueprint $table): void {
                    $table->index(
                        ['foreign_price_auto_update', 'foreign_price_next_update_at'],
                        self::LEGACY_DUE_INDEX
                    );
                });
            }
        }

        // Keep the nullable diagnostic column on rollback. It may have been
        // created by an earlier deployment version, and dropping it could
        // remove data owned by that version. Older application code ignores it.
    }
};
