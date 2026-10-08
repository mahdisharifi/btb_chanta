<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCompletionOperationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductCompletionOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'operation_type',
        'idempotency_key',
        'status',
        'payload_json',
        'attempts',
        'available_at',
        'locked_at',
        'completed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductCompletionOperationStatus::class,
            'payload_json' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $operation): void {
            $operation->public_id ??= (string) Str::ulid();
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
}
