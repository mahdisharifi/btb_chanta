<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $fillable = [
        'product_supplier_id',
        'user_id',
        'price_type',
        'old_price',
        'new_price',
    ];

    /**
     * ارتباط با محصول تامین‌کننده
     */
    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class);
    }

    /**
     * ارتباط با کاربری که تغییر را انجام داده
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
