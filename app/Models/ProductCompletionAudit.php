<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class ProductCompletionAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'product_id',
        'user_id',
        'actor_type',
        'action',
        'entity_type',
        'entity_id',
        'field_name',
        'before_json',
        'after_json',
        'metadata_json',
        'request_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Product completion audit records are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Product completion audit records cannot be deleted.');
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductCompletionTask::class, 'task_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
