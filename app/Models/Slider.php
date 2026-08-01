<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Slider extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'image_path',
        'mobile_image_path',
        'link_url',
        'link_text',
        'link_target',
        'bg_color',
        'text_color',
        'overlay_color',
        'text_position',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
        'click_count',
        'view_count',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'sort_order'  => 'integer',
        'click_count' => 'integer',
        'view_count'  => 'integer',
    ];

    // ─── Scopes ───

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }

    // ─── Accessors ───

    public function getImageUrlAttribute(): string
    {
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }

        return asset('images/default-slider.jpg');
    }

    public function getMobileImageUrlAttribute(): ?string
    {
        if ($this->mobile_image_path) {
            return asset('storage/' . $this->mobile_image_path);
        }

        return null;
    }
}
