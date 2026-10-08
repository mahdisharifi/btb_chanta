<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductCompletionTask;
use App\Models\User;

class ProductCompletionPublisher
{
    public function __construct(private readonly ProductCompletionDraftValidator $validator)
    {
    }

    /**
     * Apply only allowlisted content fields. The caller must hold a Product row lock.
     */
    public function apply(
        ProductCompletionTask $task,
        Product $product,
        array $payload,
        ?User $actor = null,
    ): Product {
        $content = $this->validator->validate($task, $payload, $actor, true)->toArray();
        $updates = [];

        foreach (['title', 'short_description', 'full_description', 'specifications'] as $field) {
            if ($this->scopeAllows($task, $field) && array_key_exists($field, $content)) {
                $updates[$field] = $content[$field];
            }
        }

        if ($this->scopeAllows($task, 'seo') && array_key_exists('seo', $content)) {
            $seoMapping = [
                'title' => 'seo_title',
                'description' => 'seo_description',
                'keywords' => 'seo_keywords',
                'canonical_url' => 'seo_canonical_url',
            ];

            foreach ($seoMapping as $source => $target) {
                if (data_get($task->scope_json, "seo.{$source}", false) === true
                    && array_key_exists($source, $content['seo'])) {
                    $updates[$target] = $content['seo'][$source];
                }
            }
        }

        $attributesTouched = $this->scopeAllows($task, 'attributes')
            && array_key_exists('attributes', $content);

        if ($attributesTouched) {
            $this->syncAttributes($product, $content['attributes']);
        }

        $product->fill($updates);

        if ($product->isDirty() || $attributesTouched) {
            $product->forceFill([
                'content_version' => (int) $product->content_version + 1,
                'content_updated_at' => now(),
            ])->save();
        }

        return $product->refresh();
    }

    private function syncAttributes(Product $product, array $attributes): void
    {
        $valueIds = collect($attributes)
            ->flatMap(static fn (array $attribute): array => $attribute['value_ids'])
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $valueTexts = AttributeValue::query()
            ->whereIn('id', $valueIds)
            ->pluck('value', 'id');
        $syncData = [];

        foreach ($valueIds as $valueId) {
            $syncData[$valueId] = ['value' => $valueTexts[$valueId] ?? null];
        }

        $product->attributeValues()->sync($syncData);
    }

    private function scopeAllows(ProductCompletionTask $task, string $key): bool
    {
        $scope = data_get($task->scope_json, $key, false);

        return $scope === true || is_array($scope);
    }
}
