<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductPricesUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $productId,
        public readonly int $priceRevision,
        public readonly string $audienceRole,
        public readonly array $supplierUpdates,
        public readonly array $productPrices,
        public readonly int $productTiersCount,
        public readonly ?int $productLowest,
        public readonly ?int $productHighest,
        public readonly ?int $productAverage,
        public readonly string $updatedAt,
        public readonly bool $requiresPageRefresh = false,
        // Queue attribution only; deliberately excluded from broadcastWith().
        public readonly array $supplierIds = [],
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('product-prices.' . $this->productId . '.' . $this->audienceRole)];
    }

    public function broadcastAs(): string
    {
        return 'product.prices.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'price_revision' => $this->priceRevision,
            'supplier_updates' => $this->supplierUpdates,
            'product_prices' => $this->productPrices,
            'product_tiers_count' => $this->productTiersCount,
            'product_lowest' => $this->productLowest,
            'product_highest' => $this->productHighest,
            'product_average' => $this->productAverage,
            'updated_at' => $this->updatedAt,
            'requires_page_refresh' => $this->requiresPageRefresh,
        ];
    }
}
