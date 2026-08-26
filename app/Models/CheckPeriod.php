<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class CheckPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'supplier_id',
        'scope',
        'days',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

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
        return $query->orderBy('sort_order')->orderBy('days');
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('scope', 'supplier')
                     ->where('supplier_id', $supplierId);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('scope', 'global');
    }

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS
    // ══════════════════════════════════════════════════════════

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: "چک {$this->days} روزه";
    }

    public function getFormattedDaysAttribute(): string
    {
        if ($this->days < 30) {
            return "{$this->days} روز";
        }
        
        $months = round($this->days / 30);
        return "{$months} ماه";
    }

    // ══════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════

    public static function getOptionsForSupplier(?int $supplierId = null): array
    {
        $query = static::active()->ordered();
        
        if ($supplierId) {
            $query->where(function ($q) use ($supplierId) {
                $q->whereNull('supplier_id')
                  ->orWhere('supplier_id', $supplierId);
            });
        } else {
            $query->whereNull('supplier_id');
        }
        
        return $query->get()->pluck('display_label', 'id')->toArray();
    }
}
