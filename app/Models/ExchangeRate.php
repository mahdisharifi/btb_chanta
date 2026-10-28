<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\CarbonImmutable;

class ExchangeRate extends Model
{
    protected $table = 'exchange_rates';

    protected $primaryKey = 'currency';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'currency',
        'rate_toman',
        'source',
        'source_market_date',
        'source_market_time',
        'source_updated_at',
        'fetched_at',
        'last_attempt_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'rate_toman' => 'decimal:4',
        ];
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
}
