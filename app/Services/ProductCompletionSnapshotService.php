<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Support\ProductCompletionPayload;

class ProductCompletionSnapshotService
{
    public function build(Product $product, array $scope): array
    {
        $snapshot = [
            'schema_version' => (int) config('product_completion.schema_version', 1),
            'product_content_version' => (int) $product->content_version,
            'product_updated_at' => $product->updated_at?->toAtomString(),
            'category_id' => (int) $product->category_id,
        ];

        foreach (['title', 'short_description', 'full_description'] as $field) {
            if ($this->enabled($scope, $field)) {
                $snapshot[$field] = $product->{$field};
            }
        }

        if ($this->enabled($scope, 'attributes')) {
            $snapshot['attributes'] = $product->attributeValues()
                ->select(['attribute_values.id', 'attribute_values.attribute_id'])
                ->get()
                ->groupBy('attribute_id')
                ->map(static fn ($values, $attributeId): array => [
                    'attribute_id' => (int) $attributeId,
                    'value_ids' => $values->pluck('id')->map(static fn ($id): int => (int) $id)->sort()->values()->all(),
                ])
                ->sortKeys()
                ->values()
                ->all();
        }

        if ($this->enabled($scope, 'gallery')) {
            $snapshot['gallery'] = [
                'items' => $product->galleries()
                    ->where('file_type', 'image')
                    ->select([
                        'id',
                        'sort_order',
                        'is_primary',
                        'title',
                        'alt_text',
                    ])
                    ->orderBy('sort_order')
                    ->get()
                    ->map(static fn ($gallery): array => [
                        'product_gallery_id' => (int) $gallery->id,
                        'action' => 'keep',
                        'sort_order' => (int) $gallery->sort_order,
                        'is_primary' => (bool) $gallery->is_primary,
                        'title' => $gallery->title,
                        'alt_text' => $gallery->alt_text,
                    ])
                    ->all(),
            ];
        }

        return $snapshot;
    }

    public function draftFromSnapshot(array $snapshot): array
    {
        return array_intersect_key(
            ProductCompletionPayload::withoutExcludedFields($snapshot),
            array_flip(config('product_completion.draft_fields', []))
        );
    }

    private function enabled(array $scope, string $key): bool
    {
        $value = $scope[$key] ?? false;

        return $value === true || is_array($value);
    }
}
