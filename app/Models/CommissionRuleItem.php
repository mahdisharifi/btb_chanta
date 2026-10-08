<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CommissionRuleItem extends Model
{
    private const VERSIONED_FIELDS = [
        'commission_rule_id',
        'content_type',
        'unit_amount',
        'max_payable_units',
        'is_enabled',
    ];

    protected $fillable = [
        'commission_rule_id',
        'content_type',
        'unit_amount',
        'max_payable_units',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => CommissionContentType::class,
            'unit_amount' => 'decimal:2',
            'max_payable_units' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $rule = CommissionRule::query()->find($item->commission_rule_id);

            if ($rule === null
                || (int) $rule->pricing_schema_version < 2
                || $rule->is_active) {
                throw new LogicException('Commission rule items may only be created while a version 2 rule is inactive.');
            }
        });

        static::updating(function (self $item): void {
            foreach (self::VERSIONED_FIELDS as $field) {
                if ($item->isDirty($field)) {
                    throw new LogicException("Commission rule item field [{$field}] is versioned; create a new rule version.");
                }
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Commission rule items are immutable; create a new rule version.');
        });
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }
}
