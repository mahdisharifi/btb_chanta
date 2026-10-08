<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCompletionSubmissionMedia extends Model
{
    use HasFactory;

    protected $table = 'product_completion_submission_media';

    protected $fillable = [
        'submission_id',
        'media_id',
        'product_gallery_id',
        'action',
        'sort_order',
        'is_primary',
        'is_seo_image',
        'title',
        'alt_text',
        'review_status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'is_seo_image' => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ProductCompletionSubmission::class, 'submission_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(ProductCompletionMedia::class, 'media_id');
    }

    public function productGallery(): BelongsTo
    {
        return $this->belongsTo(ProductGallery::class, 'product_gallery_id');
    }
}
