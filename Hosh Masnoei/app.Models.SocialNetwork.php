<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialNetwork extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'icon',
        'base_url',
        'validation_pattern',
        'placeholder_example',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function validate(string $value): bool
    {
        return (bool) preg_match('/' . $this->validation_pattern . '/', $value);
    }
}
