<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceUpdateItem extends Model
{
    protected $fillable = [
        'price_update_batch_id',
        'product_supplier_id',
        'old_prices',
        'new_prices',
        'old_stock',
        'new_stock',
        'old_min_order_quantity',
        'new_min_order_quantity',
        'old_is_active',
        'new_is_active',
        'status',
        'error_message',
    ];

    protected $casts = [
        'old_prices' => 'array',
        'new_prices' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PriceUpdateBatch::class, 'price_update_batch_id');
    }

    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class);
    }
}
