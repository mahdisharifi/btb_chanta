<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceUpdateBatch extends Model
{
    protected $fillable = [
        'supplier_id',
        'operation_type',
        'percentage',
        'status',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceUpdateItem::class);
    }
}
