<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCompletionMediaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductCompletionMedia extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'product_completion_media';

    protected $fillable = [
        'task_id',
        'uploaded_by',
        'source_product_gallery_id',
        'published_product_gallery_id',
        'disk',
        'temporary_path',
        'prepared_path',
        'final_path',
        'original_name',
        'safe_name',
        'mime_type',
        'extension',
        'file_size',
        'width',
        'height',
        'checksum',
        'versions_json',
        'metadata_json',
        'status',
        'rejection_reason',
        'prepared_at',
        'published_at',
    ];

    protected $hidden = [
        'disk',
        'temporary_path',
        'prepared_path',
        'final_path',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'versions_json' => 'array',
            'metadata_json' => 'array',
            'status' => ProductCompletionMediaStatus::class,
            'prepared_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->public_id ??= (string) Str::ulid();
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sourceGallery(): BelongsTo
    {
        return $this->belongsTo(ProductGallery::class, 'source_product_gallery_id');
    }

    public function publishedGallery(): BelongsTo
    {
        return $this->belongsTo(ProductGallery::class, 'published_product_gallery_id');
    }

    public function submissionItems(): HasMany
    {
        return $this->hasMany(ProductCompletionSubmissionMedia::class, 'media_id');
    }
}
