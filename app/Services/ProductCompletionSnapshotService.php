<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

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

        foreach (['title', 'short_description', 'full_description', 'specifications'] as $field) {
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
                        'is_seo_image',
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
                        'is_seo_image' => (bool) $gallery->is_seo_image,
                        'title' => $gallery->title,
                        'alt_text' => $gallery->alt_text,
                    ])
                    ->all(),
            ];
        }

        if ($this->enabled($scope, 'seo')) {
            $snapshot['seo'] = $this->seoSnapshot($product, (array) $scope['seo']);
        }

        return $snapshot;
    }

    public function draftFromSnapshot(array $snapshot): array
    {
        return array_intersect_key(
            $snapshot,
            array_flip(config('product_completion.draft_fields', []))
        );
    }

    private function seoSnapshot(Product $product, array $scope): array
    {
        $mapping = [
            'title' => 'seo_title',
            'description' => 'seo_description',
            'keywords' => 'seo_keywords',
            'canonical_url' => 'seo_canonical_url',
        ];
        $seo = [];

        foreach ($mapping as $scopeKey => $productField) {
            if (($scope[$scopeKey] ?? false) === true) {
                $seo[$scopeKey] = $product->{$productField};
            }
        }

        return $seo;
    }

    private function enabled(array $scope, string $key): bool
    {
        $value = $scope[$key] ?? false;

        return $value === true || is_array($value);
    }
}
