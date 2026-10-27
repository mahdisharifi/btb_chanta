<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCompletionCommissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductCompletionCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'commission_rule_id',
        'currency',
        'base_amount',
        'revision_amount',
        'bonus_amount',
        'penalty_amount',
        'adjustment_amount',
        'total_amount',
        'breakdown_json',
        'status',
        'calculated_at',
        'approved_by',
        'approved_at',
        'payable_at',
        'paid_by',
        'paid_at',
        'transaction_id',
        'payment_idempotency_key',
        'dispute_reason',
    ];

    protected $hidden = [
        'payment_idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'revision_amount' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'breakdown_json' => 'array',
            'status' => ProductCompletionCommissionStatus::class,
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'payable_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $commission): void {
            $commission->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductCompletionTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
