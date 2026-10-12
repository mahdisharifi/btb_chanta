<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\ProductCompletionPayloadCast;

use App\Enums\ProductCompletionRevisionClassification;
use App\Enums\ProductCompletionRevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductCompletionRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'open_task_id',
        'submission_id',
        'requested_by',
        'requested_for',
        'revision_no',
        'classification',
        'status',
        'is_commissionable',
        'reason',
        'requested_fields_json',
        'pricing_schema_version',
        'changed_units_json',
        'changed_unit_count',
        'resolved_by_submission_id',
        'requested_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'classification' => ProductCompletionRevisionClassification::class,
            'status' => ProductCompletionRevisionStatus::class,
            'is_commissionable' => 'boolean',
            'requested_fields_json' => ProductCompletionPayloadCast::class,
            'pricing_schema_version' => 'integer',
            'changed_units_json' => 'array',
            'changed_unit_count' => 'integer',
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision): void {
            $revision->public_id ??= (string) Str::ulid();
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

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProductCompletionSubmission::class, 'submission_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_for');
    }

    public function resolvedBySubmission(): BelongsTo
    {
        return $this->belongsTo(ProductCompletionSubmission::class, 'resolved_by_submission_id');
    }
}
