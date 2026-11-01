<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\PriceVisibilityService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('product-prices.{productId}.{audienceRole}', function ($user, int $productId, string $audienceRole): bool {
    $visibility = app(PriceVisibilityService::class);
    $product = Product::query()->find($productId);

    return $product !== null
        && $product->is_active
        && $product->status === ProductStatus::ACTIVE
        && $visibility->getUserRole($user) === $audienceRole
        && $visibility->canSeeWholesalePrice($user);
});
