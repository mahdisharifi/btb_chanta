<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierExchangeRateSetting extends Model
{
    protected $table = 'supplier_exchange_rate_settings';

    protected $fillable = [
        'supplier_id',
        'currency',
        'rate_toman',
        'manual_rate_toman',
        'auto_update',
        'refresh_hours',
        'next_update_at',
        'source',
        'source_market_date',
        'source_market_time',
        'source_updated_at',
        'fetched_at',
        'last_attempt_at',
        'last_error',
        'last_apply_error',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'rate_toman' => 'decimal:4',
            'manual_rate_toman' => 'decimal:4',
            'auto_update' => 'boolean',
            'refresh_hours' => 'integer',
            'revision' => 'integer',
        ];
    }

    protected function nextUpdateAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function sourceUpdatedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function fetchedAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    protected function lastAttemptAt(): Attribute
    {
        return $this->utcTimestampAttribute();
    }

    private function utcTimestampAttribute(): Attribute
    {
        return Attribute::make(
            get: static fn ($value) => $value === null ? null : CarbonImmutable::parse($value, 'UTC'),
            set: static fn ($value) => $value === null ? null : CarbonImmutable::parse($value)->utc(),
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
