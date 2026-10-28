<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProductPriceRevisionService
{
    /** Call from the same transaction that changes the product price matrix. */
    public function increment(int $productId): void
    {
        DB::table('products')
            ->where('id', $productId)
            ->increment('price_revision');
    }
}
