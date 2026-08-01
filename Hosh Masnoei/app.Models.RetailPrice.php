<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RetailPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'retail_price',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'retail_price' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getRetailPriceFormattedAttribute(): string
    {
        return number_format($this->retail_price ) . ' تومان';
    }

    public function getRetailPriceInTomanAttribute(): float
    {
        return $this->retail_price ;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public static function setPriceFromToman(float $toman): int
    {
        return (int) ($toman );
    }
}
