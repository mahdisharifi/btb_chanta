<?php
// app/Models/ProductSlugRedirect.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSlugRedirect extends Model
{
    public $timestamps = false;
    protected $fillable = ['product_id', 'old_slug'];

    protected static function booted(): void
    {
        static::creating(fn($m) => $m->created_at ??= now());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
