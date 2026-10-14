<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OutsourcingSettlement extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['creation_key', 'request_hash'];
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'include_previous' => 'boolean',
            'gross_amount' => 'decimal:2', 'previous_amount' => 'decimal:2', 'period_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2', 'net_amount' => 'decimal:2',
            'approved_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OutsourcingSettlementItem::class, 'settlement_id'); }
    public function payments(): HasMany { return $this->hasMany(OutsourcingSettlementPayment::class, 'settlement_id'); }
}
