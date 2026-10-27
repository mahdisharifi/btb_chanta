<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCompletionTaskBatch extends Model
{
    protected $fillable = [
        'created_by',
        'status',
        'eligible_count',
        'created_count',
        'failed_count',
        'cancelled_count',
        'protected_count',
        'rollback_processed_count',
        'rollback_cursor_task_id',
        'completed_at',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'eligible_count' => 'integer',
            'created_count' => 'integer',
            'failed_count' => 'integer',
            'cancelled_count' => 'integer',
            'protected_count' => 'integer',
            'rollback_processed_count' => 'integer',
            'rollback_cursor_task_id' => 'integer',
            'completed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProductCompletionTask::class, 'bulk_creation_batch_id');
    }
}
