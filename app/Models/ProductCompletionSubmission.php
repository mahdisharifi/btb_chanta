<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCompletionSubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ProductCompletionSubmission extends Model
{
    use HasFactory;

    private const IMMUTABLE_FIELDS = [
        'task_id',
        'submitted_by',
        'version_no',
        'draft_version',
        'payload_json',
        'diff_json',
        'media_manifest_json',
        'payload_hash',
        'base_product_version',
        'idempotency_key',
        'submitted_at',
    ];

    protected $fillable = [
        'task_id',
        'submitted_by',
        'version_no',
        'draft_version',
        'payload_json',
        'diff_json',
        'media_manifest_json',
        'payload_hash',
        'base_product_version',
        'idempotency_key',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'draft_version' => 'integer',
            'payload_json' => 'array',
            'diff_json' => 'array',
            'media_manifest_json' => 'array',
            'base_product_version' => 'integer',
            'status' => ProductCompletionSubmissionStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            $submission->public_id ??= (string) Str::ulid();
        });

        static::updating(function (self $submission): void {
            foreach (self::IMMUTABLE_FIELDS as $field) {
                if ($submission->isDirty($field)) {
                    throw new LogicException("Submission field [{$field}] is immutable.");
                }
            }
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

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function requestedRevisions(): HasMany
    {
        return $this->hasMany(ProductCompletionRevision::class, 'submission_id');
    }

    public function resolvedRevisions(): HasMany
    {
        return $this->hasMany(ProductCompletionRevision::class, 'resolved_by_submission_id');
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(ProductCompletionSubmissionMedia::class, 'submission_id');
    }
}
