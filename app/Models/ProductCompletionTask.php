<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCompletionReviewMode;
use App\Enums\ProductCompletionTaskStatus;
use App\Enums\ProductCompletionTaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class ProductCompletionTask extends Model
{
    use HasFactory;

    private const IMMUTABLE_COMMISSION_FIELDS = [
        'commission_rule_id',
        'commission_rule_snapshot_json',
        'quoted_base_amount',
        'currency',
    ];

    protected $fillable = [
        'product_id',
        'active_product_id',
        'supplier_id',
        'created_by',
        'assigned_by',
        'assigned_to',
        'reviewer_id',
        'last_submission_id',
        'task_type',
        'status',
        'review_mode',
        'scope_schema_version',
        'scope_json',
        'initial_snapshot_json',
        'current_draft_json',
        'draft_version',
        'lock_version',
        'product_version_at_assignment',
        'product_updated_at_assignment',
        'commission_rule_id',
        'commission_rule_snapshot_json',
        'quoted_base_amount',
        'currency',
        'revision_limit',
        'revision_count',
        'commission_rate_percentage',
        'full_rate_approved_by',
        'full_rate_approved_at',
        'full_rate_approval_reason',
        'priority',
        'due_at',
        'assignment_expires_at',
        'published_at',
        'assigned_at',
        'accepted_at',
        'started_at',
        'submitted_at',
        'approved_at',
        'completed_at',
        'cancelled_at',
        'expired_at',
        'applied_product_version',
        'content_applied_at',
        'media_published_at',
        'conflict_json',
        'cancellation_reason',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'task_type' => ProductCompletionTaskType::class,
            'status' => ProductCompletionTaskStatus::class,
            'review_mode' => ProductCompletionReviewMode::class,
            'scope_schema_version' => 'integer',
            'scope_json' => 'array',
            'initial_snapshot_json' => 'array',
            'current_draft_json' => 'array',
            'draft_version' => 'integer',
            'lock_version' => 'integer',
            'product_version_at_assignment' => 'integer',
            'product_updated_at_assignment' => 'datetime',
            'commission_rule_snapshot_json' => 'array',
            'quoted_base_amount' => 'decimal:2',
            'revision_limit' => 'integer',
            'revision_count' => 'integer',
            'commission_rate_percentage' => 'decimal:2',
            'full_rate_approved_at' => 'datetime',
            'priority' => 'integer',
            'due_at' => 'datetime',
            'assignment_expires_at' => 'datetime',
            'published_at' => 'datetime',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'applied_product_version' => 'integer',
            'content_applied_at' => 'datetime',
            'media_published_at' => 'datetime',
            'conflict_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $task): void {
            $task->public_id ??= (string) Str::ulid();
        });

        static::updating(function (self $task): void {
            foreach (self::IMMUTABLE_COMMISSION_FIELDS as $field) {
                if ($task->isDirty($field)) {
                    throw new LogicException("Product completion commission field [{$field}] is immutable after task creation.");
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function activeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'active_product_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function fullRateApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'full_rate_approved_by');
    }

    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function lastSubmission(): BelongsTo
    {
        return $this->belongsTo(ProductCompletionSubmission::class, 'last_submission_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProductCompletionSubmission::class, 'task_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProductCompletionRevision::class, 'task_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductCompletionMedia::class, 'task_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ProductCompletionAudit::class, 'task_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductCompletionOperation::class, 'task_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(ProductCompletionCommission::class, 'task_id');
    }

    public function remainingRevisions(): int
    {
        return max(0, $this->revision_limit - $this->revision_count);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_to !== null && $this->assigned_to === $user->getKey();
    }
}
