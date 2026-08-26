<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PriceType extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    // ══════════════════════════════════════════════════════════
    //  SCOPES
    // ══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeCash(Builder $query): Builder
    {
        return $query->where('code', 'cash');
    }

    public function scopeCheck(Builder $query): Builder
    {
        return $query->where('code', 'check');
    }

    public function scopeWarranty(Builder $query): Builder
    {
        return $query->where('code', 'warranty');
    }
}
