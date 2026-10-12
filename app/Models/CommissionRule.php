<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionCalculationType;
use App\Enums\ProductCompletionRevisionPolicy;
use App\Enums\ProductCompletionTaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class CommissionRule extends Model
{
    use HasFactory;

    private const VERSIONED_FIELDS = [
        'name',
        'version',
        'task_type',
        'calculation_type',
        'revision_policy',
        'base_amount',
        'per_revision_amount',
        'per_approved_item_amount',
        'max_amount',
        'currency',
        'conditions_json',
        'pricing_schema_version',
        'revision_percentage',
    ];

    protected $fillable = [
        'name',
        'version',
        'task_type',
        'calculation_type',
        'revision_policy',
        'base_amount',
        'per_revision_amount',
        'per_approved_item_amount',
        'max_amount',
        'currency',
        'conditions_json',
        'pricing_schema_version',
        'revision_percentage',
        'is_active',
        'effective_from',
        'effective_until',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'task_type' => ProductCompletionTaskType::class,
            'calculation_type' => CommissionCalculationType::class,
            'revision_policy' => ProductCompletionRevisionPolicy::class,
            'base_amount' => 'decimal:2',
            'per_revision_amount' => 'decimal:2',
            'per_approved_item_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'conditions_json' => 'array',
            'pricing_schema_version' => 'integer',
            'revision_percentage' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('withoutExcludedFields', static fn (Builder $query) => $query->where($query->getModel()->qualifyColumn('task_type'), '!=', ProductCompletionTaskType::SEO->value));
        static::creating(function (self $rule): void {
            $rule->public_id ??= (string) Str::ulid();
        });

        static::updating(function (self $rule): void {
            foreach (self::VERSIONED_FIELDS as $field) {
                if ($rule->isDirty($field)) {
                    throw new LogicException("Commission rule field [{$field}] is versioned; create a new rule version.");
                }
            }
        });

        static::deleting(static function (): void {
            throw new LogicException('Commission rules are immutable; deactivate the rule instead.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommissionRuleItem::class, 'commission_rule_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProductCompletionTask::class, 'commission_rule_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ProductCompletionCommission::class, 'commission_rule_id');
    }
}
