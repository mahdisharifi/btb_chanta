<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'province_id',
        'country_id',
        'name',
        'native_name',
        'postal_code_pattern',
        'latitude',
        'longitude',
        'is_active',
        'population',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'latitude'   => 'decimal:8',
        'longitude'  => 'decimal:8',
        'population'  => 'integer',
        'sort_order'  => 'integer',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
